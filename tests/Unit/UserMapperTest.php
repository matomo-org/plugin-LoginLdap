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
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;

/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_UserMapperTest
 */
class UserMapperTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var UserMapper
     */
    private $userMapper;

    public function setUp()
    {
        parent::setUp();

        Config::unsetInstance();
        Config::getInstance()->setTestEnvironment();

        $this->userMapper = new UserMapper();
    }

    public function tearDown()
    {
        parent::tearDown();

        Config::unsetInstance();
    }

    public function test_makeConfigured_CreatesCorrectUserMapper_WhenAllConfigOptionsSupplied()
    {
        Config::getInstance()->LoginLdap = array(
            'ldap_user_id_field' => 'userIdField',
            'ldap_last_name_field' => 'lastNameField',
            'ldap_first_name_field' => 'firstNameField',
            'ldap_alias_field' => 'aliasField',
            'ldap_mail_field' => 'mailField',
            'user_email_suffix' => 'userEmailSuffix',
        );

        $userMapper = UserMapper::makeConfigured();

        $this->assertUserMapperIsCorrectlyConfigured($userMapper);
    }

    public function test_makeConfigured_CreatesCorrectUserMapper_WhenOldConfigNamesUsed()
    {
        Config::getInstance()->LoginLdap = array(
            'userIdField' => 'userIdField',
            'ldap_last_name_field' => 'lastNameField',
            'ldap_first_name_field' => 'firstNameField',
            'aliasField' => 'aliasField',
            'mailField' => 'mailField',
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
            'givenName' => 'Martha',
            'mail' => 'martha@unit.co.uk',
            'userpassword' => 'pass',
            'other' => 'sfdklsdjf'
        ));

        $this->assertEquals(array(
            'login' => 'martha',
            'password' => '{LDAP}1a1dc91c907325c69271ddf0c9',
            'email' => 'martha@unit.co.uk',
            'alias' => 'A real doctor'
        ), $result);
    }

    public function test_createPiwikUserFromLdapUser_CreatesCorrectPiwikUser_WhenCustomLdapAttributesAreUsedAndPresent()
    {
        $this->userMapper->setLdapAliasField('testfield1');
        $this->userMapper->setLdapUserIdField('testfield2');
        $this->userMapper->setLdapMailField('testfield3');
        $this->userMapper->setLdapFirstNameField('testfield4');
        $this->userMapper->setLdapLastNameField('testfield5');

        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'testfield1' => 'am i bovvered?',
            'testfield2' => 'donna',
            'testfield3' => 'donna@rstad.com',
            'testfield4' => 'Donna',
            'testfield5' => 'Noble',
            'userpassword' => 'pass',
            'other3' => 'sdlfdsf'
        ));

        $this->assertEquals(array(
            'login' => 'donna',
            'password' => '{LDAP}1a1dc91c907325c69271ddf0c9',
            'email' => 'donna@rstad.com',
            'alias' => 'am i bovvered?'
        ), $result);

        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'testfield2' => 'donna',
            'testfield3' => 'donna@rstad.com',
            'testfield4' => 'Donna',
            'testfield5' => 'Noble',
            'userpassword' => 'pass',
            'other3' => 'sdlfdsf'
        ));

        $this->assertEquals(array(
            'login' => 'donna',
            'password' => '{LDAP}1a1dc91c907325c69271ddf0c9',
            'email' => 'donna@rstad.com',
            'alias' => 'Donna Noble'
        ), $result);
    }

    /**
     * @expectedException Exception
     */
    public function test_createPiwikUserFromLdapUser_FailsToCreatePiwikUser_WhenUIDAttributeIsMissing()
    {
        $this->userMapper->createPiwikUserFromLdapUser(array(
            'cn' => 'the impossible girl',
            'sn' => 'Oswald',
            'givenName' => 'Clara',
            'mail' => 'clara@coalhill.co.uk',
            'userpassword' => 'pass'
        ));
    }

    public function test_createPiwikUserFromLdapUser_CreatesPiwikUser_WhenAliasAndNamesAreMissing()
    {
        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => 'clara',
            'mail' => 'clara@coalhill.co.uk',
            'userpassword' => 'pass'
        ));

        $this->assertEmpty($result['alias']);
    }

    /**
     * @expectedException Exception
     */
    public function test_createPiwikUserFromLdapUser_FailsToCreatePiwikUser_WhenUserPasswordIsMissing()
    {
        $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => 'clara',
            'sn' => 'Oswald',
            'givenName' => 'Clara',
            'mail' => 'clara@coalhill.co.uk'
        ));
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
            'password' => '{LDAP}1a1dc91c907325c69271ddf0c9',
            'email' => 'pond@mydomain.com',
            'alias' => 'kissogram'
        ), $result);

        $this->userMapper->setUserEmailSuffix('@royalleadworthhospital.co.uk');
        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => 'mrpond',
            'cn' => 'not quite Bond',
            'userpassword' => 'pass'
        ));

        $this->assertEquals(array(
            'login' => 'mrpond',
            'password' => '{LDAP}1a1dc91c907325c69271ddf0c9',
            'email' => 'mrpond@royalleadworthhospital.co.uk',
            'alias' => 'not quite Bond'
        ), $result);
    }

    public function test_createPiwikUserEntryForLdapUser_SetsCorrectAlias_WhenUserHasFirstAndLastName()
    {
        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => 'harkness',
            'sn' => 'Harkness',
            'givenName' => 'Captain',
            'userpassword' => 'pass',
            'other' => 'sfdklsdjf'
        ));

        $this->assertEquals(array(
            'login' => 'harkness',
            'password' => '{LDAP}1a1dc91c907325c69271ddf0c9',
            'email' => 'harkness@mydomain.com',
            'alias' => 'Captain Harkness'
        ), $result);
    }

    public function test_createPiwikUserEntryForLdapUser_CreatesCorrectPiwikUser_IfLdapUserInfoIsAnArray()
    {
        $result = $this->userMapper->createPiwikUserFromLdapUser(array(
            'uid' => array('rose'),
            'cn' => array('bad wolf'),
            'sn' => array('Tyler'),
            'givenName' => array('Rose'),
            'mail' => array('rose@linda.com'),
            'userpassword' => array('pass'),
            'other' => array('sfdklsdjf)')
        ));

        $this->assertEquals(array(
            'login' => 'rose',
            'password' => '{LDAP}1a1dc91c907325c69271ddf0c9',
            'email' => 'rose@linda.com',
            'alias' => 'bad wolf'
        ), $result);
    }

    public function test_isUserLdapUser_ReportsUserAsLdapUser_IfUserInfoHasSpecialPassword()
    {
        $isLdapUser = UserMapper::isUserLdapUser(array('password' => "{LDAP}..."));
        $this->assertTrue($isLdapUser);
    }

    public function test_isUserLdapUser_ReportsUserAsLdapUser_IfUserInfoHasNormalPasswordHash()
    {
        $isLdapUser = UserMapper::isUserLdapUser(array('password' => "..."));
        $this->assertFalse($isLdapUser);
    }

    private function assertUserMapperIsCorrectlyConfigured(UserMapper $userMapper)
    {
        $this->assertEquals('userIdField', $userMapper->getLdapUserIdField());
        $this->assertEquals('lastNameField', $userMapper->getLdapLastNameField());
        $this->assertEquals('firstNameField', $userMapper->getLdapFirstNameField());
        $this->assertEquals('aliasField', $userMapper->getLdapAliasField());
        $this->assertEquals('mailField', $userMapper->getLdapMailField());
        $this->assertEquals('userEmailSuffix', $userMapper->getUserEmailSuffix());
    }

    private function assertUserMapperHasCorrectDefaultPropertyValues(UserMapper $userMapper)
    {
        $this->assertEquals('uid', $userMapper->getLdapUserIdField());
        $this->assertEquals('sn', $userMapper->getLdapLastNameField());
        $this->assertEquals('givenName', $userMapper->getLdapFirstNameField());
        $this->assertEquals('cn', $userMapper->getLdapAliasField());
        $this->assertEquals('mail', $userMapper->getLdapMailField());
        $this->assertEquals('@mydomain.com', $userMapper->getUserEmailSuffix());
    }
}