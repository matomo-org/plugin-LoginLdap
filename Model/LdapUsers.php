<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\Model;

use Piwik\Db;
use Piwik\Config;
use Piwik\Log;
use Piwik\Common;
use Piwik\Plugins\UsersManager\UsersManager;
use Piwik\Plugins\UsersManager\API as UsersManagerApi;
use Piwik\Plugins\LoginLdap\Ldap\Client as LdapClient;
use InvalidArgumentException;
use Exception;

/**
 * DAO class for user related operations that use LDAP as a backend.
 */
class LdapUsers
{
    const FUNCTION_START_LOG_MESSAGE = "Model\\LdapUsers: start %s() with %s";
    const FUNCTION_END_LOG_MESSAGE = "Model\\LdapUsers: end %s() with %s";

    /**
     * The LDAP server hostname.
     *
     * @var string
     */
    private $serverHostname;

    /**
     * The port to use when connecting to the LDAP server.
     *
     * @var int
     */
    private $serverPort = LdapClient::DEFAULT_LDAP_PORT;

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
     * The base DN to use when searching the LDAP server. Determines which specific
     * LDAP database is searched.
     *
     * @var string
     */
    private $baseDn;

    /**
     * If set, the user must be a member of a specific LDAP groupOfNames in order
     * to authenticate to Piwik. Users that are not a part of this group will not
     * be able to access Piwik.
     *
     * @var string
     */
    private $authenticationRequiredMemberOf;

    /**
     * If set, this value is added to the end of usernames before authentication
     * is attempted. This includes the admin user in addition to attempted logins.
     *
     * @var string
     */
    private $authenticationUsernameSuffix;

    /**
     * An LDAP filter that should be used to further filter LDAP users. Users that
     * do not pass this filter will not be able to access Piwik.
     *
     * @var string ie, `"(&!((uidNumber=1002))(gidNumber=550))"`
     */
    private $authenticationLdapFilter;

    /**
     * The 'admin' LDAP user to use when authenticating. This user must have read
     * access to other users so we can search for the person attempting login.
     *
     * TODO: is this needed? ie, since we only want the user's data and not others, can we just bind w/ the user?
     *       if it works, allow adminUserName to be null.
     *
     * @var string
     */
    private $adminUserName;

    /**
     * The password to use when binding w/ the 'admin' LDAP user.
     *
     * @var string
     */
    private $adminUserPassword;

    /**
     * The fully qualified class name of the LDAP client to use. Mostly for testing purposes,
     * but it might have some future use.
     *
     * @var string
     */
    private $ldapClientClass = "Piwik\\Plugins\\LoginLdap\\Ldap\\Client";

    /**
     * Constructor.
     */
    public function __construct()
    {
        // empty
    }

    /**
     * Authenticates a username/password pair using LDAP and returns LDAP user info on success.
     *
     * @param string $username The LDAP user's username. This is the value of the LDAP field specified by
     *                         {@link $ldapUserIdField}.
     * @param string $password The password to try and authenticate.
     * @param bool $alreadyAuthenticated Whether to assume the user has already been authenticated or not.
     *                                   If true, we make sure the user is allowed to access Piwik based on
     *                                   the {@link $authenticationRequiredMemberOf} and {@link $authenticationLdapFilter}
     *                                   fields.
     * @param Ldap\Client|null $ldapClient The client to use. If none specified, a new one is created and
     *                                     a connection made. Before the function exists, the connection is
     *                                     closed.
     * @return array|null On success, returns user info stored in the LDAP database. On failure returns `null`.
     */
    public function authenticate($username, $password, $alreadyAuthenticated = false, LdapClient $ldapClient = null)
    {
        Log::debug(self::FUNCTION_START_LOG_MESSAGE, __FUNCTION__,
            array($username, "<password[length=" . strlen($password) . "]>", $alreadyAuthenticated));

        if (empty($username)) {
            throw new InvalidArgumentException('No username supplied in Model\\LdapUsers::authenticate().');
        }

        // if password is empty, avoid connecting to the LDAP server
        if (empty($password)
            && !$alreadyAuthenticated
        ) {
            return null;
        }

        try {
            $result = $this->doWithClient($ldapClient, function ($self, $ldapClient) use ($username, $password, $alreadyAuthenticated) {
                $user = $self->getUser($username, $ldapClient);

                if (empty($user)) {
                    Log::debug("ModelUsers\\LdapUsers::%s: No such user '%s' or user is not a member of '%s'.",
                        __FUNCTION__, $username, $this->authenticationRequiredMemberOf);

                    return null;
                }

                if ($alreadyAuthenticated) {
                    return $user;
                }

                if (empty($user['dn'])) {
                    Log::debug("ModelUsers\\LdapUsers::%s: LDAP user info for '%s' has no dn attribute! (info = %s)",
                        __FUNCTION__, $username, $user);

                    return null;
                }

                if ($ldapClient->bind($user['dn'], $password)) {
                    if ($self->updateCredentials($username, $password)) {
                        Log::debug("ModelUsers\\LdapUsers::%s: Updated credentails for LDAP user '%'.", __FUNCTION__, $username);
                    }

                    return $user;
                } else {
                    return null;
                }
            });
        } catch (Exception $ex) {
            Log::debug($ex);

            $result = null;
        }

        Log::debug(self::FUNCTION_END_LOG_MESSAGE, __FUNCTION__, $result);

        return $result;
    }

