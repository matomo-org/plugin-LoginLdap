<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\LoginLdap\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\AuthResult;
use Piwik\Config;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\LoginLdap\Auth\WebServerAuth;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
use Piwik\Plugins\UsersManager\Model as UserModel;

/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_WebServerAuthAccessSyncTest
 */
class WebServerAuthAccessSyncTest extends TestCase
{
    private const LOGIN = 'ironman';

    /**
     * The user's superuser_access column, as access synchronization leaves it.
     *
     * @var int
     */
    private $superUserAccessInDb = 1;

    /**
     * @var array
     */
    private $configBackup;

    public function setUp(): void
    {
        parent::setUp();

        $this->configBackup = Config::getInstance()->LoginLdap;
        Config::getInstance()->LoginLdap = array(
            'use_webserver_auth' => 1,
            'strip_domain_from_web_auth' => 0,
        );

        $_SERVER['REMOTE_USER'] = self::LOGIN;
    }

    public function tearDown(): void
    {
        unset($_SERVER['REMOTE_USER']);
        Config::getInstance()->LoginLdap = $this->configBackup;

        parent::tearDown();
    }

    /**
     * Access synchronization runs after the user row has been read, so the result has to be built from the
     * row as synchronization left it rather than the one read before it.
     */
    public function test_authenticate_IsNotSuperUser_IfAccessSynchronizationJustRevokedIt()
    {
        $auth = $this->makeAuth($accessSynchronizationLeaves = 0);

        $result = $auth->authenticate();

        $this->assertEquals(0, $this->superUserAccessInDb);
        $this->assertEquals(AuthResult::SUCCESS, $result->getCode());
        $this->assertFalse($result->hasSuperUserAccess());
    }

    public function test_authenticate_IsSuperUser_IfAccessSynchronizationKeptIt()
    {
        $auth = $this->makeAuth($accessSynchronizationLeaves = 1);

        $result = $auth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $result->getCode());
        $this->assertTrue($result->hasSuperUserAccess());
    }

    /**
     * @param int $accessSynchronizationLeaves the superuser_access the LDAP attributes map to now
     */
    private function makeAuth($accessSynchronizationLeaves)
    {
        $this->superUserAccessInDb = 1;

        $auth = new WebServerAuth($this->createMock(LoggerInterface::class));

        $usersModel = $this->getMockBuilder(UserModel::class)
                           ->onlyMethods(array('getUser', 'generateRandomTokenAuth'))
                           ->getMock();
        $usersModel->method('getUser')->willReturnCallback(function () {
            return array('login' => self::LOGIN, 'superuser_access' => $this->superUserAccessInDb);
        });
        $usersModel->method('generateRandomTokenAuth')->willReturn('atoken');
        $auth->setUsersModel($usersModel);

        $ldapUsers = $this->getMockBuilder(LdapUsers::class)
                          ->disableOriginalConstructor()
                          ->onlyMethods(array('getUser'))
                          ->getMock();
        $ldapUsers->method('getUser')->willReturn(array('uid' => array(self::LOGIN)));
        $auth->setLdapUsers($ldapUsers);

        $synchronizer = $this->getMockBuilder(UserSynchronizer::class)
                             ->onlyMethods(array('synchronizeLdapUser', 'synchronizePiwikAccessFromLdap'))
                             ->getMock();

        // identity synchronization returns the row as it stands before access is synchronized
        $synchronizer->method('synchronizeLdapUser')->willReturnCallback(function () use ($usersModel) {
            return $usersModel->getUser(self::LOGIN);
        });
        $synchronizer->method('synchronizePiwikAccessFromLdap')
                     ->willReturnCallback(function () use ($accessSynchronizationLeaves) {
                         $this->superUserAccessInDb = $accessSynchronizationLeaves;
                     });
        $auth->setUserSynchronizer($synchronizer);

        return $auth;
    }
}
