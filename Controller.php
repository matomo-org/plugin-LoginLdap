<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap;

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
    public function admin($infoMessage = false)
    {
        Piwik::checkUserHasSuperUserAccess();
        $view = new View('@LoginLdap/index');

        ControllerAdmin::setBasicVariablesAdminView($view);

        if (!function_exists('ldap_connect')) {
            $notification = new Notification(Piwik::translate('LoginLdap_LdapFunctionsMissing'));
            $notification->context = Notification::CONTEXT_ERROR;
            Notification\Manager::notify('LoginLdap_LdapFunctionsMissing', $notification);
        }

        $this->setBasicVariablesView($view);
        $view->infoMessage = nl2br($infoMessage);

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