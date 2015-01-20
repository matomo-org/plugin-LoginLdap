<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
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
     * The UserSynchronizer instance to use when synchronizing users.
     *
     * @var UserSynchronizer
     */
    private $userSynchronizer;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->ldapUsers = LdapUsers::makeConfigured();
        $this->userSynchronizer = UserSynchronizer::makeConfigured();
    }

    /**
     * Saves LoginLdap config.
     *
     * @param string $data JSON encoded config array.
     * @return array
     * @throws Exception if user does not have super access, if this is not a POST method or
     *                   if JSON is not supplied.
     */
    public function saveLdapConfig($data)
    {
        $this->checkHttpMethodIsPost();
        Piwik::checkUserHasSuperUserAccess();

        $data = json_decode(Common::unsanitizeInputValue($data), true);

        Config::savePluginOptions($data);

        return array('result' => 'success', 'message' => Piwik::translate("General_YourChangesHaveBeenSaved"));
    }

    /**
     * Saves LDAP server config.
     *
     * @param string $data JSON encoded array w/ server info.
     * @return array
     * @throws Exception
     */
    public function saveServersInfo($data)
    {
        $this->checkHttpMethodIsPost();
        Piwik::checkUserHasSuperUserAccess();

        $servers = json_decode(Common::unsanitizeInputValue($data), true);

        Config::saveLdapServerConfigs($servers);

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

        $memberOfField = Config::getRequiredMemberOfField();

        return $this->ldapUsers->getCountOfUsersMatchingFilter("(".$memberOfField."=?)", array($memberOf));
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

    /**
     * Synchronizes a single user in LDAP. This method can be used by superusers to synchronize
     * a user before (s)he logs in.
     *
     * @param string $login The login of the user.
     * @throws Exception if the user cannot be found or a problem occurs during synchronization.
     */
    public function synchronizeUser($login)
    {
        Piwik::checkUserHasSuperUserAccess();

        $ldapUser = $this->ldapUsers->getUser($login);
        if (empty($ldapUser)) {
            throw new Exception(Piwik::translate('LoginLdap_UserNotFound', $login));
        }

        $this->userSynchronizer->synchronizeLdapUser($login, $ldapUser);
        $this->userSynchronizer->synchronizePiwikAccessFromLdap($login, $ldapUser);
    }

    private function checkHttpMethodIsPost()
    {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            throw new Exception("Invalid HTTP method.");
        }
    }
}