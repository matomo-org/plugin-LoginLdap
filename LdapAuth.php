<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap;

use Exception;
use Piwik\Auth;
use Piwik\AuthResult;
use Piwik\Db;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Session;
use Piwik\Log;
use Piwik\Plugins\UsersManager\Model as UserModel;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;

/**
 * LDAP based authentication implementation: allows authenticating to Piwik via
 * an LDAP server.
 *
 * Supports authenticating by login and password, and supports authenticating by
 * token auth (with login or without).
 *
 * ## Implementation Details
 *
 * **Authenticating By Password**
 *
 * When authenticating by password LdapAuth will communicate with the LDAP server.
 * On a successful authentication, the details of the LDAP user will be synchronized
 * in Piwik's DB (except the password). This is so Piwik can be personalized for
 * the user without having to communicate w/ the LDAP server on every request.
 *
 * If the user does not exist in the MySQL DB on first authentication, it will be
 * created. If it does exist, it will be updated. This way, changes made in the
 * LDAP server will be reflected in the UI.
 *
 * **Authenticating By Token Auth**
 *
 * Authenticating by token auth is more complicated than by authenticating by password.
 * There is no LDAP concept of a authentication token, and connecting to the LDAP
 * server for every token auth authentication would be very wasteful.
 *
 * So instead, when a user is synchronized, a token auth is generated using part of
 * the password hash stored in LDAP. We don't want to store the whole password hash
 * so attackers cannot get the true hash if they gain access to the MySQL DB.
 *
 * Once the token auth is generated, authenticating with it is done in the same way
 * as with {@link Piwik\Plugins\Login\Auth}. In fact, this class will create an
 * instance of that one to authenticate.
 *
 * **Non-LDAP Users**
 *
 * After LoginLdap is enabled, normal Piwik users are not allowed to authenticate.
 * Only normal super users so the plugin can be managed w/o LDAP users existing.
 *
 * **Default View Access**
 *
 * When a user is created in Piwik, (s)he must be provided with access to at least
 * one website. The website(s) new users are given access to is determined by the
 * `[LoginLdap] new_user_default_sites_view_access` INI config option.
 */
class LdapAuth implements Auth
{
    /**
     * The username to authenticate with.
     *
     * @var null|string
     */
    private $login = null;

    /**
     * The token auth to authenticate with.
     *
     * @var null|string
     */
    private $token_auth = null;

    /**
     * The password to authenticate with (unhashed).
     *
     * @var null|string
     */
    private $password = null;

    /**
     * LdapUsers DAO instance.
     *
     * @var Model\LdapUsers
     */
    private $ldapUsers;

    /**
     * Piwik Users model. Used to query for data in the Piwik users table.
     *
     * @var \Piwik\Plugins\UsersManager\Model
     */
    private $usersModel;

    /**
     * UserSynchronizer instance used to convert LDAP users to Piwik users and then
     * persist them in Piwik's MySQL database. Doing so allows Piwik to authorize and
     * authenticate LDAP users without having to communicate with the LDAP server
     * on each request.
     *
     * @var UserSynchronizer
     */
    private $userSynchronizer;

    /**
     * UsersManager API instance.
     *
     * @var UsersManagerAPI
     */
    private $usersManagerAPI;

    /**
     * Cache of user info for the current user being authenticated. This is the result of
     * UserModel::getUser().
     *
     * @var string[]
     */
    private $userForLogin = null;

    /**
     * Whether to use web server authentication or not. If true, no LDAP binding is done, instead
     * the authenticated user is taken from the REMOTE_USER server variable.
     *
     * @var bool
     */
    private $useWebServerAuthentication;

    /**
     * Authentication module's name, e.g., "LoginLdap"
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
        $this->userSynchronizer = UserSynchronizer::makeConfigured();
        $this->usersModel = new UserModel();
        $this->usersManagerAPI = UsersManagerAPI::getInstance();

        $this->useWebServerAuthentication = Config::shouldUseWebServerAuthentication();
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
     * Unsupported (unless web server authentication is being used).
     *
     * @param string $passwordHash The hashed password.
     * @throws Exception if authentication by hashed password is not supported.
     */
    public function setPasswordHash($passwordHash)
    {
        // if using web server auth, do nothing since the password isn't used anyway
        if ($this->useWebServerAuthentication) {
            return;
        }

        throw new Exception("LdapLogin: authentication by password hash not supported.");
    }

