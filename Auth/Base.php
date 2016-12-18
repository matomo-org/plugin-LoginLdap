<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\Auth;

use Exception;
use Piwik\Auth;
use Piwik\AuthResult;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\LoginLdap\Config;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\UsersManager\Model as UserModel;
use Psr\Log\LoggerInterface;

/**
 * Base class for LoginLdap authentication implementations.
 */
abstract class Base implements Auth
{
    /**
     * The username to authenticate with.
     *
     * @var null|string
     */
    protected $login = null;

    /**
     * The token auth to authenticate with.
     *
     * @var null|string
     */
    protected $token_auth = null;

    /**
     * The password to authenticate with (unhashed).
     *
     * @var null|string
     */
    protected $password = null;

    /**
     * The password hash to authenticate with.
     *
     * @var string
     */
    private $passwordHash = null;

    /**
     * LdapUsers DAO instance.
     *
     * @var LdapUsers
     */
    protected $ldapUsers;

    /**
     * Piwik Users model. Used to query for data in the Piwik users table.
     *
     * @var UserModel
     */
    protected $usersModel;

    /**
     * UserSynchronizer instance used to convert LDAP users to Piwik users and then
     * persist them in Piwik's MySQL database. Doing so allows Piwik to authorize and
     * authenticate LDAP users without having to communicate with the LDAP server
     * on each request.
     *
     * @var UserSynchronizer
     */
    protected $userSynchronizer;

    /**
     * UsersManager API instance.
     *
     * @var UsersManagerAPI
     */
    protected $usersManagerAPI;

