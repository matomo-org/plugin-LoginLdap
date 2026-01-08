<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\LoginLdap\tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use Piwik\Access;
use Piwik\Auth\Password;
use Piwik\Config;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
use Piwik\Plugins\UsersManager\Model;
use Piwik\Plugins\UsersManager\UserAccessFilter;
use Piwik\Tests\Framework\Mock\FakeLogger;

/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_UserSynchronizerTest
 */
class UserSynchronizerTest extends TestCase
{
    /**
     * @var UserSynchronizer
     */
    private $userSynchronizer;

    /**
     * @var array
     */
    public $userAccess;

    /**
     * @var array
     */
    public $superUserAccess;

    public function setUp(): void
    {
        $this->userSynchronizer = new UserSynchronizer();
        $this->userSynchronizer->setNewUserDefaultSitesWithViewAccess(array(1,2));
        $this->setUserModelMock($this->getPiwikUserData());
        $this->setUserMapperMock($this->getPiwikUserData());

        $this->userAccess = array();
        $this->superUserAccess = array();
    }

    public function test_makeConfigured_DoesNotThrow_WhenUserMapperCorrectlyConfigured()
    {
        Config::getInstance()->LoginLdap = array(
            'ldap_user_id_field' => 'userIdField',
            'ldap_mail_field' => 'mailField',
            'user_email_suffix' => 'userEmailSuffix',
        );

        $result = UserSynchronizer::makeConfigured();
        $this->assertNotNull($result);
    }

    public function test_synchronizeLdapUser_Throws_IfUserMapperCannotCorrectlyCreatePiwikUser()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('dummy');

        $this->setUserMapperMock($value = null, $throws = true);

