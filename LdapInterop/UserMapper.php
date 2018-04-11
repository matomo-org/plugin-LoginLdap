<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\LdapInterop;

use Exception;
use Piwik\Access;
use Piwik\API\Proxy;
use Piwik\API\Request;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\LoginLdap\Config;
use Psr\Log\LoggerInterface;

/**
 * Maps LDAP users to arrays that can be used to create new Piwik
 * users.
 *
 * See {@link UserSynchronizer} for more information.
 */
class UserMapper
{
    const DEFAULT_USER_EMAIL_SUFFIX = '@mydomain.com';

    const USER_PREFERENCE_NAME_IS_LDAP_USER = 'isLDAPUser';

    /**
     * The LDAP resource field that holds a user's username.
     *
     * @var string
     */
    private $ldapUserIdField = 'uid';

    /**
     * The LDAP resource field to use when determining a user's alias.
     *
     * @var string
     */
    private $ldapAliasField = 'cn';

    /**
     * The LDAP resource field to use when determining a user's email address.
     *
     * @var string
     */
    private $ldapMailField = 'mail';

    /**
     * The LDAP resource field to use when determining a user's first name.
     *
     * @var string
     */
    private $ldapFirstNameField = 'givenname';

    /**
     * The LDAP resource field to use when determining a user's last name.
     *
     * @var string
     */
    private $ldapLastNameField = 'sn';

    /**
     * The LDAP resource field to use when determining a user's password.
     *
     * @var string
     */
    private $ldapUserPasswordField = 'userpassword';

    /**
     * Suffix to be appended to user names of LDAP users that have no email address.
     * Email addresses are required for Piwik users, so something must be entered.
     *
     * @var string
     */
    private $userEmailSuffix = self::DEFAULT_USER_EMAIL_SUFFIX;

