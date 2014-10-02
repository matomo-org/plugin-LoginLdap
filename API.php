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
use Exception;

/**
 */
class API extends \Piwik\Plugin\API
{
    /**
     * The LdapUsers instance to use when executing LDAP logic regarding LDAP users.
     *
     * @var LdapUsers
     */
    private $ldapUsers;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->ldapUsers = LdapUsers::makeConfigured();
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

    /**
     * Returns count of users in LDAP that are member of a specific group of names. Uses a search
     * filter with memberof=?.
     *
     * @param string $memberOf The group to check.
     * @return int
     * @throws Exception if the current user is not a Super User or something goes wrong with LDAP.
     */
    public function getCountOfUsersMemberOf($memberOf)
    {
        Piwik::checkUserHasSuperUserAccess();

        $memberOf = Common::unsanitizeInputValue($memberOf);

        return $this->ldapUsers->getCountOfUsersMatchingFilter("(memberof=?)", array($memberOf));
    }

    /**
     * Returns count of users in LDAP that match an LDAP filter. If the filter is incorrect,
     * `null` is returned.
     *
     * @param string $filter The filter to match.
     * @return int|null
     * @throws Exception if the current user is not a Super User or something goes wrong with LDAP.
     */
    public function getCountOfUsersMatchingFilter($filter)
    {
        Piwik::checkUserHasSuperUserAccess();

        $filter = Common::unsanitizeInputValue($filter);

        try {
            return $this->ldapUsers->getCountOfUsersMatchingFilter($filter);
        } catch (Exception $ex) {
            if (stripos($ex->getMessage(), "Bad search filter") !== false) {
                throw new Exception(Piwik::translate("LoginLdap_InvalidFilter"));
            } else {
                throw $ex;
            }
        }
    }

    private function checkHttpMethodIsPost()
    {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            throw new Exception("Invalid HTTP method.");
        }
    }
}