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

        $this->assertPasswordIsRandomPlaceholder($result);
        unset($result['password']);

        $this->assertEquals(array(
            'login' => 'martha',
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

        $this->assertPasswordIsRandomPlaceholder($result);
        unset($result['password']);

        $this->assertEquals(array(
            'login' => 'donna',
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

        $this->assertPasswordIsRandomPlaceholder($result);
        unset($result['password']);

        $this->assertEquals(array(
            'login' => 'donna',
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

        $this->assertPasswordIsRandomPlaceholder($result);
    }

    /**
     * The stored password must not be derivable from the LDAP userPassword attribute, otherwise
     * anyone holding that attribute value could use it to log in as the user.
     */
    public function test_createPiwikUserFromLdapUser_DoesNotDerivePasswordFromLdapPassword()
    {
        $ldapUser = array(
            'uid' => 'alice',
            'mail' => 'alice@unit.co.uk',
            'userpassword' => '{SSHA}f5k9BA2C5L7VKqARAYvBCHBkPC3oTqaw'
        );

        $result = $this->userMapper->createPiwikUserFromLdapUser($ldapUser);

        $this->assertPasswordIsRandomPlaceholder($result, '{SSHA}f5k9BA2C5L7VKqARAYvBCHBkPC3oTqaw');

        $again = $this->userMapper->createPiwikUserFromLdapUser($ldapUser);

        $this->assertNotEquals($result['password'], $again['password']);
    }

    public function test_createPiwikUserFromLdapUser_SetsCorrectEmail_WhenUserHasNone()
    {
        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => 'pond',
            'cn' => 'kissogram',
            'userpassword' => 'pass'
        ));

        $this->assertPasswordIsRandomPlaceholder($result);
        unset($result['password']);

        $this->assertEquals(array(
            'login' => 'pond',
            'email' => 'pond@mydomain.com'
        ), $result);

        $this->userMapper->setUserEmailSuffix('@royalleadworthhospital.co.uk');
        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => 'mrpond',
            'cn' => 'not quite Bond',
            'userpassword' => 'pass'
        ));

        $this->assertPasswordIsRandomPlaceholder($result);
        unset($result['password']);

        $this->assertEquals(array(
            'login' => 'mrpond',
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

        $this->assertPasswordIsRandomPlaceholder($result);
        unset($result['password']);

        $this->assertEquals(array(
            'login' => 'harkness',
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

        $this->assertPasswordIsRandomPlaceholder($result);
        unset($result['password']);

        $this->assertEquals(array(
            'login' => 'rose',
            'email' => 'rose@linda.com'
        ), $result);
    }

    public function test_createPiwikUserEntryForLdapUser_UsesExistingPassword()
    {
        Config::getInstance()->LoginLdap['synchronize_users_after_login'] = 0;
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

    public function test_createPiwikUserEntryForLdapUser_UpdatesExistingPassword()
    {
        Config::getInstance()->LoginLdap['synchronize_users_after_login'] = 1;
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

        $this->assertPasswordIsRandomPlaceholder($result);
        $this->assertNotEquals('existingpass', $result['password']);
        unset($result['password']);

        $this->assertEquals(array(
            'login' => 'leela',
            'email' => 'leela@gallifrey.???'
        ), $result);
        Config::getInstance()->LoginLdap['synchronize_users_after_login'] = 0;
    }

    /**
     * Asserts the generated password is an unguessable placeholder of the shape Matomo expects,
     * and not derived from the LDAP password attribute.
     */
    private function assertPasswordIsRandomPlaceholder(array $result, $ldapPassword = 'pass')
    {
        // UsersManager::checkPasswordHash() requires an MD5 shaped hash
        $this->assertSame(32, strlen($result['password']));
        $this->assertTrue(ctype_xdigit($result['password']));

        $this->assertNotEquals(md5($ldapPassword), $result['password']);
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
