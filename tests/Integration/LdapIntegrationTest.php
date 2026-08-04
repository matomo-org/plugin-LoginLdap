<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\Log\Logger;
use Piwik\Access;
use Piwik\Auth\Password;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Config;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\LoginLdap\Ldap\LdapFunctions;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\UsersManager\UserUpdater;
use Piwik\Tests\Framework\Fixture;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

require_once PIWIK_INCLUDE_PATH . '/plugins/LoginLdap/tests/Mocks/LdapFunctions.php';

abstract class LdapIntegrationTest extends IntegrationTestCase
{
    public const SERVER_HOST_NAME = 'localhost';
    public const SERVER_PORT = 389;
    public const SERVER_BASE_DN = "dc=avengers,dc=shield,dc=org";
    public const GROUP_NAME = 'cn=avengers,dc=avengers,dc=shield,dc=org';

    public const TEST_LOGIN = 'ironman';
    public const TEST_PASS = 'piedpiper';
    public const TEST_PASS_LDAP = '{MD5}Dv6yiT/W4FvaM5gBdqHwlQ==';

    public const TEST_LOGIN2 = 'ironman2';

    public const OTHER_TEST_LOGIN = 'blackwidow';
    public const OTHER_TEST_PASS = 'redledger';

    public const TEST_SUPERUSER_LOGIN = 'captainamerica';
    public const TEST_SUPERUSER_PASS = 'thaifood';

    public const NON_LDAP_USER = 'stan';
    public const NON_LDAP_PASS = 'whereisthefourthwall?';

    public const NON_LDAP_NORMAL_USER = 'amber';
    public const NON_LDAP_NORMAL_PASS = 'crossingthefourthwall';

    public function setUp(): void
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
            'admin_pass' => 'secrets',
            'start_tls' => false
        );

        LdapFunctions::$phpUnitMock = null;

        // create sites referenced in setup_ldap.sh
        Fixture::createWebsite('2013-01-01 00:00:00');
        Fixture::createWebsite('2013-01-01 00:00:00');
        Fixture::createWebsite('2013-01-01 00:00:00');

        Fixture::loadAllTranslations();
    }

    public function tearDown(): void
    {
        Fixture::resetTranslations();

        parent::tearDown();
    }

    /**
     * Creates an app specific token as an unauthenticated request (providing the user's
     * credentials). The configured global authentication object is left untouched so it can
     * still verify those credentials, only the current access identity is reset.
     */
    protected function createAppSpecificTokenAuthAsAnonymous(string $login, string $password, string $description): string
    {
        // Create the token as if from an unauthenticated request: only the current access
        // identity is switched to anonymous for the call and restored afterwards. The
        // configured global authentication object is left untouched so it can still verify
        // the given credentials.
        $access = Access::getInstance();
        $loginProperty = new \ReflectionProperty(Access::class, 'login');
        $loginProperty->setAccessible(true);
        $previousLogin = $loginProperty->getValue($access);

        $loginProperty->setValue($access, 'anonymous');

        try {
            return UsersManagerAPI::getInstance()->createAppSpecificTokenAuth($login, $password, $description);
        } finally {
            $loginProperty->setValue($access, $previousLogin);
        }
    }

    protected function addPreexistingSuperUser()
    {
        UsersManagerAPI::getInstance()->addUser(self::TEST_SUPERUSER_LOGIN, self::TEST_SUPERUSER_PASS, 'srodgers@aol.com');
        $this->setSuperUserAccess(self::TEST_SUPERUSER_LOGIN, true);

        $auth = StaticContainer::get('Piwik\Auth');
        $auth->setLogin(self::TEST_SUPERUSER_LOGIN);
        $auth->setPassword(self::TEST_SUPERUSER_PASS);
        Access::getInstance()->setSuperUserAccess(false);
        Access::getInstance()->reloadAccess(StaticContainer::get('Piwik\Auth'));
    }

    protected function addNonLdapUsers()
    {
        UsersManagerAPI::getInstance()->addUser(self::NON_LDAP_USER, self::NON_LDAP_PASS, 'whatever@aol.com');
        $this->setSuperUserAccess(self::NON_LDAP_USER, true);
        UsersManagerAPI::getInstance()->addUser(self::NON_LDAP_NORMAL_USER, self::NON_LDAP_NORMAL_PASS, 'witchy@sdhs.edu');
    }

    protected function getUser($login)
    {
        return Db::fetchRow("SELECT login, password, email FROM " . Common::prefixTable('user') . " WHERE login = ?", array($login));
    }

    protected function assertStarkSynchronized($expectedDomain = 'starkindustries.com')
    {
        $user = $this->getUser(self::TEST_LOGIN);
        $this->assertNotEmpty($user);
        $this->assertPasswordIsRandomPlaceholder($user['password']);
        unset($user['password']);
        $this->assertEquals(array(
            'login' => self::TEST_LOGIN,
            'email' => 'billionairephilanthropistplayboy@' . $expectedDomain,
        ), $user);
        $userMapper = new UserMapper();
        $this->assertTrue($userMapper->isUserLdapUser(self::TEST_LOGIN));
    }

    /**
     * A synchronized LDAP user's Matomo password column holds an unguessable placeholder. It must
     * not be derivable from the LDAP userPassword attribute: Matomo treats the column as a real
     * password hash, so a derivable value would let anyone holding that attribute log in as the
     * user, without the LDAP side checks (ldap_user_filter, required_member_of, account state)
     * ever running.
     */
    protected function assertPasswordIsRandomPlaceholder($storedPasswordHash)
    {
        $passwordHelper = new Password();
        $this->assertFalse($passwordHelper->verify(md5(self::TEST_PASS_LDAP), $storedPasswordHash));
        $this->assertFalse($passwordHelper->verify(md5(self::TEST_PASS), $storedPasswordHash));
    }

    protected function assertRomanovSynchronized($expectedDomain)
    {
        $user = $this->getUser('blackwidow');
        $this->assertNotEmpty($user);
        unset($user['password']);
        $this->assertEquals(array(
            'login' => 'blackwidow',
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
            LoggerInterface::class => \Piwik\DI::get(Logger::class),
            'log.level' => Logger::DEBUG,
        );
    }

    protected function setSuperUserAccess($user, $hasAccess)
    {
        $userUpdater = new UserUpdater();
        if (method_exists($userUpdater, 'setSuperUserAccessWithoutCurrentPassword')) {
            $userUpdater->setSuperUserAccessWithoutCurrentPassword($user, $hasAccess);
        } else {
            UsersManagerAPI::getInstance()->setSuperUserAccess($user, $hasAccess);
        }
    }
}
