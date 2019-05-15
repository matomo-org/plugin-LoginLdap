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
use PHPUnit_Framework_TestCase;
use Piwik\Access;
use Piwik\Auth\Password;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
use Piwik\Plugins\UsersManager\Model;
use Piwik\Plugins\UsersManager\UserAccessFilter;
use Piwik\Plugins\UsersManager\UserUpdater;

/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_UserSynchronizerTest
 */
class UserSynchronizerTest extends PHPUnit_Framework_TestCase
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

    public function setUp()
    {
        parent::setUp();

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
            'ldap_last_name_field' => 'lastNameField',
            'ldap_first_name_field' => 'firstNameField',
            'ldap_alias_field' => 'aliasField',
            'ldap_mail_field' => 'mailField',
            'user_email_suffix' => 'userEmailSuffix',
        );

        $result = UserSynchronizer::makeConfigured();
        $this->assertNotNull($result);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage dummy
     */
    public function test_synchronizeLdapUser_Throws_IfUserMapperCannotCorrectlyCreatePiwikUser()
    {
        $this->setUserMapperMock($value = null, $throws = true);

        $this->userSynchronizer->synchronizeLdapUser('piwikuser', array());
    }

    public function test_synchronizeLdapUser_ReturnsUserManagerApiResultWithoutPassword()
    {
        $this->setUserManagerApiMock($throws = false);
        $this->setUserModelMock(null);

        $result = $this->userSynchronizer->synchronizeLdapUser('piwikuser', array());

        $this->assertTrue(empty($result['password']), "Password set in synchronizeLdapUser result, it shouldn't be.");
        $this->assertEquals(array(
            array('piwikuser', 'view', array(1,2))
        ), $this->userAccess);
    }

    /**
     * @expectedException Exception
     */
    public function test_synchronizeLdapUser_Throws_IfUserManagerApiThrows()
    {
        $this->setUserManagerApiMock($throwsInAddUser = true, $throwsInUpdateUser = true);
        $this->setUserModelMock(null);

        $this->userSynchronizer->synchronizeLdapUser('piwikuser', array());
    }

    public function test_synchronizeLdapUser_Succeeds_IfUserDoesNotExistInDb()
    {
        $this->setUserManagerApiMock($throws = false);
        $this->setUserModelMock(null);

        $this->userSynchronizer->synchronizeLdapUser('piwikuser', array());
    }

    public function test_synchronizePiwikAccessFromLdap_DoesNotSynchronizeUserAccessOnUpdate_WhenUserAccessMapperNotUsed()
    {
        $this->setUserManagerApiMock($throwsOnAdd = false, $throwsOnUpdate = false, $throwsOnSetAccess = true);

        $this->userSynchronizer->synchronizePiwikAccessFromLdap('piwikuser', array());
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

    public function test_synchronizePiwikAccessFromLdap_Succeeds_IfLdapUserHasNoAccess()
    {
        $this->setUserManagerApiMock($throwsOnAdd = false);
        $this->setUserAccessMapperMock(array());

        $this->userSynchronizer->synchronizePiwikAccessFromLdap('piwikuser', array());
        $this->assertEquals(array(), $this->userAccess);
        $this->assertEquals(array(), $this->superUserAccess);
    }

    private function setUserManagerApiMock($throwsOnAddUser, $throwsOnUpdateUser = false, $throwsOnSetAccess = false)
    {
        $self = $this;
        $model = new Model();

        $mock = $this->getMockBuilder('Piwik\Plugins\UsersManager\API')
                     ->setMethods(array('addUser', 'updateUser', 'getUser', 'setUserAccess', 'setSuperUserAccess'))
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

        $this->userSynchronizer->setUsersManagerApi($mock);

        $mock = $this->getMockBuilder('Piwik\Plugins\UsersManager\UserUpdater')
            ->setMethods(array('updateUserWithoutCurrentPassword', 'setSuperUserAccessWithoutCurrentPassword'))
            ->getMock();
        if ($throwsOnUpdateUser) {
            $mock->expects($this->any())->method('updateUserWithoutCurrentPassword')->willThrowException(new Exception("dummy message"));
        } else {
            $mock->expects($this->any())->method('updateUserWithoutCurrentPassword');
        }

        $mock->expects($this->any())->method('setSuperUserAccessWithoutCurrentPassword')->willReturnCallback(function ($login, $hasSuperUserAccess) use ($self) {
            $self->superUserAccess[] = array($login, $hasSuperUserAccess);
        });

        $this->userSynchronizer->setUserUpdater($mock);

    }

    private function getPiwikUserData()
    {
        return array(
            'login' => 'piwikuser',
            'password' => 'password',
            'email' => 'email',
            'alias' => 'alias'
        );
    }

    private function setUserMapperMock($value, $throws = false)
    {
        $mock = $this->getMockBuilder('Piwik\Plugins\LoginLdap\LdapInterop\UserMapper')
                     ->setMethods(array('createPiwikUserFromLdapUser', 'markUserAsLdapUser'))
                     ->getMock();
        if ($throws) {
            $mock->expects($this->any())->method('createPiwikUserFromLdapUser')->will($this->throwException(new Exception("dummy")));
        } else {
            $mock->expects($this->any())->method('createPiwikUserFromLdapUser')->will($this->returnValue($value));
        }
        $this->userSynchronizer->setUserMapper($mock);
    }

    private function setUserAccessMapperMock($value)
    {
        $mock = $this->getMockBuilder('Piwik\Plugins\LoginLdap\LdapInterop\UserAccessMapper')
                     ->setMethods( array('getPiwikUserAccessForLdapUser'))
                     ->getMock();
        $mock->expects($this->any())->method('getPiwikUserAccessForLdapUser')->will($this->returnValue($value));
        $this->userSynchronizer->setUserAccessMapper($mock);
    }

    private function setUserModelMock($returnValue)
    {
        $mock = $this->getMockBuilder('Piwik\Plugins\UsersManager\Model')
                     ->setMethods(array('getUser', 'deleteUserAccess', 'setSuperUserAccess'))
                     ->getMock();
        $mock->expects($this->any())->method('getUser')->will($this->returnValue($returnValue));

        $this->userSynchronizer->setUserModel($mock);
    }
}
