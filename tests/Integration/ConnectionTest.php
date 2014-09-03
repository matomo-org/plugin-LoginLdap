<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\AuthResult;
use Piwik\Config;
use Piwik\Log;
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

    const OTHER_TEST_LOGIN = 'blackwidow';
    const OTHER_TEST_PASS = 'redledger';

    const TEST_SUPERUSER_LOGIN = 'captainamerica';
    const TEST_SUPERUSER_PASS = 'thaifood';

    public function setUp()
    {
        if (!function_exists('ldap_bind')) {
            throw new \Exception("PHP not compiled w/ --with-ldap!");
        }

        parent::setUp();

        // make sure logging logic is executed so we can test whether there are bugs in the logging code
        Log::getInstance()->setLogLevel(Log::VERBOSE);

        Config::getInstance()->LoginLdap = Config::getInstance()->LoginLdapTest + array(
            'serverUrl' => self::SERVER_HOST_NAME,
            'ldapPort' => self::SERVER_POST,
            'baseDn' => self::SERVER_BASE_DN,
            'adminUser' => 'cn=fury,' . self::SERVER_BASE_DN,
            'adminPass' => 'secrets',
            'useKerberos' => 'false'
        );

        UsersManagerAPI::getInstance()->addUser(self::TEST_LOGIN, self::TEST_PASS, 'billionairephilanthropistplayboy@starkindustries.com', $alias = false);
        
        UsersManagerAPI::getInstance()->addUser(self::TEST_SUPERUSER_LOGIN, self::TEST_SUPERUSER_PASS, 'srodgers@aol.com', $alias = false);
        UsersManagerAPI::getInstance()->setSuperUserAccess(self::TEST_SUPERUSER_LOGIN, true);
    }

    public function tearDown()
    {
        Log::unsetInstance();
    }

    public function testLdapAuthSucceedsWithCorrectCredentials()
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function testLdapAuthFailsWithIncorrectPassword()
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword('slkdjfsd');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testLdapAuthFailsWithNonexistantUser()
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin('skldfjsd');
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testLdapAuthChecksMemberOf()
    {
        Config::getInstance()->LoginLdap['memberOf'] = "cn=S.H.I.E.L.D.," . self::SERVER_BASE_DN;

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());

        Config::getInstance()->LoginLdap['memberOf'] = "cn=avengers," . self::SERVER_BASE_DN;

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function testLdapAuthUsesConfiguredFilter()
    {
        Config::getInstance()->LoginLdap['filter'] = "(!(mobile=none))";

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::OTHER_TEST_LOGIN);
        $ldapAuth->setPassword(self::OTHER_TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testWebServerAuthWorksIfUserExistsRegardlessOfPassword()
    {
        Config::getInstance()->LoginLdap['useKerberos'] = 1;

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = new LdapAuth();
        $ldapAuth->setPassword('slkdjfdslf');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());

        $ldapAuth = new LdapAuth();
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function testWebServerAuthFailsIfUserDoesNotExist()
    {
        Config::getInstance()->LoginLdap['useKerberos'] = 1;

        $_SERVER['REMOTE_USER'] = 'abcdefghijk';

        $ldapAuth = new LdapAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testWebServerAuthFailsIfUserIsNotPartOfRequiredGroup()
    {
        Config::getInstance()->LoginLdap['useKerberos'] = 1;
        Config::getInstance()->LoginLdap['memberOf'] = "cn=S.H.I.E.L.D.," . self::SERVER_BASE_DN;

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = new LdapAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testWebServerAuthFailsIfUserIsNotMatchedByCustomFilter()
    {
        Config::getInstance()->LoginLdap['useKerberos'] = 1;
        Config::getInstance()->LoginLdap['filter'] = "(mobile=none)";

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = new LdapAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testLdapAuthReturnsCorrectCodeForSuperUsers()
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_SUPERUSER_LOGIN);
        $ldapAuth->setPassword(self::TEST_SUPERUSER_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
    }

    public function testWebServerAuthReturnsCorrectCodeForSuperUsers()
    {
        $this->markTestSkipped("Superuser access from LDAP not implemented yet"); // TODO remove when implemented

        $_SERVER['REMOTE_USER'] = self::TEST_SUPERUSER_LOGIN;

        $ldapAuth = new LdapAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
    }

    // TODO: rename kerberos stuff w/ webserver auth
}