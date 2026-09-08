<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap\Auth;

use Piwik\AuthResult;
use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\LoginLdap\Config;
use Piwik\Plugins\LoginLdap\UserIdentity;
use Piwik\Session;
use Piwik\Session\SessionAuth;
use Piwik\Session\SessionFingerprint;

/**
 * Decorates the core session authentication so that a Matomo session cannot outlive the web server identity
 * it was created for.
 *
 * FrontController authenticates from the session before it consults `Piwik\Auth`, and skips the configured
 * auth implementation when that succeeds, so {@link WebServerAuth} is not consulted again while a session
 * exists. Ending the session on a mismatch makes FrontController fall through to WebServerAuth, which then
 * logs the newly asserted user in.
 */
class WebServerSessionAuth extends SessionAuth
{
    /**
     * @var SessionAuth
     */
    private $wrapped;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SessionAuth $wrapped
     * @param LoggerInterface|null $logger
     * @param bool $shouldDestroySession For tests, since there is no actual session there.
     */
    public function __construct(SessionAuth $wrapped, ?LoggerInterface $logger = null, $shouldDestroySession = true)
    {
        parent::__construct(null, $shouldDestroySession);

        $this->wrapped = $wrapped;
        $this->logger = $logger ?: StaticContainer::get(LoggerInterface::class);
    }

    public function authenticate()
    {
        $sessionUser = $this->getSessionUserToEndFor();

        if ($sessionUser === null) {
            return $this->wrapped->authenticate();
        }

        $this->logger->info(
            "WebServerSessionAuth::{func}: ending the session of '{sessionUser}', the web server now "
                . "authenticates '{assertedUser}'.",
            array(
                'func' => __FUNCTION__,
                'sessionUser' => $sessionUser,
                'assertedUser' => WebServerAuth::getAssertedLogin(),
            )
        );

        $this->endSession();

        return new AuthResult(AuthResult::FAILURE, null, null);
    }

    /**
     * Returns the session's user when the web server now authenticates somebody else, null otherwise.
     *
     * The wrapped session auth is deliberately not run first, so that on a mismatch nothing of the previous
     * user is left on this instance for FrontController to read back.
     *
     * @return string|null
     */
    private function getSessionUserToEndFor(): ?string
    {
        if (!Config::shouldUseWebServerAuthentication()) {
            return null;
        }

        $assertedLogin = WebServerAuth::getAssertedLogin();

        // an absent REMOTE_USER is not a mismatch: setups that require web server authentication on only some
        // paths would otherwise log people out at random
        if ($assertedLogin === null) {
            return null;
        }

        $sessionUser = (new SessionFingerprint())->getUser();

        if (empty($sessionUser) || UserIdentity::isSameLoginExact($assertedLogin, $sessionUser)) {
            return null;
        }

        return $sessionUser;
    }

    /**
     * Empties the session before regenerating it.
     *
     * `destroyCurrentSession()` only clears the session fingerprint, and regenerating the id copies the
     * remaining session data across, which would hand the previous user's namespaces to the new one.
     */
    private function endSession(): void
    {
        if (Session::isSessionStarted()) {
            $_SESSION = array();
        }

        $this->destroyCurrentSession(new SessionFingerprint());
    }

    public function getName()
    {
        return $this->wrapped->getName();
    }

    public function setTokenAuth(
        #[\SensitiveParameter]
        $token_auth
    ) {
        $this->wrapped->setTokenAuth($token_auth);
    }

    public function getLogin()
    {
        return $this->wrapped->getLogin();
    }

    public function getTokenAuth()
    {
        return $this->wrapped->getTokenAuth();
    }

    public function getTokenAuthSecret()
    {
        return $this->wrapped->getTokenAuthSecret();
    }

    public function setLogin($login)
    {
        $this->wrapped->setLogin($login);
    }

    public function setPassword(
        #[\SensitiveParameter]
        $password
    ) {
        $this->wrapped->setPassword($password);
    }

    public function setPasswordHash(
        #[\SensitiveParameter]
        $passwordHash
    ) {
        $this->wrapped->setPasswordHash($passwordHash);
    }

    public function wasSessionExpired(): bool
    {
        return $this->wrapped->wasSessionExpired();
    }
}
