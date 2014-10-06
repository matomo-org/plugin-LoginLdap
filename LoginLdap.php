<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package LoginLdap
 */
namespace Piwik\Plugins\LoginLdap;

use Exception;
use Piwik\Config;
use Piwik\FrontController;
use Piwik\Menu\MenuAdmin;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Plugin\Manager;
use Piwik\Plugins\Login\Login;
use Piwik\Session;

/**
 *
 * @package LoginLdap
 */
class LoginLdap extends \Piwik\Plugin
{
    public static $defaultConfig = array(
        'userIdField' => "uid",
        'mailField' => "mail",
        'aliasField' => "cn",
        'usernameSuffix' => "",
        'adminUser' => "",
        'adminPass' => "",
        'memberOf' => "",
        'filter' => ""
    );

    /**
     * @see Piwik_Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        $hooks = array(
            'Menu.Admin.addItems'                    => 'addMenu',
            'Request.initAuthenticationObject'       => 'initAuthenticationObject',
            'User.isNotAuthorized'                   => 'noAccess',
            'API.Request.authenticate'               => 'ApiRequestAuthenticate',
            'AssetManager.getJavaScriptFiles'        => 'getJsFiles',
            'AssetManager.getStylesheetFiles'        => 'getStylesheetFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys'
        );
        return $hooks;
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/Login/javascripts/login.js";
        $jsFiles[] = "plugins/LoginLdap/javascripts/angularjs/admin/controller.js";
    }

    public function getStylesheetFiles(&$stylesheetFiles)
    {
        $stylesheetFiles[] = "plugins/LoginLdap/javascripts/angularjs/admin/admin.less";
    }

    public function getClientSideTranslationKeys(&$keys)
    {
        $keys[] = "General_NUsers";
        $keys[] = "LoginLdap_OneUser";
        $keys[] = "LoginLdap_MemberOfCount";
        $keys[] = "LoginLdap_FilterCount";
    }

    /**
     * Deactivate default Login module, as both cannot be activated together
     *
     * TODO: shouldn't disable Login plugin but have to until Dependency Injection is added to core
     */
    public function activate()
    {
        if (Manager::getInstance()->isPluginActivated("Login") == true) {
            Manager::getInstance()->deactivatePlugin("Login");
        }
    }

    /**
     * Activate default Login module, as one of them is needed to access Piwik
     */
    public function deactivate()
    {
        if (Manager::getInstance()->isPluginActivated("Login") == false) {
            Manager::getInstance()->activatePlugin("Login");
        }
    }

    /**
     * Redirects to Login form with error message.
     * Listens to User.isNotAuthorized hook.
     */
    public function noAccess(Exception $exception)
    {
        $exceptionMessage = $exception->getMessage();

        echo FrontController::getInstance()->dispatch('LoginLdap', 'login', array($exceptionMessage));
    }

    /**
     * Add admin menu items
     */
    function addMenu()
    {
        MenuAdmin::getInstance()->add('CoreAdminHome_MenuManage', 'LoginLdap_MenuLdap', array('module' => 'LoginLdap', 'action' => 'admin'),
            Piwik::hasUserSuperUserAccess(), $order = 3);
    }

    /**
     * Set login name and authentication token for authentication request.
     * Listens to API.Request.authenticate hook.
     */
    public function ApiRequestAuthenticate($tokenAuth)
    {
        \Piwik\Registry::get('auth')->setLogin($login = null);
        \Piwik\Registry::get('auth')->setTokenAuth($tokenAuth);
    }

    /**
     * Initializes the authentication object.
     * Listens to Request.initAuthenticationObject hook.
     */
    function initAuthenticationObject($activateCookieAuth = false)
    {
        $auth = new LdapAuth();
        \Piwik\Registry::set('auth', $auth);

        Login::initAuthenticationFromCookie($auth, $activateCookieAuth);
    }
}
