<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\LdapInterop;

use Exception;
use Piwik\Config;
use Piwik\Plugins\UsersManager\UsersManager;

/**
 * Maps LDAP users to arrays that can be used to create new Piwik
 * users.
 *
 * See {@link UserSynchronizer} for more information.
 */
class UserMapper
{
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
    private $ldapFirstNameField = 'givenName';

    /**
     * The LDAP resource field to use when determining a user's last name.
     *
     * @var string
     */
    private $ldapLastNameField = 'sn';

    /**
     * Suffix to be appended to user names of LDAP users that have no email address.
     * Email addresses are required for Piwik users, so something must be entered.
     *
     * @var string
     */
    private $userEmailSuffix = '@mydomain.com';

    /**
     * Creates an array with normal Piwik user information using LDAP data for the user. The
     * information in the result should be used with the **UsersManager.addUser** API method.
     *
     * This method is used in syncing LDAP user information with Piwik user info.
     *
     * @param string[] $ldapUser Associative array containing LDAP field data, eg, `array('dn' => '...')`
     * @return string[]
     */
    public function createPiwikUserFromLdapUser($ldapUser)
    {
        $login = $this->getRequiredLdapUserField($ldapUser, $this->ldapUserIdField);

        return array(
            'login' => $login,
            'password' => $this->getPiwikPasswordForLdapUser($ldapUser),
            'email' => $this->getEmailAddressForLdapUser($ldapUser, $login),
            'alias' => $this->getAliasForLdapUser($ldapUser)
        );
    }

    private function getPiwikPasswordForLdapUser($ldapUser)
    {
        // TODO: explain the synchronizing approach better.
        // we don't actually use this in authentication, we just add it as an extra security precaution, in case
        // someone manages to disable LDAP auth or get's access to the Piwik database. it's also used to generate the token auth.
        $password = $this->getRequiredLdapUserField($ldapUser, 'userpassword');
        $password = substr($password, 0, UsersManager::PASSWORD_MAX_LENGTH - 1);
        return $password;
    }

    private function getEmailAddressForLdapUser($ldapUser, $login)
    {
        $email = @$ldapUser[$this->ldapMailField];
        if (empty($email)) { // a valid email is needed to create a new user
            $email = $login . $this->userEmailSuffix;
        }
        return $email;
    }

    private function getAliasForLdapUser($ldapUser)
    {
        $alias = @$ldapUser[$this->ldapAliasField];
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

        $result = $ldapUser[$fieldName];
        if ($fetchSingleValue
            && is_array($result)
        ) {
            $result = reset($result);
        }
        return $result;
    }

    /**
     * Creates a UserMapper instance configured using INI options.
     *
     * @return UserMapper
     */
    public static function makeConfigured()
    {
        $config = Config::getInstance()->LoginLdap;

        $result = new UserMapper();
        self::setPropertyFromConfigurationOption($config, $result->ldapUserIdField, 'ldap_user_id_field', 'userIdField');
        self::setPropertyFromConfigurationOption($config, $result->ldapLastNameField, 'ldap_last_name_field');
        self::setPropertyFromConfigurationOption($config, $result->ldapFirstNameField, 'ldap_first_name_field');
        self::setPropertyFromConfigurationOption($config, $result->ldapAliasField, 'ldap_alias_field', 'aliasField');
        self::setPropertyFromConfigurationOption($config, $result->ldapMailField, 'ldap_mail_field', 'mailField');
        self::setPropertyFromConfigurationOption($config, $result->userEmailSuffix, 'user_email_suffix', 'usernameSuffix');
        return $result;
    }

    private static function setPropertyFromConfigurationOption($config, &$value, $optionName, $alternateName = false)
    {
        if (!empty($config[$optionName])) {
            $value = $config[$optionName];
        } else if (!empty($$alternateName)
            && !empty($config[$alternateName])
        ) {
            $value = $config[$alternateName];
        }
    }
}