    /**
     * Attempts to authenticate a user.
     *
     * @return AuthResult
     */
    public function authenticate()
    {
        if ($this->useWebServerAuthentication) {
            $webServerAuthUser = $this->getAlreadyAuthenticatedLogin();

            if (empty($webServerAuthUser)) {
                return $this->makeAuthFailure();
            } else {
                $this->login = preg_replace('/@.*/', '', $webServerAuthUser);
                $this->password = '';

                Log::info("User '%s' authenticated by webserver.", $this->login);

                return $this->authenticateByPassword();
            }
        } else if (!empty($this->password)) {
            return $this->authenticateByPassword();
        } else {
            return $this->authenticateByTokenAuth();
        }
    }

    private function authenticateByPassword()
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
            if ($this->authenticateByLdap()) {
                $user = $this->getUserForLogin();
                return $this->makeSuccessLogin($user);
            } else {
                // if LDAP auth failed, try normal auth. if we have a login for a superuser, let it through.
                // this way, LoginLdap can be managed even if no users exist in LDAP.
                $auth = new \Piwik\Plugins\Login\Auth();
                $auth->setLogin($this->login);
                $auth->setPassword($this->password);
                $result = $auth->authenticate();

                if ($result->getCode() == AuthResult::SUCCESS_SUPERUSER_AUTH_CODE) {
                    return $result;
                }
            }
        } catch (Exception $ex) {
            Log::debug($ex);

            throw $ex;
        }

        return self::makeAuthFailure();
    }

    private function authenticateByTokenAuth()
    {
        $auth = new \Piwik\Plugins\Login\Auth();
        $auth->setLogin($this->login);
        $auth->setTokenAuth($this->token_auth);
        $result = $auth->authenticate();

        // allow all super users to authenticate, even if they are not LDAP users, but stop
        // normal non-LDAP users to authenticate.
        if ($result->getCode() == AuthResult::SUCCESS_SUPERUSER_AUTH_CODE
            || ($result->getCode() == AuthResult::SUCCESS
                && $this->isUserAllowedToAuthenticateByTokenAuth())
        ) {
            return $result;
        } else {
            return $this->makeAuthFailure();
        }
    }

    private function authenticateByLdap()
    {
        $ldapUser = $this->ldapUsers->authenticate($this->login, $this->password, $this->useWebServerAuthentication);
        if (!empty($ldapUser)) {
            $this->userForLogin = $this->userSynchronizer->synchronizeLdapUser($ldapUser);
            $this->userSynchronizer->synchronizePiwikAccessFromLdap($this->login, $ldapUser);

            return true;
        } else {
            return false;
        }
    }

    private function getAlreadyAuthenticatedLogin()
    {
        if (!isset($_SERVER['REMOTE_USER'])) {
            Log::debug("using web server authentication, but REMOTE_USER server variable not found.");
            return null;
        }

        return $_SERVER['REMOTE_USER'];
    }

    private function isUserAllowedToAuthenticateByTokenAuth()
    {
        if ($this->token_auth == 'anonymous') {
            return true;
        }

        $user = $this->getUserForLogin();
        return UserMapper::isUserLdapUser($user);
    }

    private function getUserForLogin()
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

    private function makeSuccessLogin($userInfo)
    {
        $successCode = $userInfo['superuser_access'] ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;
        $tokenAuth = $this->usersManagerAPI->getTokenAuth($userInfo['login'], $this->getTokenAuthSecret());

        return new AuthResult($successCode, $userInfo['login'], $tokenAuth);
    }

    private function makeAuthFailure()
    {
        return new AuthResult(AuthResult::FAILURE, $this->login, $this->token_auth);
    }
}