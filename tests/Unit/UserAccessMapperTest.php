<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Unit;

use PHPUnit_Framework_TestCase;
use Piwik\Config;
use Piwik\Plugins\LoginLdap\LdapInterop\UserAccessAttributeParser;
use Piwik\Plugins\LoginLdap\LdapInterop\UserAccessMapper;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;

/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_UserAccessMapperTest
 */
class UserAccessMapperTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    public function setUp()
    {
        parent::setUp();

        Config::getInstance()->LoginLdap = array();

        $this->setSitesManagerApiMock();

        $this->userAccessMapper = new UserAccessMapper();

        $attributeParser = new UserAccessAttributeParser();
        $attributeParser->setThisPiwikInstanceName('thisPiwik');
        $this->userAccessMapper->setUserAccessAttributeParser($attributeParser);
    }

    public function tearDown()
    {
        SitesManagerAPI::unsetInstance();
    }

    public function test_makeConfigured_CreatesCorrectlyConfiguredInstance_WhenAllConfigSupplied()
    {
        Config::getInstance()->LoginLdap = array(
            'ldap_view_access_field' => 'viewaccessfield',
            'ldap_admin_access_field' => 'adminaccessfield',
            'ldap_superuser_access_field' => 'superuseraccessfield'
        );

        $userAccessMapper = UserAccessMapper::makeConfigured();
        $this->assertEquals('viewaccessfield', $userAccessMapper->getViewAttributeName());
        $this->assertEquals('adminaccessfield', $userAccessMapper->getAdminAttributeName());
        $this->assertEquals('superuseraccessfield', $userAccessMapper->getSuperuserAttributeName());
    }

    public function test_makeConfigured_CreatesCorrectlyConfiguredInstance_WhenNoConfigOptionsPresent()
    {
        $userAccessMapper = UserAccessMapper::makeConfigured();
        $this->assertEquals('view', $userAccessMapper->getViewAttributeName());
        $this->assertEquals('admin', $userAccessMapper->getAdminAttributeName());
        $this->assertEquals('superuser', $userAccessMapper->getSuperuserAttributeName());
    }

    public function test_getPiwikUserAccessForLdapUser_CorrectlyMapsAccess_WhenUserIsSuperUser()
    {
        $access = $this->userAccessMapper->getPiwikUserAccessForLdapUser(array(
            'superuser' => '1'
        ));

        $this->checkSuperUserAccess($access);

        $access = $this->userAccessMapper->getPiwikUserAccessForLdapUser(array(
            'admin' => '1,2,3',
            'view' => '3,4,5',
            'superuser' => '1'
        ));

        $this->checkSuperUserAccess($access);

        $access = $this->userAccessMapper->getPiwikUserAccessForLdapUser(array(
            'superuser' => null
        ));

        $this->checkSuperUserAccess($access);

        $access = $this->userAccessMapper->getPiwikUserAccessForLdapUser(array(
            'superuser' => array()
        ));

        $this->assertEquals(array(), $access);
    }

    public function test_getPiwikUserAccessForLdapUser_CorrectlyMapsAccess_WhenUserHasViewAndAdminAccess()
    {
        $access = $this->userAccessMapper->getPiwikUserAccessForLdapUser(array(
            'view' => '3,4',
            'admin' => '1,2'
        ));

        $expectedAccess = array(
            'admin' => array(1,2),
            'view' => array(3,4)
        );
        $this->assertEquals($expectedAccess, $access);
    }

    public function test_getPiwikUserAccessForLdapUser_UsesHighestAccessLevel_WhenUserHasViewAndAdminAccessForSameSite()
    {
        $access = $this->userAccessMapper->getPiwikUserAccessForLdapUser(array(
            'view' => '1,4',
            'admin' => '1,2'
        ));

        $expectedAccess = array(
            'admin' => array(1,2),
            'view' => array(4)
        );
        $this->assertEquals($expectedAccess, $access);
    }

    public function test_getPiwikUserAccessForLdapUser_CorrectlyMapsAccess_WhenLdapAttributesAreArrays()
    {
        $access = $this->userAccessMapper->getPiwikUserAccessForLdapUser(array(
            'view' => array('3,5,6', 4),
            'admin' => array(1,'2')
        ));

        $expectedAccess = array(
            'admin' => array(1,2),
            'view' => array(3,5,6,4)
        );
        $this->assertEquals($expectedAccess, $access);
    }

    public function test_getPiwikUserAccessForLdapUser_CorrectlyMapsAccess_WhenLdapAttributeIsAllString()
    {
        $access = $this->userAccessMapper->getPiwikUserAccessForLdapUser(array(
            'view' => 'all',
            'admin' => 'all'
        ));

        $expectedAccess = array(
            'admin' => array(1,2,3,4,5,6)
        );
        $this->assertEquals($expectedAccess, $access);
    }

    public function test_getPiwikUserAccessForLdapUser_IgnoresSitesThatDoNotExist()
    {
        $access = $this->userAccessMapper->getPiwikUserAccessForLdapUser(array(
            'view' => array(15,16,'17,18'),
            'admin' => '11,12,13'
        ));

        $expectedAccess = array();
        $this->assertEquals($expectedAccess, $access);
    }

    private function checkSuperUserAccess($access)
    {
        $this->assertEquals(array('superuser' => true), $access);
    }

    private function setSitesManagerApiMock()
    {
        $mock = $this->getMockBuilder('stdClass')
                     ->setMethods(array('getSitesIdWithAtLeastViewAccess', 'getAllSitesId'))
                     ->getMock();
        $mock->expects($this->any())->method('getSitesIdWithAtLeastViewAccess')->willReturn(array(1,2,3,4,5,6));
        $mock->expects($this->any())->method('getAllSitesId')->willReturn(array(1,2,3,4,5,6));
        SitesManagerAPI::setSingletonInstance($mock);
    }
}
