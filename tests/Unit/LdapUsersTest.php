<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Unit;

use Piwik\Log;
use Piwik\Plugins\LoginLdap\Ldap\ServerInfo;
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

    /**
     * @var LdapUsers
     */
    private $ldapUsers = null;

    public function setUp()
    {
        // make sure logging logic is executed so we can test whether there are bugs in the logging code
        Log::getInstance()->setLogLevel(Log::VERBOSE);

        $this->ldapUsers = new LdapUsers();
        $this->ldapUsers->setLdapServers(array(new ServerInfo("localhost", "basedn")));
    }

    public function tearDown()
    {
        Log::unsetInstance();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testAuthenticateThrowsExceptionIfUsernameEmpty()
    {
        $this->ldapUsers->authenticate(null, self::PASSWORD);
        $this->ldapUsers->authenticate("", self::PASSWORD);
    }

    public function testAuthenticateReturnsNullWhenPasswordIsEmpty()
    {
        $result = $this->ldapUsers->authenticate(self::TEST_USER, null);
        $this->assertNull($result);

        $result = $this->ldapUsers->authenticate(self::TEST_USER, "");
        $this->assertNull($result);
    }

    public function testAuthenticateCreatesOneClientWhenNoExistingClientSupplied()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);
        $mockLdapClient->expects($this->once())->method('connect');

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD);
    }

    public function testAuthenticateFailsWhenUserDoesNotExist()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->method('bind')->will($this->returnValue(true));
        $mockLdapClient->method('fetchAll')->will($this->returnValue(array()));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD);

        $this->assertNull($result);
    }

    public function testAuthenticateFailsWhenUserDoesNotExistAndWebServerAuthUsed()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->method('bind')->will($this->returnValue(true));
        $mockLdapClient->method('fetchAll')->will($this->returnValue(array()));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD, $alreadyAuthenticated = true);

        $this->assertNull($result);
    }

    public function testAuthenticateSucceedsWithoutLdapBindWhenWebServerAuthUsedAndUserExists()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->method('bind')->will($this->returnCallback(function () {
            static $i = 0;

            ++$i;

            if ($i == 1) {
                return true;
            } else {
                return false; // fail binding after first calls
            }
        }));
        $mockLdapClient->method('fetchAll')->will($this->returnValue(array(array('uid' => self::TEST_USER))));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD, $alreadyAuthenticated = true);

        $this->assertNotNull($result);
    }

    public function testAuthenticateSucceedsWhenLdapBindSucceedsAndUserExists()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD, $alreadyAuthenticated = false);

        $this->assertNotNull($result);
    }

    public function testAuthenticateFailsWhenLdapBindFails()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->method('bind')->will($this->returnCallback(function () {
            static $i = 0;

            ++$i;

            if ($i == 1) {
                return true;
            } else {
                return false; // fail binding after first calls
            }
        }));
        $mockLdapClient->method('fetchAll')->will($this->returnValue(array(array('uid' => self::TEST_USER, 'dn' => 'thedn'))));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD, $alreadyAuthenticated = false);

        $this->assertNull($result);
    }

    public function testAuthenticateFailsWhenLdapUserInfoDoesNotHaveDn()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->method('bind')->will($this->returnValue(true));
        $mockLdapClient->method('fetchAll')->will($this->returnValue(array(array('uid' => self::TEST_USER))));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD, $alreadyAuthenticated = false);

        $this->assertNull($result);
    }

    public function testErrorsThrownByLdapClientAreNotPropagatedByAuthenticate()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->method('bind')->will($this->returnCallback(function () {
            throw new \Exception("dummy error");
        }));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD, $alreadyAuthenticated = false);

        $this->assertNull($result);
    }

    public function testAuthenticateAddsUsernameSuffixIfOneIsConfigured()
    {
        $adminUserName = null;
        $userName = null;
        $filterUsed = null;
        $filterBind = null;

        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->method('bind')->will($this->returnCallback(function ($bindResource) use (&$adminUserName, &$userName) {
            static $i = 0;

            if ($i == 0) {
                $adminUserName = $bindResource;
            } else {
                $userName = $bindResource;
            }

            ++$i;

            return true;
        }));
        $mockLdapClient->method('fetchAll')->will($this->returnCallback(function ($baseDn, $filter, $bind) use (&$filterUsed, &$filterBind) {
            $filterUsed = $filter;
            $filterBind = $bind;

            return array(array('uid' => LdapUsersTest::TEST_USER, 'dn' => 'thedn'));
        }));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->ldapUsers->setLdapServers(array(new ServerInfo("localhost", "basedn", 389, self::TEST_ADMIN_USER)));
        $this->ldapUsers->setAuthenticationUsernameSuffix('whoa');
        $this->ldapUsers->authenticate(self::TEST_USER, self::PASSWORD);

        $this->assertEquals(self::TEST_ADMIN_USER . 'whoa', $adminUserName);
        $this->assertEquals('thedn', $userName);
        $this->assertContains("uid=?", $filterUsed);
        $this->assertEquals(array(self::TEST_USER . 'whoa'), $filterBind);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Could not bind as LDAP admin.
     */
    public function testGetUserFailsIfAdminBindFails()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->method('bind')->will($this->returnCallback(function ($bindResource) {
            if ($bindResource == LdapUsersTest::TEST_ADMIN_USER) {
                return false;
            } else {
                return true;
            }
        }));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->ldapUsers->setLdapServers(array(new ServerInfo("localhost", "basedn", 389, self::TEST_ADMIN_USER)));
        $this->ldapUsers->getUser(self::TEST_USER);
    }

    public function testGetUserReturnsNullWhenThereIsNoUser()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->method('bind')->will($this->returnValue(true));
        $mockLdapClient->method('fetchAll')->will($this->returnValue(array()));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->getUser(self::TEST_USER);

        $this->assertNull($result);
    }

    public function testGetUserReturnsTheUserWhenAUserIsFound()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $result = $this->ldapUsers->getUser(self::TEST_USER);

        $this->assertEquals(array('uid' => self::TEST_USER, 'otherval' => 34, 'dn' => 'thedn'), $result);
    }

    public function testGetUserWillUseCorrectLDAPFilterAndBaseDn()
    {
        $usedBaseDn = null;
        $usedFilter = null;
        $filterBind = null;

        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->method('bind')->will($this->returnValue(true));
        $mockLdapClient->method('fetchAll')->will($this->returnCallback(function ($baseDn, $filter, $bind) use (&$usedBaseDn, &$usedFilter, &$filterBind) {
            $usedBaseDn = $baseDn;
            $usedFilter = $filter;
            $filterBind = $bind;

            return array(array('uid' => LdapUsersTest::TEST_USER));
        }));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->ldapUsers->setLdapServers(array(new ServerInfo("localhost", self::TEST_BASE_DN, 389, self::TEST_ADMIN_USER)));
        $this->ldapUsers->setAuthenticationLdapFilter(self::TEST_EXTRA_FILTER);
        $this->ldapUsers->setAuthenticationRequiredMemberOf(self::TEST_MEMBER_OF);
        $this->ldapUsers->getUser(self::TEST_USER);

        $this->assertEquals($usedBaseDn, self::TEST_BASE_DN);
        $this->assertContains(self::TEST_EXTRA_FILTER, $usedFilter);
        $this->assertContains('memberof=?', $usedFilter);
        $this->assertContains(self::TEST_MEMBER_OF, $filterBind);
    }

    public function testGetUserCreatesOneClientWhenNoExistingClientSupplied()
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
    public function testGetUserPropagatesExceptionsThrownByLdapClient()
    {
        $mockLdapClient = $this->makeMockLdapClient();
        $mockLdapClient->method('bind')->will($this->returnCallback(function () {
            throw new \Exception("dummy error");
        }));

        $this->ldapUsers->setLdapClientClass($mockLdapClient);
        $this->ldapUsers->getUser(self::TEST_USER);
    }

    public function testCreatePiwikUserEntryForLdapUserCreatesCorrectPiwikUser()
    {
        $result = $this->ldapUsers->createPiwikUserEntryForLdapUser(array(
            'uid' => 'martha',
            'cn' => 'A real doctor',
            'mail' => 'martha@unit.co.uk',
            'userpassword' => 'pass',
            'other' => 'sfdklsdjf'
        ));

        $this->assertEquals(array('login' => 'martha', 'password' => 'pass', 'email' => 'martha@unit.co.uk', 'alias' => 'A real doctor'), $result);

        $this->ldapUsers->setLdapAliasField('testfield1');
        $this->ldapUsers->setLdapUserIdField('testfield2');
        $this->ldapUsers->setLdapMailField('testfield3');
        $result = $this->ldapUsers->createPiwikUserEntryForLdapUser(array(
            'testfield1' => 'am i bovvered?',
            'testfield2' => 'donna',
            'testfield3' => 'donna@rstad.com',
            'userpassword' => 'pass',
            'other3' => 'sdlfdsf'
        ));

        $this->assertEquals(array('login' => 'donna', 'password' => 'pass', 'email' => 'donna@rstad.com', 'alias' => 'am i bovvered?'), $result);
    }

    public function testCreatePiwikUserEntryForLdapUserSetsCorrectEmailWhenUserHasNone()
    {
        $result = $this->ldapUsers->createPiwikUserEntryForLdapUser(array(
            'uid' => 'pond',
            'cn' => 'kissogram',
            'userpassword' => 'pass'
        ));

        $this->assertEquals(array('login' => 'pond', 'password' => 'pass', 'email' => 'pond@mydomain.com', 'alias' => 'kissogram'), $result);

        $this->ldapUsers->setAuthenticationUsernameSuffix('@royalleadworthhospital.co.uk');
        $result = $this->ldapUsers->createPiwikUserEntryForLdapUser(array(
            'uid' => 'mrpond',
            'cn' => 'not quite Bond',
            'userpassword' => 'pass'
        ));

        $this->assertEquals(array(
            'login' => 'mrpond',
            'password' => 'pass',
            'email' => 'mrpond@royalleadworthhospital.co.uk',
            'alias' => 'not quite Bond'
        ), $result);
    }

    /**
     * @expectedException Exception
     */
    public function testCreatePiwikUserEntryForLdapUserFailsWhenInfoMissing()
    {
        $this->ldapUsers->createPiwikUserEntryForLdapUser(array('useless' => 'info'));
    }

    public function testDoWithCllientSuccessfullyManagesLdapConnections()
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

    public function testDoWithClientCreatesAClientUsingFirstSuccessfulConnection()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);
        $mockLdapClient->method('connect')->will($this->returnCallback(function () {
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
    public function testDoWithClientPropagatesCallbackExceptions()
    {
        $mockLdapClient = $this->makeMockLdapClient($forSuccess = true);
        $this->ldapUsers->setLdapClientClass($mockLdapClient);

        $serverInfo = new ServerInfo("localhost", 389);
        $this->ldapUsers->setLdapServers(array($serverInfo));

        $this->ldapUsers->doWithClient(function () {
            throw new \Exception("test");
        });
    }

    private function makeMockLdapClient($forSuccess = false)
    {
        $methods = array('__construct', 'connect', 'close', 'bind', 'fetchAll', 'isOpen');

        $mock = $this->getMockBuilder('Piwik\Plugins\LoginLdap\Ldap\Client')
                     ->disableOriginalConstructor()
                     ->setMethods($methods)
                     ->getMock();

        if ($forSuccess) {
            $mock->method('bind')->will($this->returnValue(true));
            $mock->method('fetchAll')->will($this->returnValue(array(array('uid' => self::TEST_USER, 'otherval' => 34, 'dn' => 'thedn'))));
        }

        return $mock;
    }
}