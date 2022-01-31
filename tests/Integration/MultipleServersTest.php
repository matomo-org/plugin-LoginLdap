<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\Config;
use Piwik\Plugins\LoginLdap\Auth\LdapAuth;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_MultipleServersTest
 */
class MultipleServersTest extends LdapIntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        Config::getInstance()->LoginLdap_dummyserver1 = array(
            'hostname' => "notanldaphost.com",
            'port' => self::SERVER_PORT,
            'base_dn' => self::SERVER_BASE_DN,
            'admin_user' => 'cn=fury,' . self::SERVER_BASE_DN,
            'admin_pass' => 'secrets',
            'start_tls' => false
        );
        Config::getInstance()->LoginLdap_dummyserver2 = array(
            'hostname' => "localhost",
            'port' => 999,
            'base_dn' => self::SERVER_BASE_DN,
            'admin_user' => 'cn=fury,' . self::SERVER_BASE_DN,
            'admin_pass' => 'secrets',
            'start_tls' => false
        );
    }

    public function testAuthenticateSucceedsWhenFirstServerWorksButOthersFailToConnect()
    {
        Config::getInstance()->LoginLdap['servers'] = array('testserver', 'dummyserver1', 'dummyserver2');

        $this->doAuthTest();
    }

    public function testAuthenticateSucceedsWhenOneServerSucceedsButOthersFailToConnect()
    {
        Config::getInstance()->LoginLdap['servers'] = array('dummyserver1', 'testserver', 'dummyserver2');

        $this->doAuthTest();

        Config::getInstance()->LoginLdap['servers'] = array('dummyserver1', 'dummyserver2', 'testserver');

        $this->doAuthTest();
    }

    public function testAuthenticateFailsWhenAllServersFailToConnect()
    {
        $this->expectException(\Piwik\Plugins\LoginLdap\Ldap\Exceptions\ConnectionException::class);
        $this->expectExceptionMessage('Could not connect to any of the');

        Config::getInstance()->LoginLdap['servers'] = array('dummyserver1', 'dummyserver2');

        $this->doAuthTest($expectCode = 0);
    }

    private function doAuthTest($expectCode = 1)
    {
        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals($expectCode, $authResult->getCode());
    }
}