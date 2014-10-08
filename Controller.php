<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap;

use Piwik\Nonce;
use Piwik\Notification;
use Piwik\Piwik;
use Piwik\Plugin\ControllerAdmin;
use Piwik\Session;
use Piwik\View;

/**
 * Login controller
 *
 * @package Login
 */
class Controller extends \Piwik\Plugins\Login\Controller
{
    /**
     * @return string
     */
    public function admin()
    {
        Piwik::checkUserHasSuperUserAccess();
        $view = new View('@LoginLdap/index');

        ControllerAdmin::setBasicVariablesAdminView($view);

        if (!function_exists('ldap_connect')) {
            $notification = new Notification(Piwik::translate('LoginLdap_LdapExtensionMissing'));
            $notification->context = Notification::CONTEXT_ERROR;
            $notification->type = Notification::TYPE_PERSISTENT;
            Notification\Manager::notify('LoginLdap_LdapExtensionMissing', $notification);
        }

        $this->setBasicVariablesView($view);

        $serverNames = Config::getServerNameList() ?: array();

        $view->servers = array();
        foreach ($serverNames as $server) {
            $serverConfig = Config::getServerConfig($server);
            if (!empty($serverConfig)) {
                $serverConfig['name'] = $server;
                $view->servers[] = $serverConfig;
            }
        }

        $view->ldapConfig = Config::getPluginOptionValuesWithDefaults();

        return $view->render();
    }
}