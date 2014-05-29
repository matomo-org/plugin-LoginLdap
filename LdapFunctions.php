<?php
/**
 * Piwik - Open source web analytics
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

        $this->connect();
        $result = $this->getUserEntries($username);
        $this->log("INFO: ldapfunctions authenticateFu(" . $username . ") - getUserEntries: ".@json_encode($result), 1);
        if ($result['count'] > 0 && $result[0]['dn']) {
            $success = @ldap_bind($this->ldapconn, $result[0]['dn'], $password);
            $this->log("INFO: ldapfunctions authenticateFu(" . $username . ") - ldap_bind(" . $result[0]['dn'] . "): " . ($success ? 'success' : 'fail'), 0);
            $memberOfList = @$result[0]['memberof'];
            if ($success && !empty($this->memberOf) && !empty($memberOfList)) {
                $success = false;
                for ($i = 0; $i < $memberOfList['count']; $i++) {

                    if ($memberOfList[$i] == $this->memberOf) {
                        $success = true;
                    }
                }
                $this->log("INFO: ldapfunctions authenticateFu(" . $username . ") - check memberOf (" . $this->memberOf . "): " . ($success ? 'success' : 'fail'), 0);
            }
        }

        $this->close();

        if ($success) {
            if ($this->updateCredentials($username, $password)) {
                $this->log("INFO: ldapfunctions authenticateFu(" . $username . ") - ldap user password and token update success.", 1);
            }
        }

        return $success;
    }

    public function kerbthenticate($username)
    {

        if (isset($_SERVER["REMOTE_USER"])) {
            $this->log("INFO: ldapfunctions kerbthenticate(" . $username . ") - REMOTE_USER: " . $_SERVER["REMOTE_USER"], 1);
        }

        $success = false;

        $this->validate();

        if (empty($username)) {
            throw new Exception('username is not set');
        }

        $this->connect();

        $result = $this->getUserEntries($username);
        $this->log("INFO: ldapfunctions kerbthenticate(" . $username . ") - getUserEntries()", 0);
        if ($result['count'] > 0 && $result[0]['dn']) {
            $success = true;
            $this->log("INFO: ldapfunctions kerbthenticate(" . $username . ") - so far success, now we must validate memberOf group", 1);
            $memberOfList = @$result[0]['memberof'];
            if (!empty($this->memberOf) && !empty($memberOfList)) {
                $success = false;
                for ($i = 0; $i < $memberOfList['count']; $i++) {
                    if ($memberOfList[$i] == $this->memberOf) {
                        $success = true;
                    }
                }
                $this->log("INFO: ldapfunctions kerbthenticate(" . $username . ") - check memberOf (" . $this->memberOf . "): " . ($success ? 'success' : 'fail'), 0);
            }
        }

        $this->close();

        return $success;
    }

    public function getUser($username, $aliasField = 'cn', $mailField = 'mail')
    {

        $user = array();

        $this->validate();

        if (empty($username)) {
            throw new Exception('username is not set');
        }

        $this->connect();

        $result = $this->getUserEntries($username);
        if ($result['count'] > 0) {
            $user['username'] = $username;
            $user['alias'] = $result[0][$aliasField][0];
            $user['mail'] = $result[0][$mailField][0];
        }

        $this->close();

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

    private function connect()
    {

        $ldapconn = @ldap_connect($this->serverUrl, $this->ldapPort);
        $this->log("INFO: ldapfunctions connect() - ldap_connect(" . $this->serverUrl . ") started.", 1);

        if (!$ldapconn) {
            $this->log("WARN: ldapfunctions connect() - ldap_connect(" . $this->serverUrl . ") FAILED!",0);
            throw new Exception('could not connect to ldap server');
        }

        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);

        $this->ldapconn = $ldapconn;
    }

    private function getUserEntries($username)
    {

        $this->log("INFO: ldapfunctions getUserEntries(" . $username . ") - ldap_bind: " . $this->addSuffix($this->adminUser), 0);
        if (!empty($this->adminUser) && !@ldap_bind($this->ldapconn, $this->addSuffix($this->adminUser), $this->adminPass)) {
            $this->log("WARN: ldapfunctions getUserEntries(" . $username . ") - ldap_bind() as admin FAILED!", 0);
            throw new Exception('could not bind as admin');
        }

        if (!empty($this->filter)) {
            $searchFilter = "(&" . $this->filter . "(" . $this->userIdField . "=" . $this->addSuffix($username) . "))";
        } else {
            $searchFilter = $this->userIdField . "=" . $this->addSuffix($username);
        }
        $this->log("INFO: ldapfunctions getUserEntries(" . $username . ") - ldap_search: " . $searchFilter, 1);
        $search = @ldap_search($this->ldapconn, $this->baseDn, $searchFilter);
        if ($search) {
            $this->log("INFO: ldapfunctions getUserEntries(" . $username . ") - ldap_get_entries(); count: " . count($search), 1);
            return @ldap_get_entries($this->ldapconn, $search);
        } else {
            $this->log("WARN: ldapfunctions getUserEntries(" . $username . ") - ldap_get_entries() FAILED!", 0);
            throw new Exception('could not get user entries, maybe anonymous bind is forbidden or admin credentials are invalid');
        }
    }

    private function addSuffix($username)
    {
        if (!empty($this->usernameSuffix)) {
            $this->log("INFO: ldapfunctions addSuffix() - suffix (" . $this->usernameSuffix . ") added to username (" . $username . ").", 1);
            $username .= $this->usernameSuffix;
        }
        return $username;
    }

    private function close()
    {
        $this->log("INFO: ldapfunctions close() - ldap connection closed.", 1);
        @ldap_close($this->ldapconn);
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

?>
