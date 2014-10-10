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
use Piwik\Db;
use Piwik\Plugins\LoginLdap\Auth\WebServerAuth;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_WebServerAuthTest
 */
class WebServerAuthTest extends LdapIntegrationTest
{
    public function setUp()
    {
        parent::setUp();

        $this->addPreexistingSuperUser();
    }

    public function testWebServerAuthWorksIfUserExistsRegardlessOfPassword()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = new WebServerAuth();
        $ldapAuth->setPassword('slkdjfdslf');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());

        $ldapAuth = new WebServerAuth();
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function testWebServerAuthFailsIfUserDoesNotExist()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;

        $_SERVER['REMOTE_USER'] = 'abcdefghijk';

        $ldapAuth = new WebServerAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testWebServerAuthFailsIfUserIsNotPartOfRequiredGroup()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;
        Config::getInstance()->LoginLdap['memberOf'] = "cn=S.H.I.E.L.D.," . self::SERVER_BASE_DN;

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = new WebServerAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testWebServerAuthFailsIfUserIsNotMatchedByCustomFilter()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;
        Config::getInstance()->LoginLdap['filter'] = "(mobile=none)";

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = new WebServerAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testWebServerAuthFailsIfUserIsNoRemoteUserExists()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;

        unset($_SERVER['REMOTE_USER']);

        $ldapAuth = new WebServerAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testWebServerAuthReturnsCorrectCodeForSuperUsers()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;

        $_SERVER['REMOTE_USER'] = self::TEST_SUPERUSER_LOGIN;

        $ldapAuth = new WebServerAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
    }

    public function test_SuperUsersCanLogin_IfWebServerAuthUsed_AndWebServerAuthSetupIncorreclty()
    {
        unset($_SERVER['REMOTE_USER']);

        $ldapAuth = new WebServerAuth();
        $ldapAuth->setLogin(self::TEST_SUPERUSER_LOGIN);
        $ldapAuth->setPassword(self::TEST_SUPERUSER_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());

        $ldapAuth = new WebServerAuth();
        $ldapAuth->setLogin(self::TEST_SUPERUSER_LOGIN);
        $ldapAuth->setTokenAuth(UsersManagerAPI::getInstance()->getTokenAuth(self::TEST_SUPERUSER_LOGIN, md5(self::TEST_SUPERUSER_PASS)));
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
    }
}