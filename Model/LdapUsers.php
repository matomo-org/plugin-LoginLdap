<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\Model;

use Piwik\Config;
use Piwik\Log;
use Piwik\Plugins\LoginLdap\Ldap\Client as LdapClient;
use InvalidArgumentException;
use Exception;

/**
 * TODO
 */
class LdapUsers
{
    const FUNCTION_START_LOG_MESSAGE = "Model\\LdapUsers: start %s() with %s";
    const FUNCTION_END_LOG_MESSAGE = "Model\\LdapUsers: end %s() with %s";

    /**
     * TODO
     */
    private $serverHostname;

    /**
     * TODO
     */
    private $serverPort;

    /**
     * TODO (uid)
     */
    private $ldapUserIdField;

    /**
     * TODO (cn)
     */
    private $ldapAliasField;

    /**
     * TODO (mail)
     */
    private $ldapMailField;

    /**
     * TODO
     */
    private $baseDn;

    /**
     * TODO
     */
    private $authenticationRequiredMemberOf;

    /**
     * TODO
     */
    private $authenticationUsernameSuffix;

    /**
     * TODO
     */
    private $authenticationLdapFilter;

    /**
     * TODO
     */
    private $adminUserName;

    /**
     * TODO
     */
    private $adminUserPassword;

    /**
     * TODO
     */
    public function __construct()
    {
        $this->serverHostname = Config::getInstance()->LoginLdap['serverUrl'];
        $this->serverPort = Config::getInstance()->LoginLdap['ldapPort'];
        $this->baseDn = Config::getInstance()->LoginLdap['baseDn'];
        $this->ldapUserIdField = Config::getInstance()->LoginLdap['userIdField'];
        $this->authenticationUsernameSuffix = Config::getInstance()->LoginLdap['usernameSuffix'];
        $this->adminUserName = Config::getInstance()->LoginLdap['adminUser'];
        $this->adminUserPassword = Config::getInstance()->LoginLdap['adminPass'];
        $this->ldapMailField = Config::getInstance()->LoginLdap['mailField'];
        $this->ldapAliasField = Config::getInstance()->LoginLdap['aliasField'];
        $this->authenticationRequiredMemberOf = Config::getInstance()->LoginLdap['memberOf'];
        $this->authenticationLdapFilter = Config::getInstance()->LoginLdap['filter'];

        // TODO: validate configuration issues?
    }

    /**
     * TODO
     */
    public function authenticate($username, $password, $usingWebServerAuth, LdapClient $ldapClient = null)
    {
        Log::debug(self::FUNCTION_START_LOG_MESSAGE, __FUNCTION__,
            array($username, "<password[length=" . strlen($password) . "]>", $usingWebServerAuth));

        if (empty($username)) {
            throw new InvalidArgumentException('No username supplied in Model\\LdapUsers::authenticate().');
        }

        // if password is empty, avoid connecting to the LDAP server
        if (empty($password)
            && !$useWebServerAuth
        ) {
            return null;
        }

        try {
            $result = $this->doWithClient($ldapClient, function ($self, $ldapClient) use ($username, $password, $useWebServerAuth) {
                $user = $self->getUser($username, $ldapClient);

                if (empty($user)) {
                    Log::debug("ModelUsers\\LdapUsers::%s: No such user '%s' or user is not a member of '%s'.",
                        __FUNCTION__, $username, $this->authenticationRequiredMemberOf);

                    return null;
                }

                if ($useWebServerAuth) {
                    return $user;
                }

                if ($ldapClient->bind($username, $password)) {
                    if ($self->updateCredentials($username, $password)) {
                        Log::debug("ModelUsers\\LdapUsers::%s: Updated credentails for LDAP user '%'.", __FUNCTION__, $username);
                    }

                    return $user;
                } else {
                    return null;
                }
            });
        } catch (Exception $ex) {
            Log::debug($ex); // TODO: should be a warning but these errors shouldn't be printed to the screen...

            $result = null;
        }

        Log::debug(self::FUNCTION_END_LOG_MESSAGE, __FUNCTION__, $result);

        return $result;
    }

    /**
     * TODO
     */
    public function getUser($username, $ldapClient = null)
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
            if ($userEntries === null) {
                throw new Exception("LDAP search for entries failed.");
            }

            if (empty($userEntries)
                || $userEntries['count'] == 0
            ) {
                return null;
            } else {
                return $userEntries[0];
            }
        });

        Log::debug(self::FUNCTION_END_LOG_MESSAGE, __FUNCTION__, $result);

        return $result;
    }

    /**
     * TODO
     */
    public function createPiwikUserEntryForLdapUser($ldapUser, $ldapClient = null)
    {
        return array(
            'login' => $ldapUser[$this->ldapUserIdField],
            'password' => "-",
            'email' => $ldapUser[$this->ldapMailField],
            'alias' => $ldapUser[$this->ldapAliasField]
        );
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
        $bind[] = $this->addSuffix($username);

        $filter = "(&" . implode('', $conditions) . ")";

        return array($filter, $bind);
    }

    private function addUsernameSuffix($username)
    {
        if (!empty($this->authenticationUsernameSuffix)) {
            Log::debug("Model\\LdapUsers::%s: Adding suffix '%s' to username '%s'.", $this->authenticationUsernameSuffix, $username);
        }

        return $username . $this->authenticationUsernameSuffix;
    }

    /**
     * TODO
     */
    private function doWithClient(LdapClient $ldapClient, $function)
    {
        $closeClient = false;

        try {
            if ($ldapClient == null) {
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
        $ldapClient = new LdapClient();
        $ldapClient->connect($this->serverHostname, $this->serverPort);
    }

    /**
     * Update password and token in the database.
     * This is needed because the initially entered password of LDAP users is just a dummy one.
     * The update should only happen for LDAP users and only the first time they login.
     * @param $login
     * @param $password
     */
    private function updateCredentials($login, $password)
    {
        $password = UsersManager::getPasswordHash($password);
        $token_auth = API::getInstance()->getTokenAuth($login, $password);
        $result = Db::query("UPDATE " . Common::prefixTable('user')
            . " SET password='" . $password . "', token_auth='" . $token_auth
            . "' WHERE login='" . $login . "' and password != '" . $password . "'");
        return $result;
    }
}