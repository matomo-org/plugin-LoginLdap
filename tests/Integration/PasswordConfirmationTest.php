<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\Access;
use Piwik\AuthResult;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\LoginLdap\API;
use Piwik\Plugins\LoginLdap\Auth\LdapAuth;
use Piwik\Plugins\LoginLdap\Auth\WebServerAuth;
use Piwik\Plugins\LoginLdap\Controller;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\Login\PasswordVerifier;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_PasswordConfirmationTest
 */
class PasswordConfirmationTest extends LdapIntegrationTest
{
    /**
     * @var PasswordVerifier
     */
    private $passwordVerifier;

    /**
     * @var API
     */
    private $api;

    public function setUp(): void
    {
        parent::setUp();

        \Zend_Session::$_unitTestEnabled = true;

        // ensure a leftover REMOTE_USER from another test doesn't make us look web-server authenticated
        unset($_SERVER['REMOTE_USER']);

        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 0;
        $this->addNonLdapUsers();
        $this->passwordVerifier = new PasswordVerifier();
        $this->passwordVerifier->setDisableRedirect();
        StaticContainer::getContainer()->set('Piwik\Plugins\Login\PasswordVerifier', $this->passwordVerifier);
        $this->api = new API();
    }

    public function testPasswordConfirmationStillRequiredForNonLdapUsersWhenDisabledInConfig()
    {
        $this->setCurrentUser(self::NON_LDAP_USER, self::NON_LDAP_PASS);

        $this->assertTrue(Piwik::doesUserRequirePasswordConfirmation(self::NON_LDAP_USER));

        $this->passwordVerifier->requirePasswordVerifiedRecently(['module' => 'Login', 'action' => 'test']);
        $plugin = new \Piwik\Plugins\LoginLdap\LoginLdap();
        $plugin->checkIfPasswordConfirmationCanBeSkipped();

        $this->assertFalse($this->passwordVerifier->hasBeenVerified());

        $controller = new Controller(null, null, null, $this->passwordVerifier);
        $result = $controller->confirmPassword();

        $this->assertIsString($result);
        $this->assertStringContainsString('confirmPasswordForm', $result);
        $this->assertFalse($this->passwordVerifier->hasBeenVerified());
    }

    public function testPasswordConfirmationCanStillBeSkippedForLdapUsersWhenDisabledInConfig()
    {
        $this->addLdapUser(self::TEST_LOGIN, self::TEST_PASS);
        $this->setCurrentUser(self::TEST_LOGIN, self::TEST_PASS);

        $this->assertFalse(Piwik::doesUserRequirePasswordConfirmation(self::TEST_LOGIN));

        $this->passwordVerifier->requirePasswordVerifiedRecently(['module' => 'Login', 'action' => 'test']);
        $plugin = new \Piwik\Plugins\LoginLdap\LoginLdap();
        $plugin->checkIfPasswordConfirmationCanBeSkipped();

        $this->assertTrue($this->passwordVerifier->hasBeenVerified());

        $this->passwordVerifier->forgetVerifiedPassword();
        $this->passwordVerifier->requirePasswordVerifiedRecently(['module' => 'Login', 'action' => 'test']);

        $controller = new Controller(null, null, null, $this->passwordVerifier);
        $result = $controller->confirmPassword();

        $this->assertNull($result);
        $this->assertTrue($this->passwordVerifier->hasBeenVerified());
    }

    public function testSaveLdapConfigRequiresPasswordConfirmationWhenEnabled()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 1;
        $this->setCurrentUser(self::NON_LDAP_USER, self::NON_LDAP_PASS);

