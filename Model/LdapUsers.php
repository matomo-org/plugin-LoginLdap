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
use Piwik\Plugins\LoginLdap\Ldap\ServerInfo;
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
     * The fully qualified class name of the LDAP client to use. Mostly for testing purposes,
     * but it might have some future use.
     *
     * @var string
     */
    private $ldapClientClass = "Piwik\\Plugins\\LoginLdap\\Ldap\\Client";

    /**
     * Information describing the list of LDAP servers that should be used.
     * When connecting, we try to connect with the first available server.
     *
     * @var ServerInfo[]
     */
    private $ldapServers = null;

    /**
     * The current LDAP client object if any. It is set when the {@link $doWithClient}
     * method creates a Client and unset when the same method is done with a client.
     *
     * @var LdapClient|null
     */
    private $ldapClient;

    /**
     * The current {@link ServerInfo} instance describing the LDAP server we are
     * currently connected to. It is set to the ServerInfo instance in {@link $servers}
     * that describes the connected server. It is used to get server specific
     * information such as the server's base DN or the admin user to bind with for
     * the server.
     *
     * @var ServerInfo
     */
    private $currentServerInfo;

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
     * @return array|null On success, returns user info stored in the LDAP database. On failure returns `null`.
     */
    public function authenticate($username, $password, $alreadyAuthenticated = false)
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
            $authenticationRequiredMemberOf = $this->authenticationRequiredMemberOf;
            $result = $this->doWithClient(function (LdapUsers $self, LdapClient $ldapClient)
                use ($username, $password, $alreadyAuthenticated, $authenticationRequiredMemberOf) {
                
                $user = $self->getUser($username, $ldapClient);

                if (empty($user)) {
                    Log::debug("ModelUsers\\LdapUsers::%s: No such user '%s' or user is not a member of '%s'.",
                        __FUNCTION__, $username, $authenticationRequiredMemberOf);

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
     * @return string[] Associative array containing LDAP field data, eg, `array('dn' => '...')`
     */
    public function getUser($username)
    {
        Log::debug(self::FUNCTION_START_LOG_MESSAGE, __FUNCTION__, array($username));

        $result = $this->doWithClient(function (LdapUsers $self, LdapClient $ldapClient, ServerInfo $server)
            use ($username) {
            $adminUserName = $self->addUsernameSuffix($server->getAdminUsername());

            // bind using the admin user which has at least read access to LDAP users
            if (!$ldapClient->bind($adminUserName, $server->getAdminPassword())) {
                throw new Exception("Could not bind as LDAP admin.");
            }

            // look for the user, applying extra filters
            list($filter, $bind) = $self->getUserEntryQuery($username);
            $userEntries = $ldapClient->fetchAll($server->getBaseDn(), $filter, $bind);

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
        $login = $ldapUser[$this->ldapUserIdField];

        // we don't actually use this in authentication, we just add it as an extra security precaution, in case
        // someone manages to disable LDAP auth
        $password = substr($ldapUser['userpassword'], 0, UsersManager::PASSWORD_MAX_LENGTH - 1);

        $email = @$ldapUser[$this->ldapMailField];
        if (empty($email)) { // a valid email is needed to create a new user
            $suffix = $this->authenticationUsernameSuffix;
            $domain = !empty($suffix) ? $suffix : '@mydomain.com';
            $email = $login . $domain; // TODO: this assumes username suffix is a email suffix (ie @whatever.com)
        }

        return array(
            'login' => $login,
            'password' => $password, 
            'email' => $email,
            'alias' => $ldapUser[$this->ldapAliasField]
        );
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
     * Sets the {@link $ldapClientClass} member.
     *
     * @param string $ldapClientClass
     */
    public function setLdapClientClass($ldapClientClass)
    {
        $this->ldapClientClass = $ldapClientClass;
    }

    /**
     * Returns the {@link $ldapServers} member.
     *
     * @return ServerInfo[]
     */
    public function getLdapServers()
    {
        return $this->ldapServers;
    }

    /**
     * Sets the {@link $ldapServers} member.
     *
     * @param ServerInfo[] $ldapServers
     */
    public function setLdapServers($ldapServers)
    {
        $this->ldapServers = $ldapServers;
    }

    /**
     * Public only for use in closure.
     */
    public function getUserEntryQuery($username)
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

    /**
     * Public only for use in closure.
     */
    public function addUsernameSuffix($username)
    {
        if (!empty($this->authenticationUsernameSuffix)) {
            Log::debug("Model\\LdapUsers::%s: Adding suffix '%s' to username '%s'.", __FUNCTION__, $this->authenticationUsernameSuffix, $username);
        }

        return $username . $this->authenticationUsernameSuffix;
    }

    /**
     * Executes a closure with a connected LDAP client. If a client has already been
     * created, the stored client will be used.
     *
     * Using this method allows users of this class & methods of this class to combine
     * multiple calls without creating multiple LDAP connections.
     *
     * If an LDAP client is created, it will be closed before the end of this method.
     *
     * @param callable|null $function Should accept 3 parameters: The LdapUsers instance,
     *                                a connected LdapClient instance and a ServerInfo
     *                                instance that describes the LDAP server we are
     *                                connected to.
     * @return mixed Returns the result of the callback.
     * @throws Exception Forwards exceptions thrown by the callback and throws LDAP
     *                   exceptions.
     */
    public function doWithClient($function = null)
    {
        $closeClient = false;

        try {
            if ($this->ldapClient === null) {
                $this->makeLdapClient();

                $closeClient = true;
            }

            $result = $function($this, $this->ldapClient, $this->currentServerInfo);
        } catch (Exception $ex) {
            if ($closeClient
                && isset($this->ldapClient)
            ) {
                try {
                    $this->closeLdapClient();
                } catch (Exception $ex) {
                    Log::debug($ex);
                }
            }

            throw $ex;
        }

        if ($closeClient) {
            $this->closeLdapClient();
        }

        return $result;
    }

    private function makeLdapClient()
    {
        if (empty($this->ldapServers)) { // sanity check
            throw new Exception("No LDAP servers configured in LdapUsers instance.");
        }

        $ldapClientClass = $this->ldapClientClass;
        $this->ldapClient = is_string($ldapClientClass) ? new $ldapClientClass() : $ldapClientClass;

        foreach ($this->ldapServers as $server) {
            try {
                $this->ldapClient->connect($server->getServerHostname(), $server->getServerPort());

                $this->ldapClient = $ldapClient;
                $this->currentServerInfo = $server;

                return;
            } catch (Exception $ex) {
                // TODO: should be warning but default Piwik logger is 'screen'
                Log::info("Model\\LdapUsers::%s: Could not connect to LDAP server %s:%s.",
                    $server->getServerHostname(), $server->getServerPort());
            }
        }

        $this->throwCouldNotConnectException();
    }

    private function closeLdapClient()
    {
        $this->ldapClient->close();
        $this->ldapClient = null;
    }

    private function throwCouldNotConnectException()
    {
        if (count($this->ldapServers) > 1) { // TODO: translate this message
            $message = "Could not connect to any of the " . count($this->ldapServers) . " LDAP servers.";
        } else {
            $message = "Could not connect to the LDAP server.";
        }

        throw new Exception($message);
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
     * @return int
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

        $result->setLdapServers(self::getConfiguredLdapServers($config));

        if (!empty($config['userIdField'])) {
            $result->setLdapUserIdField($config['userIdField']);
        }

        if (!empty($config['usernameSuffix'])) {
            $result->setAuthenticationUsernameSuffix($config['usernameSuffix']);
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

    /**
     * Returns a list of {@link ServerInfo} instances describing the LDAP servers
     * that should be connected to.
     *
     * @param array $config The `[LoginLdap]` INI config section.
     * @return ServerInfo[]
     */
    private static function getConfiguredLdapServers($config)
    {
        $serverNameList = @$config['servers'];

        if (empty($serverNameList)) {
            $server = ServerInfo::makeFromOldConfig($config);
            return array($server);
        } else {
            $servers = array();
            foreach ($serverNameList as $name) {
                try {
                    $servers[] = ServerInfo::makeConfigured($name);
                } catch (Exception $ex) {
                    Log::debug("Model\\LdapUsers::%s: LDAP server info '%s' is configured incorrectly: %s",
                        __FUNCTION__, $name, $ex->getMessage());
                }
            }
            return $servers;
        }
    }
}