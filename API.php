<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
use Exception;

/**
 * Exposes administrative endpoints for LoginLdap configuration and LDAP user synchronization.
 *
 * @method static \Piwik\Plugins\LoginLdap\API getInstance()
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
     * Saves the LoginLdap plugin configuration.
     *
     * @param string $data JSON-encoded LoginLdap configuration values.
     * @return array{result: string, message: string} The save status payload.
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
     * Saves the configured LDAP server definitions.
     *
     * @param string $data JSON-encoded LDAP server configuration entries.
     * @return array{result: string, message: string} The save status payload.
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
     * Returns how many LDAP users belong to a specific group.
     *
     * @param string $memberOf The LDAP group value to match against the configured membership field.
     * @return int The number of matching LDAP users.
     */
    public function getCountOfUsersMemberOf($memberOf)
    {
        Piwik::checkUserHasSuperUserAccess();

        $memberOf = Common::unsanitizeInputValue($memberOf);

        $memberOfField = Config::getRequiredMemberOfField();

        return $this->ldapUsers->getCountOfUsersMatchingFilter("(" . $memberOfField . "=?)", array($memberOf));
    }

    /**
     * Returns count of users in LDAP that match an LDAP filter.
     *
     * @param string $filter The LDAP search filter to evaluate.
     * @return int The number of matching LDAP users.
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
     * Synchronizes one LDAP user into Matomo before that user logs in.
     *
     * @param string $login The Matomo login to synchronize from LDAP.
     * @return void
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

    /**
     * Returns the LDAP-backed Matomo logins already stored in the database.
     *
     * @return string[] The Matomo login names marked as LDAP users.
     */
    public function getExistingLdapUsersFromDb()
    {
        Piwik::checkUserHasSuperUserAccess();

        $ldapUsers = new LdapUsers();

        return $ldapUsers->getExistingLdapUsersFromDb();
    }

    private function checkHttpMethodIsPost()
    {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            throw new Exception("Invalid HTTP method.");
        }
    }
}