    /**
     * If true, the user email suffix is appended to the Piwik user's login. This means
     * the DB will store the user's login w/ the suffix, but user's will login without
     * the suffix. This emulates pre-3.0 behavior and is necessary for backwards
     * compatibility.
     *
     * @var bool
     */
    private $appendUserEmailSuffixToUsername = true;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: StaticContainer::get('Psr\Log\LoggerInterface');
    }

    /**
     * Creates an array with normal Piwik user information using LDAP data for the user. The
     * information in the result should be used with the **UsersManager.addUser** API method.
     *
     * This method is used in syncing LDAP user information with Piwik user info.
     *
     * @param string[] $ldapUser Associative array containing LDAP field data, eg, `array('dn' => '...')`
     * @param string[]|null $piwikUser The existing Piwik user or null if none exists yet.
     * @return string[]
     */
    public function createPiwikUserFromLdapUser($ldapUser, $user = null)
    {
        $login = $this->getRequiredLdapUserField($ldapUser, $this->ldapUserIdField);

        return array(
            'login' => $login,
            'password' => $this->getPiwikPasswordForLdapUser($ldapUser, $user),
            'email' => $this->getEmailAddressForLdapUser($ldapUser, $login),
            'alias' => $this->getAliasForLdapUser($ldapUser)
        );
    }

    /**
     * Returns the expected LDAP username using a Piwik login. If a user email suffix is
     * configured, it is appended to the login. This is to provide compatible behavior
     * with old versions of the plugin.
     *
     * @param string $login The Piwik login.
     * @return string The expected LDAP login.
     */
    public function getExpectedLdapUsername($login)
    {
        if (!empty($this->userEmailSuffix)
            && $this->appendUserEmailSuffixToUsername
            && $this->userEmailSuffix != self::DEFAULT_USER_EMAIL_SUFFIX
        ) {
            $login .= $this->userEmailSuffix;
        }
        return $login;
    }

    /**
     * The password we store for a mapped user isn't used to authenticate, it's just
     * data used to generate a user's token auth.
     */
    private function getPiwikPasswordForLdapUser($ldapUser, $user)
    {
        $ldapPassword = $this->getLdapUserField($ldapUser, $this->ldapUserPasswordField);

        if (!empty($user['password'])) {
            // do not generate new passwords for users that are already synchronized
            return $user['password'];
        } elseif (!empty($ldapPassword)) {
            return $this->hashLdapPassword($ldapPassword);
        } else {
            $this->logger->warning("UserMapper::{func}: Could not find LDAP password for user '{user}', generating random one.",
                array(
                    'func' => __FUNCTION__,
                    'user' => @$ldapUser[$this->ldapUserIdField]
                ));

            return $this->generateRandomPassword();
        }
    }

    /**
     * Generates a random string to be used as the 'dummy' password stored in the MySQL DB.
     *
     * @return string
     */
    public function generateRandomPassword()
    {
        return $this->hashLdapPassword(uniqid());
    }

    private function getEmailAddressForLdapUser($ldapUser, $login)
    {
        $email = $this->getLdapUserField($ldapUser, $this->ldapMailField);
        if (empty($email)) { // a valid email is needed to create a new user
            $email = $login;
            if (strpos($email, '@') === false) {
                $email .= $this->userEmailSuffix;
            }
        }
        return $email;
    }

    private function getAliasForLdapUser($ldapUser)
    {
        $alias = $this->getLdapUserField($ldapUser, $this->ldapAliasField);
        if (empty($alias)
            && !empty($ldapUser[$this->ldapFirstNameField])
            && !empty($ldapUser[$this->ldapLastNameField])
        ) {
            $alias = $this->getRequiredLdapUserField($ldapUser, $this->ldapFirstNameField)
                . ' '
                . $this->getRequiredLdapUserField($ldapUser, $this->ldapLastNameField);
        }
        return $alias;
    }

    private function getRequiredLdapUserField($ldapUser, $fieldName, $fetchSingleValue = true)
    {
        if (!isset($ldapUser[$fieldName])) {
            throw new Exception("LDAP entity missing required '$fieldName' field.");
        }

        return $this->getLdapUserField($ldapUser, $fieldName, $fetchSingleValue);
    }

    private function getLdapUserField($ldapUser, $fieldName, $fetchSingleValue = true)
    {
        $result = @$ldapUser[$fieldName];
        if ($fetchSingleValue
            && is_array($result)
        ) {
            $result = reset($result);
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getLdapUserIdField()
    {
        return $this->ldapUserIdField;
    }

    /**
     * @param string $ldapUserIdField
     */
    public function setLdapUserIdField($ldapUserIdField)
    {
        $this->ldapUserIdField = strtolower($ldapUserIdField);
    }

    /**
     * Returns the {@link $ldapAliasField} property.
     *
     * @return string
     */
    public function getLdapAliasField()
    {
        return $this->ldapAliasField;
    }

    /**
     * Sets the {@link $ldapAliasField} property.
     *
     * @param string $ldapAliasField
     */
    public function setLdapAliasField($ldapAliasField)
    {
        $this->ldapAliasField = strtolower($ldapAliasField);
    }

    /**
     * Returns the {@link $ldapMailField} property.
     *
     * @return string
     */
    public function getLdapMailField()
    {
        return $this->ldapMailField;
    }

    /**
     * Sets the {@link $ldapMailField} property.
     *
     * @param string $ldapMailField
     */
    public function setLdapMailField($ldapMailField)
    {
        $this->ldapMailField = strtolower($ldapMailField);
    }

    /**
     * Returns the {@link $ldapFirstNameField} property.
     *
     * @return string
     */
    public function getLdapFirstNameField()
    {
        return $this->ldapFirstNameField;
    }

    /**
     * Sets the {@link $ldapFirstNameField} property.
     *
     * @param string $ldapFirstNameField
     */
    public function setLdapFirstNameField($ldapFirstNameField)
    {
        $this->ldapFirstNameField = strtolower($ldapFirstNameField);
    }

    /**
     * Returns the {@link $ldapLastNameField} property.
     *
     * @return string
     */
    public function getLdapLastNameField()
    {
        return $this->ldapLastNameField;
    }

    /**
     * Sets the {@link $ldapLastNameField} property.
     *
     * @param string $ldapLastNameField
     */
    public function setLdapLastNameField($ldapLastNameField)
    {
        $this->ldapLastNameField = strtolower($ldapLastNameField);
    }

    /**
     * Returns the {@link $ldapUserPasswordField} property.
     *
     * @return string
     */
    public function getLdapUserPasswordField()
    {
        return $this->ldapUserPasswordField;
    }

    /**
     * Sets the {@link $ldapUserPasswordField} property.
     *
     * @param string $userPasswordField
     */
    public function setLdapUserPasswordField($userPasswordField)
    {
        $this->ldapUserPasswordField = strtolower($userPasswordField);
    }

    /**
     * Returns the {@link $userEmailSuffix} property.
     *
     * @return string
     */
    public function getUserEmailSuffix()
    {
        return $this->userEmailSuffix;
    }

    /**
     * Sets the {@link $userEmailSuffix} property.
     *
     * @param string $userEmailSuffix
     */
    public function setUserEmailSuffix($userEmailSuffix)
    {
        $this->userEmailSuffix = $userEmailSuffix;
    }

    /**
     * Returns the {@link $appendUserEmailSuffixToUsername} property.
     *
     * @return bool
     */
    public function getAppendUserEmailSuffixToUsername()
    {
        return $this->appendUserEmailSuffixToUsername;
    }

    /**
     * Sets the {@link $appendUserEmailSuffixToUsername} property.
     *
     * @param bool $appendUserEmailSuffixToUsername
     */
    public function setAppendUserEmailSuffixToUsername($appendUserEmailSuffixToUsername)
    {
        $this->appendUserEmailSuffixToUsername = $appendUserEmailSuffixToUsername;
    }

    /**
     * Hashes the LDAP password so no part the real LDAP password (or the hash stored in
     * LDAP) will be stored in Piwik's DB.
     */
    protected function hashLdapPassword($password)
    {
        return md5($password);
    }

    /**
     * Returns true if the user information is for a Piwik user that was mapped from LDAP,
     * false if otherwise.
     *
     * @param string $userLogin The user login
     * @return bool
     */
    public function isUserLdapUser($userLogin)
    {
        return Access::doAsSuperUser(function () use ($userLogin) {
            $class      = Request::getClassNameAPI('UsersManager');
            $parameters = array(
                'userLogin'      => $userLogin,
                'preferenceName' => self::USER_PREFERENCE_NAME_IS_LDAP_USER

            );
            $preference = Proxy::getInstance()->call($class, 'getUserPreference', $parameters);
            return !!$preference;
        });
    }

    /**
     * Marks a user a synchronized LDAP user
     *
     * @param string $userLogin The user login
     */
    public function markUserAsLdapUser($userLogin)
    {
        Access::doAsSuperUser(function () use ($userLogin) {
            $class     = Request::getClassNameAPI('UsersManager');
            $parameters = array(
                'userLogin'       => $userLogin,
                'preferenceName'  => self::USER_PREFERENCE_NAME_IS_LDAP_USER,
                'preferenceValue' => 1

            );
            Proxy::getInstance()->call($class, 'setUserPreference', $parameters);
        });
    }



    /**
     * Creates a UserMapper instance configured using INI options.
     *
     * @return UserMapper
     */
    public static function makeConfigured()
    {
        $result = new UserMapper();

        $uidField = Config::getLdapUserIdField();
        if (!empty($uidField)) {
            $result->setLdapUserIdField($uidField);
        }

        $lastNameField = Config::getLdapLastNameField();
        if (!empty($lastNameField)) {
            $result->setLdapLastNameField($lastNameField);
        }

        $firstNameField = Config::getLdapFirstNameField();
        if (!empty($firstNameField)) {
            $result->setLdapFirstNameField($firstNameField);
        }

        $aliasField = Config::getLdapAliasField();
        if (!empty($aliasField)) {
            $result->setLdapAliasField($aliasField);
        }

        $mailField = Config::getLdapMailField();
        if (!empty($mailField)) {
            $result->setLdapMailField($mailField);
        }

        $userPasswordField = Config::getLdapPasswordField();
        if (!empty($userPasswordField)) {
            $result->setLdapUserPasswordField($userPasswordField);
        }

        $userEmailSuffix = Config::getLdapUserEmailSuffix();
        if (!empty($userEmailSuffix)) {
            $result->setUserEmailSuffix($userEmailSuffix);
        }

        $appendUserEmailSuffixToUsername = Config::shouldAppendUserEmailSuffixToUsername();
        $result->setAppendUserEmailSuffixToUsername($appendUserEmailSuffixToUsername);

        return $result;
    }
}