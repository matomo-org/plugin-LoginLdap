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

    public function authenticateFu($username, $password)
    {
        $this->log("INFO: ldapfunctions authenticateFu(" . $username . ") - started. Password is " . strlen($password) . " chars.", 0);

        if (empty($password)) {
            $this->log("WARN: ldapfunctions authenticateFu(" . $username . ") - password is not set!", 1);
            return false;
        }

        $success = false;

        $this->validate();

        if (empty($username)) {
            $this->log("WARN: ldapfunctions authenticateFu(" . $username . ") - username is not set!", 1);
            throw new Exception('username is not set');
        }

        $ldapClient = new LdapClient();

        try {
            $ldapClient->connect($this->serverUrl, $this->ldapPort);

            $result = $this->getUserEntries($ldapClient, $username); // TODO: removed logging statement, think about putting back
            if ($result['count'] > 0 && !empty($result[0]['dn'])) {
                $userDn = $result[0]['dn'];

                if (!$ldapClient->bind($userDn, $password)) {
                    throw new Exception("Unable to bind to $userDn.");
                }
            } else {
                throw new Exception("No such user '$username'.");
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
            $this->log("INFO: ldapfunctions authenticateFu(" . $username . ") - ldap user password and token update success.", 1);
        }

        return true;
    }

    public function kerbthenticate($username)
    {
        if (isset($_SERVER["REMOTE_USER"])) {
            $this->log("INFO: ldapfunctions kerbthenticate(" . $username . ") - REMOTE_USER: " . $_SERVER["REMOTE_USER"], 1);
        }

        $this->validate();

        if (empty($username)) {
            throw new Exception('username is not set');
        }

        $ldapClient = new LdapClient();

        try {
            $ldapClient->connect($this->serverUrl, $this->ldapPort);

            $result = $this->getUserEntries($ldapClient, $username); // TODO: removed logging statement, think about putting back
            if ($result['count'] == 0 || empty($result[0]['dn'])) {
                throw new Exception("User not member of required group.");
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

        $userEntries = $ldapClient->fetchAll($this->baseDn, $this->getUserEntryQuery($username));

        // TODO: test anonymous bind (for validity of old error message)
        if ($userEntries === null) {
            throw new Exception("Couldn't search for LDAP entries.");
        }

        return $userEntries;
    }

    private function getUserEntryQuery($username)
    {
        // TODO: add LDAP query builder?
        $username = $this->addSuffix($username);

        $conditions = array();
        if (!empty($this->filter)) {
            $conditions[] = $this->filter;
        }
        if (!empty($this->memberOf)) {
            $conditions[] = "(memberof=" . $this->memberOf . ")";
        }
        $conditions[] = "(" . $this->userIdField . "=" . $username . ")";

        return "(&" . implode('', $conditions) . ")";
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
        $debugEnabled = @Config::getInstance()->LoginLdap['debugEnabled'];
        if ($debugEnabled == "" || $debugEnabled == "false") {
            $debugEnabled = false;
        }
        if ($isDebug == 0 or ($isDebug == 1 and $debugEnabled == true)) {
            if (LdapAuth::getLogPath()) {
                $path = LdapAuth::getLogPath();
                $f = fopen($path, 'a');
                if ($f != null) {
                    fwrite($f, strftime('%F %T') . ": $text\n");
                    fclose($f);
                }
            }
        }
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