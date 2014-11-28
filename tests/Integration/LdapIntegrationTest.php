<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\Common;
use Piwik\Db;
use Piwik\Log;
use Piwik\Config;
use Piwik\Plugins\LoginLdap\Ldap\LdapFunctions;
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

    const OTHER_TEST_LOGIN = 'blackwidow';
    const OTHER_TEST_PASS = 'redledger';

    const TEST_SUPERUSER_LOGIN = 'captainamerica';
    const TEST_SUPERUSER_PASS = 'thaifood';

    const NON_LDAP_USER = 'stan';
    const NON_LDAP_PASS = 'whereisthefourthwall?';

    const NON_LDAP_NORMAL_USER = 'amber';
    const NON_LDAP_NORMAL_PASS = 'crossingthefourthwall';

    const LDAP_ADDED_PASS = '{LDAP}e40511e34ec2ee1cc75d42c926';

    public function setUp()
    {
        if (!function_exists('ldap_bind')) {
            throw new \Exception("PHP not compiled w/ --with-ldap!");
        }

        if (!$this->isLdapServerRunning()) {
            throw new \Exception("LDAP server not found on port localhost:389. For integration tests, an LDAP server must be running with the "
                               . "data and configuration found in tests/travis/setup_ldap.sh script. An OpenLDAP server is expected, but any "
                               . "will work assuming the attributes names & data are the same.");
        }

        parent::setUp();

        Log::info("Setting up " . get_class($this));

        // make sure logging logic is executed so we can test whether there are bugs in the logging code
        Log::getInstance()->setLogLevel(Log::DEBUG);

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

    public function tearDown()
    {
        Log::info("Tearing down " . get_class($this));

        Log::unsetInstance();

        parent::tearDown();
    }

    protected function addPreexistingSuperUser()
    {
        UsersManagerAPI::getInstance()->addUser(self::TEST_SUPERUSER_LOGIN, self::TEST_SUPERUSER_PASS, 'srodgers@aol.com', $alias = false);
        UsersManagerAPI::getInstance()->setSuperUserAccess(self::TEST_SUPERUSER_LOGIN, true);
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

    protected function assertStarkSynchronized()
    {
        $user = $this->getUser(self::TEST_LOGIN);
        $this->assertNotEmpty($user);
        $this->assertEquals(array(
            'login' => self::TEST_LOGIN,
            'password' => self::LDAP_ADDED_PASS,
            'alias' => 'Tony Stark',
            'email' => 'billionairephilanthropistplayboy@starkindustries.com',
            'token_auth' => UsersManagerAPI::getInstance()->getTokenAuth(self::TEST_LOGIN, self::LDAP_ADDED_PASS)
        ), $user);
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
}