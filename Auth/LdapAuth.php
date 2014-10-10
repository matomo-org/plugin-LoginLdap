<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\Auth;

use Exception;
use Piwik\AuthResult;
use Piwik\Log;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\UsersManager\Model as UserModel;

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
class LdapAuth extends Base
{
    /**
     * Sets the hash of the password to authenticate with. The hash will be an MD5 hash.
     *
     * @param string $passwordHash The hashed password.
     * @throws Exception if authentication by hashed password is not supported.
     */
    public function setPasswordHash($passwordHash)
    {
        throw new Exception("Authentication by password hash is not supported when authenticating by LDAP.");
    }

    /**
     * Attempts to authenticate with the information set on this instance.
     *
     * @return AuthResult
     */
    public function authenticate()
    {
        if (!empty($this->password)) {
            return $this->authenticateByPassword();
        } else {
            return $this->authenticateByTokenAuth();
        }
    }

    private function authenticateByPassword()
    {
        try {
            if (empty($this->login)) { // sanity check
                Log::warning("authenticateByPassword: empty login encountered.");

                return $this->makeAuthFailure();
            }

            if ($this->login == 'anonymous') { // sanity check
                Log::warning("authenticateByPassword: login is 'anonymous', this is not expected.");

                return $this->makeAuthFailure();
            }

            if ($this->authenticateByLdap()) {
                $user = $this->getUserForLogin();
                return $this->makeSuccessLogin($user);
            } else {
                // if LDAP auth failed, try normal auth. if we have a login for a superuser, let it through.
                // this way, LoginLdap can be managed even if no users exist in LDAP.
                return $this->tryNormalAuth($onlySuperUsers = true);
            }
        } catch (Exception $ex) {
            Log::debug($ex);
        }

        return $this->makeAuthFailure();
    }

    private function isUserAllowedToAuthenticateByTokenAuth()
    {
        if ($this->token_auth == 'anonymous') {
            return true;
        }

        $user = $this->getUserForLogin();
        return UserMapper::isUserLdapUser($user);
    }

    private function authenticateByTokenAuth()
    {
        $auth = new \Piwik\Plugins\Login\Auth();
        $auth->setLogin($this->login);
        $auth->setTokenAuth($this->token_auth);
        $result = $auth->authenticate();

        // allow all super users to authenticate, even if they are not LDAP users, but stop
        // normal non-LDAP users from authenticating.
        if ($result->getCode() == AuthResult::SUCCESS_SUPERUSER_AUTH_CODE
            || ($result->getCode() == AuthResult::SUCCESS
                && $this->isUserAllowedToAuthenticateByTokenAuth())
        ) {
            return $result;
        } else {
            return $this->makeAuthFailure();
        }
    }

    /**
     * Returns a WebServerAuth instance configured with INI config.
     *
     * @return LdapAuth
     */
    public static function makeConfigured()
    {
        $result = new LdapAuth();
        $result->setLdapUsers(LdapUsers::makeConfigured());
        $result->setUsersManagerAPI(UsersManagerAPI::getInstance());
        $result->setUsersModel(new UserModel());
        $result->setUserSynchronizer(UserSynchronizer::makeConfigured());
        return $result;
    }
}