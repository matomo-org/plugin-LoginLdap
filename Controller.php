<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap;

use Piwik\Config;
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

        $config = Config::getInstance()->LoginLdap;

        $serverNames = @$config['servers'] ?: array();

        $view->servers = array();
        foreach ($serverNames as $server) {
            $configName = 'LoginLdap_' . $server;
            $serverConfig = Config::getInstance()->__get($configName);
            if (!empty($serverConfig)) {
                $serverConfig['name'] = $server;
                $view->servers[] = $serverConfig;
            }
        }

        $view->ldapConfig = LoginLdap::$defaultConfig;

        unset($config['servers']);
        foreach ($view->ldapConfig as $name => &$value) {
            if (isset($config[$name])) {
                $view->ldapConfig[$name] = $config[$name];
            }
        }

        return $view->render();
    }
}