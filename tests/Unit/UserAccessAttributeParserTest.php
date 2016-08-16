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
use Piwik\Option;
use Piwik\Plugins\LoginLdap\LdapInterop\UserAccessAttributeParser;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Piwik\SettingsPiwik;


/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_UserAccessAttributeParserTest
 */
class UserAccessAttributeParserTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var UserAccessAttributeParser
     */
    private $userAccessAttributeParser;

    public function setUp()
    {
        parent::setUp();

        Config::getInstance()->LoginLdap = array();

        $this->setSitesManagerApiMock();

        $this->userAccessAttributeParser = new UserAccessAttributeParser();
    }

    public function tearDown()
    {
        Option::setSingletonInstance(null);
    }

    public function test_makeConfigured_CreatesCorrectInstance_WhenAllConfigOptionsSpecified()
    {
        Config::getInstance()->LoginLdap = array(
            'user_access_attribute_server_specification_delimiter' => '#',
            'user_access_attribute_server_separator' => '|',
            'instance_name' => 'myPiwik'
        );

        $parser = UserAccessAttributeParser::makeConfigured();

        $this->assertEquals('#', $parser->getServerSpecificationDelimiter());
        $this->assertEquals('|', $parser->getServerIdsSeparator());
        $this->assertEquals('myPiwik', $parser->getThisPiwikInstanceName());
    }

    public function test_makeConfigured_CreatesCorrectInstance_WhenNoConfigOptionsSpecified()
    {
        $parser = UserAccessAttributeParser::makeConfigured();

        $this->assertEquals(';', $parser->getServerSpecificationDelimiter());
        $this->assertEquals(':', $parser->getServerIdsSeparator());
        $this->assertNull($parser->getThisPiwikInstanceName());
    }

    public function getInstanceNamesToTest()
    {
        return array(
            array('myPiwik'),
            array(null)
        );
    }

    public function getInstanceUrlVariationsToTest()
    {
        return array(
            array("https://whatever.com", "whatever.com"),
            array("https://whatever.com", "http://whatever.com"),
            array("https://whatever.com", "https://whatever.com"),
            array("https://whatever.com", "https://whatever.com:80"),
            array("http://whatever.com/what/ever?abc", "whatever.com/what/ever"),
            array("http://whatever.com/what/ever?abc", "https://whatever.com/what/ever"),
            array("http://whatever.com/what/ever?abc", "http://whatever.com/what/ever"),
            array("http://whatever.com/what/ever?abc", "whatever.com/what/ever?def"),
            array("www.whatever.com", "www.whatever.com"),
            array("www.whatever.com", "www.whatever.com?abc#def"),
            array("http://whatever.com:80", "whatever.com"),
            array("http://whatever.com/", "whatever.com"),
            array("http://whatever.com/index.php", "whatever.com/index.php"),
            array("http://whatever.com/index.php", "whatever.com/index.php/"),
        );
    }

    /**
     * @dataProvider getInstanceNamesToTest
     */
    public function test_getSiteIdsFromAccessAttribute_ReturnsCorrectSiteIdList_WhenNoInstanceUsed($instanceName)
    {
        $this->userAccessAttributeParser->setThisPiwikInstanceName($instanceName);

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute("1,2,3");
        $this->assertEquals(array(1,2,3), $ids);

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute("2");
        $this->assertEquals(array(2), $ids);

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute('all');
        $this->assertEquals(array(1,2,3,4,5,6), $ids);
    }

    public function test_getSiteIdsFromAccessAttribute_ReturnsCorrectSiteIdList_WhenDifferentInstanceNamesUsed()
    {
        $this->userAccessAttributeParser->setThisPiwikInstanceName('myPiwik');

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute("myPiwik:1,2;otherPiwik:4;myPiwik:3");
        $this->assertEquals(array(1,2,3), $ids);

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute("  myPiwik  :  1,2 ;  other:3 ");
        $this->assertEquals(array(1,2), $ids);
    }

    public function test_getSiteIdsFromAccessAttribute_ReturnsCorrectSiteIdList_WhenAllStringUsed()
    {
        $this->userAccessAttributeParser->setThisPiwikInstanceName('myPiwik');

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute("otherPiwik:1,2;myPiwik:all;another:3");
        $this->assertEquals(array(1,2,3,4,5,6), $ids);
    }

    /**
     * @dataProvider getInstanceUrlVariationsToTest
     */
    public function test_getSiteIdsFromAccessAttribute_ReturnsCorrectSiteIdList_WhenDifferentInstanceUrlUsed($thisUrl, $instanceId)
    {
        $this->setThisPiwikUrl($thisUrl);
        $this->userAccessAttributeParser->setServerIdsSeparator('|');

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute($instanceId . "|1,2,3");
        $this->assertEquals(array(1,2,3), $ids);
    }

    public function test_getSiteIdsFromAccessAttribute_ReturnsCorrectSiteIdList_WhenAttributeValueIsMalformed_AndMatchingInstanceByUrl()
    {
        $this->setThisPiwikUrl("http://whatever.com/a?b=c");

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute(";;whatever.com/a:1,2,3;*{}@@.co?m:1,2;;");
        $this->assertEquals(array(1,2,3), $ids);

        $this->setThisPiwikUrl("ht??p://@@.com");

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute("whatever.com:1,2,3");
        $this->assertEquals(array(), $ids);

        $this->userAccessAttributeParser->setThisPiwikInstanceName("myPi|wik");

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute(" ; ; myPi|wik : 1,2 ; myPi|wik = view:3,def; myPi | wik:4,5 ; ; ");
        $this->assertEquals(array(1,2,3), $ids);
    }

    public function test_getSiteIdsFromAccessAttribute_ReturnsCorrectSiteIdList_WhenCustomDelimitersAreUsed()
    {
        $this->userAccessAttributeParser->setThisPiwikInstanceName('myPiwik');
        $this->userAccessAttributeParser->setServerSpecificationDelimiter('#');
        $this->userAccessAttributeParser->setServerIdsSeparator('|');

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute("1,2");
        $this->assertEquals(array(1,2), $ids);

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute("wrongPiwik|all#myPiwik|1,2#anotherPiwik|3,4");
        $this->assertEquals(array(1,2), $ids);

        $this->userAccessAttributeParser->setThisPiwikInstanceName(null);

        $this->setThisPiwikUrl("http://whatever.com:80");

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute("whatever.com:80|1,2,3#whatever.com:8080|3,4");
        $this->assertEquals(array(1,2,3), $ids);

        $ids = $this->userAccessAttributeParser->getSiteIdsFromAccessAttribute("http://whatever.com:801|1,2#whatever.com:80|3,4#http://whatever.com:80|5,6");
        $this->assertEquals(array(3,4,5,6), $ids);
    }

    public function test_getSuperUserAccessFromSuperUserAttribute_ReturnsTrue_IfNoInstanceSpecified()
    {
        $this->userAccessAttributeParser->setThisPiwikInstanceName('myPiwik');

        $this->assertTrue($this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute(""));
        $this->assertTrue($this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("1"));
        $this->assertTrue($this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("true"));
        $this->assertTrue($this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("tRuE"));

        $this->assertFalse($this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("false"));
        $this->assertFalse($this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("server"));
    }

    public function test_getSuperUserAccessFromSuperUserAttribute_ReturnsTrue_IfInstanceInSpecifiedList()
    {
        $this->userAccessAttributeParser->setThisPiwikInstanceName('myPiwik');

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("myPiwik");
        $this->assertTrue($hasSuperUserAccess);

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("somePiwik;myPiwik;anotherPiwik");
        $this->assertTrue($hasSuperUserAccess);

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("myPiwik : somePiwik : anotherPiwik");
        $this->assertTrue($hasSuperUserAccess);

        $this->userAccessAttributeParser->setThisPiwikInstanceName(null);
        $this->userAccessAttributeParser->setServerIdsSeparator('|');
        $this->setThisPiwikUrl("https://whatever.com/piwik");

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("whatever.com/piwik");
        $this->assertTrue($hasSuperUserAccess);

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("http://whatever.com/piwik|anotherpiwik.com");
        $this->assertTrue($hasSuperUserAccess);

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute(" anotherpiwik.com  |  https://whatever.com/piwik ");
        $this->assertTrue($hasSuperUserAccess);
    }

    public function test_getSuperUserAccessFromSuperUserAttribute_ReturnsFalse_IfInstanceNotInAttribute()
    {
        $this->userAccessAttributeParser->setThisPiwikInstanceName('myPiwik');

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("whatever");
        $this->assertFalse($hasSuperUserAccess);

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("one;two;three");
        $this->assertFalse($hasSuperUserAccess);

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("one:two:three");
        $this->assertFalse($hasSuperUserAccess);

        $this->userAccessAttributeParser->setThisPiwikInstanceName(null);
        $this->userAccessAttributeParser->setServerIdsSeparator('|');
        $this->setThisPiwikUrl("https://whatever.com/piwik");

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("https://whatever.com:8080/piwik");
        $this->assertFalse($hasSuperUserAccess);

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("https://whatever.com:8080");
        $this->assertFalse($hasSuperUserAccess);
    }

    public function test_getSuperUserAccessFromSuperUserAttribute_ReturnsCorrectResult_IfAttributeValueIsMalformed()
    {
        $this->userAccessAttributeParser->setThisPiwikInstanceName('myPiwik');

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute(" myPiwik = superuser ; whatever");
        $this->assertTrue($hasSuperUserAccess);

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("anothe; superuser = myPiwik ; whatever");
        $this->assertTrue($hasSuperUserAccess);

        $this->userAccessAttributeParser->setThisPiwikInstanceName(null);
        $this->userAccessAttributeParser->setServerSpecificationDelimiter('|');
        $this->setThisPiwikUrl("https://whatever.com/piwik");

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("whatever.com/piwik | @{}{}@ | /&/?//");
        $this->assertTrue($hasSuperUserAccess);
    }

    public function test_getSuperUserAccessFromSuperUserAttribute_ReturnsCorrectResult_WhenCustomDelimetersAreUsed()
    {
        $this->userAccessAttributeParser->setThisPiwikInstanceName('myPiwik');
        $this->userAccessAttributeParser->setServerSpecificationDelimiter('#');
        $this->userAccessAttributeParser->setServerIdsSeparator('|');

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("anoth | myPiwik | whatever");
        $this->assertTrue($hasSuperUserAccess);

        $hasSuperUserAccess = $this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute("a # myPiwik # c");
        $this->assertTrue($hasSuperUserAccess);
    }

    private function setSitesManagerApiMock()
    {
        $mock = $this->getMockBuilder('stdClass')
                     ->setMethods( array('getSitesIdWithAtLeastViewAccess', 'getAllSitesId'))
                     ->getMock();
        $mock->expects($this->any())->method('getSitesIdWithAtLeastViewAccess')->willReturn(array(1,2,3,4,5,6));
        $mock->expects($this->any())->method('getAllSitesId')->willReturn(array(1,2,3,4,5,6));
        SitesManagerAPI::setSingletonInstance($mock);
    }

    private function setThisPiwikUrl($thisUrl)
    {
        $mock = $this->getMockBuilder('stdClass')
                     ->setMethods(array('getValue'))
                     ->getMock();
        $mock->expects($this->any())->method('getValue')->willReturnCallback(function ($key) use ($thisUrl) {
            if ($key == SettingsPiwik::OPTION_PIWIK_URL) {
                return $thisUrl;
            } else {
                return "...";
            }
        });

        Option::setSingletonInstance($mock);
    }
}
