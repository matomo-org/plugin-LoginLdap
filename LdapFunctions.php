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

    private $ldapconn = null;

    public function authenticateFu($username, $password)
    {

        if (empty($password)) {
            return false;
        }

        $success = false;

        $this->validate();

        if (empty($username)) {
            throw new Exception('username is not set');
        }

        $this->connect();
        $result = $this->getUserEntries($username);
        $this->log("FUNC: authenticateFu(" . $username . ") getUserEntries()");
        // $this->log("FUNC: getUserEntries: ".@json_encode($result));
        if ($result['count'] > 0 && $result[0]['dn']) {
            $success = @ldap_bind($this->ldapconn, $result[0]['dn'], $password);
            $this->log("FUNC: authenticateFu(" . $username . ", ...) ldap_bind (" . $result[0]['dn'] . "): " . ($success ? 'success' : 'fail'));
            $memberOfList = @$result[0]['memberof'];
            if ($success && !empty($this->memberOf) && !empty($memberOfList)) {
                $success = false;
                for ($i = 0; $i < $memberOfList['count']; $i++) {

                    if ($memberOfList[$i] == $this->memberOf) {
                        $success = true;
                    }
                }
                $this->log("FUNC: authenticateFu(" . $username . ", ...) check memberOf (" . $this->memberOf . "): " . ($success ? 'success' : 'fail'));
            }
        }

        $this->close();

        if ($success) {
            if ($this->updateCredentials($username, $password)) {
                $this->log("FUNC: LDAP user password and token updated successfully.");
            }
        }

        return $success;
    }

    public function kerbthenticate($username)
    {

        if (isset($_SERVER["REMOTE_USER"])) {
            $this->log("REMOTE_USER: " . $_SERVER["REMOTE_USER"]);
        }

        $success = false;

        $this->validate();

        if (empty($username)) {
            throw new Exception('username is not set');
        }

        $this->connect();

        $result = $this->getUserEntries($username);
        $this->log("FUNC: authenticateFu(" . $username . ") getUserEntries()");
        if ($result['count'] > 0 && $result[0]['dn']) {
            $success = true;
            $memberOfList = @$result[0]['memberof'];
            if (!empty($this->memberOf) && !empty($memberOfList)) {
                $success = false;
                for ($i = 0; $i < $memberOfList['count']; $i++) {
                    if ($memberOfList[$i] == $this->memberOf) {
                        $success = true;
                    }
                }
                $this->log("FUNC: authenticateFu(" . $username . ") check memberOf (" . $this->memberOf . "): " . ($success ? 'success' : 'fail'));
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
        $this->log("FUNC: ldap_connect(" . $this->serverUrl . ")");

        if (!$ldapconn) {
            $this->log("FUNC: ldap_connect() FAILED!");
            throw new Exception('could not connect to ldap server');
        }

        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);

        $this->ldapconn = $ldapconn;
    }

    private function getUserEntries($username)
    {

        $this->log("FUNC: getUserEntries(" . $username . ") ldap_bind: " . $this->addSuffix($this->adminUser));
        if (!empty($this->adminUser) && !@ldap_bind($this->ldapconn, $this->addSuffix($this->adminUser), $this->adminPass)) {
            $this->log("FUNC: ldap_bind() as admin FAILED!");
            throw new Exception('cound not bind as admin');
        }

        if (!empty($this->filter)) {
            $searchFilter = "(&" . $this->filter . "(" . $this->userIdField . "=" . $this->addSuffix($username) . "))";
        } else {
            $searchFilter = $this->userIdField . "=" . $this->addSuffix($username);
        }
        $this->log("FUNC: getUserEntries(" . $username . ", ...) ldap_search: " . $searchFilter);
        $search = @ldap_search($this->ldapconn, $this->baseDn, $searchFilter);
        if ($search) {
            $this->log("FUNC: ldap_get_entries(); count: " . count($search));
            return @ldap_get_entries($this->ldapconn, $search);
        } else {
            $this->log("FUNC: ldap_get_entries() FAILED!");
            throw new Exception('could not get user entries, maybe anonymous bind is forbidden or admin credentials are invalid');
        }
    }

    private function addSuffix($username)
    {

        if (!empty($this->usernameSuffix)) {
            $username .= $this->usernameSuffix;
        }
        return $username;
    }

    private function close()
    {
        $this->log("FUNC: ldap_close()");
        @ldap_close($this->ldapconn);
    }

    private function log($text)
    {
        $path = LdapAuth::getLogPath();
        $f = fopen($path, 'a');
        if ($f != null) {
            fwrite($f, strftime('%F %T') . ": $text\n");
            fclose($f);
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
        $password = UsersManager::getPasswordHash($password);
        $token_auth = API::getInstance()->getTokenAuth($login, $password);
        $result = Db::query("UPDATE " . Common::prefixTable('user')
            . " SET password='" . $password . "', token_auth='" . $token_auth
            . "' WHERE login='" . $login . "' and password != '" . $password . "'");
        return $result;
    }

}

?>
