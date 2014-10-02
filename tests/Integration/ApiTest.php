<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Exception;
use Piwik\Plugins\LoginLdap\API;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_ApiTest
 */
class ApiTest extends LdapIntegrationTest
{
    /**
     * @var API
     */
    private $api;

    public function setUp()
    {
        parent::setUp();

        $this->api = new API();
    }

    public function test_getCountOfUsersMemberOf_ReturnsZero_WhenNoUsersAreMemberOfGroup()
    {
        $count = $this->api->getCountOfUsersMemberOf("not()hing");
        $this->assertEquals(0, $count);
    }

    public function test_getCountOfUsersMemberOf_ReturnsCorrectResponse_WhenUsersAreMemberOfGroup()
    {
        $count = $this->api->getCountOfUsersMemberOf("cn=avengers," . self::SERVER_BASE_DN);
        $this->assertEquals(3, $count);
    }

    public function test_getCountOfUsersMatchingFilter_ReturnsZero_WhenNoUsersMatchTheFilter()
    {
        $count = $this->api->getCountOfUsersMemberOf("(objectClass=whatever)");
        $this->assertEquals(0, $count);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage LoginLdap_InvalidFilter
     */
    public function test_getCountOfUsersMatchingFilter_Throws_WhenFilterIsInvalid()
    {
        $this->api->getCountOfUsersMatchingFilter("lksjdf()a;sk");
    }

    public function test_getCountOfUsersMatchingFilter_ReturnsCorrectResult_WhenUsersMatchFilter()
    {
        $count = $this->api->getCountOfUsersMatchingFilter("(objectClass=person)");
        $this->assertEquals(3, $count);
    }
}