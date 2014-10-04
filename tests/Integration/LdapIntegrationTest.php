<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Integration;

use DatabaseTestCase;
use Piwik\Log;
use Piwik\Config;
use Piwik\Plugins\LoginLdap\Ldap\LdapFunctions;
use Piwik\Tests\Fixture;

abstract class LdapIntegrationTest extends DatabaseTestCase
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

    public function setUp()
    {
        if (!function_exists('ldap_bind')) {
            throw new \Exception("PHP not compiled w/ --with-ldap!");
        }

        parent::setUp();

        // make sure logging logic is executed so we can test whether there are bugs in the logging code
        Log::getInstance()->setLogLevel(Log::VERBOSE);

        Config::getInstance()->LoginLdap = Config::getInstance()->LoginLdapTest + array(
            'servers' => 'testserver',
            'useKerberos' => 'false',
            'new_user_default_sites_view_access' => '1,2'
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
        Log::unsetInstance();

        parent::tearDown();
    }
}