    /**
     * Cache of user info for the current user being authenticated. This is the result of
     * UserModel::getUser().
     *
     * @var string[]
     */
    protected  $userForLogin = null;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: StaticContainer::get('Psr\Log\LoggerInterface');
    }

    /**
     * Authentication module's name, e.g., "LoginLdap"
     *+
     * @return string
     */
    public function getName()
    {
        return 'LoginLdap';
    }

    /**
     * Sets the password to authenticate with.
     *
     * @param string $password password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * Sets the authentication token to authenticate with.
     *
     * @param string $token_auth authentication token
     */
    public function setTokenAuth($token_auth)
    {
        $this->token_auth = $token_auth;
    }

    /**
     * Sets the hash of the password to authenticate with. The hash will be an MD5 hash.
     *
     * @param string $passwordHash The hashed password.
     * @throws Exception if authentication by hashed password is not supported.
     */
    public function setPasswordHash($passwordHash)
    {
        $this->passwordHash = $passwordHash;
    }

    /**
     * Returns the login of the user being authenticated.
     *
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * Returns the user's token auth.
     *
     * @return string
     */
    public function getTokenAuth()
    {
        if (!empty($this->token_auth)) {
            return $this->token_auth;
        }

        if (!empty($this->login) && $tokenAuthSecret = $this->getTokenAuthSecret()) {
            return $this->usersManagerAPI->getTokenAuth($this->login, $tokenAuthSecret);
        }

        return null;
    }

    /**
     * Returns the secret used to calculate a user's token auth.
     *
     * @return string|null
     */
    public function getTokenAuthSecret()
    {
        if (!empty($this->passwordHash)) {
            return $this->passwordHash;
        }

        if (!empty($this->password)) {
            return md5($this->password);
        }

        return null;
    }

    /**
     * Sets the login name to authenticate with.
     *
     * @param string $login The username.
     */
    public function setLogin($login)
    {
        $this->login = $login;
    }

    /**
     * Gets the {@link $ldapUsers} property.
     *
     * @return LdapUsers
     */
    public function getLdapUsers()
    {
        return $this->ldapUsers;
    }

    /**
     * Sets the {@link $ldapUsers} property.
     *
     * @param LdapUsers $ldapUsers
     */
    public function setLdapUsers($ldapUsers)
    {
        $this->ldapUsers = $ldapUsers;
    }

    /**
     * Gets the {@link $usersModel} property.
     *
     * @return \Piwik\Plugins\UsersManager\Model
     */
    public function getUsersModel()
    {
        return $this->usersModel;
    }

    /**
     * Sets the {@link $usersModel} property.
     *
     * @param \Piwik\Plugins\UsersManager\Model $usersModel
     */
    public function setUsersModel($usersModel)
    {
        $this->usersModel = $usersModel;
    }

    /**
     * Gets the {@link $userSynchronizer} property.
     *
     * @return UserSynchronizer
     */
    public function getUserSynchronizer()
    {
        return $this->userSynchronizer;
    }

    /**
     * Sets the {@link $userSynchronizer} property.
     *
     * @param UserSynchronizer $userSynchronizer
     */
    public function setUserSynchronizer($userSynchronizer)
    {
        $this->userSynchronizer = $userSynchronizer;
    }

    /**
     * Gets the {@link $usersManagerAPI} property.
     *
     * @return UsersManagerAPI
     */
    public function getUsersManagerAPI()
    {
        return $this->usersManagerAPI;
    }

    /**
     * Sets the {@link $usersManagerAPI} property.
     *
     * @param UsersManagerAPI $usersManagerAPI
     */
    public function setUsersManagerAPI($usersManagerAPI)
    {
        $this->usersManagerAPI = $usersManagerAPI;
    }

    protected function getUserForLogin()
    {
        if (empty($this->userForLogin)) {
            if (!empty($this->login)) {
                $this->userForLogin = $this->usersModel->getUser($this->login);
            } else if (!empty($this->token_auth)) {
                $this->userForLogin = $this->usersModel->getUserByTokenAuth($this->token_auth);
            } else {
                throw new Exception("Cannot get user details, neither login nor token auth are set.");
            }
        }
        return $this->userForLogin;
    }

    protected function tryFallbackAuth($onlySuperUsers = true, Auth $auth = null)
    {
        if (empty($auth)) {
            $auth = new \Piwik\Plugins\Login\Auth();
        } else {
            $this->logger->debug("Auth\\Base::{func}: trying fallback auth with auth implementation '{impl}'", array(
                'func' => __FUNCTION__,
                'impl' => get_class($auth)
            ));
        }

        $auth->setLogin($this->login);
        if (!empty($this->password)) {
            $this->logger->debug("Auth\\Base::{func}: trying normal auth with user password", array('func' => __FUNCTION__));

            $auth->setPassword($this->password);
        } else if (!empty($this->passwordHash)) {
            $this->logger->debug("Auth\\Base::{func}: trying normal auth with hashed password", array('func' => __FUNCTION__));

            $auth->setPasswordHash($this->passwordHash);
        } else {
            $this->logger->debug("Auth\\Base::{func}: trying normal auth with token auth", array('func' => __FUNCTION__));

            $auth->setTokenAuth($this->getTokenAuth());
        }
        $result = $auth->authenticate();

        $this->logger->debug("Auth\\Base::{func}: normal auth returned result code {code} for user '{login}'", array(
            'func' => __FUNCTION__,
            'code' => $result->getCode(),
            'login' => $this->login
        ));

        if (!$onlySuperUsers
            || $result->getCode() == AuthResult::SUCCESS_SUPERUSER_AUTH_CODE
        ) {
            return $result;
        } else {
            return $this->makeAuthFailure();
        }
    }

    protected function synchronizeLdapUser($ldapUser)
    {
        $this->userForLogin = $this->userSynchronizer->synchronizeLdapUser($this->login, $ldapUser);
        $this->userSynchronizer->synchronizePiwikAccessFromLdap($this->login, $ldapUser);
    }

    protected function makeSuccessLogin($userInfo)
    {
        $successCode = $userInfo['superuser_access'] ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;

        if ($userInfo['token_auth']) {
            $tokenAuth = $userInfo['token_auth'];
        } else {
            $tokenAuth = $this->getTokenAuth();

            if (empty($userInfo['login']) || empty($tokenAuth)) {
                throw new Exception('User couldn\'t be found');
            }
        }

        return new AuthResult($successCode, $userInfo['login'], $tokenAuth);
    }

    protected function makeAuthFailure()
    {
        return new AuthResult(AuthResult::FAILURE, $this->login, $this->getTokenAuth());
    }

    protected function authenticateByLdap()
    {
        $this->checkLdapFunctionsAvailable();

        $ldapUser = $this->ldapUsers->authenticate($this->login, $this->password);
        if (!empty($ldapUser)) {
            $this->synchronizeLdapUser($ldapUser);

            return true;
        } else {
            return false;
        }
    }

    private function checkLdapFunctionsAvailable()
    {
        if (!function_exists('ldap_connect')) {
            throw new Exception(Piwik::translate('LoginLdap_LdapFunctionsMissing'));
        }
    }

    /**
     * Returns the authentication implementation to use in LoginLdap based on certain
     * INI configuration values.
     *
     * @return Base
     */
    public static function factory()
    {
        if (Config::shouldUseWebServerAuthentication()) {
            return WebServerAuth::makeConfigured();
        } else if (Config::getUseLdapForAuthentication()) {
            return LdapAuth::makeConfigured();
        } else {
            return SynchronizedAuth::makeConfigured();
        }
    }
}