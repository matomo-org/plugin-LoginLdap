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
use Piwik\Log;
use Piwik\Piwik;
use Piwik\Plugins\LoginLdap\Config;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\UsersManager\Model as UserModel;

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
     * Returns the secret used to calculate a user's token auth.
     *
     * @return string
     * @throws Exception if the token auth cannot be calculated at the current time.
     */
    public function getTokenAuthSecret()
    {
        $user = $this->getUserForLogin();

        if (empty($user)) {
            throw new Exception("Cannot find user '{$this->login}', if the user is in LDAP, he/she has not been synchronized with Piwik.");
        }

        return $user['password'];
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
            Log::debug("Auth\\Base::%s: trying fallback auth with auth implementation '%s'", __FUNCTION__, get_class($auth));
        }

        $auth->setLogin($this->login);
        if (!empty($this->password)) {
            Log::debug("Auth\\Base::%s: trying normal auth with user password", __FUNCTION__);

            $auth->setPassword($this->password);
        } else if (!empty($this->passwordHash)) {
            Log::debug("Auth\\Base::%s: trying normal auth with hashed password", __FUNCTION__);

            $auth->setPasswordHash($this->passwordHash);
        } else {
            Log::debug("Auth\\Base::%s: trying normal auth with token auth", __FUNCTION__);

            $auth->setTokenAuth($this->token_auth);
        }
        $result = $auth->authenticate();

        Log::debug("Auth\\Base::%s: normal auth returned result code %s for user '%s'", __FUNCTION__, $result->getCode(), $this->login);

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
        $tokenAuth = $this->usersManagerAPI->getTokenAuth($userInfo['login'], $this->getTokenAuthSecret());

        return new AuthResult($successCode, $userInfo['login'], $tokenAuth);
    }

    protected function makeAuthFailure()
    {
        return new AuthResult(AuthResult::FAILURE, $this->login, $this->token_auth);
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