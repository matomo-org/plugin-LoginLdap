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
use Piwik\Config;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;

/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_UserMapperTest
 */
class UserMapperTest extends TestCase
{
    /**
     * @var UserMapper
     */
    private $userMapper;

    public function setUp(): void
    {
        parent::setUp();

        $this->userMapper = new UserMapper();
    }

    public function test_makeConfigured_CreatesCorrectUserMapper_WhenAllConfigOptionsSupplied()
    {
        Config::getInstance()->LoginLdap = array(
            'ldap_user_id_field' => 'userIdField',
            'ldap_mail_field' => 'mailField',
            'ldap_password_field' => 'passwordField',
            'user_email_suffix' => 'userEmailSuffix',
        );

        $userMapper = UserMapper::makeConfigured();

        $this->assertUserMapperIsCorrectlyConfigured($userMapper);
    }

    public function test_makeConfigured_CreatesCorrectUserMapper_WhenOldConfigNamesUsed()
    {
        Config::getInstance()->LoginLdap = array(
            'userIdField' => 'userIdField',
            'aliasField' => 'aliasField',
            'mailField' => 'mailField',
            'ldap_password_field' => 'passwordField',
            'usernameSuffix' => 'userEmailSuffix',
        );

        $userMapper = UserMapper::makeConfigured();

        $this->assertUserMapperIsCorrectlyConfigured($userMapper);
    }

    public function test_makeConfigured_UsesCorrectDefaultValues()
    {
        Config::getInstance()->LoginLdap = array();

        $userMapper = UserMapper::makeConfigured();

        $this->assertUserMapperHasCorrectDefaultPropertyValues($userMapper);
    }

    public function test_createPiwikUserFromLdapUser_CreatesCorrectPiwikUser_WhenAllLdapUserFieldsArePresent()
    {
        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => 'martha',
            'cn' => 'A real doctor',
            'sn' => 'Jones',
            'givenname' => 'Martha',
            'mail' => 'martha@unit.co.uk',
            'userpassword' => 'pass',
            'other' => 'sfdklsdjf'
        ));

        $this->assertEquals(array(
            'login' => 'martha',
            'password' => md5('pass'),
            'email' => 'martha@unit.co.uk',
        ), $result);
    }

    public function test_createPiwikUserFromLdapUser_CreatesCorrectPiwikUser_WhenCustomLdapAttributesAreUsedAndPresent()
    {
        $this->userMapper->setLdapUserIdField('testfield2');
        $this->userMapper->setLdapMailField('testfield3');
        $this->userMapper->setLdapUserPasswordField('testfield6');

        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'testfield1' => 'am i bovvered?',
            'testfield2' => 'donna',
            'testfield3' => 'donna@rstad.com',
            'testfield4' => 'Donna',
            'testfield5' => 'Noble',
            'testfield6' => 'pass',
            'other3' => 'sdlfdsf'
        ));

        $this->assertEquals(array(
            'login' => 'donna',
            'password' => md5('pass'),
            'email' => 'donna@rstad.com'
        ), $result);

        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'testfield2' => 'donna',
            'testfield3' => 'donna@rstad.com',
            'testfield4' => 'Donna',
            'testfield5' => 'Noble',
            'testfield6' => 'pass',
            'other3' => 'sdlfdsf'
        ));

        $this->assertEquals(array(
            'login' => 'donna',
            'password' => md5('pass'),
            'email' => 'donna@rstad.com'
        ), $result);
    }

    public function test_createPiwikUserFromLdapUser_FailsToCreatePiwikUser_WhenUIDAttributeIsMissing()
    {
        $this->expectException(Exception::class);

        $this->userMapper->createPiwikUserFromLdapUser(array(
            'cn' => 'the impossible girl',
            'sn' => 'Oswald',
            'givenname' => 'Clara',
            'mail' => 'clara@coalhill.co.uk',
            'userpassword' => 'pass'
        ));
    }

    public function test_createPiwikUserFromLdapUser_CreatesPiwikUserWithRandomPassword_WhenUserPasswordIsMissing()
    {
        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => 'clara',
            'sn' => 'Oswald',
            'givenname' => 'Clara',
            'mail' => 'clara@coalhill.co.uk'
        ));

        $this->assertNotEmpty($result['password']);
    }

    public function test_createPiwikUserFromLdapUser_SetsCorrectEmail_WhenUserHasNone()
    {
        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => 'pond',
            'cn' => 'kissogram',
            'userpassword' => 'pass'
        ));

        $this->assertEquals(array(
            'login' => 'pond',
            'password' => md5('pass'),
            'email' => 'pond@mydomain.com'
        ), $result);

        $this->userMapper->setUserEmailSuffix('@royalleadworthhospital.co.uk');
        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => 'mrpond',
            'cn' => 'not quite Bond',
            'userpassword' => 'pass'
        ));

        $this->assertEquals(array(
            'login' => 'mrpond',
            'password' => md5('pass'),
            'email' => 'mrpond@royalleadworthhospital.co.uk'
        ), $result);
    }

    public function test_createPiwikUserEntryForLdapUser_SetsCorrectAlias_WhenUserHasFirstAndLastName()
    {
        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => 'harkness',
            'sn' => 'Harkness',
            'givenname' => 'Captain',
            'userpassword' => 'pass',
            'other' => 'sfdklsdjf'
        ));

        $this->assertEquals(array(
            'login' => 'harkness',
            'password' => md5('pass'),
            'email' => 'harkness@mydomain.com'
        ), $result);
    }

    public function test_createPiwikUserEntryForLdapUser_CreatesCorrectPiwikUser_IfLdapUserInfoIsAnArray()
    {
        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => array('rose'),
            'cn' => array('bad wolf'),
            'sn' => array('Tyler'),
            'givenname' => array('Rose'),
            'mail' => array('rose@linda.com'),
            'userpassword' => array('pass'),
            'other' => array('sfdklsdjf)')
        ));

        $this->assertEquals(array(
            'login' => 'rose',
            'password' => md5('pass'),
            'email' => 'rose@linda.com'
        ), $result);
    }

    public function test_createPiwikUserEntryForLdapUser_UsesExistingPassword()
    {
        $existingUser = array(
            'login' => 'broken',
            'email' => 'wrongmail',
            'password' => 'existingpass'
        );

        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => 'leela',
            'cn' => 'Leela of the Sevateem',
            'mail' => 'leela@gallifrey.???',
            'userpassword' => 'pass'
        ), $existingUser);

        $this->assertEquals(array(
            'login' => 'leela',
            'password' => 'existingpass',
            'email' => 'leela@gallifrey.???'
        ), $result);
    }

    private function assertUserMapperIsCorrectlyConfigured(UserMapper $userMapper)
    {
        $this->assertEquals('useridfield', $userMapper->getLdapUserIdField());
        $this->assertEquals('mailfield', $userMapper->getLdapMailField());
        $this->assertEquals('passwordfield', $userMapper->getLdapUserPasswordField());
        $this->assertEquals('userEmailSuffix', $userMapper->getUserEmailSuffix());
    }

    private function assertUserMapperHasCorrectDefaultPropertyValues(UserMapper $userMapper)
    {
        $this->assertEquals('uid', $userMapper->getLdapUserIdField());
        $this->assertEquals('mail', $userMapper->getLdapMailField());
        $this->assertEquals('userpassword', $userMapper->getLdapUserPasswordField());
        $this->assertEquals('@mydomain.com', $userMapper->getUserEmailSuffix());
    }
}