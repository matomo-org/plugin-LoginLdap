<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\AuthResult;
use Piwik\Config;
use Piwik\Plugins\LoginLdap\Auth\WebServerAuth;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_WebServerAuthTest
 */
class WebServerAuthTest extends LdapIntegrationTest
{
    private $testSuperUserTokenAuth;

    public function setUp(): void
    {
        parent::setUp();

        $this->addPreexistingSuperUser();
        $this->testSuperUserTokenAuth = UsersManagerAPI::getInstance()->createAppSpecificTokenAuth(
            self::TEST_SUPERUSER_LOGIN,
            self::TEST_SUPERUSER_PASS,
            'test'
        );
    }

    public function test_WebServerAuth_Works_IfUserExists_RegardlessOfPassword()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = WebServerAuth::makeConfigured();
        $ldapAuth->setPassword('slkdjfdslf');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());

        $ldapAuth = WebServerAuth::makeConfigured();
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function test_WebServerAuth_Fails_IfUserDoesNotExist()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;

        $_SERVER['REMOTE_USER'] = 'abcdefghijk';

        $ldapAuth = WebServerAuth::makeConfigured();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function test_WebServerAuth_Fails_IfUserIsNotPartOfRequiredGroup()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;
        Config::getInstance()->LoginLdap['memberOf'] = "cn=S.H.I.E.L.D.," . self::SERVER_BASE_DN;

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = WebServerAuth::makeConfigured();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function test_WebServerAuth_Fails_IfUserIsNotMatchedByCustomFilter()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;
        Config::getInstance()->LoginLdap['filter'] = "(mobile=none)";

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = WebServerAuth::makeConfigured();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function test_WebServerAuth_Fails_IfUserNoRemoteUserExists_AndNoUserSpecifiedThroughAuth()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;

        unset($_SERVER['REMOTE_USER']);

        $ldapAuth = WebServerAuth::makeConfigured();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function test_WebServerAuth_UsesCorrectFallbackAuth_IfNoRemoteUserExists_AndAuthDetailsSpecified()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;

        unset($_SERVER['REMOTE_USER']);

        $ldapAuth = WebServerAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function test_WebServerAuth_ReturnsCorrectCodeForSuperUsers()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;

        $_SERVER['REMOTE_USER'] = self::TEST_SUPERUSER_LOGIN;

        $ldapAuth = WebServerAuth::makeConfigured();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
    }

    public function test_SuperUsersCanLogin_IfWebServerAuthUsed_AndWebServerAuthSetupIncorrectly()
    {
        unset($_SERVER['REMOTE_USER']);

        $ldapAuth = WebServerAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_SUPERUSER_LOGIN);
        $ldapAuth->setPassword(self::TEST_SUPERUSER_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());

        $ldapAuth = WebServerAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_SUPERUSER_LOGIN);
        $ldapAuth->setTokenAuth($this->testSuperUserTokenAuth);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
    }

    public function test_WebServerAuth_Fails_IfDomainNotStrippedCorrectly()
    {
        Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;

        Config::getInstance()->LoginLdap['strip_domain_from_web_auth'] = 1;
        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN . '@shield.org';
        $ldapAuth = WebServerAuth::makeConfigured();
        $authResult = $ldapAuth->authenticate();
        $this->assertEquals(AuthResult::SUCCESS, $authResult->getCode());

        Config::getInstance()->LoginLdap['strip_domain_from_web_auth'] = 0;
        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN . '@shield.org';
        $ldapAuth = WebServerAuth::makeConfigured();
        $authResult = $ldapAuth->authenticate();
        $this->assertEquals(AuthResult::FAILURE, $authResult->getCode());
    }
}
