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
     * Add a user from LDAP in the database.
     * A user is retrieved from LDAP by
     * - a login that has to be unique and valid
     *
     * @see userExists()
     * @see isValidLoginString()
     * @see isValidPasswordString()
     * @see isValidEmailString()
     *
     * @exception in case of an invalid parameter
     */
    private function addUserLdap($userLogin)
    {
        $ldapUsers = new LdapUsers();

        $ldapUser = $ldap->getUser($userLogin);
        if (!empty($ldapUser)) {
            $piwikUser = $ldapUsers->createPiwikUserEntryForLdapUser($ldapUser);

            if (empty($user['email'])) { // a valid email is needed to create a new user
                $suffix = @Config::getInstance()->LoginLdap['usernameSuffix'];
                $domain = !empty($suffix) ? $suffix : '@mydomain.com';
                $user['email'] = $userLogin . $domain;
            }

            UsersManagerApi::getInstance()->addUser($user['login'], $user['password'], $user['email'], $user['alias']);

            return true;
        }

        return false;
    }

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

        if (!Config::getInstance()->LoginLdap) {
            $view->serverUrl = "ldap://localhost";
            $view->ldapPort = "389";
            $view->baseDn = "";
            $view->userIdField = "sAMAccountName";
            $view->mailField = "mail";
            $view->aliasField = "cn";
            $view->usernameSuffix = "@dc.com";
            $view->adminUser = "ldap_user";
            $view->adminPass = "ldap_pass";
            $view->memberOf = "";
            $view->filter = "";
            $view->useKerberos = "false";
            $view->debugEnabled = "false";
            $view->autoCreateUser = "false";
        } else {
            $view->serverUrl = @Config::getInstance()->LoginLdap['serverUrl'];
            $view->ldapPort = @Config::getInstance()->LoginLdap['ldapPort'];
            $view->baseDn = @Config::getInstance()->LoginLdap['baseDn'];
            $view->userIdField = @Config::getInstance()->LoginLdap['userIdField'];
            $view->mailField = @Config::getInstance()->LoginLdap['mailField'];
            $view->aliasField = @Config::getInstance()->LoginLdap['aliasField'];
            $view->usernameSuffix = @Config::getInstance()->LoginLdap['usernameSuffix'];
            $view->adminUser = @Config::getInstance()->LoginLdap['adminUser'];
            $view->adminPass = @Config::getInstance()->LoginLdap['adminPass'];
            $view->memberOf = @Config::getInstance()->LoginLdap['memberOf'];
            $view->filter = @Config::getInstance()->LoginLdap['filter'];
            $view->useKerberos = @Config::getInstance()->LoginLdap['useKerberos'];
            $view->debugEnabled = @Config::getInstance()->LoginLdap['debugEnabled'];
            $view->autoCreateUser = @Config::getInstance()->LoginLdap['autoCreateUser'];
        }
        return $view->render();
    }

    /**
     * Download log file
     *
     * @param none
     * @return void
     */
    public function getLog()
    {
        Piwik::checkUserHasSuperUserAccess();
        $file = LdapAuth::getLogPath();

        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . basename($file));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            ob_clean();
            flush();
            readfile($file);
            exit;
        } else {
            throw new Exception(Piwik::translate('LoginLdap_LogEmpty'));
        }
    }

    /**
     * LoadUser action
     *
     * @param none
     * @return void
     */
    public function loadUser()
    {
        Piwik::checkUserHasSuperUserAccess();
        $username = Common::getRequestVar('username', '');
        if (!empty($username)) {
            try {
                $success = $this->addUserLdap($username);
                if ($success) {
                    $notification = new Notification(Piwik::translate('LoginLdap_LdapUserAdded'));
                    $notification->context = Notification::CONTEXT_SUCCESS;
                    Notification\Manager::notify('LoginLdap_LdapUserAdded', $notification);

                    $this->redirectToIndex('UsersManager', 'index', null, null, null, array('added' => 1));
                } else {
                    throw new Exception(Piwik::translate('LoginLdap_UserNotFound', $username));
                }
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        } else {
            throw new Exception(Piwik::translate('LoginLdap_NoUserName', $username));
        }
    }

    /**
     *
     */
    public function saveSettings()
    {
        Piwik::checkUserHasSuperUserAccess();

        Config::getInstance()->LoginLdap = array(
            'serverUrl'      => Common::getRequestVar('serverUrl', ''),
            'ldapPort'       => Common::getRequestVar('ldapPort', ''),
            'baseDn'         => Common::getRequestVar('baseDn', ''),
            'userIdField'    => Common::getRequestVar('userIdField', ''),
            'mailField'      => Common::getRequestVar('mailField', ''),
            'aliasField'     => Common::getRequestVar('aliasField', ''),
            'usernameSuffix' => Common::getRequestVar('usernameSuffix', ''),
            'adminUser'      => Common::getRequestVar('adminUser', ''),
            'adminPass'      => Common::getRequestVar('adminPass', ''),
            'memberOf'       => Common::getRequestVar('memberOf', ''),
            'filter'         => Common::getRequestVar('filter', ''),
            'useKerberos'    => Common::getRequestVar('useKerberos', ''),
            'debugEnabled'    => Common::getRequestVar('debugEnabled', ''),
            'autoCreateUser'    => Common::getRequestVar('autoCreateUser', '')
        );
        Config::getInstance()->forceSave();

        $notification = new Notification(Piwik::translate('General_YourChangesHaveBeenSaved'));
        $notification->context = Notification::CONTEXT_SUCCESS;
        Notification\Manager::notify('LoginLdap_ChangesHaveBeenSaved', $notification);

        $this->redirectToIndex('LoginLdap', 'admin', null, null, null, array('updated' => 1));
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

    public function autoCreateUser ($username) {
        if(Config::getInstance()->LoginLdap['autoCreateUser'] == true) {
            return $this->addUserLdap($username);
        } 
        return false;
    }
}
