<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Unit;

use Piwik\ErrorHandler;
use Piwik\Plugins\LoginLdap\Ldap\Client as LdapClient;
use Piwik\Plugins\LoginLdap\Ldap\LdapFunctions;
use PHPUnit_Framework_TestCase;

require_once PIWIK_INCLUDE_PATH . '/plugins/LoginLdap/tests/Mocks/LdapFunctions.php';

/**
 * @group LoginLdap
 * @group LoginLdap_Unit
 * @group LoginLdap_LdapClientTest
 */
class LdapClientTest extends PHPUnit_Framework_TestCase
{
    const ERROR_MESSAGE = "triggered error";

    public function setUp()
    {
        parent::setUp();

        LdapFunctions::$phpUnitMock = $this->getMockBuilder('stdClass')
                                           ->setMethods( array('ldap_connect', 'ldap_close',
                                             'ldap_bind', 'ldap_search', 'ldap_set_option',
                                             'ldap_get_entries', 'ldap_count_entries'))
                                           ->getMock();
    }

    public function tearDown()
    {
        restore_error_handler();

        LdapFunctions::$phpUnitMock = null;

        parent::tearDown();
    }

    public function test_construction_WithNoArguments_DoesNotConnect()
    {
        $ldapClient = new LdapClient();

        $this->assertFalse($ldapClient->isOpen());
    }

    public function test_construction_WithHostnameAndPort_AttemptsToConnect()
    {
        $this->addLdapConnectMethodMock("hostname", 1234);

        $ldapClient = new LdapClient("hostname", 1234);

        $this->assertTrue($ldapClient->isOpen());
    }

    public function test_connect_Closes_IfConnectionCurrentlyOpen()
    {
        $this->addLdapConnectMethodMock();

        LdapFunctions::$phpUnitMock->expects($this->exactly(1))->method('ldap_close')
            ->withConsecutive(
                array($this->equalTo("connection_resource_hostname_1234")),
                array($this->equalTo("connection_resource_hostname2_4567"))
            );

        $ldapClient = new LdapClient("hostname", 1234);
        $this->assertTrue($ldapClient->isOpen());

        $ldapClient->connect("hostname2", 4567);
        $this->assertTrue($ldapClient->isOpen());
    }

    /**
     * @expectedException \Piwik\Exception\ErrorException
     * @expectedExceptionMessage triggered error
     */
    public function test_connect_ThrowsPhpErrors()
    {
        $this->setPiwikErrorHandling();

        $this->addLdapMethodThatTriggersPhpError('ldap_connect');

        $ldapClient = new LdapClient();
        $ldapClient->connect("hostname", 1234);
    }

    public function test_close_Succeeds_IfConnectionAlreadyClosed()
    {
        $ldapClient = new LdapClient();
        $ldapClient->close();
    }

    /**
     * @expectedException \Piwik\Exception\ErrorException
     * @expectedExceptionMessage triggered error
     */
    public function test_close_ThrowsPhpErrors()
    {
        $this->setPiwikErrorHandling();

        $this->addLdapConnectMethodMock();
        $this->addLdapMethodThatTriggersPhpError('ldap_close');

        $ldapClient = new LdapClient("hostname", 1234);
        $ldapClient->close();
    }

    public function test_bind_ForwardsLdapBindResult()
    {
        LdapFunctions::$phpUnitMock->expects($this->once())->method('ldap_bind')->will($this->returnValue("ldap_bind result"));

        $ldapClient = new LdapClient();
        $result = $ldapClient->bind("resource", "password");
        $this->assertEquals("ldap_bind result", $result);
    }

    /**
     * @expectedException \Piwik\Exception\ErrorException
     * @expectedExceptionMessage triggered error
     */
    public function test_bind_ThrowsPhpErrors()
    {
        $this->setPiwikErrorHandling();

        $this->addLdapMethodThatTriggersPhpError('ldap_bind');

        $ldapClient = new LdapClient();
        $ldapClient->bind("resource", "password");
    }

    /**
     * @expectedException \Piwik\Exception\ErrorException
     * @expectedExceptionMessage triggered error
     */
    public function test_fetchAll_ThrowsPhpErrors()
    {
        $this->setPiwikErrorHandling();

        $this->addLdapMethodThatTriggersPhpError('ldap_search');
        $this->addLdapMethodThatTriggersPhpError('ldap_get_entries');

        $ldapClient = new LdapClient();
        $ldapClient->fetchAll("base dn", "filter");
    }

    public function test_fetchAll_ReturnsNull_IfLdapSearchFailsSilently()
    {
        LdapFunctions::$phpUnitMock->expects($this->any())->method('ldap_search')->will($this->returnValue(null));

        $ldapClient = new LdapClient();
        $result = $ldapClient->fetchAll("base dn", "filter");

        $this->assertNull($result);
    }

