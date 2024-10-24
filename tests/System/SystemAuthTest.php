<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap\tests\System;

use Piwik\Auth;
use Piwik\AuthResult;
use Piwik\Common;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Db;
use Piwik\Plugins\LoginLdap\LoginLdap;
use Piwik\Plugins\LoginLdap\Auth\LdapAuth;
use Piwik\Plugins\LoginLdap\Auth\SynchronizedAuth;
use Piwik\Plugins\LoginLdap\Auth\WebServerAuth;
use Piwik\Plugins\LoginLdap\tests\Integration\LdapIntegrationTest;
use Piwik\Plugins\UsersManager\API;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestingEnvironmentVariables;

/**
 * @group LoginLdap
 * @group LoginLdap_System
 * @group LoginLdap_SystemAuthTest
 */
class SystemAuthTest extends LdapIntegrationTest
{
    private $superUserTokenAuth;

    public function getAuthModesToTest()
    {
        return array(
            array('ldap_only'),
            array('synchronized'),
            array('web_server'),
        );
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->addPreexistingSuperUser();
        $this->superUserTokenAuth = API::getInstance()->createAppSpecificTokenAuth(
            self::TEST_SUPERUSER_LOGIN,
            self::TEST_SUPERUSER_PASS,
            'test'
        );
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($_SERVER['REMOTE_USER']);
    }

    /**
     * @dataProvider getAuthModesToTest
     */
    public function test_LdapAuthentication_WorksForNormalTrackingRequest($authStrategy)
    {
        $this->setUpLdap($authStrategy);

        $superUserTokenAuth = $this->getSuperUserTokenAuth();

        $date = Date::factory('2015-01-01 00:00:00');

        $tracker = Fixture::getTracker($idSite = 1, $date->addHour(1)->getDatetime());
        $tracker->setTokenAuth($superUserTokenAuth);

        $tracker->setUrl('http://shield.org/protocol/theta');
        Fixture::checkResponse($tracker->doTrackPageView('I Am A Robot Tourist'));

        // authentication is required to track dates in the past, so to verify we
        // authenticated, we check the tracked visit times
        $expectedDateTimes = array('2015-01-01 01:00:00');
        $actualDateTimes = $this->getVisitDateTimes();

        $this->assertEquals($expectedDateTimes, $actualDateTimes);
    }

    /**
     * @dataProvider getAuthModesToTest
     */
    public function test_LdapAuthentication_WorksDuringBulkTracking($authStrategy)
    {
        $this->setUpLdap($authStrategy);

        $superUserTokenAuth = $this->getSuperUserTokenAuth();

        $date = Date::factory('2015-01-01 00:00:00');

        $tracker = Fixture::getTracker($idSite = 1, $date->getDatetime());
        $tracker->setTokenAuth($superUserTokenAuth);
        $tracker->enableBulkTracking();

        $tracker->setForceVisitDateTime($date->getDatetime());
        $tracker->setUrl('http://shield.org/level/10/dandr/pcoulson');
        $tracker->doTrackPageView('Death & Recovery');

        $tracker->setForceVisitDateTime($date->addHour(1)->getDatetime());
        $tracker->setUrl('http://shield.org/logout');
        $tracker->doTrackPageView('Going dark');

        Fixture::checkBulkTrackingResponse($tracker->doBulkTrack());

        // authentication is required to track dates in the past, so to verify we
        // authenticated, we check the tracked visit times
        $expectedDateTimes = array('2015-01-01 00:00:00', '2015-01-01 01:00:00');
        $actualDateTimes = $this->getVisitDateTimes();

        $this->assertEquals($expectedDateTimes, $actualDateTimes);
    }

    private function getVisitDateTimes()
    {
        $rows = Db::fetchAll("SELECT visit_last_action_time FROM " . Common::prefixTable('log_visit')
            . " ORDER BY visit_last_action_time ASC");

        $dates = array();
        foreach ($rows as $row) {
            $dates[] = $row['visit_last_action_time'];
        }
        return $dates;
    }

    private function setUpLdap($authStrategy)
    {
        $testVars = new TestingEnvironmentVariables();
        $configOverride = $testVars->configOverride;

        if ($authStrategy == 'ldap_only') {
            Config::getInstance()->LoginLdap['use_ldap_for_authentication'] = 1;
            $configOverride['LoginLdap']['use_ldap_for_authentication'] = 1;
        } elseif ($authStrategy == 'synchronized') {
            Config::getInstance()->LoginLdap['use_ldap_for_authentication'] = 0;
            $configOverride['LoginLdap']['use_ldap_for_authentication'] = 0;
        } elseif ($authStrategy == 'web_server') {
            Config::getInstance()->LoginLdap['use_webserver_auth'] = 1;
            $configOverride['LoginLdap']['use_webserver_auth'] = 1;
        } else {
            throw new \Exception("Unknown LDAP auth strategy $authStrategy.");
        }

        $configOverride['Tracker']['debug_on_demand'] = 1;

        $testVars->configOverride = $configOverride;
        $testVars->save();

        // make sure our superuser is synchronized before hand
        $this->authenticateUserOnce($authStrategy);
    }

    private function getSuperUserTokenAuth()
    {
        return $this->superUserTokenAuth;
    }

    private function authenticateUserOnce($authStrategy)
    {
        $auth = null;
        if ($authStrategy == 'ldap_only') {
            $auth = LdapAuth::makeConfigured();
        } elseif ($authStrategy == 'synchronized') {
            $auth = SynchronizedAuth::makeConfigured();
        } elseif ($authStrategy == 'web_server') {
            $auth = WebServerAuth::makeConfigured();

            $_SERVER['REMOTE_USER'] = self::TEST_SUPERUSER_LOGIN;
        } else {
            throw new \Exception("Unknown LDAP auth strategy $authStrategy.");
        }

        StaticContainer::getContainer()->set(Auth::class, $auth);

        $auth->setLogin(self::TEST_SUPERUSER_LOGIN);
        $auth->setPassword(self::TEST_SUPERUSER_PASS);
        $result = $auth->authenticate();

        $this->assertNotEquals(AuthResult::FAILURE, $result->getCode());

        $this->setSuperUserAccess(self::TEST_SUPERUSER_LOGIN, true);
    }

    public function testLdapUserPassChange()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Changing your password is not supported for LDAP users');

        $auth = LdapAuth::makeConfigured();
        $auth->setLogin(self::TEST_LOGIN);
        $auth->setPassword(self::TEST_PASS);
        $result = $auth->authenticate();

        $this->assertNotEquals(AuthResult::FAILURE, $result->getCode());

        $loginLdap = new LoginLdap();
        $loginLdap->disablePasswordChangeForLdapUsers($auth);
    }
}
