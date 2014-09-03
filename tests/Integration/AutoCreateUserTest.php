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
class AutoCreateUserTest extends DatabaseTestCase
{
    const SERVER_HOST_NAME = 'localhost';
    const SERVER_POST = 389;
    const SERVER_BASE_DN = "dc=avengers,dc=shield,dc=org";
    const GROUP_NAME = 'cn=avengers,dc=avengers,dc=shield,dc=org';

    const TEST_LOGIN = 'ironman';
    const TEST_PASS = 'piedpiper';

    public function setUp() // TODO: there is code redundancy w/ this and ConnectionTest. should move common code to base type
    {
        if (!function_exists('ldap_bind')) {
            throw new \Exception("PHP not compiled w/ --with-ldap!");
        }

        parent::setUp();

        // make sure logging logic is executed so we can test whether there are bugs in the logging code
        Log::getInstance()->setLogLevel(Log::VERBOSE);

        Config::getInstance()->LoginLdap = Config::getInstance()->LoginLdapTest + array(
            'serverUrl' => self::SERVER_HOST_NAME,
            'ldapPort' => self::SERVER_POST,
            'baseDn' => self::SERVER_BASE_DN,
            'adminUser' => 'cn=fury,' . self::SERVER_BASE_DN,
            'adminPass' => 'secrets',
            'useKerberos' => 'false', // tests backwards compatibility, old config allowed 'false' as a string
            'autoCreateUser' => 1
        );

        Piwik::setUserHasSuperUserAccess(false);
    }

    public function tearDown()
    {
        Log::unsetInstance();
        Piwik::setUserHasSuperUserAccess(true);
    }

    public function testPiwikUserIsCreatedWhenLdapLoginSucceedsButPiwikUserDoesntExist()
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());

        $addedPass = md5('{SSHA}55twESDhNvJdhDO+oNC');

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

    public function testPiwikUserIsNotCreatedIfAutoCreateUserIsNotEnabled()
    {
        Config::getInstance()->LoginLdap['autoCreateUser'] = 0;

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode()); // should fail because we need normal user info like alias/email

        $user = Db::fetchRow("SELECT login, password, alias, email, token_auth FROM " . Common::prefixTable('user') . " WHERE login = ?", array(self::TEST_LOGIN));
        $this->assertEmpty($user);
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