<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\Access;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\LoginLdap\API;
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

    public function testSaveLdapConfigSucceedsWithoutPasswordConfirmationWhenDisabledForLdapUsers()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 0;
        $this->addLdapUser(self::TEST_LOGIN, self::TEST_PASS);
        $this->setSuperUserAccess(self::TEST_LOGIN, true);
        $this->setCurrentUser(self::TEST_LOGIN, self::TEST_PASS);

        $result = $this->api->saveLdapConfig(json_encode(array(
            'use_ldap_for_authentication' => 0,
        )));

        $this->assertSame('success', $result['result']);
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

    public function testSaveServersInfoSucceedsWithoutPasswordConfirmationWhenDisabledForLdapUsers()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 0;
        $this->addLdapUser(self::TEST_LOGIN, self::TEST_PASS);
        $this->setSuperUserAccess(self::TEST_LOGIN, true);
        $this->setCurrentUser(self::TEST_LOGIN, self::TEST_PASS);

        $result = $this->api->saveServersInfo(json_encode($this->getServerPayload()));

        $this->assertSame('success', $result['result']);
    }

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
