<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap;

use Piwik\Option;
use Piwik\Updater;
use Piwik\Updates;

/**
 */
class Updates_3_0_0 extends Updates
{
    public function doUpdate(Updater $updater)
    {
        // when updating from pre-3.0 versions, set use_ldap_for_authentication to 0 and make sure
        // a warning displays in the UI to not set it to 1
        \Piwik\Config::getInstance()->LoginLdap['use_ldap_for_authentication'] = 0;
        \Piwik\Config::getInstance()->forceSave();

        Option::set('LoginLdap_updatedFromPre3_0', 1);
    }
}
