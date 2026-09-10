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
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\LoginLdap\Auth\WebServerAuth;

/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_WebServerAuthAssertionTest
 */
class WebServerAuthAssertionTest extends TestCase
{
    /**
     * @var array
     */
    private $configBackup;

    /**
     * @var mixed
     */
    private $authBackup;

    public function setUp(): void
    {
        parent::setUp();

        $this->configBackup = Config::getInstance()->LoginLdap;
        $this->authBackup = StaticContainer::get('Piwik\Auth');

        Config::getInstance()->LoginLdap = array(
            'use_webserver_auth' => 1,
            'strip_domain_from_web_auth' => 1,
        );

        StaticContainer::getContainer()->set('Piwik\Auth', new WebServerAuth($this->createMock(LoggerInterface::class)));
    }

    public function tearDown(): void
    {
        unset($_SERVER['REMOTE_USER']);
        StaticContainer::getContainer()->set('Piwik\Auth', $this->authBackup);
        Config::getInstance()->LoginLdap = $this->configBackup;

        parent::tearDown();
    }

    /**
     * @dataProvider getAssertionsNamingNobody
     */
    public function test_getAssertedLogin_ReturnsNull_IfTheAssertionNamesNobody($remoteUser)
    {
        $_SERVER['REMOTE_USER'] = $remoteUser;

        $this->assertNull(WebServerAuth::getAssertedLogin());
    }

    /**
     * Callers skip Matomo's password confirmation on the strength of this, so an assertion that authenticates
     * nobody must not report the request as web server authenticated.
     *
     * @dataProvider getAssertionsNamingNobody
     */
    public function test_isCurrentRequestWebServerAuthenticated_IsFalse_IfTheAssertionNamesNobody($remoteUser)
    {
        $_SERVER['REMOTE_USER'] = $remoteUser;

        $this->assertFalse(WebServerAuth::isCurrentRequestWebServerAuthenticated());
    }

    public function getAssertionsNamingNobody()
    {
        return array(
            'absent' => array(null),
            'empty' => array(''),
            'whitespace only' => array('   '),
            'domain only' => array('SHIELD\\'),
            'at sign only' => array('@shield.org'),
        );
    }

    /**
     * @dataProvider getAssertionsNamingSomebody
     */
    public function test_getAssertedLogin_ReturnsTheLoginUntrimmed($expected, $remoteUser)
    {
        $_SERVER['REMOTE_USER'] = $remoteUser;

        $this->assertSame($expected, WebServerAuth::getAssertedLogin());
        $this->assertTrue(WebServerAuth::isCurrentRequestWebServerAuthenticated());
    }

    public function getAssertionsNamingSomebody()
    {
        return array(
            'plain' => array('ironman', 'ironman'),
            'domain stripped' => array('ironman', 'SHIELD\\ironman'),
            'suffix stripped' => array('ironman', 'ironman@shield.org'),

            // surrounding whitespace is part of the asserted identity, not noise to be cleaned up: the login
            // column's collation returns 'ironman' for 'ironman ', and trimming would authenticate that user
            'trailing space kept' => array('ironman ', 'ironman '),
            'leading space kept' => array(' ironman', ' ironman'),
        );
    }
}
