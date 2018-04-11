<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\Model;

use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\LoginLdap\Config;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\LoginLdap\Ldap\Client as LdapClient;
use Piwik\Plugins\LoginLdap\Ldap\ServerInfo;
use Piwik\Plugins\LoginLdap\Ldap\Exceptions\ConnectionException;
use InvalidArgumentException;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * DAO class for user related operations that use LDAP as a backend.
 */
class LdapUsers
{
    const FUNCTION_START_LOG_MESSAGE = "Model\\LdapUsers: start {function}() with {params}";
    const FUNCTION_END_LOG_MESSAGE = "Model\\LdapUsers: end {function}() with {result}";

    /**
     * If set, the user must be a member of a specific LDAP groupOfNames in order
     * to authenticate to Piwik. Users that are not a part of this group will not
     * be able to access Piwik.
     *
     * @var string
     */
    private $authenticationRequiredMemberOf;

     /**
     *Field used by your LDAP to indicate membership, by default \"memberOf\"
     * @var string
     */
    private $authenticationMemberOfField;

    /**
     * If set, this value is added to the end of usernames before authentication
     * is attempted.
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
     * The UserMapper instance used to get the LDAP user ID field to use. Used when
     * searching for a specific user.
     *
     * @var UserMapper
     */
    private $ldapUserMapper;

    /**
     * The timeout value in seconds for all LDAP network requests.
     *
     * @var int
     */
    private $ldapNetworkTimeout = LdapClient::DEFAULT_TIMEOUT_SECS;

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
     * @throws ConnectionException if we connect to any configured LDAP server.
     */
    public function authenticate($username, $password, $alreadyAuthenticated = false)
    {
        $this->logger->debug(self::FUNCTION_START_LOG_MESSAGE, array(
            'function' => __FUNCTION__,
            'params' => array($username, "<password[length=" . strlen($password) . "]>", $alreadyAuthenticated)
        ));

        if (empty($username)) {
            throw new InvalidArgumentException('No username supplied in Model\\LdapUsers::authenticate().');
        }

        // if password is empty, avoid connecting to the LDAP server
        if (empty($password)
            && !$alreadyAuthenticated
        ) {
            $this->logger->debug("LdapUsers::{function}: empty password, skipping authentication", array(
                'function' => __FUNCTION__
            ));

            return null;
        }

        try {
            $authenticationRequiredMemberOf = $this->authenticationRequiredMemberOf;
            $logger = $this->logger;
            $result = $this->doWithClient(function (LdapUsers $self, LdapClient $ldapClient)
                use ($username, $password, $alreadyAuthenticated, $authenticationRequiredMemberOf, $logger) {

                $user = $self->getUser($username);

                if (empty($user)) {
                    $logger->debug("LdapUsers::{function}: No such user '{user}' or user is not a member of '{group}'.", array(
                        'function' => __FUNCTION__,
                        'user' => $username,
                        'group' => $authenticationRequiredMemberOf
                    ));

                    return null;
                }

                if ($alreadyAuthenticated) {
                    $logger->debug("LdapUsers::{function}: assuming user {user} already authenticated, skipping LDAP authentication", array(
                        'function' => __FUNCTION__,
                        'user' => $username
                    ));

                    return $user;
                }

                if (empty($user['dn'])) {
                    $logger->debug("LdapUsers::{function}: LDAP user info for '{user}' has no dn attribute! (info = {info})", array(
                        'function' => __FUNCTION__,
                        'user' => $username,
                        'info' => array_keys($user)
                    ));

                    return null;
                }

                if ($ldapClient->bind($user['dn'], $password)) {
                    return $user;
                } else {
                    return null;
                }
            });
        } catch (ConnectionException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->logger->debug("LDAP authentication failure: {message}", array('message' => $ex->getMessage(), 'exception' => $ex));

            $result = null;
        }

        $this->logger->debug(self::FUNCTION_END_LOG_MESSAGE, array(
            'function' => __FUNCTION__,
            'result' => $result === null ? 'null' : array_keys($result)
        ));

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
        $this->logger->debug(self::FUNCTION_START_LOG_MESSAGE, array(
            'function' => __FUNCTION__,
            'params' => array($username)
        ));

        $result = $this->doWithClient(function (LdapUsers $self, LdapClient $ldapClient, ServerInfo $server)
            use ($username) {
            $self->bindAsAdmin($ldapClient, $server);

            // look for the user, applying extra filters
            list($filter, $bind) = $self->getUserEntryQuery($username);
            $userEntries = $ldapClient->fetchAll($server->getBaseDn(), $filter, $bind);

            if ($userEntries === null) { // sanity check
                throw new Exception("LDAP search for entries failed. (Unexpected Error, ldap_search returned null)");
            }

            if (empty($userEntries)) {
                return null;
            } else {
                return $userEntries[0];
            }
        });

        $this->logger->debug(self::FUNCTION_END_LOG_MESSAGE, array(
            'function' => __FUNCTION__,
            'result' => $result === null ? 'null' : array_keys($result)
        ));

        return $result;
    }

