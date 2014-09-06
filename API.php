<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap;

use Piwik\Common;
use Piwik\Config;
use Piwik\Piwik;
use Piwik\Plugins\LoginLdap\Ldap\ServerInfo;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
use Piwik\Plugins\UsersManager\API as UsersManagerApi;
use Exception;

/**
 */
class API extends \Piwik\Plugin\API
{
    /**
     * Creates a Piwik user using LDAP information.
     *
     * @param string $ldapUserName The username in LDAP.
     * @throws Exception if no user is found or an error occurs while connecting to LDAP.
     * @return array
     */
    public function synchronizeLdapUser($ldapUserName)
    {
        $this->checkHttpMethodIsPost();
        Piwik::checkUserHasSuperUserAccess();

        $ldapUsers = LdapUsers::makeConfigured();
        $ldapUser = $ldapUsers->getUser(Common::unsanitizeInputValue($ldapUserName));

        if (empty($ldapUser)) {
            throw new Exception(Piwik::translate('LoginLdap_UserNotFound', $ldapUserName));
        }

        $piwikUser = $ldapUsers->createPiwikUserEntryForLdapUser($ldapUser);

        UsersManagerApi::getInstance()->addUser(
            $piwikUser['login'], $piwikUser['password'], $piwikUser['email'], $piwikUser['alias']);

        return array('result' => 'success', 'message' => Piwik::translate('LoginLdap_LdapUserAdded'));
    }

    /**
     * Saves LoginLdap config.
     *
     * @param string $config JSON encoded config array.
     * @return array
     * @throws Exception if user does not have super access, if this is not a POST method or
     *                   if JSON is not supplied.
     */
    public function saveLdapConfig($config)
    {
        $this->checkHttpMethodIsPost();
        Piwik::checkUserHasSuperUserAccess();

        $config = json_decode(Common::unsanitizeInputValue($config), true);

        foreach (LoginLdap::$defaultConfig as $name => $value) {
            if (isset($config[$name])) {
                Config::getInstance()->LoginLdap[$name] = $config[$name];
            }
        }

        Config::getInstance()->forceSave();

        return array('result' => 'success', 'message' => Piwik::translate("General_YourChangesHaveBeenSaved"));
    }

    /**
     * Saves LDAP server config.
     *
     * @param string $servers JSON encoded server info array.
     * @return array
     * @throws Exception
     */
    public function saveServersInfo($servers)
    {
        $this->checkHttpMethodIsPost();
        Piwik::checkUserHasSuperUserAccess();

        $servers = json_decode(Common::unsanitizeInputValue($servers), true);

        $serverNames = array();
        foreach ($servers as $serverInfo) {
            ServerInfo::saveServerConfig($serverInfo, $forceSave = false);

            $serverNames[] = $serverInfo['name'];
        }
        Config::getInstance()->LoginLdap['servers']= $serverNames;

        Config::getInstance()->forceSave();

        return array('result' => 'success', 'message' => Piwik::translate("General_YourChangesHaveBeenSaved"));
    }

    private function checkHttpMethodIsPost()
    {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            throw new Exception("Invalid HTTP method.");
        }
    }
}