        $this->expectException(\Exception::class);
        $this->api->saveLdapConfig(json_encode(array(
            'use_ldap_for_authentication' => 1,
        )));
    }

    public function testSaveLdapConfigRequiresPasswordConfirmationWhenDisabledForNonLdapUsers()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 0;
        $this->setCurrentUser(self::NON_LDAP_USER, self::NON_LDAP_PASS);

        $this->expectException(\Exception::class);
        $this->api->saveLdapConfig(json_encode(array(
            'use_ldap_for_authentication' => 0,
        )));
    }

    public function testSaveLdapConfigStillRequiresPasswordConfirmationForLdapUsersWhenDisabled()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 0;
        $this->addLdapUser(self::TEST_LOGIN, self::TEST_PASS);
        $this->setSuperUserAccess(self::TEST_LOGIN, true);
        $this->setCurrentUser(self::TEST_LOGIN, self::TEST_PASS);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(Piwik::translate('UsersManager_ConfirmWithReAuthentication'));

        $this->api->saveLdapConfig(json_encode(array(
            'use_ldap_for_authentication' => 0,
        )));
    }

    public function testSaveLdapConfigSucceedsForLdapUserWithPasswordConfirmation()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 0;
        $this->useRealLdapUser();

        $result = $this->api->saveLdapConfig(json_encode(array(
            'use_ldap_for_authentication' => 0,
            'password_confirmation' => self::TEST_PASS,
        )));

        $this->assertSame('success', $result['result']);
    }

    public function testSaveLdapConfigRejectsWrongPasswordForLdapUser()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 0;
        $this->useRealLdapUser();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(Piwik::translate('UsersManager_CurrentPasswordNotCorrect'));

        $this->api->saveLdapConfig(json_encode(array(
            'use_ldap_for_authentication' => 0,
            'password_confirmation' => 'not-' . self::TEST_PASS,
        )));
    }

    /**
     * A synchronized LDAP user's Matomo password column holds md5() of the LDAP password
     * *hash* (see UserMapper::getPiwikPasswordForLdapUser), never the plaintext LDAP
     * password, so confirming with TEST_PASS can only succeed by binding to LDAP.
     */
    public function testLdapUserPasswordConfirmationIsCheckedAgainstLdapNotTheMatomoDatabase()
    {
        $ldapAuth = $this->useRealLdapUser();

        $user = $this->getUser(self::TEST_LOGIN);
        $passwordHelper = new \Piwik\Auth\Password();
        $this->assertFalse($passwordHelper->verify(md5(self::TEST_PASS), $user['password']));
        $this->assertTrue($passwordHelper->verify(md5(self::TEST_PASS_LDAP), $user['password']));

        StaticContainer::getContainer()->set('Piwik\Auth', new \Piwik\Plugins\Login\Auth());
        $this->assertFalse($this->passwordVerifier->isPasswordCorrect(self::TEST_LOGIN, self::TEST_PASS));

        StaticContainer::getContainer()->set('Piwik\Auth', $ldapAuth);
        $this->assertTrue($this->passwordVerifier->isPasswordCorrect(self::TEST_LOGIN, self::TEST_PASS));
    }

    public function testSaveLdapConfigSucceedsWithPasswordConfirmationWhenEnabled()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 1;
        $this->setCurrentUser(self::NON_LDAP_USER, self::NON_LDAP_PASS);

        $result = $this->api->saveLdapConfig(
            json_encode(array(
                'use_ldap_for_authentication' => 0,
                'password_confirmation' => self::NON_LDAP_PASS,
            ))
        );

        $this->assertSame('success', $result['result']);
    }

    public function testSaveLdapConfigSkipsPasswordConfirmationForWebServerAuthUser()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 1;

        $this->addLdapUser(self::TEST_LOGIN, self::TEST_PASS);
        $this->setSuperUserAccess(self::TEST_LOGIN, true);
        $this->setCurrentUser(self::TEST_LOGIN, self::TEST_PASS);

        // simulate a web-server-authenticated request: there is no password to confirm
        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;
        StaticContainer::getContainer()->set('Piwik\Auth', new WebServerAuth());

        $result = $this->api->saveLdapConfig(json_encode(array(
            'use_ldap_for_authentication' => 0,
        )));

        $this->assertSame('success', $result['result']);
    }

    public function testSaveLdapConfigTreatsZeroStringAsAProvidedPassword()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 1;
        $this->setCurrentUser(self::NON_LDAP_USER, self::NON_LDAP_PASS);

        // "0" is a provided (wrong) password, not a missing one: it must reach verification and
        // fail with "current password not correct", not the "re-authentication required" path.
        try {
            $this->api->saveLdapConfig(json_encode(array(
                'use_ldap_for_authentication' => 0,
                'password_confirmation' => '0',
            )));
            $this->fail('Expected an exception to be thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString(
                Piwik::translate('UsersManager_CurrentPasswordNotCorrect'),
                $e->getMessage()
            );
            $this->assertStringNotContainsString(
                Piwik::translate('UsersManager_ConfirmWithReAuthentication'),
                $e->getMessage()
            );
        }
    }

    public function testSaveServersInfoRequiresPasswordConfirmationWhenEnabled()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 1;
        $this->setCurrentUser(self::NON_LDAP_USER, self::NON_LDAP_PASS);

        $this->expectException(\Exception::class);
        $this->api->saveServersInfo(json_encode($this->getServerPayload()));
    }

    public function testSaveServersInfoSucceedsAfterPasswordConfirmationWhenEnabled()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 1;
        $this->setCurrentUser(self::NON_LDAP_USER, self::NON_LDAP_PASS);

        $result = $this->api->saveServersInfo(json_encode($this->getServerPayload()), self::NON_LDAP_PASS);

        $this->assertSame('success', $result['result']);
    }

    public function testSaveServersInfoRequiresPasswordConfirmationWhenDisabledForNonLdapUsers()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 0;
        $this->setCurrentUser(self::NON_LDAP_USER, self::NON_LDAP_PASS);

        $this->expectException(\Exception::class);
        $this->api->saveServersInfo(json_encode($this->getServerPayload()));
    }

    public function testSaveServersInfoStillRequiresPasswordConfirmationForLdapUsersWhenDisabled()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 0;
        $this->addLdapUser(self::TEST_LOGIN, self::TEST_PASS);
        $this->setSuperUserAccess(self::TEST_LOGIN, true);
        $this->setCurrentUser(self::TEST_LOGIN, self::TEST_PASS);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(Piwik::translate('UsersManager_ConfirmWithReAuthentication'));

        $this->api->saveServersInfo(json_encode($this->getServerPayload()));
    }

    public function testSaveServersInfoSucceedsForLdapUserWithPasswordConfirmation()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 0;
        $this->useRealLdapUser();

        $result = $this->api->saveServersInfo(json_encode($this->getServerPayload()), self::TEST_PASS);

        $this->assertSame('success', $result['result']);
    }

    public function testSaveServersInfoRejectsWrongPasswordForLdapUser()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 0;
        $this->useRealLdapUser();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(Piwik::translate('UsersManager_CurrentPasswordNotCorrect'));

        $this->api->saveServersInfo(json_encode($this->getServerPayload()), 'not-' . self::TEST_PASS);
    }

    /**
     * Synchronizes TEST_LOGIN from the real LDAP server and binds LdapAuth as the request's
     * auth adapter. Required by any test that actually verifies a password.
     */
    private function useRealLdapUser(): LdapAuth
    {
        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $this->assertSame(AuthResult::SUCCESS, $ldapAuth->authenticate()->getCode());

        StaticContainer::getContainer()->set('Piwik\Auth', $ldapAuth);

        $userMapper = new UserMapper();
        $this->assertTrue($userMapper->isUserLdapUser(self::TEST_LOGIN));

        $this->setSuperUserAccess(self::TEST_LOGIN, true);
        $this->setCurrentUser(self::TEST_LOGIN, self::TEST_PASS);

        return $ldapAuth;
    }

    /**
     * Creates a Matomo user merely *marked* as an LDAP user; its password lives in Matomo's
     * user table, so this cannot exercise LDAP re-authentication.
     */
    private function addLdapUser(string $login, string $password): void
    {
        \Piwik\Plugins\UsersManager\API::getInstance()->addUser(
            $login,
            $password,
            $login . '@shield.org'
        );

        $userMapper = new UserMapper();
        $userMapper->markUserAsLdapUser($login);

        \Piwik\Access::doAsSuperUser(function () use ($login) {
            \Piwik\Plugins\UsersManager\API::getInstance()->setUserAccess($login, 'view', [1]);
        });
    }

    private function setCurrentUser(string $login, string $password): void
    {
        $auth = StaticContainer::get('Piwik\Auth');
        $auth->setLogin($login);
        $auth->setPassword($password);
        Access::getInstance()->setSuperUserAccess(false);
        Access::getInstance()->reloadAccess($auth);
    }

    private function getServerPayload(): array
    {
        return array(
            array(
                'name' => 'server1',
                'hostname' => 'localhost',
                'port' => 389,
                'base_dn' => 'dc=avengers,dc=shield,dc=org',
                'admin_user' => 'cn=fury,dc=avengers,dc=shield,dc=org',
                'admin_pass' => 'secrets',
                'start_tls' => false,
            ),
        );
    }
}
