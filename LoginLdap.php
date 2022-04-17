<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap;

use Exception;
use Piwik\Auth;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\FrontController;
use Piwik\Piwik;
use Piwik\Plugin\Manager;
use Piwik\Plugins\LoginLdap\Auth\Base as AuthBase;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
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
    }

    public function getStylesheetFiles(&$stylesheetFiles)
    {
        $stylesheetFiles[] = "plugins/Login/stylesheets/login.less";
        $stylesheetFiles[] = "plugins/Login/stylesheets/variables.less";
        $stylesheetFiles[] = "plugins/LoginLdap/vue/src/Admin/Admin.less";
        $stylesheetFiles[] = "plugins/LoginLdap/vue/src/TestableField/TestableField.less";
    }

    public function getClientSideTranslationKeys(&$keys)
    {
        $keys[] = "General_NUsers";
        $keys[] = "LoginLdap_OneUser";
        $keys[] = "LoginLdap_MemberOfCount";
        $keys[] = "LoginLdap_FilterCount";
        $keys[] = "LoginLdap_Test";
        $keys[] = "General_NUsers";
        $keys[] = 'LoginLdap_Settings';
        $keys[] = 'General_Note';
        $keys[] = 'LoginLdap_UpdateFromPre300Warning';
        $keys[] = 'LoginLdap_UseLdapForAuthentication';
        $keys[] = 'LoginLdap_Kerberos';
        $keys[] = 'LoginLdap_KerberosDescription';
        $keys[] = 'LoginLdap_StripDomainFromWebAuth';
        $keys[] = 'LoginLdap_StripDomainFromWebAuthDescription';
        $keys[] = 'LoginLdap_NetworkTimeout';
        $keys[] = 'LoginLdap_MemberOfField';
        $keys[] = 'LoginLdap_MemberOfFieldDescription';
        $keys[] = 'LoginLdap_MemberOf';
        $keys[] = 'LoginLdap_MemberOfCount';
        $keys[] = 'LoginLdap_Filter';
        $keys[] = 'LoginLdap_FilterCount';
        $keys[] = 'LoginLdap_FilterDescription';
        $keys[] = 'LoginLdap_UserSyncSettings';
        $keys[] = 'LoginLdap_UserIdField';
        $keys[] = 'LoginLdap_UserIdFieldDescription';
        $keys[] = 'LoginLdap_PasswordField';
        $keys[] = 'LoginLdap_MailField';
        $keys[] = 'LoginLdap_MailFieldDescription';
        $keys[] = 'LoginLdap_UsernameSuffix';
        $keys[] = 'LoginLdap_UsernameSuffixDescription';
        $keys[] = 'LoginLdap_NewUserDefaultSitesViewAccess';
        $keys[] = 'LoginLdap_NewUserDefaultSitesViewAccessDescription';
        $keys[] = 'LoginLdap_AccessSyncSettings';
        $keys[] = 'LoginLdap_EnableLdapAccessSynchronization';
        $keys[] = 'LoginLdap_EnableLdapAccessSynchronizationDescription';
        $keys[] = 'LoginLdap_ExpectedLdapAttributes';
        $keys[] = 'LoginLdap_ExpectedLdapAttributesPrelude';
        $keys[] = 'LoginLdap_LdapViewAccessField';
        $keys[] = 'LoginLdap_LdapViewAccessFieldDescription';
        $keys[] = 'LoginLdap_LdapAdminAccessField';
        $keys[] = 'LoginLdap_LdapAdminAccessFieldDescription';
        $keys[] = 'LoginLdap_LdapSuperUserAccessField';
        $keys[] = 'LoginLdap_LdapSuperUserAccessFieldDescription';
        $keys[] = 'LoginLdap_LdapUserAccessAttributeServerSpecDelimiter';
        $keys[] = 'LoginLdap_LdapUserAccessAttributeServerSpecDelimiterDescription';
        $keys[] = 'LoginLdap_LdapUserAccessAttributeServerSeparator';
        $keys[] = 'LoginLdap_LdapUserAccessAttributeServerSeparatorDescription';
        $keys[] = 'LoginLdap_ThisPiwikInstanceName';
        $keys[] = 'LoginLdap_ThisPiwikInstanceNameDescription';
        $keys[] = 'LoginLdap_LoadUser';
        $keys[] = 'LoginLdap_LoadUserDescription';
        $keys[] = 'LoginLdap_Go';
        $keys[] = 'LoginLdap_LDAPServers';
        $keys[] = 'LoginLdap_ServerName';
        $keys[] = 'LoginLdap_ServerUrl';
        $keys[] = 'LoginLdap_LdapPort';
        $keys[] = 'LoginLdap_LdapUrlPortWarning';
        $keys[] = 'LoginLdap_StartTLS';
        $keys[] = 'LoginLdap_StartTLSFieldHelp';
        $keys[] = 'LoginLdap_BaseDn';
        $keys[] = 'LoginLdap_AdminUser';
        $keys[] = 'LoginLdap_AdminUserDescription';
        $keys[] = 'LoginLdap_AdminPass';
        $keys[] = 'LoginLdap_PasswordFieldHelp';
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
