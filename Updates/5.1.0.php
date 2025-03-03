<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap;

use Piwik\Plugin\Manager;
use Piwik\Updater;
use Piwik\Updates;

/**
 */
class Updates_5_1_0 extends Updates
{
    public function doUpdate(Updater $updater)
    {
        if (Manager::getInstance()->isPluginActivated("Login") == false) {
            Manager::getInstance()->activatePlugin("Login");
        }
    }
}
