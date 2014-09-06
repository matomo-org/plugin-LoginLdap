<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\Config;
use Piwik\Log;
use Piwik\Db;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\LoginLdap\LdapAuth;
use DatabaseTestCase;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_AutoCreateUserTest
 */
class AutoCreateUserTest extends LdapIntegrationTest
{
    public function setUp() // TODO: there is code redundancy w/ this and ConnectionTest. should move common code to base type
    {
        parent::setUp();

        Piwik::setUserHasSuperUserAccess(false);
    }

    public function tearDown()
    {
        Piwik::setUserHasSuperUserAccess(true);

        parent::tearDown();
    }

    public function testPiwikUserIsCreatedWhenLdapLoginSucceedsButPiwikUserDoesntExist()
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());

        $addedPass = md5('{MD5}Dv6yiT/W4FvaM5gBdqHw');

        $user = Db::fetchRow("SELECT login, password, alias, email, token_auth FROM " . Common::prefixTable('user') . " WHERE login = ?", array(self::TEST_LOGIN));
        $this->assertNotEmpty($user);
        $this->assertEquals(array(
            'login' => self::TEST_LOGIN,
            'password' => $addedPass,
            'alias' => 'Tony Stark',
            'email' => 'billionairephilanthropistplayboy@starkindustries.com',
            'token_auth' => UsersManagerAPI::getInstance()->getTokenAuth(self::TEST_LOGIN, $addedPass)
        ), $user);
    }

    public function testPiwikUserIsNotCreatedIfPiwikUserAlreadyExists()
    {
        Piwik::setUserHasSuperUserAccess(true);
        UsersManagerAPI::getInstance()->addUser(self::TEST_LOGIN, self::TEST_PASS, 'billionairephilanthropistplayboy@starkindustries.com', $alias = false);
        Piwik::setUserHasSuperUserAccess(false);

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());

        $user = Db::fetchRow("SELECT login, password, alias, email, token_auth FROM " . Common::prefixTable('user') . " WHERE login = ?", array(self::TEST_LOGIN));
        $this->assertNotEmpty($user);
        $this->assertEquals(array(
            'login' => self::TEST_LOGIN,
            'password' => md5(self::TEST_PASS),
            'alias' => self::TEST_LOGIN,
            'email' => 'billionairephilanthropistplayboy@starkindustries.com',
            'token_auth' => UsersManagerAPI::getInstance()->getTokenAuth(self::TEST_LOGIN, md5(self::TEST_PASS))
        ), $user);
    }
}