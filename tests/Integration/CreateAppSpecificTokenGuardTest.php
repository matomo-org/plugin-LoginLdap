<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\LoginLdap\Auth\WebServerAuth;
use Piwik\Plugins\LoginLdap\LoginLdap;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * Regression tests for the LoginLdap privilege-escalation guard on
 * UsersManager.createAppSpecificTokenAuth.
 *
 * In web-server-auth mode WebServerAuth is the global Piwik\Auth and authenticate() succeeds off
 * $_SERVER['REMOTE_USER'] while ignoring the supplied password. Because
 * PasswordVerifier::isPasswordCorrect() reuses that global adapter and never checks the
 * authenticated identity against the requested login, createAppSpecificTokenAuth (whose only
 * authorization gate is that password confirmation, with an arbitrary target userLogin) could
 * otherwise mint a full-access token_auth for any account. The plugin blocks that method under
 * web-server auth via its API.Request.dispatch listener; these tests pin that behaviour down.
 *
 * @group LoginLdap
 * @group LoginLdap_Integration
 */
class CreateAppSpecificTokenGuardTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Fixture::loadAllTranslations();
    }

    public function tearDown(): void
    {
        unset($_SERVER['REMOTE_USER']);
        Fixture::resetTranslations();
        parent::tearDown();
    }

    public function test_blocksCreateAppSpecificTokenAuth_whenWebServerAuthenticated()
    {
        $this->installWebServerAuth();
        $_SERVER['REMOTE_USER'] = 'attacker';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(Piwik::translate('LoginLdap_CreateAppSpecificTokenAuthBlocked'));

        $parameters = ['userLogin' => 'somevictim'];
        (new LoginLdap())->onApiRequestDispatch($parameters, 'UsersManager', 'createAppSpecificTokenAuth');
    }

    public function test_doesNotBlock_whenRemoteUserAbsent()
    {
        // No REMOTE_USER means WebServerAuth delegates to its password-validating fallback, so
        // password confirmation is genuine and token creation must stay allowed.
        $this->installWebServerAuth();
        unset($_SERVER['REMOTE_USER']);

        $parameters = ['userLogin' => 'somevictim'];
        (new LoginLdap())->onApiRequestDispatch($parameters, 'UsersManager', 'createAppSpecificTokenAuth');

        $this->assertTrue(true);
    }

    public function test_doesNotBlock_forOtherMethods()
    {
        $this->installWebServerAuth();
        $_SERVER['REMOTE_USER'] = 'attacker';

        $parameters = [];
        (new LoginLdap())->onApiRequestDispatch($parameters, 'UsersManager', 'getUsers');

        $this->assertTrue(true);
    }

    public function test_doesNotBlock_whenNotWebServerAuth()
    {
        // A password-validating adapter (the normal case) must never be blocked, even if a
        // REMOTE_USER variable happens to be present in the environment.
        StaticContainer::getContainer()->set('Piwik\Auth', new \Piwik\Plugins\Login\Auth());
        $_SERVER['REMOTE_USER'] = 'attacker';

        $parameters = ['userLogin' => 'somevictim'];
        (new LoginLdap())->onApiRequestDispatch($parameters, 'UsersManager', 'createAppSpecificTokenAuth');

        $this->assertTrue(true);
    }

    private function installWebServerAuth(): void
    {
        StaticContainer::getContainer()->set('Piwik\Auth', new WebServerAuth());
    }
}
