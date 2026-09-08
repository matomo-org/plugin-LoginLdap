<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap\Auth;

use Exception;
use Piwik\Auth;
use Piwik\AuthResult;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\LoginLdap\Config;
use Piwik\Plugins\LoginLdap\Ldap\Exceptions\ConnectionException;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
use Piwik\Plugins\LoginLdap\UserIdentity;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\UsersManager\Model as UserModel;
use Piwik\Session;
use Piwik\Session\SessionFingerprint;

/**
 * Auth implementation that assumes the web server that hosts Piwik has authenticated
 * users.
 *
 * Supports every type of authentication since authentication is delegated to the web server.
 *
 * ## Implementation Details
 *
 * Checks for the $_SERVER['REMOTE_USER'] variable, if present assumes the user was authenticated
 * by the web server.
 *
 * This auth implementation will still connect to LDAP in order to synchronize user details.
 *
 * If the `[LoginLdap] synchronize_users_after_login` option is set to 0, synchronization
 * will not occur after login.
 */
class WebServerAuth extends Base
{
    /**
     * Whether a user's LDAP information should be synchronized with Piwik's DB after each
     * successful login or not.
     *
     * @var bool
     */
    private $synchronizeUsersAfterSuccessfulLogin = true;

    /**
     * Fallback LDAP Auth implementation to use if REMOTE_USER is not found.
     *
     * @var Auth
     */
    private $fallbackAuth;

    /**
     * Attempts to authenticate with the information set on this instance.
     *
     * @return AuthResult
     */
    public function authenticate()
    {
        try {
            $webServerAuthUser = self::getAlreadyAuthenticatedLogin();

            if (empty($webServerAuthUser)) {
                $this->logger->debug("using web server authentication, but REMOTE_USER server variable not found.");

                return $this->tryFallbackAuth($onlySuperUsers = false, $this->fallbackAuth);
            } else {
                $this->login = self::getAssertedLogin();
                $this->password = '';

                $this->logger->info("User '{login}' authenticated by webserver.", array('login' => $this->login));

                // resolve the login before synchronizing, so that a login resolving to a different existing
                // user is rejected before synchronization writes to that user's row
                $this->getUserForLogin();

                if ($this->synchronizeUsersAfterSuccessfulLogin) {
                    $this->synchronizeLoggedInUser();
                } else {
                    $this->logger->debug("WebServerAuth::{func}: not synchronizing user '{login}'.", array(
                        'func' => __FUNCTION__,
                        'login' => $this->login
                    ));
                }

                $result = $this->makeSuccessLogin($this->getUserForLogin());
                $this->initializeSessionFingerprint($result);

                return $result;
            }
        } catch (ConnectionException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->logger->debug("WebServerAuth::{func} failed: {message}", array(
                'func' => __FUNCTION__,
                'message' => $ex->getMessage(),
                'exception' => $ex
            ));
        }

        return $this->makeAuthFailure();
    }

    /**
     * Gets the {@link $synchronizeUsersAfterSuccessfulLogin} property.
     *
     * @return boolean
     */
    public function isSynchronizeUsersAfterSuccessfulLogin()
    {
        return $this->synchronizeUsersAfterSuccessfulLogin;
    }

    /**
     * Sets the {@link $synchronizeUsersAfterSuccessfulLogin} property.
     *
     * @param boolean $synchronizeUsersAfterSuccessfulLogin
     */
    public function setSynchronizeUsersAfterSuccessfulLogin($synchronizeUsersAfterSuccessfulLogin)
    {
        $this->synchronizeUsersAfterSuccessfulLogin = $synchronizeUsersAfterSuccessfulLogin;
    }

    /**
     * Gets the {@link $fallbackAuth} property.
     *
     * @return Auth
     */
    public function getFallbackAuth()
    {
        return $this->fallbackAuth;
    }

    /**
     * Sets the {@link $fallbackAuth} property.
     *
     * @param Auth $fallbackAuth
     */
    public function setFallbackAuth($fallbackAuth)
    {
        $this->fallbackAuth = $fallbackAuth;
    }

    private static function getAlreadyAuthenticatedLogin()
    {
        return @$_SERVER['REMOTE_USER'];
    }

    /**
     * Returns the login the web server authenticated for this request, normalized the way
     * {@link self::authenticate()} normalizes it, or null when the web server authenticated nobody.
     *
     * @return string|null
     */
    public static function getAssertedLogin(): ?string
    {
        $webServerAuthUser = self::getAlreadyAuthenticatedLogin();

        if (empty($webServerAuthUser)) {
            return null;
        }

        if (Config::getStripDomainFromWebAuth()) {
            return preg_replace('/(.*?\\\\)|(@.*)/', '', $webServerAuthUser);
        }

        return $webServerAuthUser;
    }

    public static function isCurrentRequestWebServerAuthenticated(): bool
    {
        $auth = StaticContainer::get('Piwik\Auth');
        return $auth instanceof WebServerAuth && !empty($_SERVER['REMOTE_USER']);
    }

    /**
     * No password, password hash or token auth is verified against the row the asserted login resolves to, so
     * unlike the other auth implementations this one requires the stored login to match it exactly.
     *
     * @param string $assertedLogin
     * @param string $storedLogin
     * @return bool
     */
    protected function isSameLogin(string $assertedLogin, string $storedLogin): bool
    {
        return UserIdentity::isSameLoginExact($assertedLogin, $storedLogin);
    }

    private function synchronizeLoggedInUser()
    {
        $ldapUser = $this->ldapUsers->getUser($this->login);

        if (empty($ldapUser)) {
            $this->logger->warning("Cannot find web server authenticated user {login} in LDAP!", array('login' => $this->login));
            return;
        }

        $this->synchronizeLdapUser($ldapUser);
    }

    private function initializeSessionFingerprint(AuthResult $authResult): void
    {
        if (!Session::isSessionStarted() || !Session::isWritable()) {
            return;
        }

        $sessionFingerprint = new SessionFingerprint();
        $isSameUser = $sessionFingerprint->getUser() === $authResult->getIdentity();
        $hasVerifiedTwoFactor = $isSameUser && $sessionFingerprint->hasVerifiedTwoFactor();

        Session::regenerateId();

        $sessionFingerprint->initialize(
            $authResult->getIdentity(),
            $authResult->getTokenAuth()
        );

        if ($hasVerifiedTwoFactor) {
            $sessionFingerprint->setTwoFactorAuthenticationVerified();
        }
    }

    /**
     * Returns a WebServerAuth instance configured with INI config.
     *
     * @return WebServerAuth
     */
    public static function makeConfigured()
    {
        $result = new WebServerAuth();
        $result->setLdapUsers(LdapUsers::makeConfigured());
        $result->setUsersManagerAPI(UsersManagerAPI::getInstance());
        $result->setUsersModel(new UserModel());
        $result->setUserSynchronizer(UserSynchronizer::makeConfigured());

        $synchronizeUsersAfterSuccessfulLogin = Config::getShouldSynchronizeUsersAfterLogin();
        $result->setSynchronizeUsersAfterSuccessfulLogin($synchronizeUsersAfterSuccessfulLogin);

        if (Config::getUseLdapForAuthentication()) {
            $fallbackAuth = LdapAuth::makeConfigured();
        } else {
            $fallbackAuth = SynchronizedAuth::makeConfigured();
        }

        $result->setFallbackAuth($fallbackAuth);

        return $result;
    }
}
