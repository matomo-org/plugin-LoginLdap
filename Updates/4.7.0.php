<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap;

use Piwik\Updater;
use Piwik\Updates;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
use Piwik\Plugins\LoginLdap\API as LoginLdapAPI;

/**
 */
class Updates_4_7_0 extends Updates
{
    public function doUpdate(Updater $updater)
    {
        // when updating from pre-3.0 versions, set use_ldap_for_authentication to 0 and make sure
        // a warning displays in the UI to not set it to 1
        $oldValue = \Piwik\Config::getInstance()->LoginLdap['synchronize_users_after_login'];
        \Piwik\Config::getInstance()->LoginLdap['synchronize_users_after_login'] = 1;
        \Piwik\Config::getInstance()->forceSave();

        $ldapUsers = LdapUsers::makeConfigured();
        $logins = $ldapUsers->getAllUserLogins();
        $loginLdapAPI = LoginLdapAPI::getInstance();

        foreach ($logins as $login) {
            try {
                $loginLdapAPI->synchronizeUser($login);
            } catch (\Exception $ex) {

            }
        }


        \Piwik\Config::getInstance()->LoginLdap['synchronize_users_after_login'] = $oldValue;
        \Piwik\Config::getInstance()->forceSave();
    }
}
