<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap;

use Exception;
use Piwik\Access;
use Piwik\Auth;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\FrontController;
use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;
use Piwik\Plugin\Manager;
use Piwik\Plugins\Login\Login;
use Piwik\Plugins\LoginLdap\Auth\Base as AuthBase;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Session;
use Piwik\View;

/**
 *
 * @package LoginLdap
 */
class LoginLdap extends \Piwik\Plugin
{
    /**
     * @return array
     */
    public function registerEvents()
    {
        $hooks = array(
            'Request.initAuthenticationObject'       => 'initAuthenticationObject',
            'User.isNotAuthorized'                   => 'noAccess',
            'API.Request.authenticate'               => 'ApiRequestAuthenticate',
            'AssetManager.getJavaScriptFiles'        => 'getJsFiles',
            'AssetManager.getStylesheetFiles'        => 'getStylesheetFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Controller.Login.resetPassword'         => 'disablePasswordResetForLdapUsers',
            'Controller.LoginLdap.resetPassword'     => 'disablePasswordResetForLdapUsers',
            'Controller.Login.confirmResetPassword'  => 'disableConfirmResetPasswordForLdapUsers',
            'UsersManager.checkPassword'             => 'checkPassword',
        );
        return $hooks;
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/Login/javascripts/login.js";
        $jsFiles[] = "plugins/LoginLdap/angularjs/admin/admin.controller.js";
        $jsFiles[] = "plugins/LoginLdap/angularjs/login-ldap-testable-field/login-ldap-testable-field.directive.js";
    }

    public function getStylesheetFiles(&$stylesheetFiles)
    {
        $stylesheetFiles[] = "plugins/Login/stylesheets/login.less";
        $stylesheetFiles[] = "plugins/Login/stylesheets/variables.less";
        $stylesheetFiles[] = "plugins/LoginLdap/angularjs/admin/admin.controller.less";
        $stylesheetFiles[] = "plugins/LoginLdap/angularjs/login-ldap-testable-field/login-ldap-testable-field.directive.less";
    }

    public function getClientSideTranslationKeys(&$keys)
    {
        $keys[] = "General_NUsers";
        $keys[] = "LoginLdap_OneUser";
        $keys[] = "LoginLdap_MemberOfCount";
        $keys[] = "LoginLdap_FilterCount";
        $keys[] = "LoginLdap_Test";
        $keys[] = "General_NUsers";
    }

    /**
     * Deactivate default Login module, as both cannot be activated together
     *
     * TODO: shouldn't disable Login plugin but have to wait until Dependency Injection is added to core
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

    public function disableConfirmResetPasswordForLdapUsers()
    {
        $login = Common::getRequestVar('login', false);
        if (empty($login)) {
            return;
        }

        if ($this->isUserLdapUser($login)) {
            // redirect to login w/ error message
            $errorMessage = Piwik::translate("LoginLdap_UnsupportedPasswordReset");
            echo FrontController::getInstance()->dispatch('LoginLdap', 'login', array($errorMessage));

            exit;
        }
    }

    public function disablePasswordResetForLdapUsers()
    {
        $login = Common::getRequestVar('form_login', false);
        if (empty($login)) {
            return;
        }

        if ($this->isUserLdapUser($login)) {
            $errorMessage = Piwik::translate("LoginLdap_UnsupportedPasswordReset");

            $view = new View("@Login/resetPassword");
            $view->infoMessage = null;
            $view->formErrors = array($errorMessage);

            echo $view->render();

            exit;
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
     * Initializes the authentication object.
     * Listens to Request.initAuthenticationObject hook.
     */
    function initAuthenticationObject($activateCookieAuth = false)
    {
        $auth = AuthBase::factory();
        StaticContainer::getContainer()->set('Piwik\Auth', $auth);

        Login::initAuthenticationFromCookie($auth, $activateCookieAuth);
    }

    /**
     * Set login name and authentication token for authentication request.
     * Listens to API.Request.authenticate hook.
     */
    public function ApiRequestAuthenticate($tokenAuth)
    {
        /** @var Auth $auth */
        $auth = StaticContainer::get('Piwik\Auth');
        $auth->setLogin($login = null);
        $auth->setTokenAuth($tokenAuth);
    }

    private function isUserLdapUser($login)
    {
        $userMapper = new UserMapper();
        return $userMapper->isUserLdapUser($login);
    }

    private function isCurrentUserLdapUser(Auth $auth)
    {
        $currentUserLogin = $auth->getLogin();

        if (empty($currentUserLogin)) {
            return false;
        }

        return $this->isUserLdapUser($auth->getLogin());
    }

    /**
     * Throws Exception when LDAP user tries to change password
     * because such user's pass should be managed directly on LDAP host
     *
     * @throws Exception
     */
    public function disablePasswordChangeForLdapUsers(Auth $auth)
    {
        if ($this->isCurrentUserLdapUser($auth)) {
            throw new Exception(
                Piwik::translate('LoginLdap_LdapUserCantChangePassword')
            );
        }
    }

    /**
     * Listens to UsersManager.checkPassword hook.
     */
    public function checkPassword()
    {
        $auth = StaticContainer::get('Piwik\Auth');
        $this->disablePasswordChangeForLdapUsers($auth);
    }
}
