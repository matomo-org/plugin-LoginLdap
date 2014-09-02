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
use Piwik\Plugins\LoginLdap\LdapAuth;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use DatabaseTestCase;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_ConnectionTest
 */
class ConnectionTest extends DatabaseTestCase
{
    const SERVER_HOST_NAME = 'localhost';
    const SERVER_POST = 389;
    const SERVER_BASE_DN = "dc=avengers,dc=shield,dc=org";
    const GROUP_NAME = 'cn=avengers,dc=avengers,dc=shield,dc=org';

    const TEST_LOGIN = 'ironman';
    const TEST_PASS = 'piedpiper';

    public function setUp()
    {
        if (!function_exists('ldap_bind')) {
            throw new \Exception("PHP not compiled w/ --with-ldap!");
        }

        parent::setUp();

        Config::getInstance()->LoginLdap = Config::getInstance()->LoginLdapTest + array(
            'serverUrl' => self::SERVER_HOST_NAME,
            'ldapPort' => self::SERVER_POST,
            'baseDn' => self::SERVER_BASE_DN,
            'userIdField' => 'uid',
            'usernameSuffix' => '',
            'adminUser' => 'cn=fury,' . self::SERVER_BASE_DN,
            'adminPass' => 'secrets',
            'mailField' => '',
            'aliasField' => '',
            'memberOf' => self::GROUP_NAME,
            'filter' => '',
            'useKerberos' => 'false'
        );

        UsersManagerAPI::getInstance()->addUser(self::TEST_LOGIN, self::TEST_PASS, 'billionairephilanthropistplayboy@starkindustries.com', $alias = false);
    }

    public function testBasicAuthTest()
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function testBasicAuthFailure()
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword('slkadjfasldfj');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    // TODO: keep this test until refactoring done, but LoginLdap doesn't actually use kerberos so much as delegate
    //       authentication to the HTTP server when useKerberos = 1. code must reflect that.
    public function testKerberosConnection()
    {
        Config::getInstance()->LoginLdap['useKerberos'] = 1;

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = new LdapAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }
}