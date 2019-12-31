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
use Piwik\Auth\Password;
use Piwik\Config;
use Piwik\Db;
use Piwik\Common;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\LoginLdap\Auth\LdapAuth;
use Piwik\SettingsPiwik;
use Piwik\Tests\Framework\Fixture;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_LdapUserSynchronizationTest
 */
class LdapUserSynchronizationTest extends LdapIntegrationTest
{
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

        $superusers = $this->getSuperUsers();
        $this->assertEquals(array(), $superusers);

        $access = $this->getAccessFor(self::TEST_LOGIN); // access added due to new_user_default_sites_view_access option
        $this->assertEquals(array(
            array('site' => '1', 'access' => 'view'),
            array('site' => '2', 'access' => 'view')
        ), $access);
    }

    public function test_PiwikUserIsCreated_WithEmailSuffixed_ButNotUser_IfShouldAppendEmailIsOff()
    {
        Config::getInstance()->LoginLdap['append_user_email_suffix_to_username'] = 0;
        Config::getInstance()->LoginLdap['user_email_suffix'] = '@matthers.com';

        $this->authenticateViaLdap('blackwidow', 'redledger');

        $this->assertRomanovSynchronized('matthers.com');
    }

    public function test_PiwikUserIsCreatedWithAccessToAllSites_WhenLdapLoginSucceeds_AndDefaultSitesToAddIsAll()
    {
        Config::getInstance()->LoginLdap['new_user_default_sites_view_access'] = 'all';

        $this->authenticateViaLdap();

        $this->assertStarkSynchronized();

        $access = $this->getAccessFor(self::TEST_LOGIN); // access added due to new_user_default_sites_view_access option
        $this->assertEquals(array(
            array('site' => '1', 'access' => 'view'),
            array('site' => '2', 'access' => 'view'),
            array('site' => '3', 'access' => 'view'),
            array('site' => '4', 'access' => 'view'),
            array('site' => '5', 'access' => 'view'),
            array('site' => '6', 'access' => 'view')
        ), $access);
    }

    public function test_PiwikUserIsNotCreated_IfPiwikUserAlreadyExists()
    {
        Access::getInstance()->setSuperUserAccess(true);
        UsersManagerAPI::getInstance()->addUser(self::TEST_LOGIN, self::TEST_PASS, 'billionairephilanthropistplayboy@starkindustries.com', $alias = false);
        Access::getInstance()->setSuperUserAccess(false);

        $this->authenticateViaLdap();

        $user = Db::fetchRow("SELECT login, password, alias, email, token_auth FROM " . Common::prefixTable('user') . " WHERE login = ?", array(self::TEST_LOGIN));
        $this->assertNotEmpty($user);
        $passwordHelper = new Password();
        $this->assertTrue($passwordHelper->verify(md5(self::TEST_PASS), $user['password']));
        unset($user['password']);
        $this->assertEquals(array(
            'login' => self::TEST_LOGIN,
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
                LdapUserSynchronizationTest::TEST_LOGIN, LdapUserSynchronizationTest::TEST_PASS_LDAP, 'something@domain.com', $alias = false, $isPasswordHashed = false);
        });

        $userMapper = new UserMapper();
        $userMapper->markUserAsLdapUser(self::TEST_LOGIN);

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
                LdapUserSynchronizationTest::TEST_LOGIN, md5('anypass'), 'something@domain.com', $alias = false, $isPasswordHashed = true);
            UsersManagerAPI::getInstance()->setUserAccess(LdapUserSynchronizationTest::TEST_LOGIN, 'view', array(4,5));
            UsersManagerAPI::getInstance()->setUserAccess(LdapUserSynchronizationTest::TEST_LOGIN, 'admin', array(6));
        });

        $this->authenticateViaLdap();

        $this->assertStarkAccessSynchronized();
    }

    public function test_AdminAndViewAccessSynchronized_WhenLdapAccessInfoPresent_AndInstanceNameUsed()
    {
        Config::getInstance()->LoginLdap['instance_name'] = 'myPiwik';

        $this->enableAccessSynchronization();

        $this->authenticateViaLdap('blackwidow', 'redledger');

        $access = $this->getAccessFor('blackwidow');
        $this->assertEquals(array(
            array('site' => '1', 'access' => 'view'),
            array('site' => '2', 'access' => 'view'),
            array('site' => '3', 'access' => 'admin'),
            array('site' => '4', 'access' => 'admin'),
        ), $access);
    }

    public function test_SuperUserAccessSynchronized_WhenLdapAccessInfoPresent_AndInstanceNameUsed_AndUserIsSuperUser()
    {
        Config::getInstance()->LoginLdap['instance_name'] = 'myPiwik';
        $this->enableAccessSynchronization();

        $this->authenticateViaLdap('thor', 'bilgesnipe');

        $superusers = $this->getSuperUsers();
        $this->assertEquals(array('thor'), $superusers);
    }

    public function test_AdminAndViewAccessSynchronized_WhenLdapAccessInfoPresent_AndInstancePiwikUrlUsed()
    {
        $this->setPiwikInstanceUrl('http://localhost/');
        Config::getInstance()->LoginLdap['ldap_superuser_access_field'] = 'isasuperuser'; // disable superuser check so we can check user's normal access

        $this->enableAccessSynchronization();

        $this->authenticateViaLdap('thor', 'bilgesnipe');

        $access = $this->getAccessFor('thor');
        $this->assertEquals(array(
            array('site' => '1', 'access' => 'view'),
            array('site' => '2', 'access' => 'view'),
            array('site' => '3', 'access' => 'admin'),
            array('site' => '4', 'access' => 'admin'),
        ), $access);
    }

    public function test_SuperUserAccessSynchronized_WhenLdapAccessInfoPresent_AndInstancePiwikUrlUsed_AndUserIsSuperUser()
    {
        $this->setPiwikInstanceUrl('http://localhost/');
        $this->enableAccessSynchronization();

        $this->authenticateViaLdap('thor', 'bilgesnipe');

        $superusers = $this->getSuperUsers();
        $this->assertEquals(array('thor'), $superusers);
    }

    public function test_RandomPasswordGenerated()
    {
        $passwordManager = new Password();

        $this->authenticateViaLdap();

        $user = $this->getUser(self::TEST_LOGIN);

        $this->assertTrue($passwordManager->verify(md5(self::TEST_PASS_LDAP), $user['password']));

        // test that password doesn't change after re-synchronizing
        $this->authenticateViaLdap();

        $userAgain = $this->getUser(self::TEST_LOGIN);

        $this->assertTrue($passwordManager->verify(md5(self::TEST_PASS_LDAP), $userAgain['password']));
    }

    public function test_CorrectExistingUserUpdated_WhenUserEmailSuffixUsed()
    {
        Config::getInstance()->LoginLdap['user_email_suffix'] = '@xmansion.org';

        // authenticate via ldap to add the user w/ the email suffix
        $this->authenticateViaLdap($login = 'rogue', $pass = 'cherry');

        $user = $this->getUser('rogue@xmansion.org');

        $this->assertNotEmpty($user);

        // authenticate again to make sure the correct user is updated and we didn't try to add again
        $this->authenticateViaLdap($login = 'rogue', $pass = 'cherry');
    }

    private function assertNoAccessInDb()
    {
        $access = $this->getAccessFor(self::TEST_LOGIN);
        $this->assertEquals(array(), $access);

        $superusers = $this->getSuperUsers();
        $this->assertEquals(array(), $superusers);
    }

    private function getAccessFor($login)
    {
        return Access::doAsSuperUser(function () use ($login) {
            return UsersManagerAPI::getInstance()->getSitesAccessFromUser($login);
        });
    }

    private function getSuperUsers()
    {
        $result = array();
        foreach (Db::fetchAll("SELECT login FROM " . Common::prefixTable('user') . " WHERE superuser_access = 1") as $row) {
            $result[] = $row['login'];
        }
        return $result;
    }

    private function authenticateViaLdap($login = self::TEST_LOGIN, $pass = self::TEST_PASS)
    {
        $ldapAuth = LdapAuth::makeConfigured();
        $ldapAuth->setLogin($login);
        $ldapAuth->setPassword($pass);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());

        return $authResult;
    }

    private function enableAccessSynchronization()
    {
        Config::getInstance()->LoginLdap['enable_synchronize_access_from_ldap'] = 1;
    }

    private function assertStarkAccessSynchronized()
    {
        $access = $this->getAccessFor(self::TEST_LOGIN);

        $this->assertEquals(array(
            array('site' => '1', 'access' => 'view'),
            array('site' => '2', 'access' => 'view'),
            array('site' => '3', 'access' => 'admin')
        ), $access);
    }

    private function setPiwikInstanceUrl($url)
    {
        SettingsPiwik::overwritePiwikUrl($url);
    }
}