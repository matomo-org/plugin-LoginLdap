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
use Piwik\Config;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;

class MockAPI extends UsersManagerAPI
{
    public function __construct() {}
}

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

        Config::unsetInstance();
        Config::getInstance()->setTestEnvironment();

        $this->userSynchronizer = new UserSynchronizer();
        $this->userSynchronizer->setNewUserDefaultSitesWithViewAccess(array(1,2));
        $this->setUserModelMock($this->getPiwikUserData());
        $this->setUserMapperMock($this->getPiwikUserData());

        $this->userAccess = array();
        $this->superUserAccess = array();
    }

    public function tearDown()
    {
        Config::unsetInstance();

        parent::tearDown();
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
        $this->assertNotNUll($result);
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

        $mock = $this->getMock('Piwik\Plugins\LoginLdap\tests\Unit\MockAPI', array(
            'addUser', 'updateUser', 'getUser', 'setUserAccess', 'setSuperUserAccess'));
        if ($throwsOnAddUser) {
            $mock->expects($this->any())->method('addUser')->willThrowException(new Exception("dummy message"));
        } else {
            $mock->expects($this->any())->method('addUser');
        }
        if ($throwsOnUpdateUser) {
            $mock->expects($this->any())->method('updateUser')->willThrowException(new Exception("dummy message"));
        } else {
            $mock->expects($this->any())->method('updateUser');
        }

        if ($throwsOnSetAccess) {
            $mock->expects($this->any())->method('setUserAccess')->willThrowException(new Exception("dummy message"));
        } else {
            $mock->expects($this->any())->method('setUserAccess')->willReturnCallback(function ($login, $access, $sites) use ($self) {
                $self->userAccess[] = array($login, $access, $sites);
            });
        }

        $mock->expects($this->any())->method('setSuperUserAccess')->willReturnCallback(function ($login, $hasSuperUserAccess) use ($self) {
            $self->superUserAccess[] = array($login, $hasSuperUserAccess);
        });

        $this->userSynchronizer->setUsersManagerApi($mock);
    }

    private function getPiwikUserData()
    {
        return array(
            'login' => 'piwikuser',
            'password' => '{LDAP}password',
            'email' => 'email',
            'alias' => 'alias'
        );
    }

    private function setUserMapperMock($value, $throws = false)
    {
        $mock = $this->getMock('Piwik\Plugins\LoginLdap\LdapInterop\UserMapper', array('createPiwikUserFromLdapUser'));
        if ($throws) {
            $mock->expects($this->any())->method('createPiwikUserFromLdapUser')->will($this->throwException(new Exception("dummy")));
        } else {
            $mock->expects($this->any())->method('createPiwikUserFromLdapUser')->will($this->returnValue($value));
        }
        $this->userSynchronizer->setUserMapper($mock);
    }

    private function setUserAccessMapperMock($value)
    {
        $mock = $this->getMock('Piwik\Plugins\LoginLdap\LdapInterop\UserAccessMapper', array('getPiwikUserAccessForLdapUser'));
        $mock->expects($this->any())->method('getPiwikUserAccessForLdapUser')->will($this->returnValue($value));
        $this->userSynchronizer->setUserAccessMapper($mock);
    }

    private function setUserModelMock($returnValue)
    {
        $mock = $this->getMock('Piwik\Plugins\UsersManager\Model', array('getUser', 'deleteUserAccess'));
        $mock->expects($this->any())->method('getUser')->will($this->returnValue($returnValue));

        $this->userSynchronizer->setUserModel($mock);
    }
}