    /**
     * Retrieves LDAP user information for a given username.
     *
     * @param string $username The username of the user to get LDAP information for.
     * @param Ldap\Client|null $ldapClient The client to use. If none specified, a new one is created and
     *                                     a connection made. Before the function exists, the connection is
     *                                     closed.
     * @return string[] Associative array containing LDAP field data, eg, `array('dn' => '...')`
     */
    public function getUser($username, LdapClient $ldapClient = null)
    {
        Log::debug(self::FUNCTION_START_LOG_MESSAGE, __FUNCTION__, array($username));

        $result = $this->doWithClient($ldapClient, function ($self, $ldapClient) use ($username) {
            $adminUserName = $self->addUsernameSuffix($self->adminUserName);

            // bind using the admin user which has at least read access to LDAP users
            if (!$ldapClient->bind($adminUserName, $self->adminUserPassword)) {
                throw new Exception("Could not bind as LDAP admin.");
            }

            // look for the user, applying extra filters
            list($filter, $bind) = $self->getUserEntryQuery($username);
            $userEntries = $ldapClient->fetchAll($self->baseDn, $filter, $bind);

            // TODO: test anonymous bind (for validity of old error message in LdapFunctions.php)
            if ($userEntries === null) { // sanity check
                throw new Exception("LDAP search for entries failed.");
            }

            if (empty($userEntries)) {
                return null;
            } else {
                return $userEntries[0];
            }
        });

        Log::debug(self::FUNCTION_END_LOG_MESSAGE, __FUNCTION__, $result);

        return $result;
    }

    /**
     * Creates an array with normal Piwik user information using LDAP data for the user. The
     * information in the result should be used with the **UsersManager.addUser** API method.
     *
     * This method is used in syncing LDAP user information with Piwik user info.
     *
     * @param string[] $ldapUser Associative array containing LDAP field data, eg, `array('dn' => '...')`
     * @return string[]
     */
    public function createPiwikUserEntryForLdapUser($ldapUser)
    {
        return array(
            'login' => $ldapUser[$this->ldapUserIdField],
            'password' => "-",
            'email' => $ldapUser[$this->ldapMailField],
            'alias' => $ldapUser[$this->ldapAliasField]
        );
    }

    /**
     * Sets the {@link $serverHostname} member.
     *
     * @param string $serverHostname
     */
    public function setServerHostname($serverHostname)
    {
        $this->serverHostname = $serverHostname;
    }

    /**
     * Sets the {@link $serverPort} member.
     *
     * @param int $serverPort
     */
    public function setServerPort($serverPort)
    {
        $this->serverPort = $serverPort;
    }

    /**
     * Sets the {@link $ldapUserIdField} member.
     *
     * @param string $ldapUserIdField
     */
    public function setLdapUserIdField($ldapUserIdField)
    {
        $this->ldapUserIdField = $ldapUserIdField;
    }

    /**
     * Sets the {@link $ldapAliasField} member.
     *
     * @param string $ldapAliasField
     */
    public function setLdapAliasField($ldapAliasField)
    {
        $this->ldapAliasField = $ldapAliasField;
    }

    /**
     * Sets the {@link $ldapMailField} member.
     *
     * @param string $ldapMailField
     */
    public function setLdapMailField($ldapMailField)
    {
        $this->ldapMailField = $ldapMailField;
    }

    /**
     * Sets the {@link $baseDn} member.
     *
     * @param string $baseDn
     */
    public function setBaseDn($baseDn)
    {
        $this->baseDn = $baseDn;
    }

    /**
     * Sets the {@link $authenticationRequiredMemberOf} member.
     *
     * @param string $authenticationRequiredMemberOf
     */
    public function setAuthenticationRequiredMemberOf($authenticationRequiredMemberOf)
    {
        $this->authenticationRequiredMemberOf = $authenticationRequiredMemberOf;
    }

    /**
     * Sets the {@link $authenticationUsernameSuffix} member.
     *
     * @param string $authenticationUsernameSuffix
     */
    public function setAuthenticationUsernameSuffix($authenticationUsernameSuffix)
    {
        $this->authenticationUsernameSuffix = $authenticationUsernameSuffix;
    }

    /**
     * Sets the {@link $authenticationLdapFilter} member.
     *
     * @param string $authenticationLdapFilter
     */
    public function setAuthenticationLdapFilter($authenticationLdapFilter)
    {
        $this->authenticationLdapFilter = $authenticationLdapFilter;
    }

