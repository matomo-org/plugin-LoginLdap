<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\LoginLdap\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\AuthResult;
use Piwik\Config;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\LoginLdap\Auth\WebServerSessionAuth;
use Piwik\Session\SessionAuth;
use Piwik\Session\SessionFingerprint;

/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_WebServerSessionAuthTest
 */
class WebServerSessionAuthTest extends TestCase
{
    private const SESSION_USER = 'karen';

    /**
     * @var array
     */
    private $sessionBackup;

    /**
     * @var array
     */
    private $configBackup;

    public function setUp(): void
    {
        parent::setUp();

        $this->sessionBackup = $_SESSION ?? array();
        $this->configBackup = Config::getInstance()->LoginLdap;

        Config::getInstance()->LoginLdap = array(
            'use_webserver_auth' => 1,
            'strip_domain_from_web_auth' => 0,
        );

        $_SESSION = array(SessionFingerprint::USER_NAME_SESSION_VAR_NAME => self::SESSION_USER);
    }

    public function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
        unset($_SERVER['REMOTE_USER']);

        Config::getInstance()->LoginLdap = $this->configBackup;

        parent::tearDown();
    }

    public function test_authenticate_UsesTheSessionAuth_IfTheWebServerAssertsTheSessionUser()
    {
        $_SERVER['REMOTE_USER'] = self::SESSION_USER;

        $result = $this->makeAuth($this->makeWrappedAuth($isCalled = true))->authenticate();

        $this->assertEquals(AuthResult::SUCCESS, $result->getCode());
        $this->assertEquals(self::SESSION_USER, $result->getIdentity());
        $this->assertSessionWasKept();
    }

    public function test_authenticate_UsesTheSessionAuth_IfTheWebServerAssertsNobody()
    {
        unset($_SERVER['REMOTE_USER']);

        $result = $this->makeAuth($this->makeWrappedAuth($isCalled = true))->authenticate();

        $this->assertEquals(AuthResult::SUCCESS, $result->getCode());
        $this->assertSessionWasKept();
    }

    public function test_authenticate_UsesTheSessionAuth_IfThereIsNoSessionYet()
    {
        $_SESSION = array();
        $_SERVER['REMOTE_USER'] = 'someoneelse';

        $result = $this->makeAuth($this->makeWrappedAuth($isCalled = true))->authenticate();

        $this->assertEquals(AuthResult::SUCCESS, $result->getCode());
    }

    public function test_authenticate_UsesTheSessionAuth_IfWebServerAuthIsNotUsed()
    {
        Config::getInstance()->LoginLdap = array('use_webserver_auth' => 0);

        $_SERVER['REMOTE_USER'] = 'someoneelse';

        $result = $this->makeAuth($this->makeWrappedAuth($isCalled = true))->authenticate();

        $this->assertEquals(AuthResult::SUCCESS, $result->getCode());
        $this->assertSessionWasKept();
    }

    /**
     * @dataProvider getLoginsThatAreNotTheSessionUser
     */
    public function test_authenticate_EndsTheSession_IfTheWebServerAssertsSomebodyElse($remoteUser)
    {
        $_SERVER['REMOTE_USER'] = $remoteUser;

        $result = $this->makeAuth($this->makeWrappedAuth($isCalled = false))->authenticate();

        $this->assertEquals(AuthResult::FAILURE, $result->getCode());
        $this->assertNull($result->getIdentity());
        $this->assertSessionWasEnded();
    }

    public function getLoginsThatAreNotTheSessionUser()
    {
        return array(
            'a different user' => array('bob'),

            // the session guard uses the same exact comparison WebServerAuth uses
            'ascii case difference' => array('Karen'),
            'accented character' => array("\xc3\xa1aren"),
            'kelvin sign' => array("\xe2\x84\xaaaren"),
        );
    }

    public function test_authenticate_UsesTheSessionAuth_IfOnlyTheStrippedDomainDiffers()
    {
        Config::getInstance()->LoginLdap = array(
            'use_webserver_auth' => 1,
            'strip_domain_from_web_auth' => 1,
        );

        $_SERVER['REMOTE_USER'] = 'SHIELD\\' . self::SESSION_USER;

        $result = $this->makeAuth($this->makeWrappedAuth($isCalled = true))->authenticate();

        $this->assertEquals(AuthResult::SUCCESS, $result->getCode());
        $this->assertSessionWasKept();
    }

    public function test_authenticate_EndsTheSession_IfTheDomainIsNotStripped()
    {
        $_SERVER['REMOTE_USER'] = 'SHIELD\\' . self::SESSION_USER;

        $result = $this->makeAuth($this->makeWrappedAuth($isCalled = false))->authenticate();

        $this->assertEquals(AuthResult::FAILURE, $result->getCode());
        $this->assertSessionWasEnded();
    }

    private function assertSessionWasKept()
    {
        $this->assertEquals(self::SESSION_USER, (new SessionFingerprint())->getUser());
    }

    private function assertSessionWasEnded()
    {
        $this->assertNull((new SessionFingerprint())->getUser());
        $this->assertEquals(array(), $_SESSION);
    }

    private function makeAuth(SessionAuth $wrapped)
    {
        return new WebServerSessionAuth(
            $wrapped,
            $this->createMock(LoggerInterface::class),
            $shouldDestroySession = false
        );
    }

    private function makeWrappedAuth($isCalled)
    {
        $wrapped = $this->getMockBuilder(SessionAuth::class)
                        ->onlyMethods(array('authenticate'))
                        ->getMock();

        $wrapped->expects($isCalled ? $this->once() : $this->never())
                ->method('authenticate')
                ->willReturn(new AuthResult(AuthResult::SUCCESS, self::SESSION_USER, 'atoken'));

        return $wrapped;
    }
}
