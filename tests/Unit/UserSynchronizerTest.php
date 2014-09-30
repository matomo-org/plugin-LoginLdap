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
    private $userAccess;

    public function setUp()
    {
        parent::setUp();

        Config::unsetInstance();
        Config::getInstance()->setTestEnvironment();

        $this->userSynchronizer = new UserSynchronizer();
        $this->userSynchronizer->setUserModel($this->getUserModelMock($this->getPiwikUserData()));
        $this->userSynchronizer->setNewUserDefaultSitesWithViewAccess(array(1,2));
        $this->setUserMapperMock($this->getPiwikUserData());

        $this->userAccess = array();
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
        $userMapperMock = $this->getMock('Piwik\Plugins\LoginLdap\LdapInterop\UserMapper');
        $userMapperMock->expects($this->any())->method('createPiwikUserFromLdapUser')->will($this->throwException(new Exception("dummy")));
        $this->userSynchronizer->setUserMapper($userMapperMock);

        $this->userSynchronizer->synchronizeLdapUser(array());
    }

    public function test_synchronizeLdapUser_ReturnsUserManagerApiWithoutPassword()
    {
        $this->setUserManagerApiMock($throws = false);
        $this->userSynchronizer->setUserModel($this->getUserModelMock(null));

        $result = $this->userSynchronizer->synchronizeLdapUser(array());

        $this->assertTrue(empty($result['password']), "Password set in synchronizeLdapUser result, it shouldn't be.");
        $this->assertEquals(array('piwikuser', 'view', array(1,2,)), $this->userAccess);
    }

    /**
     * @expectedException Exception
     */
    public function test_synchronizeLdapUser_Throws_IfUserManagerApiThrows()
    {
        $this->setUserManagerApiMock($throwsInAddUser = true, $throwsInUpdateUser = true);
        $this->userSynchronizer->setUserModel($this->getUserModelMock(null));

        $this->userSynchronizer->synchronizeLdapUser(array());
    }

    public function test_synchronizeLdapUser_Succeeds_IfUserDoesNotExistInDb()
    {
        $this->setUserManagerApiMock($throws = false);
        $this->userSynchronizer->setUserModel($this->getUserModelMock(null));

        $this->userSynchronizer->synchronizeLdapUser(array());
    }

    private function setUserManagerApiMock($throwsOnAddUser, $throwsOnUpdateUser = false)
    {
        $mock = $this->getMock('Piwik\Plugins\LoginLdap\tests\Unit\MockAPI', array('addUser', 'updateUser', 'getUser', 'setUserAccess'));
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

        $self = $this;
        $mock->expects($this->any())->method('setUserAccess')->willReturnCallback(function ($login, $access, $sites) use ($self) {
            $self->userAccess = array($login, $access, $sites);
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

    private function getUserModelMock($returnValue)
    {
        $mock = $this->getMock('Piwik\Plugins\UsersManager\Model', array('getUser'));
        $mock->expects($this->any())->method('getUser')->will($this->returnValue($returnValue));
        return $mock;
    }

    private function setUserMapperMock($value)
    {
        $mock = $this->getMock('Piwik\Plugins\LoginLdap\LdapInterop\UserMapper', array('createPiwikUserFromLdapUser'));
        $mock->expects($this->any())->method('createPiwikUserFromLdapUser')->will($this->returnValue($value));
        $this->userSynchronizer->setUserMapper($mock);
    }
}