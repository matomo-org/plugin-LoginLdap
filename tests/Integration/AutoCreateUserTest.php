<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\Access;
use Piwik\Config;
use Piwik\Db;
use Piwik\Common;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\LoginLdap\LdapAuth;
use Piwik\Tests\Fixture;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_AutoCreateUserTest
 *
 * TODO: rename LdapUserSynchronizationTest
 */
class AutoCreateUserTest extends LdapIntegrationTest
{
    const LDAP_ADDED_PASS = '{LDAP}Dv6yiT/W4FvaM5gBdqHwlQ==--';
    const LDAP_ADDED_PASS_DIFF = "{LDAP}...";

    public function setUp()
    {
        parent::setUp();

        // create extra sites that users won't have access to
        Fixture::createWebsite('2013-01-01 00:00:00');
        Fixture::createWebsite('2013-01-01 00:00:00');
        Fixture::createWebsite('2013-01-01 00:00:00');

        Access::getInstance()->setSuperUserAccess(false);
    }

    public function tearDown()
    {
        Access::getInstance()->setSuperUserAccess(true);

        parent::tearDown();
    }

    public function test_PiwikUserIsCreated_WhenLdapLoginSucceeds_ButPiwikUserDoesntExist()
    {
        $this->authenticateViaLdap();

        $this->assertStarkSynchronized();

        $this->assertNoAccessInDb();
    }

    public function test_PiwikUserIsNotCreated_IfPiwikUserAlreadyExists()
    {
        Access::getInstance()->setSuperUserAccess(true);
        UsersManagerAPI::getInstance()->addUser(self::TEST_LOGIN, self::TEST_PASS, 'billionairephilanthropistplayboy@starkindustries.com', $alias = false);
        Access::getInstance()->setSuperUserAccess(false);

        $this->authenticateViaLdap();

        $user = Db::fetchRow("SELECT login, password, alias, email, token_auth FROM " . Common::prefixTable('user') . " WHERE login = ?", array(self::TEST_LOGIN));
        $this->assertNotEmpty($user);
        $this->assertEquals(array(
            'login' => self::TEST_LOGIN,
            'password' => md5(self::TEST_PASS),
            'alias' => self::TEST_LOGIN,
            'email' => 'billionairephilanthropistplayboy@starkindustries.com',
            'token_auth' => UsersManagerAPI::getInstance()->getTokenAuth(self::TEST_LOGIN, md5(self::TEST_PASS))
        ), $user);

        $this->assertNoAccessInDb();
    }

    public function test_PiwikUserIsUpdated_IfLdapUserAlreadySynchronized_ButLdapUserInfoIsDifferent()
    {
        Access::doAsSuperUser(function () {
            UsersManagerAPI::getInstance()->addUser(
                AutoCreateUserTest::TEST_LOGIN, AutoCreateUserTest::LDAP_ADDED_PASS_DIFF, '', $alias = false, $isPasswordHashed = true);
        });

        $this->authenticateViaLdap();

        $this->assertStarkSynchronized();
    }

    public function test_LdapSuperUserHasSuperUserAccess_WhenUserIsSynchronized()
    {
        $this->enableAccessSynchronization();

        $this->authenticateViaLdap(self::TEST_SUPERUSER_LOGIN, self::TEST_SUPERUSER_PASS);

        $superusers = $this->getSuperUsers();
        $this->assertEquals(array(self::TEST_SUPERUSER_LOGIN), $superusers);
    }

    public function test_AdminAndViewAccessAddedForLdapUser_WhenLdapUserHasAccess_AndUserIsSynchronizedForFirstTime()
    {
        $this->enableAccessSynchronization();

        $this->authenticateViaLdap();

        $this->assertStarkAccessSynchronized();
    }

    public function test_AdminAndViewAccessUpdated_WhenLdapUserALreadySynchronized_ButLdapAccessInfoIsDifferent()
    {
        $this->enableAccessSynchronization();

        Access::doAsSuperUser(function () {
            UsersManagerAPI::getInstance()->addUser(
                AutoCreateUserTest::TEST_LOGIN, AutoCreateUserTest::LDAP_ADDED_PASS_DIFF, '', $alias = false, $isPasswordHashed = true);
            UsersManagerAPI::getInstance()->setUserAccess(AutoCreateUserTest::TEST_LOGIN, 'view', array(4,5));
            UsersManagerAPI::getInstance()->setUserAccess(AutoCreateUserTest::TEST_LOGIN, 'admin', array(6));
        });

        $this->authenticateViaLdap();

        $this->assertStarkAccessSynchronized();
    }

    private function assertNoAccessInDb()
    {
        $access = $this->getAllAccess();
        $this->assertEmpty($access);

        $superusers = $this->getSuperUsers();
        $this->assertEmpty($superusers);
    }

    private function getAllAccess()
    {
        return Db::fetchAll("SELECT * FROM " . Common::prefixTable('access'));
    }

    private function getSuperUsers()
    {
        return Db::fetchAll("SELECT login FROM " . Common::prefixTable('user') . " WHERE superuser_access = 1");
    }

    private function authenticateViaLdap($login = self::TEST_LOGIN, $pass = self::TEST_PASS)
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin($login);
        $ldapAuth->setPassword($pass);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());

        return $authResult;
    }

    private function assertStarkSynchronized()
    {
        $user = Db::fetchRow("SELECT login, password, alias, email, token_auth FROM " . Common::prefixTable('user') . " WHERE login = ?", array(self::TEST_LOGIN));
        $this->assertNotEmpty($user);
        $this->assertEquals(array(
            'login' => self::TEST_LOGIN,
            'password' => self::LDAP_ADDED_PASS,
            'alias' => 'Tony Stark',
            'email' => 'billionairephilanthropistplayboy@starkindustries.com',
            'token_auth' => UsersManagerAPI::getInstance()->getTokenAuth(self::TEST_LOGIN, self::LDAP_ADDED_PASS)
        ), $user);
    }

    private function enableAccessSynchronization()
    {
        Config::getInstance()->LoginLdap['enable_synchronize_access_from_ldap'] = 1;
    }

    private function assertStarkAccessSynchronized()
    {
        $access = Access::doAsSuperUser(function () {
            return UsersManagerAPI::getInstance()->getSitesAccessFromUser(AutoCreateUserTest::TEST_LOGIN);
        });

        $this->assertEquals(array(
            '1' => 'view',
            '2' => 'view',
            '3' => 'admin'
        ), $access);
    }
}