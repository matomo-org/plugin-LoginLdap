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
use Piwik\Plugins\LoginLdap\Ldap\ServerInfo;

/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_ServerInfoTest
 */
class ServerInfoTest extends TestCase
{
    public const TEST_HOST_NAME = 'some-host.com';
    public const TEST_ADMIN_USER = 'who?';
    public const TEST_ADMIN_PASS = 'pass123!';
    public const TEST_BASE_DN = 'testbasedn';

    public function testConstruct()
    {
        $serverInfo = new ServerInfo(
            self::TEST_HOST_NAME,
            self::TEST_BASE_DN,
            ServerInfo::DEFAULT_LDAP_PORT,
            self::TEST_ADMIN_USER,
            self::TEST_ADMIN_PASS
        );

        $this->assertSame(self::TEST_HOST_NAME, $serverInfo->getServerHostname());
        $this->assertSame(self::TEST_BASE_DN, $serverInfo->getBaseDn());
        $this->assertSame(ServerInfo::DEFAULT_LDAP_PORT, $serverInfo->getServerPort());
        $this->assertSame(self::TEST_ADMIN_USER, $serverInfo->getAdminUsername());
        $this->assertSame(self::TEST_ADMIN_PASS, $serverInfo->getAdminPassword());
    }

    public function testConstructWithQuoteInPassword()
    {
        $serverInfo = new ServerInfo(
            self::TEST_HOST_NAME,
            self::TEST_BASE_DN,
            ServerInfo::DEFAULT_LDAP_PORT,
            self::TEST_ADMIN_USER,
            "some\'pass"
        );

        $this->assertSame(self::TEST_HOST_NAME, $serverInfo->getServerHostname());
        $this->assertSame(self::TEST_BASE_DN, $serverInfo->getBaseDn());
        $this->assertSame(ServerInfo::DEFAULT_LDAP_PORT, $serverInfo->getServerPort());
        $this->assertSame(self::TEST_ADMIN_USER, $serverInfo->getAdminUsername());
        $this->assertSame("some'pass", $serverInfo->getAdminPassword());
    }

    public function testSetAdminPassword()
    {
        $serverInfo = new ServerInfo(self::TEST_HOST_NAME, self::TEST_BASE_DN);
        $this->assertEmpty($serverInfo->getAdminPassword());
        $serverInfo->setAdminPassword(self::TEST_ADMIN_PASS);
        $this->assertSame(self::TEST_ADMIN_PASS, $serverInfo->getAdminPassword());
    }

    public function testSetAdminPasswordWithQuoteInPassword()
    {
        $serverInfo = new ServerInfo(self::TEST_HOST_NAME, self::TEST_BASE_DN);
        $this->assertEmpty($serverInfo->getAdminPassword());
        $serverInfo->setAdminPassword("some\'pass");
        $this->assertSame("some'pass", $serverInfo->getAdminPassword());
    }
}
