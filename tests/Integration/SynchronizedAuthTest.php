<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\Auth;
use Piwik\Auth\Password;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\LoginLdap\Auth\SynchronizedAuth;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Log\LoggerInterface;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_SynchronizedAuthTest
 */
class SynchronizedAuthTest extends LdapIntegrationTest
{
    private $testUserTokenAuth;

    public function setUp(): void
    {
        parent::setUp();

        Config::getInstance()->LoginLdap_brokenserver = array(
            'hostname' => "localhost",
            'port' => 999,
            'base_dn' => self::SERVER_BASE_DN,
            'admin_user' => 'cn=fury,' . self::SERVER_BASE_DN,
            'admin_pass' => 'secrets',
            'start_tls' => false
        );
    }

    public function test_SynchronizedLdapUsersCanLogin_WithoutConnectingToLdap_WhenUsersExistInPiwikDB()
    {
        Config::getInstance()->LoginLdap['servers'] = array('brokenserver');

        $this->addPreSynchronizedUser();

        $this->doAuthTest($code = 1);
    }

    public function test_NormalPiwikUsersCanLogin_WithoutConnectingToLdap()
    {
        Config::getInstance()->LoginLdap['servers'] = array('brokenserver');

        $this->addNonLdapUsers();

        $this->doAuthTest($code = 42, self::NON_LDAP_USER, self::NON_LDAP_PASS);
        $this->doAuthTest($code = 1, self::NON_LDAP_NORMAL_USER, self::NON_LDAP_NORMAL_PASS);
    }

    public function test_SynchronizedLdapUsersCanLogin_WithoutConnectingToLdap_ByTokenAuth()
    {
        Config::getInstance()->LoginLdap['servers'] = array('brokenserver');

        $this->addPreSynchronizedUser();

        $this->doAuthTestByTokenAuth($code = 1, self::TEST_LOGIN, self::TEST_PASS, $this->testUserTokenAuth);
    }

    public function test_NormalPiwikUsersCanLogin_WithoutConnectingToLdap_ByTokenAuth()
    {
        Config::getInstance()->LoginLdap['servers'] = array('brokenserver');

        $this->addNonLdapUsers();

        $this->doAuthTestByTokenAuth($code = 42, self::NON_LDAP_USER, self::NON_LDAP_PASS);
        $this->doAuthTestByTokenAuth($code = 1, self::NON_LDAP_NORMAL_USER, self::NON_LDAP_NORMAL_PASS);
    }

    public function test_SynchronizedLdapUsersCanLogin_WithoutConnectingToLdap_ByPasswordHash()
    {
        Config::getInstance()->LoginLdap['servers'] = array('brokenserver');

        $this->addPreSynchronizedUser();

        $this->doAuthTestByPasswordHash($code = 1);
    }

    public function test_NormalPiwikUsersCanLogin_WithoutConnectingToLdap_ByPasswordHash()
    {
        StaticContainer::get(LoggerInterface::class)->info("NEXT TEST");
        Config::getInstance()->LoginLdap['servers'] = array('brokenserver');

        $this->addNonLdapUsers();

        $this->doAuthTestByPasswordHash($code = 42, self::NON_LDAP_USER, self::NON_LDAP_PASS);
        $this->doAuthTestByPasswordHash($code = 1, self::NON_LDAP_NORMAL_USER, self::NON_LDAP_NORMAL_PASS);
    }

    public function test_LdapUsersCannotLogin_IfUnsynchronized_AndLdapServerBroken()
    {
        $this->expectException(\Piwik\Plugins\LoginLdap\Ldap\Exceptions\ConnectionException::class);

        Config::getInstance()->LoginLdap['servers'] = ['brokenserver'];

        $this->doAuthTest($code = 0);
    }

    public function test_LdapUserCannotLogin_IfUserNotInDb_SynchronizingAfterLoginDisabled()
    {
        Config::getInstance()->LoginLdap['synchronize_users_after_login'] = 0;

        $this->doAuthTest($code = 0);
    }

