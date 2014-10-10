<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\Config;
use Piwik\Plugins\LoginLdap\Auth\LdapAuth;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_BackwardsCompatibilityTest
 */
class BackwardsCompatibilityTest extends LdapIntegrationTest
{
    public function setUp()
    {
        parent::setUp();

        Config::getInstance()->LoginLdap = array(
            'serverUrl' => @Config::getInstance()->LoginLdap_testserver['hostname'] ?: self::SERVER_HOST_NAME,
            'ldapPort' => @Config::getInstance()->LoginLdap_testserver['port'] ?: self::SERVER_PORT,
            'baseDn' => self::SERVER_BASE_DN,
            'adminUser' => 'cn=fury,' . self::SERVER_BASE_DN,
            'adminPass' => 'secrets',
            'useKerberos' => 'false'
        );

        UsersManagerAPI::getInstance()->addUser(self::TEST_LOGIN, self::TEST_PASS, 'billionairephilanthropistplayboy@starkindustries.com', $alias = false);
    }

    public function testAuthenticationWithOldServerConfig()
    {
        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }
}