<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Unit;

use Exception;
use InvalidArgumentException;
use Piwik\Plugins\LoginLdap\Ldap\ServerInfo;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
use PHPUnit_Framework_TestCase;


/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_LdapUsersTest
 */
class LdapUsersTest extends PHPUnit_Framework_TestCase
{
    const TEST_USER = "rose";
    const PASSWORD = "bw";
    const TEST_ADMIN_USER = "who?";
    const TEST_BASE_DN = 'testbasedn';
    const TEST_EXTRA_FILTER = '(testfilter)';
    const TEST_MEMBER_OF = "member";
    const TEST_MEMBER_OF_Field = "memberOf";

    /**
     * @var LdapUsers
     */
    private $ldapUsers = null;

    public function setUp()
    {
        parent::setUp();

        $this->ldapUsers = new LdapUsers();
        $this->ldapUsers->setLdapServers(array(new ServerInfo("localhost", "basedn")));
        $this->ldapUsers->setLdapUserMapper(new UserMapper());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_authenticate_ThrowsException_IfUsernameEmpty()
    {
        $this->ldapUsers->authenticate(null, self::PASSWORD);
        $this->ldapUsers->authenticate("", self::PASSWORD);
    }

    public function test_authenticate_ReturnsNull_WhenPasswordIsEmpty()
    {
        $result = $this->ldapUsers->authenticate(self::TEST_USER, null);
        $this->assertNull($result);

        $result = $this->ldapUsers->authenticate(self::TEST_USER, "");
        $this->assertNull($result);
    }

    public function test_authenticate_CreatesOneClient_WhenNoExistingClientSupplied()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);
        $mockLdapClient->expects($this->once())->method('connect');

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD);
    }

    public function test_authenticate_Fails_WhenUserDoesNotExist()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->expects($this->any())->method('bind')->will($this->returnValue(true));
        $mockLdapClient->expects($this->any())->method('fetchAll')->will($this->returnValue(array()));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD);

        $this->assertNull($result);
    }

    public function test_authenticate_Fails_WhenUserDoesNotExist_AndWebServerAuthUsed()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->expects($this->any())->method('bind')->will($this->returnValue(true));
        $mockLdapClient->expects($this->any())->method('fetchAll')->will($this->returnValue(array()));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD, $alreadyAuthenticated = true);

        $this->assertNull($result);
    }

    public function test_authenticate_SucceedsWithoutLdapBind_WhenWebServerAuthUsed_AndUserExists()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->expects($this->any())->method('bind')->will($this->returnCallback(function () {
            static $i = 0;

            ++$i;

            if ($i == 1) {
                return true;
            } else {
                return false; // fail binding after first calls
            }
        }));
        $mockLdapClient->expects($this->any())->method('fetchAll')->will($this->returnValue(array(array('uid' => self::TEST_USER))));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD, $alreadyAuthenticated = true);

        $this->assertNotNull($result);
    }

    public function test_authenticate_Succeeds_WhenLdapBindSucceedsAndUserExists()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD, $alreadyAuthenticated = false);

        $this->assertNotNull($result);
    }

    public function test_authenticate_Fails_WhenLdapBindFails()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->expects($this->any())->method('bind')->will($this->returnCallback(function () {
            static $i = 0;

            ++$i;

            if ($i == 1) {
                return true;
            } else {
                return false; // fail binding after first calls
            }
        }));
        $mockLdapClient->expects($this->any())->method('fetchAll')->will($this->returnValue(array(array('uid' => self::TEST_USER, 'dn' => 'thedn'))));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD, $alreadyAuthenticated = false);

        $this->assertNull($result);
    }

    public function test_authenticate_Fails_WhenLdapUserInfoDoesNotHaveDn()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->expects($this->any())->method('bind')->will($this->returnValue(true));
        $mockLdapClient->expects($this->any())->method('fetchAll')->will($this->returnValue(array(array('uid' => self::TEST_USER))));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD, $alreadyAuthenticated = false);

        $this->assertNull($result);
    }

    public function test_authenticate_DoesNotPropagateErrors_WhenErrorsThrownByLdapClient()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->expects($this->any())->method('bind')->will($this->returnCallback(function () {
            throw new \Exception("dummy error");
        }));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD, $alreadyAuthenticated = false);

        $this->assertNull($result);
    }

    public function test_authenticate_AddsUsernameSuffix_IfOneIsConfigured()
    {
        $adminUserName = null;
        $userName = null;
        $filterUsed = null;
        $filterBind = null;

        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->expects($this->any())->method('bind')->will($this->returnCallback(function ($bindResource) use (&$adminUserName, &$userName) {
            static $i = 0;

            if ($i == 0) {
                $adminUserName = $bindResource;
            } else {
                $userName = $bindResource;
            }

            ++$i;

            return true;
        }));
        $mockLdapClient->expects($this->any())->method('fetchAll')->will($this->returnCallback(function ($baseDn, $filter, $bind) use (&$filterUsed, &$filterBind) {
            $filterUsed = $filter;
            $filterBind = $bind;

            return array(array('uid' => LdapUsersTest::TEST_USER, 'dn' => 'thedn'));
        }));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->setSingleLdapServer();
        $this->ldapUsers->setAuthenticationUsernameSuffix('whoa');
        $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD);

        $this->assertEquals(self::TEST_ADMIN_USER, $adminUserName);
        $this->assertEquals('thedn', $userName);
        $this->assertContains("uid=?", $filterUsed);
        $this->assertEquals(array(self::TEST_USER . 'whoa'), $filterBind);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Could not bind as LDAP admin.
     */
    public function test_getUser_Throws_IfAdminBindFails()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $this->makeMockLdapClientFailOnBind($mockLdapClient);

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->setSingleLdapServer();
        $this->ldapUsers->getUser(self::TEST_USER);
    }

    public function test_getUser_ReturnsNull_WhenThereIsNoUser()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->expects($this->any())->method('bind')->will($this->returnValue(true));
        $mockLdapClient->expects($this->any())->method('fetchAll')->will($this->returnValue(array()));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->getUser(self::TEST_USER);

        $this->assertNull($result);
    }

    public function test_getUser_ReturnsTheUser_WhenAUserIsFound()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->getUser(self::TEST_USER);

        $this->assertEquals(array('uid' => self::TEST_USER, 'otherval' => 34, 'dn' => 'thedn'), $result);
    }

    public function test_getUser_UsesCorrectLDAPFilterAndBaseDn()
    {
        $usedBaseDn = null;
        $usedFilter = null;
        $filterBind = null;

        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->expects($this->any())->method('bind')->will($this->returnValue(true));
        $mockLdapClient->expects($this->any())->method('fetchAll')->will($this->returnCallback(function ($baseDn, $filter, $bind) use (&$usedBaseDn, &$usedFilter, &$filterBind) {
            $usedBaseDn = $baseDn;
            $usedFilter = $filter;
            $filterBind = $bind;

            return array(array('uid' => LdapUsersTest::TEST_USER));
        }));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->setSingleLdapServer();
        $this->ldapUsers->setAuthenticationLdapFilter(self::TEST_EXTRA_FILTER);
        $this->ldapUsers->setAuthenticationRequiredMemberOf(self::TEST_MEMBER_OF);
        $this->ldapUsers->setAuthenticationMemberOfField(self::TEST_MEMBER_OF_Field);
        $this->ldapUsers->getUser(self::TEST_USER);

        $this->assertEquals(self::TEST_BASE_DN, $usedBaseDn);
        $this->assertContains(self::TEST_EXTRA_FILTER, $usedFilter);
        $this->assertContains("(".self::TEST_MEMBER_OF_Field."=?)", $usedFilter);
        $this->assertContains(self::TEST_MEMBER_OF, $filterBind);
    }

    public function test_getUser_CreatesOneClient_WhenNoExistingClientSupplied()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);
        $mockLdapClient->expects($this->once())->method('connect');

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->ldapUsers->getUser(self::TEST_USER);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage dummy error
     */
    public function test_getUser_ThrowsException_WhenLdapClientThrows()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $this->makeMockLdapClientThrowOnBind($mockLdapClient);

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->ldapUsers->getUser(self::TEST_USER);
    }

    public function test_doWithCllient_CallsCallbackCorrectly_WhenFirstServerConnects()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);
        $mockLdapClient->expects($this->once())->method('connect');

        $this->ldapUsers->setLdapClientClass($mockLdapClient);

        $serverInfo = new ServerInfo("localhost", 389);
        $this->ldapUsers->setLdapServers(array($serverInfo));

        $passedLdapUsers = null;
        $passedClient = null;
        $passedServerInfo = null;
        $result = $this->ldapUsers->doWithClient(function ($ldapUsers, $client, $serverInfo)
            use (&$passedLdapUsers, &$passedClient, &$passedServerInfo) {

            $passedLdapUsers = $ldapUsers;
            $passedClient = $client;
            $passedServerInfo = $serverInfo;

            return "test result";
        });

        $this->assertEquals("test result", $result);
        $this->assertSame($mockLdapClient, $passedClient);
        $this->assertSame($this->ldapUsers, $passedLdapUsers);
        $this->assertSame($serverInfo, $passedServerInfo);
    }

    public function test_doWithClient_CreatesAClientUsingFirstSuccessfulConnection()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);
        $mockLdapClient->expects($this->any())->method('connect')->will($this->returnCallback(function () {
            static $i = 0;

            ++$i;

            if ($i != 3) {
                throw new \Exception("fail connection");
            } else {
                return;
            }
        }));
        $this->ldapUsers->setLdapClientClass($mockLdapClient);

        $serverInfos = array(
            new ServerInfo("localhost1", 1),
            new ServerInfo("localhost2", 2),
            new ServerInfo("localhost3", 3)
        );
        $this->ldapUsers->setLdapServers($serverInfos);

        $passedLdapUsers = null;
        $passedClient = null;
        $passedServerInfo = null;
        $this->ldapUsers->doWithClient(function ($ldapUsers, $client, $serverInfo)
            use (&$passedLdapUsers, &$passedClient, &$passedServerInfo) {

            $passedLdapUsers = $ldapUsers;
            $passedClient = $client;
            $passedServerInfo = $serverInfo;
        });

        $this->assertSame($mockLdapClient, $passedClient);
        $this->assertSame($this->ldapUsers, $passedLdapUsers);
        $this->assertSame($serverInfos[2], $passedServerInfo);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage test
     */
    public function test_doWithClient_PropagatesCallbackExceptions()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);
        $this->ldapUsers->setLdapClientClass($mockLdapClient);

        $serverInfo = new ServerInfo("localhost", 389);
        $this->ldapUsers->setLdapServers(array($serverInfo));

        $this->ldapUsers->doWithClient(function () {
            throw new Exception("test");
        });
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Could not bind as LDAP admin.
     */
    public function test_getCountOfUsersMatchingFilter_Throws_IfAdminBindFails()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $this->makeMockLdapClientFailOnBind($mockLdapClient);

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->setSingleLdapServer();
        $this->ldapUsers->getCountOfUsersMatchingFilter("dummy filter");
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage dummy error
     */
    public function test_getCountOfUsersMatchingFilter_Throws_IfLdapClientConnectThrows()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $this->makeMockLdapClientThrowOnBind($mockLdapClient);

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->setSingleLdapServer();
        $this->ldapUsers->getCountOfUsersMatchingFilter("dummy filter");
    }

    public function test_getCountOfUsersMatchingFilter_ReturnsLdapEntityCount()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);

        $passedFilter = null;
        $mockLdapClient->expects($this->any())->method('count')->will($this->returnCallback(function ($baseDn, $filter) use (&$passedFilter) {
            $passedFilter = $filter;
            return 10;
        }));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->setSingleLdapServer();
        $count = $this->ldapUsers->getCountOfUsersMatchingFilter("dummy filter");

        $this->assertEquals("dummy filter", $passedFilter);
        $this->assertEquals(10, $count);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Could not bind as LDAP admin.
     */
    public function test_getAllUserLogins_Throws_IfAdminBindFails()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $this->makeMockLdapClientFailOnBind($mockLdapClient);

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->setSingleLdapServer();
        $this->ldapUsers->getAllUserLogins();
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage dummy error
     */
    public function test_getAllUserLogins_Throws_IfLdapClientConnectThrows()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $this->makeMockLdapClientThrowOnBind($mockLdapClient);

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->setSingleLdapServer();
        $this->ldapUsers->getAllUserLogins();
    }

    public function test_getAllUserLogins_ReturnsLdapEntities()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = false);

        $usedFilter = null;

        $mockLdapClient->expects($this->any())->method('bind')->will($this->returnValue(true));
        $mockLdapClient->expects($this->any())->method('fetchAll')->will($this->returnCallback(function ($baseDn, $filter, $bind) use (&$usedFilter) {
            $usedFilter = $filter;

            return array(array('uid' => LdapUsersTest::TEST_USER), array('uid' => LdapUsersTest::TEST_ADMIN_USER));
        }));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->setSingleLdapServer();

        $logins = $this->ldapUsers->getAllUserLogins();
        $this->assertEquals(array(self::TEST_USER, self::TEST_ADMIN_USER), $logins);
    }

    private function makeMockLdapClient($forSuccess = false)
    {
        $methods = array('__construct', 'connect', 'close', 'bind', 'fetchAll', 'isOpen', 'count');

        $mock = $this->getMockBuilder('Piwik\Plugins\LoginLdap\Ldap\Client')
                     ->disableOriginalConstructor()
                     ->setMethods($methods)
                     ->getMock();

        if ($forSuccess) {
            $mock->expects($this->any())->method('bind')->will($this->returnValue(true));
            $mock->expects($this->any())->method('fetchAll')->will($this->returnValue(array(array('uid' => self::TEST_USER, 'otherval' => 34, 'dn' => 'thedn'))));
        }

        return $mock;
    }

    private function makeMockLdapClientFailOnBind(\PHPUnit_Framework_MockObject_MockObject $mockLdapClient)
    {
        $mockLdapClient->expects($this->any())->method('bind')->will($this->returnCallback(function ($bindResource) {
            if ($bindResource == LdapUsersTest::TEST_ADMIN_USER) {
                return false;
            } else {
                return true;
            }
        }));
    }

    private function makeMockLdapClientThrowOnBind(\PHPUnit_Framework_MockObject_MockObject $mockLdapClient)
    {
        $mockLdapClient->expects($this->any())->method('bind')->will($this->returnCallback(function () {
            throw new Exception("dummy error");
        }));
    }

    private function setSingleLdapServer()
    {
        $this->ldapUsers->setLdapServers(array(new ServerInfo("localhost", self::TEST_BASE_DN, 389, self::TEST_ADMIN_USER)));
    }
}