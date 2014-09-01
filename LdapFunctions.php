<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package LoginLdap
 */
namespace Piwik\Plugins\LoginLdap;

use Exception;
use Piwik\Common;
use Piwik\Config;
use Piwik\Db;
use Piwik\Plugins\UsersManager\API;
use Piwik\Plugins\UsersManager\UsersManager;
use Piwik\Session;
use Piwik\Log;
use Piwik\Plugins\LoginLdap\Ldap\Client as LdapClient;

/**
 *
 * @package LoginLdap
 */
class LdapFunctions
{
    private $serverUrl = null;
    private $ldapPort = null;
    private $userIdField = 'uid';
    private $baseDn = null;
    private $memberOf = null;
    private $adminUser = null;
    private $adminPass = null;
    private $mailField = null;
    private $aliasField = null;
    private $usernameSuffix = null;
    private $filter = null;
    private $useKerberos = null;
    private $debugEnabled = null;
    private $autoCreateUser = null;

    private $ldapconn = null;

    public function authenticateFu($username, $password, $useWebServerAuth = false)
    {
        Log::info("INFO: ldapfunctions authenticateFu(" . $username . ") - started. Password is " . strlen($password) . " chars.");

        if (empty($password)
            && !$useWebServerAuth
        ) {
            Log::debug("WARN: ldapfunctions authenticateFu(" . $username . ") - password is not set!");
            return false;
        }

        $this->validate();

        if (empty($username)) {
            Log::debug("WARN: ldapfunctions authenticateFu(" . $username . ") - username is not set!");
            throw new Exception('username is not set');
        }

        $ldapClient = new LdapClient();

        try {
            $ldapClient->connect($this->serverUrl, $this->ldapPort);

            $result = $this->getUserEntries($ldapClient, $username); // TODO: removed logging statement, think about putting back
                                                                     // TODO: removed logging statement in kerbthenticate, think about putting back
            if ($result['count'] > 0 && !empty($result[0]['dn'])) {
                $userDn = $result[0]['dn'];

                if (!$useWebServerAuth) { // using LDAP auth only
                    if (!$ldapClient->bind($userDn, $password)) {
                        throw new Exception("Unable to bind to $userDn.");
                    }
                }
            } else {
                if ($useWebServerAuth) { // delegated authentication to web server
                    throw new Exception("User not member of required group.");
                } else { // did LDAP auth in PHP
                    throw new Exception("No such user '$username'.");
                }
            }
        } catch (Exception $ex) {
            Log::debug($ex);

            try {
                $ldapClient->close();
            } catch (Exception $ex) {
                Log::debug($ex);
            }

            return false;
        }

        $ldapClient->close();

        if ($this->updateCredentials($username, $password)) {
            Log::debug("INFO: ldapfunctions authenticateFu(" . $username . ") - ldap user password and token update success.");
        }

        return true;
    }

    public function getUser($username, $aliasField = 'cn', $mailField = 'mail')
    {
        $this->validate();

        if (empty($username)) {
            throw new Exception('username is not set');
        }

        try {
            $ldapClient = new LdapClient();
            $ldapClient->connect($this->serverUrl, $this->ldapPort);

            $user = array();

            $result = $this->getUserEntries($ldapClient, $username);
            if ($result['count'] > 0) {
                $user['username'] = $username;
                $user['alias'] = $result[0][$aliasField][0];
                $user['mail'] = $result[0][$mailField][0];
            }
        } catch (Exception $ex) {
            $ldapClient->close();

            throw $ex;
        }

        $ldapClient->close();

        return $user;
    }

    public function setServerUrl($serverUrl)
    {
        $this->serverUrl = $serverUrl;
    }

    public function setUserIdField($userIdField)
    {
        $this->userIdField = $userIdField;
    }

    public function setBaseDn($baseDn)
    {
        $this->baseDn = $baseDn;
    }

    public function setMemberOf($memberOf)
    {
        $this->memberOf = $memberOf;
    }

    public function setAdminUser($adminUser)
    {
        $this->adminUser = $adminUser;
    }

    public function setAdminPass($adminPass)
    {
        $this->adminPass = $adminPass;
    }

    public function setMailField($mailField)
    {
        $this->mailField = $mailField;
    }

    public function setAliasField($aliasField)
    {
        $this->aliasField = $aliasField;
    }

    public function setUsernameSuffix($usernameSuffix)
    {
        $this->usernameSuffix = $usernameSuffix;
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    public function setKerberos($useKerberos)
    {
        $this->useKerberos = $useKerberos;
    }

    public function setDebug($debugEnabled)
    {
        $this->debugEnabled = $debugEnabled;
    }

    public function setAutoCreateUser($autoCreateUser)
    {
        $this->autoCreateUser = $autoCreateUser;
    }

    public function setLdapPort($ldapPort)
    {
        $this->ldapPort = $ldapPort;
    }

    private function validate()
    {

        if (empty($this->serverUrl)) {
            throw new Exception('serverUrl is not set');
        }
        if (empty($this->userIdField)) {
            throw new Exception('userIdField is not set');
        }
        if (empty($this->baseDn)) {
            throw new Exception('baseDn is not set');
        }
    }

    private function getUserEntries(LdapClient $ldapClient, $username)
    {
        $adminResource = $this->addSuffix($this->adminUser);

        if (!$ldapClient->bind($adminResource, $this->adminPass)) {
            throw new Exception("Could not bind as LDAP admin."); // admin user DN should not be displayed in exception message
        }

        list($filter, $bind) = $this->getUserEntryQuery($username);
        $userEntries = $ldapClient->fetchAll($this->baseDn, $filter, $bind);

        // TODO: test anonymous bind (for validity of old error message)
        if ($userEntries === null) {
            throw new Exception("Couldn't search for LDAP entries.");
        }

        return $userEntries;
    }

    private function getUserEntryQuery($username)
    {
        $username = $this->addSuffix($username);

        $bind = array();

        $conditions = array();
        if (!empty($this->filter)) {
            $conditions[] = $this->filter;
        }
        if (!empty($this->memberOf)) {
            $conditions[] = "(memberof=?)";
            $bind[] = $this->memberOf;
        }
        $conditions[] = "(" . $this->userIdField . "=?)";

        $bind[] = $username;

        $filter = "(&" . implode('', $conditions) . ")";

        return array($filter, $bind);
    }

    private function addSuffix($username)
    {
        if (!empty($this->usernameSuffix)) {
            $this->log("INFO: ldapfunctions addSuffix() - suffix (" . $this->usernameSuffix . ") added to username (" . $username . ").", 1);
            $username .= $this->usernameSuffix;
        }
        return $username;
    }

    private function log($text, $isDebug = 0)
    {
        // removed
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
        $this->log("INFO: ldapfunctions updateCredentials() - DB updated with token and password.", 1);
        $password = UsersManager::getPasswordHash($password);
        $token_auth = API::getInstance()->getTokenAuth($login, $password);
        $result = Db::query("UPDATE " . Common::prefixTable('user')
            . " SET password='" . $password . "', token_auth='" . $token_auth
            . "' WHERE login='" . $login . "' and password != '" . $password . "'");
        return $result;
    }
}