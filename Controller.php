<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap;

use Exception;
use Piwik\Common;
use Piwik\Config;
use Piwik\Notification;
use Piwik\Nonce;
use Piwik\Piwik;
use Piwik\Plugin\ControllerAdmin;
use Piwik\Plugins\Login\FormLogin;
use Piwik\Plugins\UsersManager\API as APIUsersManager;
use Piwik\Session;
use Piwik\View;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;

require_once PIWIK_INCLUDE_PATH . '/core/Config.php';

/**
 * Login controller
 *
 * @package Login
 */
class Controller extends \Piwik\Plugins\Login\Controller
{
    /**
     * @param $length
     * @return string
     */
    private function generatePassword($length)
    {
        $chars = "234567890abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $i = 0;
        $password = "";
        while ($i <= $length) {
            $password .= $chars{mt_rand(0, strlen($chars) - 1)};
            $i++;
        }
        return $password;
    }

    /**
     * Default function
     */
    public function index()
    {
        return $this->login();
    }

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

        $view->ldapConfig = LoginLdap::$defaultConfig;

        $config = Config::getInstance()->LoginLdap;
        foreach ($view->ldapConfig as $name => &$value) {
            if (isset($config[$name])) {
                $view->ldapConfig[$name] = $config[$name];
            }
        }

        return $view->render();
    }

    /**
     * @param $password
     * @throws \Exception
     */
    protected function checkPasswordHash($password)
    {
        // do not check password (Login uses hashed password, LoginLdap uses real passwords)
    }

    /**
    * Configure common view properties
    *
    * @param View $view
    */
    private function configureView($view)
    {
        $this->setBasicVariablesView($view);

        $view->linkTitle = Piwik::getRandomTitle();

        // crsf token: don't trust the submitted value; generate/fetch it from session data
        $view->nonce = Nonce::getNonce('LoginLdap.login');
    }

    /**
    * @param null $messageNoAccess
    * @param bool $infoMessage
    * @return string
    */
    public function login($messageNoAccess = null, $infoMessage = false)
    {
        $form = new FormLogin();
        if ($form->validate()) {
            $nonce = $form->getSubmitValue('form_nonce');
            if (Nonce::verifyNonce('LoginLdap.login', $nonce)) {
                $login = $form->getSubmitValue('form_login');
                $password = $form->getSubmitValue('form_password');
                $rememberMe = $form->getSubmitValue('form_rememberme') == '1';
                $md5Password = md5($password);
                try {
                    $this->authenticateAndRedirect($login, $md5Password, $rememberMe);
                } catch (Exception $e) {
                    $messageNoAccess = $e->getMessage();
                }
            } else {
                $messageNoAccess = $this->getMessageExceptionNoAccess();
            }
        }

        $view = new View('@Login/login');
        $view->AccessErrorString = $messageNoAccess;
        $view->infoMessage = nl2br($infoMessage);
        $view->addForm($form);
        $this->configureView($view);
        self::setHostValidationVariablesView($view);

        return $view->render();
    }
}
