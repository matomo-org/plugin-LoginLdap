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
use Piwik\Plugins\Login\PasswordVerifier;
use Piwik\Plugins\LoginLdap\Controller;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;

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

    public function setUp(): void
    {
        parent::setUp();

        \Zend_Session::$_unitTestEnabled = true;

        \Piwik\Config::getInstance()->LoginLdap['enable_password_confirmation'] = 0;
        $this->addNonLdapUsers();
        $this->passwordVerifier = new PasswordVerifier();
        $this->passwordVerifier->setDisableRedirect();
        StaticContainer::getContainer()->set('Piwik\Plugins\Login\PasswordVerifier', $this->passwordVerifier);
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
}
