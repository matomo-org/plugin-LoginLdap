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
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Config;
use Piwik\Plugins\LoginLdap\Ldap\LdapFunctions;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Tests\Framework\Fixture;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

require_once PIWIK_INCLUDE_PATH . '/plugins/LoginLdap/tests/Mocks/LdapFunctions.php';

abstract class LdapIntegrationTest extends IntegrationTestCase
{
    const SERVER_HOST_NAME = 'localhost';
    const SERVER_PORT = 389;
    const SERVER_BASE_DN = "dc=avengers,dc=shield,dc=org";
    const GROUP_NAME = 'cn=avengers,dc=avengers,dc=shield,dc=org';

    const TEST_LOGIN = 'ironman';
    const TEST_PASS = 'piedpiper';
    const TEST_PASS_LDAP = '{MD5}Dv6yiT/W4FvaM5gBdqHwlQ==';

    const OTHER_TEST_LOGIN = 'blackwidow';
    const OTHER_TEST_PASS = 'redledger';

    const TEST_SUPERUSER_LOGIN = 'captainamerica';
    const TEST_SUPERUSER_PASS = 'thaifood';

    const NON_LDAP_USER = 'stan';
    const NON_LDAP_PASS = 'whereisthefourthwall?';

    const NON_LDAP_NORMAL_USER = 'amber';
    const NON_LDAP_NORMAL_PASS = 'crossingthefourthwall';

    public function setUp()
    {
        if (empty(getenv('PLUGIN_NAME'))) {
            $this->markTestSkipped('LDAP tests can only be run as plugin tests.');
            return;
        }

        if (!function_exists('ldap_bind')) {
            throw new \Exception("PHP not compiled w/ --with-ldap!");
        }

        if (!$this->isLdapServerRunning()) {
            throw new \Exception("LDAP server not found on port localhost:389. For integration tests, an LDAP server must be running with the "
                               . "data and configuration found in tests/travis/setup_ldap.sh script. An OpenLDAP server is expected, but any "
                               . "will work assuming the attributes names & data are the same.");
        }

        parent::setUp();

        Config::getInstance()->LoginLdap = Config::getInstance()->LoginLdapTest + array(
            'servers' => 'testserver',
            'use_webserver_auth' => 'false',
            'new_user_default_sites_view_access' => '1,2',
            'synchronize_users_after_login' => 1
        );

        Config::getInstance()->LoginLdap_testserver = Config::getInstance()->LoginLdap_testserver + array(
            'hostname' => self::SERVER_HOST_NAME,
            'port' => self::SERVER_PORT,
            'base_dn' => self::SERVER_BASE_DN,
            'admin_user' => 'cn=fury,' . self::SERVER_BASE_DN,
            'admin_pass' => 'secrets'
        );

        LdapFunctions::$phpUnitMock = null;

        // create sites referenced in setup_ldap.sh
        Fixture::createWebsite('2013-01-01 00:00:00');
        Fixture::createWebsite('2013-01-01 00:00:00');
        Fixture::createWebsite('2013-01-01 00:00:00');
    }

    protected function addPreexistingSuperUser()
    {
        UsersManagerAPI::getInstance()->addUser(self::TEST_SUPERUSER_LOGIN, self::TEST_SUPERUSER_PASS, 'srodgers@aol.com', $alias = false);
        UsersManagerAPI::getInstance()->setSuperUserAccess(self::TEST_SUPERUSER_LOGIN, true);

        $auth = StaticContainer::get('Piwik\Auth');
        $auth->setLogin(self::TEST_SUPERUSER_LOGIN);
        $auth->setPassword(self::TEST_SUPERUSER_PASS);
        Access::getInstance()->setSuperUserAccess(false);
        Access::getInstance()->reloadAccess(StaticContainer::get('Piwik\Auth'));
    }

    protected function addNonLdapUsers()
    {
        UsersManagerAPI::getInstance()->addUser(self::NON_LDAP_USER, self::NON_LDAP_PASS, 'whatever@aol.com', $alias = false);
        UsersManagerAPI::getInstance()->setSuperUserAccess(self::NON_LDAP_USER, true);
        UsersManagerAPI::getInstance()->addUser(self::NON_LDAP_NORMAL_USER, self::NON_LDAP_NORMAL_PASS, 'witchy@sdhs.edu', $alias = false);
    }

    protected function getUser($login)
    {
        return Db::fetchRow("SELECT login, password, alias, email, token_auth FROM " . Common::prefixTable('user') . " WHERE login = ?", array($login));
    }

    protected function assertStarkSynchronized($expectedDomain = 'starkindustries.com')
    {
        $user = $this->getUser(self::TEST_LOGIN);
        $this->assertNotEmpty($user);
        $passwordHelper = new Password();
        $this->assertTrue($passwordHelper->verify(md5(self::TEST_PASS_LDAP), $user['password']));
        unset($user['password']);
        $this->assertEquals(array(
            'login' => self::TEST_LOGIN,
            'alias' => 'Tony Stark',
            'email' => 'billionairephilanthropistplayboy@' . $expectedDomain,
            'token_auth' => UsersManagerAPI::getInstance()->getTokenAuth(self::TEST_LOGIN, md5(self::TEST_PASS_LDAP))
        ), $user);
        $userMapper = new UserMapper();
        $this->assertTrue($userMapper->isUserLdapUser(self::TEST_LOGIN));
    }

    protected function assertRomanovSynchronized($expectedDomain)
    {
        $user = $this->getUser('blackwidow');
        $this->assertNotEmpty($user);
        unset($user['password']);
        unset($user['token_auth']);
        $this->assertEquals(array(
            'login' => 'blackwidow',
            'alias' => 'Natalia Romanova',
            'email' => 'blackwidow@' . $expectedDomain,
        ), $user);
        $userMapper = new UserMapper();
        $this->assertTrue($userMapper->isUserLdapUser('blackwidow'));
    }

    private function isLdapServerRunning()
    {
        $fp = @fsockopen(self::SERVER_HOST_NAME, self::SERVER_PORT, $errno, $errstr, 5);
        if (empty($fp)) {
            return false;
        } else {
            fclose($fp);
            return true;
        }
    }

    public function provideContainerConfig()
    {
        return array(
            'Psr\Log\LoggerInterface' => \DI\get('Monolog\Logger'),
        );
    }
}