    /**
     * Sets the {@link $adminUserName} member.
     *
     * @param string $adminUserName
     */
    public function setAdminUserName($adminUserName)
    {
        $this->adminUserName = $adminUserName;
    }

    /**
     * Sets the {@link $adminUserPassword} member.
     *
     * @param string $adminUserPassword
     */
    public function setAdminUserPassword($adminUserPassword)
    {
        $this->adminUserPassword = $adminUserPassword;
    }

    /**
     * Sets the {@link $ldapClientClass} member.
     *
     * @param string $ldapClientClass
     */
    public function setLdapClientClass($ldapClientClass)
    {
        $this->ldapClientClass = $ldapClientClass;
    }

    private function getUserEntryQuery($username)
    {
        $bind = array();
        $conditions = array();

        if (!empty($this->authenticationLdapFilter)) {
            $conditions[] = $this->authenticationLdapFilter;
        }

        if (!empty($this->authenticationRequiredMemberOf)) {
            $conditions[] = "(memberof=?)";
            $bind[] = $this->authenticationRequiredMemberOf;
        }

        $conditions[] = "(" . $this->ldapUserIdField . "=?)";
        $bind[] = $this->addUsernameSuffix($username);

        $filter = "(&" . implode('', $conditions) . ")";

        return array($filter, $bind);
    }

    public function addUsernameSuffix($username)
    {
        if (!empty($this->authenticationUsernameSuffix)) {
            Log::debug("Model\\LdapUsers::%s: Adding suffix '%s' to username '%s'.", __FUNCTION__, $this->authenticationUsernameSuffix, $username);
        }

        return $username . $this->authenticationUsernameSuffix;
    }

    /**
     * Utility method that executes a closure with an LDAP client. Will either use
     * the passed client or create a new one and connect.
     *
     * Using this method allows users of LdapUsers & methods of LdapUsers to combine
     * multiple calls without creating multiple LDAP connections.
     *
     * If an LDAP client is created, it will be closed before the end of this method.
     */
    private function doWithClient(LdapClient $ldapClient = null, $function = null)
    {
        $closeClient = false;

        try {
            if ($ldapClient === null) {
                $ldapClient = $this->makeLdapClient();

                $closeClient = true;
            }

            $result = $function($this, $ldapClient);
        } catch (Exception $ex) {
            if ($closeClient) {
                try {
                    $ldapClient->close();
                } catch (Exception $ex) {
                    Log::debug($ex);
                }
            }

            throw $ex;
        }

        if ($closeClient) {
            $ldapClient->close();
        }

        return $result;
    }

    private function makeLdapClient()
    {
        $ldapClientClass = $this->ldapClientClass;

        $ldapClient = is_string($ldapClientClass) ? new $ldapClientClass() : $ldapClientClass;
        $ldapClient->connect($this->serverHostname, $this->serverPort);
        return $ldapClient;
    }

    /**
     * Update password and token in the database.
     * This is needed because the initially entered password of LDAP users is just a dummy one.
     * The update should only happen for LDAP users and only the first time they login.
     *
     * Public for use w/ a closure.
     *
     * @param $login
     * @param $password
     */
    public function updateCredentials($login, $password)
    {
        $password = UsersManager::getPasswordHash($password);
        $token_auth = UsersManagerApi::getInstance()->getTokenAuth($login, $password);
        $result = Db::query("UPDATE " . Common::prefixTable('user')
            . " SET password='" . $password . "', token_auth='" . $token_auth
            . "' WHERE login='" . $login . "' and password != '" . $password . "'");
        return $result;
    }

    /**
     * Creates a new {@link LdapUsers} instance using config.ini.php values.
     *
     * @return LdapUsers
     */
    public static function makeConfigured()
    {
        $config = Config::getInstance()->LoginLdap;

        $result = new LdapUsers();
        $result->setServerHostname($config['serverUrl']);
        $result->setServerPort($config['ldapPort']);
        $result->setBaseDn($config['baseDn']);

        if (!empty($config['userIdField'])) {
            $result->setLdapUserIdField($config['userIdField']);
        }

        if (!empty($config['usernameSuffix'])) {
            $result->setAuthenticationUsernameSuffix($config['usernameSuffix']);
        }

        if (!empty($config['adminUser'])) {
            $result->setAdminUserName($config['adminUser']);
        }

        if (!empty($config['adminPass'])) {
            $result->setAdminUserPassword($config['adminPass']);
        }

        if (!empty($config['mailField'])) {
            $result->setLdapMailField($config['mailField']);
        }

        if (!empty($config['aliasField'])) {
            $result->setLdapAliasField($config['aliasField']);
        }

        if (!empty($config['memberOf'])) {
            $result->setAuthenticationRequiredMemberOf($config['memberOf']);
        }

        if (!empty($config['filter'])) {
            $result->setAuthenticationLdapFilter($config['filter']);
        }

        return $result;
    }
}