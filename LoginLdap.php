<?php
/**
 * Piwik - Open source web analytics
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
    /**
     * @see Piwik_Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        $hooks = array(
            'Menu.Admin.addItems'              => 'addMenu',
            'Request.initAuthenticationObject' => 'initAuthenticationObject',
            'User.isNotAuthorized'             => 'noAccess',
            'API.Request.authenticate'         => 'ApiRequestAuthenticate',
            'AssetManager.getJavaScriptFiles'  => 'getJsFiles'
        );
        return $hooks;
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/Login/javascripts/login.js";
    }

    /**
     * Set config parameters during install
     */
    public function install()
    {
        Config::getInstance()->LoginLdap = array(
            'serverUrl'      => 'ldap://localhost/',
            'ldapPort'       => '389',
            'baseDn'         => 'OU=users,DC=localhost,DC=com',
            'userIdField'    => 'userPrincipalName',
            'mailField'      => 'mail',
            'aliasField'     => 'cn',
            'usernameSuffix' => '',
            'adminUser'      => '',
            'adminPass'      => '',
            'memberOf'       => '',
            'filter'         => '(objectClass=person)',
            'useKerberos'    => 'false',
            'debugEnabled'    => 'false'
        );
        Config::getInstance()->forceSave();
    }

    /**
     * Deactivate default Login module, as both cannot be activated together
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
     * Set login name and autehntication token for authentication request.
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

    /**
    * Removes stored password reset info if it exists.
    *
    * @param string $login The user login to check for.
    */
    public static function removePasswordResetInfo($login)
    {
        $optionName = self::getPasswordResetInfoOptionName($login);
        Option::delete($optionName);
    }

    /**
    * Gets password hash stored in password reset info.
    *
    * @param string $login The user login to check for.
    * @return string|false The hashed password or false if no reset info exists.
    */
    public static function getPasswordToResetTo($login)
    {
        $optionName = self::getPasswordResetInfoOptionName($login);
        return Option::get($optionName);
    }

    /**
    * Gets the option name for the option that will store a user's password change
    * request.
    *
    * @param string $login The user login for whom a password change was requested.
    * @return string
    */
    public static function getPasswordResetInfoOptionName($login)
    {
        return $login . '_reset_password_info';
    }
}