    public function test_LdapUserCannotLogin_IfUserNotInDb_AndAuthenticatingByTokenAuth()
    {
        Config::getInstance()->LoginLdap['synchronize_users_after_login'] = 0;

        $this->doAuthTestByTokenAuth($code = 0, self::TEST_LOGIN, self::TEST_PASS, 'garbagetokenauth');
    }

    public function test_LdapUserCannotLogin_IfUserNotInDb_AndAuthtenticatingByPasswordHash()
    {
        Config::getInstance()->LoginLdap['synchronize_users_after_login'] = 0;

        $this->doAuthTestByPasswordHash($code = 0);
    }

    public function test_AuthenticationFails_WhenUserNotInDb_AndUserNotInLdap()
    {
        $this->doAuthTest($code = 0, self::NON_LDAP_USER, self::NON_LDAP_PASS);
    }

    public function test_AuthenticationFails_WhenUserNotInDb_AndUserInLdap_AndOnlyTokenAuthTested()
    {
        $this->doAuthTest($code = 0, self::TEST_LOGIN, null, $this->getLdapUserTokenAuth());
    }

    public function test_LdapUserPasswordUpdated_AfterSuccessfulLoginViaLdap()
    {
        $this->addLdapUserWithWrongPassword();

        $this->doAuthTest($code = 1);

        $user = $this->getUser(self::TEST_LOGIN);

        $passwordHelper = new Password();
        $this->assertTrue($passwordHelper->verify(\Piwik\Plugins\UsersManager\UsersManager::getPasswordHash(self::TEST_PASS), $user['password']));
    }

    private function addPreSynchronizedUser($pass = self::TEST_PASS)
    {
        UsersManagerAPI::getInstance()->addUser(
            self::TEST_LOGIN,
            $pass,
            'billionairephilanthropistplayboy@starkindustries.com'
        );

        $userMapper = new UserMapper();
        $userMapper->markUserAsLdapUser(self::TEST_LOGIN);

        $this->testUserTokenAuth = UsersManagerAPI::getInstance()->createAppSpecificTokenAuth(
            self::TEST_LOGIN,
            $pass,
            'test'
        );
    }

    private function addLdapUserWithWrongPassword()
    {
        $this->addPreSynchronizedUser('averywrongpassword');
    }

    private function doAuthTest($expectCode, $login = self::TEST_LOGIN, $pass = self::TEST_PASS, $token_auth = null)
    {
        $auth = SynchronizedAuth::makeConfigured();
        if (!empty($login)) {
            $auth->setLogin($login);
        }
        if (!empty($pass)) {
            $auth->setPassword($pass);
        }
        if (!empty($token_auth)) {
            $auth->setTokenAuth($token_auth);
        }
        $result = $auth->authenticate();

        $this->assertEquals($expectCode, $result->getCode());
    }

    private function doAuthTestByTokenAuth($expectCode, $login = self::TEST_LOGIN, $pass = self::TEST_PASS, $tokenAuth = null)
    {
        $auth = SynchronizedAuth::makeConfigured();
        StaticContainer::getContainer()->set(Auth::class, $auth);

        $tokenAuth = $tokenAuth ?: UsersManagerAPI::getInstance()->createAppSpecificTokenAuth($login, $pass, 'test');

        $auth->setLogin($login);
        $auth->setTokenAuth($tokenAuth);
        $result = $auth->authenticate();

        $this->assertEquals($expectCode, $result->getCode());
    }

    private function doAuthTestByPasswordHash($expectCode, $login = self::TEST_LOGIN, $pass = self::TEST_PASS)
    {
        $auth = SynchronizedAuth::makeConfigured();
        $auth->setLogin($login);
        $auth->setPasswordHash(md5($pass));
        $result = $auth->authenticate();

        $this->assertEquals($expectCode, $result->getCode());
    }

    private function getLdapUserTokenAuth()
    {
        return $this->testUserTokenAuth;
    }
}
