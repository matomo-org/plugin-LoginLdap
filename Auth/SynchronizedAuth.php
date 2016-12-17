<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\Auth;

use Exception;
use Piwik\Auth\Password;
use Piwik\AuthResult;
use Piwik\Plugins\LoginLdap\Config;
use Piwik\Plugins\LoginLdap\Ldap\Exceptions\ConnectionException;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\UsersManager\Model as UserModel;
use Piwik\Plugins\UsersManager\UsersManager;

/**
 * Auth implementation that only uses LDAP to synchronize user details.
 *
 * Supports authenticating by password, authenticating by token auth and authenticating
 * by password hash.
 *
 * ## Implementation Details
 *
 * SynchronizedAuth uses the normal Piwik authentication class (Piwik\Plugins\Login\Auth).
 * If login via this class fails, then SynchronizedAuth tries to login via LDAP, and on
 * success, synchronizes user details, including the password in LDAP.
 *
 * This means that if this auth implementation is used, the password will be stored in
 * Piwik's DB in addition to LDAP.
 *
 * Synchronizing after login can be disabled via the `[LoginLdap] synchronize_users_after_login` option.
 *
 * Note: A user's password will always be updated after a successful LDAP login, since
 * if we can't authenticate normally for the user, the password has changed in LDAP.
 *
 * Users that do not exist in LDAP, but exist in Piwik's DB will be able to authenticate.
 */
class SynchronizedAuth extends Base
{
    /**
     * Whether a user's LDAP information should be synchronized with Piwik's DB after each
     * successful login or not.
     *
     * @var bool
     */
    private $synchronizeUsersAfterSuccessfulLogin = true;

    /**
     * Attempts to authenticate with the information set on this instance.
     *
     * @return AuthResult
     */
    public function authenticate()
    {
        try {
            $result = $this->tryFallbackAuth($onlySuperUsers = false);
            if ($result->wasAuthenticationSuccessful()) {
                return $result;
            }

            if (!$this->synchronizeUsersAfterSuccessfulLogin) {
                $this->logger->debug("SynchronizedAuth::{func}: synchronizing users after login disabled, not attempting LDAP authenticate for '{login}'.",
                    array('func' => __FUNCTION__, 'login' => $this->login));

                return $this->makeAuthFailure();
            }

            if (empty($this->password)) {
                $this->logger->debug("SynchronizedAuth::{func}: cannot attempt fallback LDAP login for '{login}', password not set.",
                    array('func' => __FUNCTION__, 'login' => $this->login));

                return $this->makeAuthFailure();
            }

            $successful = $this->authenticateByLdap();
            if ($successful) {
                $this->updateUserPassword();

                return $this->makeSuccessLogin($this->getUserForLogin());
            } else {
                return $this->makeAuthFailure();
            }
        } catch (ConnectionException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->logger->debug("SynchronizedAuth::{func} failed: {message}", array(
                'func' => __FUNCTION__,
                'message' => $ex->getMessage(),
                'exception' => $ex
            ));
        }

        return $this->makeAuthFailure();
    }

    /**
     * Gets {@link $synchronizeUsersAfterSuccessfulLogin} property.
     *
     * @return boolean
     */
    public function isSynchronizeUsersAfterSuccessfulLogin()
    {
        return $this->synchronizeUsersAfterSuccessfulLogin;
    }

    /**
     * Sets {@link $synchronizeUsersAfterSuccessfulLogin} property.
     *
     * @param boolean $synchronizeUsersAfterSuccessfulLogin
     */
    public function setSynchronizeUsersAfterSuccessfulLogin($synchronizeUsersAfterSuccessfulLogin)
    {
        $this->synchronizeUsersAfterSuccessfulLogin = $synchronizeUsersAfterSuccessfulLogin;
    }

    private function updateUserPassword()
    {
        $user = $this->getUserForLogin();

        $passwordHelper = new Password();
        $passwordHash = $passwordHelper->hash(UsersManager::getPasswordHash($this->password));
        $this->usersModel->updateUser($this->login, $passwordHash, $user['email'], $user['alias'], $user['token_auth']);

        $this->userForLogin['password'] = $passwordHash;
    }

    /**
     * Returns a WebServerAuth instance configured with INI config.

     * @return SynchronizedAuth
     */
    public static function makeConfigured()
    {
        $result = new SynchronizedAuth();
        $result->setLdapUsers(LdapUsers::makeConfigured());
        $result->setUsersManagerAPI(UsersManagerAPI::getInstance());
        $result->setUsersModel(new UserModel());
        $result->setUserSynchronizer(UserSynchronizer::makeConfigured());

        $synchronizeUsersAfterSuccessfulLogin = Config::getShouldSynchronizeUsersAfterLogin();
        $result->setSynchronizeUsersAfterSuccessfulLogin($synchronizeUsersAfterSuccessfulLogin);

        return $result;
    }
}