        $this->userSynchronizer->synchronizeLdapUser('piwikuser', array());
    }

    public function test_synchronizeLdapUser_ReturnsUserManagerApiResultWithoutPassword()
    {
        $this->setUserManagerApiMock($throws = false);
        $this->setUserModelMock([]);

        $result = $this->userSynchronizer->synchronizeLdapUser('piwikuser', array());

        $this->assertTrue(empty($result['password']), "Password set in synchronizeLdapUser result, it shouldn't be.");
        $this->assertEquals(array(
            array('piwikuser', 'view', array(1,2))
        ), $this->userAccess);
    }

    public function test_synchronizeLdapUser_Throws_IfUserManagerApiThrows()
    {
        $this->expectException(Exception::class);

        $this->setUserManagerApiMock($throwsInAddUser = true, $throwsInUpdateUser = true);
        $this->setUserModelMock([]);

        $this->userSynchronizer->synchronizeLdapUser('piwikuser', array());
    }

    public function test_synchronizeLdapUser_Succeeds_IfUserDoesNotExistInDb()
    {
        $this->setUserManagerApiMock($throws = false);
        $this->setUserModelMock([]);

        $this->userSynchronizer->synchronizeLdapUser('piwikuser', array());

        self::assertTrue(true);
    }

    public function test_synchronizePiwikAccessFromLdap_DoesNotSynchronizeUserAccessOnUpdate_WhenUserAccessMapperNotUsed()
    {
        $this->setUserManagerApiMock($throwsOnAdd = false, $throwsOnUpdate = false, $throwsOnSetAccess = true);

        $this->userSynchronizer->synchronizePiwikAccessFromLdap('piwikuser', array());

        self::assertTrue(true);
    }

    public function test_synchronizePiwikAccessFromLdap_WillSetAccessCorrectly()
    {
        $this->setUserManagerApiMock($throwsOnAdd = false);
        $this->setUserAccessMapperMock(array(
            'superuser' => array(7,8,9),
            'view' => array(1,2,3),
            'admin' => array(4,5,6)
        ));

        $this->userSynchronizer->synchronizePiwikAccessFromLdap('piwikuser', array());

        $this->assertEquals(array(
            array('piwikuser', 'view', array(1,2,3)),
            array('piwikuser', 'admin', array(4,5,6))
        ), $this->userAccess);

        $this->assertEquals(array(
            array('piwikuser', true)
        ), $this->superUserAccess);
    }

    public function test_synchronizePiwikAccessFromLdap_WillNotRemoveAccessWhenAccessEmptyButSyncDisabled()
    {
        $logger = new FakeLogger();
        $userSynchronizer = new UserSynchronizer($logger);
        $this->setUserModelMock($this->getPiwikUserData(), $userSynchronizer);
        $this->setUserMapperMock($this->getPiwikUserData(), $userSynchronizer);
        $this->setUserManagerApiMock($throwsOnAdd = false, false, false, $userSynchronizer);

        // First iteration returns mapping
        $this->setUserAccessMapperMock(array(
            'superuser' => array(7,8,9),
            'view' => array(1,2,3),
            'admin' => array(4,5,6)
        ), $userSynchronizer);
        $userSynchronizer->synchronizePiwikAccessFromLdap('piwikuser', array());
        // Verify access mapping is present
        $this->assertEquals(array(
            array('piwikuser', 'view', array(1,2,3)),
            array('piwikuser', 'admin', array(4,5,6))
        ), $this->userAccess);
        $this->assertEquals(array(
            array('piwikuser', true)
        ), $this->superUserAccess);

        // Second iteration returns no mapping for the user and since enable_synchronize_access_from_ldap=0, it should do nothing
        $config = Config::getInstance()->LoginLdap;
        $oldValue = $config['enable_synchronize_access_from_ldap'] ?? 0;
        $config['enable_synchronize_access_from_ldap'] = 0;
        Config::getInstance()->LoginLdap = $config;
        $this->setUserAccessMapperMock([], $userSynchronizer);
        $userSynchronizer->synchronizePiwikAccessFromLdap('piwikuser', array());
        $this->assertEmpty($logger->output);
        $config['enable_synchronize_access_from_ldap'] = $oldValue;
        Config::getInstance()->LoginLdap = $config;
    }

    public function test_synchronizePiwikAccessFromLdap_WillRemoveAccessWhenAccessEmptyAndSyncEnabled()
    {
        $logger = new FakeLogger();
        $userSynchronizer = new UserSynchronizer($logger);
        $this->setUserModelMock($this->getPiwikUserData(), $userSynchronizer);
        $this->setUserMapperMock($this->getPiwikUserData(), $userSynchronizer);
        $this->setUserManagerApiMock($throwsOnAdd = false, false, false, $userSynchronizer);

        // First iteration returns mapping
        $this->setUserAccessMapperMock(array(
            'superuser' => array(7,8,9),
            'view' => array(1,2,3),
            'admin' => array(4,5,6)
        ), $userSynchronizer);
        $userSynchronizer->synchronizePiwikAccessFromLdap('piwikuser', array());
        // Verify access mapping is present
        $this->assertEquals(array(
            array('piwikuser', 'view', array(1,2,3)),
            array('piwikuser', 'admin', array(4,5,6))
        ), $this->userAccess);
        $this->assertEquals(array(
            array('piwikuser', true)
        ), $this->superUserAccess);

        // Second iteration returns no mapping for the user and since enable_synchronize_access_from_ldap=1, it should delete access
        $config = Config::getInstance()->LoginLdap;
        $oldValue = $config['enable_synchronize_access_from_ldap'] ?? 0;
        $config['enable_synchronize_access_from_ldap'] = 1;
        Config::getInstance()->LoginLdap = $config;
        $this->setUserAccessMapperMock([], $userSynchronizer);
        $userSynchronizer->synchronizePiwikAccessFromLdap('piwikuser', array());
        $this->assertStringContainsString("UserSynchronizer::synchronizePiwikAccessFromLdap: User 'piwikuser' has no access in LDAP, but access synchronization is enabled. Deleting access if any present.", $logger->output);
        $config['enable_synchronize_access_from_ldap'] = $oldValue;
        Config::getInstance()->LoginLdap = $config;
    }

    public function test_synchronizePiwikAccessFromLdap_Succeeds_IfLdapUserHasNoAccess()
    {
        $this->setUserManagerApiMock($throwsOnAdd = false);
        $this->setUserAccessMapperMock(array());

        $this->userSynchronizer->synchronizePiwikAccessFromLdap('piwikuser', array());
        $this->assertEquals(array(), $this->userAccess);
        $this->assertEquals(array(), $this->superUserAccess);
    }

    private function setUserManagerApiMock($throwsOnAddUser, $throwsOnUpdateUser = false, $throwsOnSetAccess = false, &$userSynchronizer = null)
    {
        $self = $this;
        $model = new Model();

        $mock = $this->getMockBuilder('Piwik\Plugins\UsersManager\API')
                     ->onlyMethods(array('addUser', 'updateUser', 'getUser', 'setUserAccess', 'setSuperUserAccess'))
                     ->setConstructorArgs(array($model, new UserAccessFilter($model, new Access()), new Password()))
                     ->getMock();
        if ($throwsOnAddUser) {
            $mock->expects($this->any())->method('addUser')->willThrowException(new Exception("dummy message"));
        } else {
            $mock->expects($this->any())->method('addUser');
        }
        if ($throwsOnSetAccess) {
            $mock->expects($this->any())->method('setUserAccess')->willThrowException(new Exception("dummy message"));
        } else {
            $mock->expects($this->any())->method('setUserAccess')->willReturnCallback(function ($login, $access, $sites) use ($self) {
                $self->userAccess[] = array($login, $access, $sites);
            });
        }

        // for previous version
        $mock->expects($this->any())->method('setSuperUserAccess')->willReturnCallback(function ($login, $hasSuperUserAccess) use ($self) {
            $self->superUserAccess[] = array($login, $hasSuperUserAccess);
        });

        if ($userSynchronizer) {
            $userSynchronizer->setUsersManagerApi($mock);
        } else {
            $this->userSynchronizer->setUsersManagerApi($mock);
        }

        $mock = $this->getMockBuilder('Piwik\Plugins\UsersManager\UserUpdater')
            ->onlyMethods(array('updateUserWithoutCurrentPassword', 'setSuperUserAccessWithoutCurrentPassword'))
            ->getMock();
        if ($throwsOnUpdateUser) {
            $mock->expects($this->any())->method('updateUserWithoutCurrentPassword')->willThrowException(new Exception("dummy message"));
        } else {
            $mock->expects($this->any())->method('updateUserWithoutCurrentPassword');
        }

        $mock->expects($this->any())->method('setSuperUserAccessWithoutCurrentPassword')->willReturnCallback(function ($login, $hasSuperUserAccess) use ($self) {
            $self->superUserAccess[] = array($login, $hasSuperUserAccess);
        });

        if ($userSynchronizer) {
            $userSynchronizer->setUserUpdater($mock);
        } else {
            $this->userSynchronizer->setUserUpdater($mock);
        }
    }

    private function getPiwikUserData()
    {
        return array(
            'login' => 'piwikuser',
            'password' => 'password',
            'email' => 'email'
        );
    }

    private function setUserMapperMock($value, $throws = false, &$userSynchronizer = null)
    {
        $mock = $this->getMockBuilder('Piwik\Plugins\LoginLdap\LdapInterop\UserMapper')
                     ->onlyMethods(array('createPiwikUserFromLdapUser', 'markUserAsLdapUser'))
                     ->getMock();
        if ($throws) {
            $mock->expects($this->any())->method('createPiwikUserFromLdapUser')->will($this->throwException(new Exception("dummy")));
        } else {
            $mock->expects($this->any())->method('createPiwikUserFromLdapUser')->will($this->returnValue($value));
        }
        if ($userSynchronizer) {
            $userSynchronizer->setUserMapper($mock);
        } else {
            $this->userSynchronizer->setUserMapper($mock);
        }
    }

    private function setUserAccessMapperMock($value, &$userSynchronizer = null)
    {
        $mock = $this->getMockBuilder('Piwik\Plugins\LoginLdap\LdapInterop\UserAccessMapper')
                     ->onlyMethods(array('getPiwikUserAccessForLdapUser'))
                     ->getMock();
        $mock->expects($this->any())->method('getPiwikUserAccessForLdapUser')->will($this->returnValue($value));
        if ($userSynchronizer) {
            $userSynchronizer->setUserAccessMapper($mock);
        } else {
            $this->userSynchronizer->setUserAccessMapper($mock);
        }
    }

    private function setUserModelMock($returnValue, &$userSynchronizer = null)
    {
        $mock = $this->getMockBuilder('Piwik\Plugins\UsersManager\Model')
                     ->onlyMethods(array('getUser', 'deleteUserAccess', 'setSuperUserAccess'))
                     ->getMock();
        $mock->expects($this->any())->method('getUser')->will($this->returnValue($returnValue));

        if ($userSynchronizer) {
            $userSynchronizer->setUserModel($mock);
        } else {
            $this->userSynchronizer->setUserModel($mock);
        }
    }
}
