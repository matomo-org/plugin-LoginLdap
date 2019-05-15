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
use Piwik\Common;
use Piwik\Config;
use Piwik\Db;
use Piwik\Plugins\LoginLdap\Auth\LdapAuth;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Tests\Framework\Fixture;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_AuthenticationTest
 */
class AuthenticationTest extends LdapIntegrationTest
{
    public function setUp()
    {
        parent::setUp();

        // test superusers should not have {LDAP} password to test that superusers can
        // login, even if they are not in LDAP
        $this->addPreexistingSuperUser();

        $this->addNonLdapUsers();
        Fixture::createSuperUser();
    }

    public function test_LdapAuth_AuthenticatesUser_WithCorrectCredentials()
    {
        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function test_LdapAuth_DoesNotAuthenticateUser_WithIncorrectPassword()
    {
        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword('slkdjfsd');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function test_LdapAuth_DoesNotAuthenticateUser_WithNonexistantUser()
    {
        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin('skldfjsd');
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function test_LdapAuth_ChecksMemberOf_WhenAuthenticating()
    {
        Config::getInstance()->LoginLdap['memberOf'] = "cn=S.H.I.E.L.D.," . self::SERVER_BASE_DN;

        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());

        Config::getInstance()->LoginLdap['memberOf'] = "cn=avengers," . self::SERVER_BASE_DN;

        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function test_LdapAuth_UsesConfiguredFilter_WhenAuthenticating()
    {
        Config::getInstance()->LoginLdap['filter'] = "(!(mobile=none))";

        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());

        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::OTHER_TEST_LOGIN);
        $ldapAuth->setPassword(self::OTHER_TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function test_LdapAuth_ReturnsCorrectCode_WhenAuthenticatingSuperUsers()
    {
        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_SUPERUSER_LOGIN);
        $ldapAuth->setPassword(self::TEST_SUPERUSER_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
    }

    public function test_LdapAuth_ReturnsCorrectCode_WhenAuthenticatingNonLdapSuperUsers()
    {
        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::NON_LDAP_USER);
        $ldapAuth->setPassword(self::NON_LDAP_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());

        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setTokenAuth($this->getNonLdapUserTokenAuth());
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(self::NON_LDAP_USER, $authResult->getIdentity());
        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
    }

    public function test_LdapAuth_ReturnsCorrectCode_WhenAuthenticatingNonLdapNormalUsers()
    {
        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::NON_LDAP_NORMAL_USER);
        $ldapAuth->setPassword(self::NON_LDAP_NORMAL_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS, $authResult->getCode());

        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setTokenAuth($this->getNonLdapNormalUserTokenAuth());
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(self::NON_LDAP_NORMAL_USER, $authResult->getIdentity());
        $this->assertEquals(AuthResult::SUCCESS, $authResult->getCode());
    }

    public function test_LdapAuth_AuthenticatesSuccessfully_WhenTokenAuthOnlyAuthenticationUsed()
    {
        $this->test_LdapAuth_AuthenticatesUser_WithCorrectCredentials();

        $tokenAuth = Db::fetchOne("SELECT token_auth FROM " . Common::prefixTable("user") . " WHERE login = ?", array(self::TEST_LOGIN));

        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setTokenAuth($tokenAuth);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function test_LdapAuth_AuthenticatesSuccessfully_WhenAuthenticatingNormalPiwikSuperUser()
    {
        UsersManagerAPI::getInstance()->addUser('zola', 'hydra___', 'zola@shield.org', $alias = false);
        $this->setSuperUserAccess('zola', true);

        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin('zola');
        $ldapAuth->setPassword('hydra___');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
    }

    public function test_LdapAuth_AuthenticatesSuccessfully_WhenAuthenticatingNormalPiwikNonSuperUser()
    {
        UsersManagerAPI::getInstance()->addUser('pcoulson', 'thispasswordwillbechanged', 'pcoulson@shield.org', $alias = false);
        UsersManagerAPI::getInstance()->updateUser('pcoulson', 'vintage', false, false, false, self::TEST_SUPERUSER_PASS);

        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin('pcoulson');
        $ldapAuth->setPassword('vintage');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS, $authResult->getCode());
    }

    public function test_LdapAuth_DoesNotAuthenticate_WhenEmptyLoginProvided()
    {
        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin('');
        $ldapAuth->setPassword('lsakjdfdslj');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function test_LdapAuth_DoesNotAuthenticate_WhenAnonymousLoginProvided()
    {
        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin('anonymous');
        $ldapAuth->setPassword('lsakjdfdslj');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    private function getNonLdapUserTokenAuth()
    {
        return UsersManagerAPI::getInstance()->getTokenAuth(self::NON_LDAP_USER, md5(self::NON_LDAP_PASS));
    }

    private function getNonLdapNormalUserTokenAuth()
    {
        return UsersManagerAPI::getInstance()->getTokenAuth(self::NON_LDAP_NORMAL_USER, md5(self::NON_LDAP_NORMAL_PASS));
    }
}