    /**
     * Returns count of users in LDAP that match an LDAP filter.
     *
     * @param string $filter The filter to match.
     * @param string[] $filterBind Bind parameters for the filter.
     * @return int
     * @throws Exception if no LDAP server can be reached, if we cannot bind to the admin user, if
     *                   the LDAP filter is incorrect, or if something else goes wrong during LDAP.
     */
    public function getCountOfUsersMatchingFilter($filter, $filterBind = array())
    {
        $this->logger->debug(self::FUNCTION_START_LOG_MESSAGE, array(
            'function' => __FUNCTION__,
            'params' => $filter
        ));

        $result = $this->doWithClient(function (LdapUsers $self, LdapClient $ldapClient, ServerInfo $server)
            use ($filter, $filterBind) {
            $self->bindAsAdmin($ldapClient, $server);

            return $ldapClient->count($server->getBaseDn(), $filter, $filterBind);
        });

        $this->logger->debug(self::FUNCTION_END_LOG_MESSAGE, array(
            'function' => __FUNCTION__,
            'params' => $result
        ));

        return $result;
    }

    /**
     * Returns all usernames found in LDAP after applying the configured filter and memberof
     * requirement.
     *
     * @return string[]
     * @throws Exception if no LDAP server can be reached, if we cannot bind to the admin user,
     *                   or if something else goes wrong during LDAP.
     */
    public function getAllUserLogins()
    {
        $this->logger->debug(self::FUNCTION_START_LOG_MESSAGE, array(
            'function' => __FUNCTION__,
            'params' => ''
        ));

        $userIdField = $this->ldapUserMapper->getLdapUserIdField();
        list($filter, $bind) = $this->getUserEntryQuery($username = null);
        $result = $this->doWithClient(function (LdapUsers $self, LdapClient $ldapClient, ServerInfo $server) use ($userIdField, $filter, $bind) {
            $self->bindAsAdmin($ldapClient, $server);

            $entries = $ldapClient->fetchAll($server->getBaseDn(), $filter, $bind, $attributes = array($userIdField));

            $userIds = array();
            foreach ($entries as $entry) {
                if (empty($entry[$userIdField])) {
                    continue;
                }

                $userId = $entry[$userIdField];
                if (is_array($userId)) {
                    $userId = reset($userId);
                }

                $userIds[] = $userId;
            }
            return $userIds;
        });

        $this->logger->debug(self::FUNCTION_END_LOG_MESSAGE, array(
            'function' => __FUNCTION__,
            'result' => $result
        ));

        return $result;
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
     * Sets the {@link $authenticationMemberOfField} member.
     *
     * @param string $authenticationMemberOfField
     */
    public function setAuthenticationMemberOfField($authenticationMemberOfField)
    {
        $this->authenticationMemberOfField = $authenticationMemberOfField;
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
     * Sets the {@link $ldapUserMapper} member.
     *
     * @param UserMapper $ldapUserMapper
     */
    public function setLdapUserMapper(UserMapper $ldapUserMapper)
    {
        $this->ldapUserMapper = $ldapUserMapper;
    }

    /**
     * Gets the {@link $ldapNetworkTimeout} member.
     *
     * @return int
     */
    public function getLdapNetworkTimeout()
    {
        return $this->ldapNetworkTimeout;
    }

    /**
     * Sets the {@link $ldapNetworkTimeout} member.
     *
     * @param int $ldapNetworkTimeout
     */
    public function setLdapNetworkTimeout($ldapNetworkTimeout)
    {
        $this->ldapNetworkTimeout = $ldapNetworkTimeout;
    }

    /**
     * Public only for use in closure.
     */
    public function getUserEntryQuery($username = null)
    {
        $bind = array();
        $conditions = array();
        
        if (!empty($this->authenticationLdapFilter)) {
            $conditions[] = $this->authenticationLdapFilter;
        }

        if (!empty($this->authenticationRequiredMemberOf)) {
            $conditions[] = "(".$this->authenticationMemberOfField."=?)";
            $bind[] = $this->authenticationRequiredMemberOf;
        }
        
        if (!empty($username)) {
            $conditions[] = "(" . $this->ldapUserMapper->getLdapUserIdField() . "=?)";
            $bind[] = $this->addUsernameSuffix($username);
        }

        $filter = "(&" . implode('', $conditions) . ")";

        return array($filter, $bind);
    }

    /**
     * Public only for use in closure.
     */
    public function addUsernameSuffix($username)
    {
        if (!empty($this->authenticationUsernameSuffix)) {
            $this->logger->debug("Model\\LdapUsers::{function}: Adding suffix '{suffix}' to username '{username}'.", array(
                'function' => __FUNCTION__,
                'suffix' => $this->authenticationUsernameSuffix,
                'username' => $username
            ));
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
                    $this->logger->debug("Failed to close LDAP client: {message}", array(
                        'message' => $ex->getMessage(),
                        'exception' => $ex
                    ));
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
                $this->ldapClient->connect($server->getServerHostname(), $server->getServerPort(), $this->getLdapNetworkTimeout());
                $this->currentServerInfo = $server;

                $this->logger->info("LdapUsers::{function}: Using LDAP server {host}:{port}", array(
                    'function' => __FUNCTION__,
                    'host' => $server->getServerHostname(),
                    'port' => $server->getServerPort()
                ));

                return;
            } catch (Exception $ex) {
                $this->logger->info("Model\\LdapUsers::{function}: Could not connect to LDAP server {host}:{port}: {message}", array(
                    'function' => __FUNCTION__,
                    'host' => $server->getServerHostname(),
                    'post' => $server->getServerPort(),
                    'message' => $ex->getMessage(),
                    'exception' => $ex
                ));
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
        if (count($this->ldapServers) > 1) {
            $message = Piwik::translate('LoginLdap_CannotConnectToServers', count($this->ldapServers));
        } else {
            $message = Piwik::translate("LoginLdap_CannotConnectToServer");
        }

        throw new ConnectionException($message);
    }

    /**
     * Public only for use in closure.
     */
    public function bindAsAdmin(LdapClient $ldapClient, ServerInfo $server)
    {
        $adminUserName = $server->getAdminUsername();

        // bind using the admin user which has at least read access to LDAP users
        if (!$ldapClient->bind($adminUserName, $server->getAdminPassword())) {
            throw new Exception("Could not bind as LDAP admin.");
        }
    }

    /**
     * Creates a new {@link LdapUsers} instance using config.ini.php values.
     *
     * @return LdapUsers
     */
    public static function makeConfigured()
    {
        $result = new LdapUsers();

        $result->setLdapServers(Config::getConfiguredLdapServers());

        if (Config::shouldAppendUserEmailSuffixToUsername()) {
            $usernameSuffix = Config::getLdapUserEmailSuffix();
            if (!empty($usernameSuffix)) {
                $result->setAuthenticationUsernameSuffix($usernameSuffix);
            }
        }

        $requiredMemberOf = Config::getRequiredMemberOf();
        if (!empty($requiredMemberOf)) {
            $result->setAuthenticationRequiredMemberOf($requiredMemberOf);
        }

        $memberOfField = Config::getRequiredMemberOfField();
        if (!empty($memberOfField)) {
            $result->setAuthenticationMemberOfField($memberOfField);
        }


        $filter = Config::getLdapUserFilter();
        if (!empty($filter)) {
            $result->setAuthenticationLdapFilter($filter);
        }

        $timeoutSecs = Config::getLdapNetworkTimeout();
        if (!empty($timeoutSecs)) {
            $result->setLdapNetworkTimeout($timeoutSecs);
        }

        $result->setLdapUserMapper(UserMapper::makeConfigured());

        return $result;
    }
}