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
 * @group LoginLdap_WebServerAuthLoginResolutionTest
 */
class WebServerAuthLoginResolutionTest extends TestCase
{
    private const LDAP_LOGIN = 'ironman';

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
    }

    public function tearDown(): void
    {
        unset($_SERVER['REMOTE_USER']);
        Config::getInstance()->LoginLdap = $this->configBackup;

        parent::tearDown();
    }

    /**
     * The Matomo user does not exist yet, so synchronization creates it from the LDAP entry's own casing.
     * Every later request asserts whatever the web server has, which has to keep resolving to that user.
     */
    public function test_authenticate_Succeeds_OnEveryRequest_IfTheUserWasProvisionedFromADifferentlyCasedLogin()
    {
        $_SERVER['REMOTE_USER'] = strtoupper(self::LDAP_LOGIN);

        $provisioning = $this->makeAuth($existingUser = array());
        $this->assertEquals(AuthResult::SUCCESS, $provisioning->authenticate()->getCode());

        $laterRequest = $this->makeAuth($this->makeUserRow());
        $result = $laterRequest->authenticate();

        $this->assertEquals(AuthResult::SUCCESS, $result->getCode());
        $this->assertEquals(self::LDAP_LOGIN, $result->getIdentity());
    }

    public function test_authenticate_Succeeds_IfAssertedLoginDiffersFromTheStoredOneOnlyByAsciiCase()
    {
        $_SERVER['REMOTE_USER'] = 'IronMan';

        $result = $this->makeAuth($this->makeUserRow())->authenticate();

        $this->assertEquals(AuthResult::SUCCESS, $result->getCode());
        $this->assertEquals(self::LDAP_LOGIN, $result->getIdentity());
    }

    /**
     * @dataProvider getLoginsWithSurroundingWhitespace
     */
    public function test_authenticate_Succeeds_IfTheAssertedLoginHasSurroundingWhitespace($remoteUser)
    {
        $_SERVER['REMOTE_USER'] = $remoteUser;

        $result = $this->makeAuth($this->makeUserRow())->authenticate();

        $this->assertEquals(AuthResult::SUCCESS, $result->getCode());
        $this->assertEquals(self::LDAP_LOGIN, $result->getIdentity());
    }

    public function getLoginsWithSurroundingWhitespace()
    {
        return array(
            'trailing' => array(self::LDAP_LOGIN . ' '),
            'leading' => array(' ' . self::LDAP_LOGIN),
        );
    }

    /**
     * @dataProvider getLoginsResolvingToADifferentUser
     */
    public function test_authenticate_Fails_IfAssertedLoginResolvesToADifferentUser($remoteUser)
    {
        $_SERVER['REMOTE_USER'] = $remoteUser;

        $result = $this->makeAuth($this->makeUserRow())->authenticate();

        $this->assertEquals(AuthResult::FAILURE, $result->getCode());
    }

    public function getLoginsResolvingToADifferentUser()
    {
        // the login column's collation returns the stored user for each of these, but they are not it
        return array(
            'accented character' => array("ironm\xc3\xa1n"),
            'kelvin sign' => array("\xe2\x84\xaaironman"),
            'a different user' => array('thanos'),
        );
    }

    private function makeUserRow()
    {
        return array('login' => self::LDAP_LOGIN, 'superuser_access' => 0, 'password' => 'whatever');
    }

    /**
     * @param array $existingUser what UserModel::getUser() returns for the asserted login
     */
    private function makeAuth($existingUser)
    {
        $auth = new WebServerAuth($this->createMock(LoggerInterface::class));

        $usersModel = $this->getMockBuilder(UserModel::class)
                           ->onlyMethods(array('getUser', 'generateRandomTokenAuth'))
                           ->getMock();
        $usersModel->method('getUser')->willReturn($existingUser);
        $usersModel->method('generateRandomTokenAuth')->willReturn('atoken');
        $auth->setUsersModel($usersModel);

        $ldapUsers = $this->getMockBuilder(LdapUsers::class)
                          ->disableOriginalConstructor()
                          ->onlyMethods(array('getUser'))
                          ->getMock();
        $ldapUsers->method('getUser')->willReturn(array('uid' => array(self::LDAP_LOGIN)));
        $auth->setLdapUsers($ldapUsers);

        $synchronizer = $this->getMockBuilder(UserSynchronizer::class)
                             ->onlyMethods(array('synchronizeLdapUser', 'synchronizePiwikAccessFromLdap'))
                             ->getMock();
        $synchronizer->method('synchronizeLdapUser')->willReturn($this->makeUserRow());
        $auth->setUserSynchronizer($synchronizer);

        return $auth;
    }
}
