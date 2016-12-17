<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Exception;
use Piwik\Config;
use Piwik\Plugins\LoginLdap\API;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_ApiTest
 */
class ApiTest extends LdapIntegrationTest
{
    /**
     * @var API
     */
    private $api;

    public function setUp()
    {
        parent::setUp();

        $this->api = new API();
    }

    public function test_getCountOfUsersMemberOf_ReturnsZero_WhenNoUsersAreMemberOfGroup()
    {
        $count = $this->api->getCountOfUsersMemberOf("not()hing");
        $this->assertEquals(0, $count);
    }

    public function test_getCountOfUsersMemberOf_ReturnsCorrectResponse_WhenUsersAreMemberOfGroup()
    {
        $count = $this->api->getCountOfUsersMemberOf("cn=avengers," . self::SERVER_BASE_DN);
        $this->assertEquals(4, $count);
    }

    public function test_getCountOfUsersMatchingFilter_ReturnsZero_WhenNoUsersMatchTheFilter()
    {
        $count = $this->api->getCountOfUsersMemberOf("(objectClass=whatever)");
        $this->assertEquals(0, $count);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage LoginLdap_InvalidFilter
     */
    public function test_getCountOfUsersMatchingFilter_Throws_WhenFilterIsInvalid()
    {
        $this->api->getCountOfUsersMatchingFilter("lksjdf()a;sk");
    }

    public function test_getCountOfUsersMatchingFilter_ReturnsCorrectResult_WhenUsersMatchFilter()
    {
        $count = $this->api->getCountOfUsersMatchingFilter("(objectClass=person)");
        $this->assertEquals(6, $count);
    }

    public function test_saveLdapConfig_SavesConfigToINIFile_AndIgnoresInvalidConfigNames()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        Config::getInstance()->LoginLdap['servers'] = array();

        $configToSave = array(
            'use_ldap_for_authentication' => 0,
            'synchronize_users_after_login' => 0,
            'enable_synchronize_access_from_ldap' => 1,
            'new_user_default_sites_view_access' => '10,11,13',
            'servers' => 'abc',
            'nonconfigoption' => 'def'
        );

        $this->api->saveLdapConfig(json_encode($configToSave));

        $ldapConfig = Config::getInstance()->LoginLdap;
        $this->assertEquals(0, $ldapConfig['use_ldap_for_authentication']);
        $this->assertEquals(0, $ldapConfig['synchronize_users_after_login']);
        $this->assertEquals(1, $ldapConfig['enable_synchronize_access_from_ldap']);
        $this->assertEquals('10,11,13', $ldapConfig['new_user_default_sites_view_access']);
        $this->assertTrue(empty($ldapConfig['servers']));
        $this->assertTrue(empty($ldapConfig['nonconfigoption']));
    }

    public function test_saveServersInfo_SavesConfigToINIFile_AndIgnoresInvalidServerInfo()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $serverInfos = array(
            array(
                'name' => 'server1',
                'hostname' => 'ahost.com',
                'port' => 389,
                'base_dn' => 'somedn'
            ),
            array(
                'invaliddata' => 'sdfjklsdj',
                'name' => 'server2',
                'hostname' => 'thehost.com',
                'port' => 456,
                'base_dn' => 'thedn',
                'admin_user' => 'admin',
                'admin_pass' => 'pass'
            ),
        );

        $this->api->saveServersInfo(json_encode($serverInfos));

        $this->assertEquals(array(
            'hostname' => 'ahost.com',
            'port' => 389,
            'base_dn' => 'somedn',
            'admin_user' => null,
            'admin_pass' => null
        ), Config::getInstance()->LoginLdap_server1);

        $this->assertEquals(array(
            'hostname' => 'thehost.com',
            'port' => 456,
            'base_dn' => 'thedn',
            'admin_user' => 'admin',
            'admin_pass' => 'pass'
        ), Config::getInstance()->LoginLdap_server2);
    }

    public function test_saveServersInfo_DoesNotOverwritePassword_IfPasswordFieldBlank()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        Config::getInstance()->LoginLdap_server2 = array(
            'hostname' => 'thehost.com',
            'port' => 456,
            'base_dn' => 'thedn',
            'admin_user' => 'admin',
            'admin_pass' => 'firstpass'
        );

        $serverInfos = array(
            array(
                'name' => 'server2',
                'hostname' => 'thehost.com',
                'port' => 456,
                'base_dn' => 'thedn',
                'admin_user' => 'admin',
                'admin_pass' => ''
            ),
        );

        $this->api->saveServersInfo(json_encode($serverInfos));

        $this->assertEquals(array(
            'hostname' => 'thehost.com',
            'port' => 456,
            'base_dn' => 'thedn',
            'admin_user' => 'admin',
            'admin_pass' => 'firstpass'
        ), Config::getInstance()->LoginLdap_server2);
    }

    public function test_saveServersInfo_OverwritesPassword_IfPasswordFieldNotBlank()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        Config::getInstance()->LoginLdap_server2 = array(
            'hostname' => 'thehost.com',
            'port' => 456,
            'base_dn' => 'thedn',
            'admin_user' => 'admin',
            'admin_pass' => 'firstpass'
        );

        $serverInfos = array(
            array(
                'name' => 'server2',
                'hostname' => 'thehost.com',
                'port' => 456,
                'base_dn' => 'thedn',
                'admin_user' => 'admin',
                'admin_pass' => 'pass'
            ),
        );

        $this->api->saveServersInfo(json_encode($serverInfos));

        $this->assertEquals(array(
            'hostname' => 'thehost.com',
            'port' => 456,
            'base_dn' => 'thedn',
            'admin_user' => 'admin',
            'admin_pass' => 'pass'
        ), Config::getInstance()->LoginLdap_server2);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage LoginLdap_UserNotFound
     */
    public function test_synchronizeUser_Throws_WhenLdapUserDoesNotExist()
    {
        $this->api->synchronizeUser('unknownuser');
    }

    public function test_synchronizeUser_Succeeds_WhenLdapUserExistsAndIsValid()
    {
        $this->api->synchronizeUser(self::TEST_LOGIN);
        $this->assertStarkSynchronized();
    }
}