    public function test_fetchAll_CorrectlyEscapesFilterParameters()
    {
        $escapedFilter = null;

        LdapFunctions::$phpUnitMock->expects($this->any())->method('ldap_search')->will($this->returnCallback(
            function ($conn, $dn, $filter) use (&$escapedFilter) {
                $escapedFilter = $filter;
            })
        );
        LdapFunctions::$phpUnitMock->expects($this->any())->method('ldap_get_entries')->will($this->returnValue("result"));

        $ldapClient = new LdapClient();

        $ldapClient->fetchAll("base dn", "(uid=name?)");
        $this->assertEquals("(uid=name?)", $escapedFilter);

        $ldapClient->fetchAll("base dn", "(uid=?)", array("na(m)e?'!"));
        $this->assertEquals('(uid=na\\28m\\29e?\'!)', $escapedFilter);

        $ldapClient->fetchAll("base dn", "(&(uid=?,?)(whatev=?))", array("on()e", "tw?", "(thre"));
        $this->assertEquals("(&(uid=on\\28\\29e,tw?)(whatev=\\28thre))", $escapedFilter);

        $ldapClient->fetchAll("base dn", "(&(uid=?,?)(whatev=?))", array("t*w()o", "t\\hr??e"));
        $this->assertEquals("(&(uid=t\\2aw\\28\\29o,t\\5chr??e)(whatev=?))", $escapedFilter);
    }

    public function getLdapTransformTestData()
    {
        return array(
            array(
                array('count' => 0),
                array()
            ),
            // test pair
            array(
                array(
                    'count' => 1,
                    0 => array(
                        'count' => 2,
                        'cn' => array('count' => 1, '0' => 'value'),
                        'dn' => 'the dn',
                        0 => 'cn',
                        1 => 'dn'
                    )
                ),
                array(
                    array('cn' => 'value', 'dn' => 'the dn')
                )
            ),
            // test pair
            array(
                array(
                    'count' => 2,
                    0 => array(
                        'count' => 2,
                        0 => 'cn',
                        1 => 'objectClass',
                        'cn' => array('count' => 1, '0' => 'value2'),
                        'objectClass' => array('count' => '1', '0' => 'top'),
                        'dn' => 'the dn'
                    ),
                    1 => array(
                        'count' => 2,
                        'cn' => array('count' => 2, '0' => 'value3'),
                        0 => 'objectclass',
                        'objectclass' => array('count' => '2', '0' => 'top', '1' => 'inetOrgPersion'),
                        1 => 'cn'
                    )
                ),
                array(
                    array('cn' => 'value2', 'objectclass' => 'top', 'dn' => 'the dn'),
                    array('objectclass' => array('top', 'inetOrgPersion'), 'cn' => 'value3'),
                )
            ),
            // test pair
            array(
                array(
                    'count' => 1,
                    0 => array(
                        'count' => 1,
                        'superuser' => array(
                            'count' => 2,
                            0 => '1',
                            1 => 'anotherpiwik'
                        ),
                        0 => 'superuser'
                    )
                ),
                array(
                    array('superuser' => array('1', 'anotherpiwik'))
                )
            )
        );
    }

    /**
     * @dataProvider getLdapTransformTestData
     */
    public function test_fetchAll_CorrectlyProcessesLdapSearchResults($ldapData, $expectedData)
    {
        LdapFunctions::$phpUnitMock->expects($this->any())->method('ldap_search')->will($this->returnValue("resource"));
        LdapFunctions::$phpUnitMock->expects($this->any())->method('ldap_get_entries')->will($this->returnValue($ldapData));

        $ldapClient = new LdapClient();
        $result = $ldapClient->fetchAll("base dn", "filter");
        $this->assertEquals($expectedData, $result);
    }

    /**
     * @expectedException \Piwik\Exception\ErrorException
     * @expectedExceptionMessage triggered error
     */
    public function test_count_ThrowsPhpErrors()
    {
        $this->setPiwikErrorHandling();

        LdapFunctions::$phpUnitMock->expects($this->any())->method('ldap_search')->will($this->returnValue("resource"));
        $this->addLdapMethodThatTriggersPhpError('ldap_count_entries');

        $ldapClient = new LdapClient();
        $ldapClient->count("base dn", "filter");
    }

    /**
     * @expectedException \Exception
     */
    public function test_count_Throws_IfLdapSearchReturnsNull()
    {
        $this->setPiwikErrorHandling();

        LdapFunctions::$phpUnitMock->expects($this->any())->method('ldap_search')->will($this->returnValue(null));

        $ldapClient = new LdapClient();
        $ldapClient->count("base dn", "filter");
    }

    public function test_count_ReturnsCorrectValue()
    {
        LdapFunctions::$phpUnitMock->expects($this->any())->method('ldap_search')->will($this->returnValue("resource"));
        LdapFunctions::$phpUnitMock->expects($this->any())->method('ldap_count_entries')->will($this->returnValue(8));

        $ldapClient = new LdapClient();
        $result = $ldapClient->count("base dn", "filter");
        $this->assertEquals(8, $result);
    }

    private function addLdapConnectMethodMock($hostname = null, $port = null)
    {
        $getConnectionResource = function ($hostname, $port) {
            return "connection_resource_$hostname" . '_' . $port;
        };

        $method = LdapFunctions::$phpUnitMock->expects($this->any())->method('ldap_connect');
        if (!empty($hostname) || !empty($port)) {
            $method = $method->with($this->equalTo($hostname), $this->equalTo($port));
        }
        $method->will($this->returnCallback($getConnectionResource));

        LdapFunctions::$phpUnitMock->expects($this->any())->method('ldap_set_option')->will($this->returnValue(null));
    }

    private function addLdapMethodThatTriggersPhpError($methodName, $returnValue = null)
    {
        LdapFunctions::$phpUnitMock->expects($this->any())->method($methodName)->will($this->returnCallback(function () use ($returnValue) {
            trigger_error(LdapClientTest::ERROR_MESSAGE, E_USER_ERROR);
            return $returnValue;
        }));
    }

    private function setPiwikErrorHandling()
    {
        ErrorHandler::registerErrorHandler();
        error_reporting(E_ALL);
    }
}
