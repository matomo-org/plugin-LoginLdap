<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package Piwik_LoginLdap
 */
namespace Piwik\Plugins\LoginLdap;

use Exception;
use Piwik\AuthResult;
use Piwik\Common;
use Piwik\Cookie;
use Piwik\Config;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\API as UsersManagerApi;
use Piwik\ProxyHttp;
use Piwik\Session;
use Piwik\SettingsPiwik;
use Piwik\Log;

use Piwik\Plugins\UsersManager\Model as UserModel;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;

/**
 *
 * @package Login
 */
class LdapAuth extends \Piwik\Plugins\Login\Auth
{
    protected $password = null;

    /**
     * @var bool
     */
    private $initializingSession = false;

    /**
     * LdapUsers DAO instance.
     *
     * @param Model\LdapUsers
     */
    private $ldapUsers;

    /**
     * Piwik Users model. Used to query for data in the Piwik users table.
     *
     * @param Piwik\Plugins\UsersManager\Model
     */
    private $usersModel;

    /**
     * Authentication module's name, e.g., "Login"
     *
     * @return string
     */
    public function getName()
    {
        return 'LoginLdap';
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->ldapUsers = LdapUsers::makeConfigured();
        $this->usersModel = new UserModel();
    }

    /**
     * Authenticates user
     *
     * @return AuthResult
     */
    public function authenticate()
    {
        $kerberosEnabled = @Config::getInstance()->LoginLdap['useKerberos'] == 1;
        if ($kerberosEnabled) {
            $httpAuthUser = $this->getAlreadyAuthenticatedLogin();

            if (!empty($httpAuthUser)) {
                $this->login = preg_replace('/@.*/', '', $httpAuthUser);
                $this->password = '';

                Log::info("Performing authentication with HTTP auth user '%s'.", $this->login);
            }
        }

        if (!$this->initializingSession
            && !empty($this->token_auth)
        ) {
            return $this->authenticateByTokenAuth();
        } else {
            return $this->authenticateByLoginAndPassword($kerberosEnabled);
        }
    }

    /**
     * Accessor to set password
     *
     * @param string $password password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    private function getConfigValue($optionName, $default = false)
    {
        $config = Config::getInstance()->LoginLdap;
        return isset($config[$optionName]) ? $config[$optionName] : $default;
    }

    /**
     * This method is used for LDAP authentication.
     */
    private function authenticateLDAP($userLogin, $password, $useWebServerAuth)
    {
        $ldapUser = $this->ldapUsers->authenticate($userLogin, $password, $useWebServerAuth);
        if (!empty($ldapUser)) {
            $user = $this->usersModel->getUser($userLogin); // TODO: is setting the token auth necessary?

            if (empty($user['token_auth'])) {
                Log::debug("Token auth for user '%s' not found in Piwik DB.", $user);

                $user = $this->ldapUsers->createPiwikUserEntryForLdapUser($ldapUser);

                Log::debug("Autocreating Piwik user (%s) for LDAP user: %s", $user, $ldapUser);

                $isSuperUser = Piwik::hasUserSuperUserAccess(); // TODO: should move this code to a utility method that uses a closure
                Piwik::setUserHasSuperUserAccess();
                UsersManagerApi::getInstance()->addUser($user['login'], $user['password'], $user['email'], $user['alias']);
                Piwik::setUserHasSuperUserAccess($isSuperUser);

                $user = $this->usersModel->getUser($userLogin);
            }

            $this->token_auth = $user['token_auth'];

            // TODO: if token auth in DB is wrong, should we update it when syncing?

            return true;
        } else {
            return false;
        }
    }

    /**
    * Authenticates the user and initializes the session.
    */
    public function initSession($login, $password, $rememberMe)
    {
        $this->setPassword($password);

        $this->initializingSession = true;
        parent::initSession($login, md5($password), $rememberMe);
        $this->initializingSession = false;

        // remove password reset entry if it exists
        LoginLdap::removePasswordResetInfo($login);
    }

    private function getAlreadyAuthenticatedLogin()
    {
        if (!isset($_SERVER['REMOTE_USER'])) {
            Log::debug("useKerberos set to 1, but REMOTE_USER not found.");
            return null;
        }

        $remoteUser = $_SERVER['REMOTE_USER'];
        if (strlen($remoteUser) <= 1) {
            Log::debug("REMOTE_USER string length too short (== %s).", strlen($remoteUser));
        }

        return $remoteUser;
    }

    private function authenticateByTokenAuth()
    {
        return parent::authenticate();
    }

    private function authenticateByLoginAndPassword($usingWebServerAuth)
    {
        if (empty($this->login)) { // sanity check
            Log::warning("authenticateByLoginAndPassword: empty login encountered.");

            return $this->makeAuthFailure();
        }

        if ($this->login == 'anonymous') { // sanity check
            Log::warning("authenticateByLoginAndPassword: login is 'anonymous', this is not expected.");

            return $this->makeAuthFailure();
        }

        try {
            if ($this->authenticateLDAP($this->login, $this->password, $usingWebServerAuth)) {
                $user = $this->getUserByTokenAuth();

                return $this->makeSuccessLogin($user);
            } else {
                if (empty($this->token_auth)) {
                    $this->token_auth = UsersManagerApi::getInstance()->getTokenAuth($this->login, md5($this->password));
                }

                // if LDAP auth failed, try normal auth. if we have a login for a superuser, let it through.
                // this way, LoginLdap can be managed even if no users exist in LDAP.
                $result = parent::authenticate();

                if ($result->getCode() == AuthResult::SUCCESS_SUPERUSER_AUTH_CODE) {
                    return $result;
                } else {
                    return self::makeAuthFailure();
                }
            }
        } catch (Exception $ex) {
            Log::debug($ex);

            throw $ex;
        }
    }

    private function getUserByTokenAuth()
    {
        $model = new UserModel();
        return $model->getUserByTokenAuth($this->token_auth);
    }

    private function makeSuccessLogin($userInfo)
    {
        $successCode = $userInfo['superuser_access'] ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;
        return new AuthResult($successCode, $userInfo['login'], $this->token_auth);
    }

    private function makeAuthFailure()
    {
        return new AuthResult(AuthResult::FAILURE, $this->login, $this->token_auth);
    }
}