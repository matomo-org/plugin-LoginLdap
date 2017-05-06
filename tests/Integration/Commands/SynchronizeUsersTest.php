<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Integration\Commands;

use Piwik\Common;
use Piwik\Config;
use Piwik\Console;
use Piwik\Db;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\LoginLdap\tests\Integration\LdapIntegrationTest;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_SynchronizeUsersTest
 */
class SynchronizeUsersTest extends LdapIntegrationTest
{
    /**
     * @var ApplicationTester
     */
    protected $applicationTester = null;

    public function setUp()
    {
        parent::setUp();

        $plugins = Config::getInstance()->Plugins;
        $plugins['Plugins'][] = 'LoginLdap';
        Config::getInstance()->Plugins = $plugins;

        $application = new Console(self::$fixture->piwikEnvironment);
        $application->setAutoExit(false);

        $this->applicationTester = new ApplicationTester($application);
    }

    protected function getCommandDisplayOutputErrorMessage()
    {
        return "Command did not behave as expected. Command output: " . $this->applicationTester->getDisplay();
    }

    public function test_CommandSynchronizesAllUsers_WhenLoginNotSpecified()
    {
        $result = $this->applicationTester->run(array(
            'command' => 'loginldap:synchronize-users',
            '-v' => true
        ));

        $this->assertEquals(0, $result, $this->getCommandDisplayOutputErrorMessage());

        $users = $this->getLdapUserLogins();
        $this->assertEquals(array('blackwidow', 'captainamerica', 'ironman', 'msmarvel', 'rogue@xmansion.org', 'thor'), $users);
    }

    public function test_CommandSynchronizesOneUser_WhenLoginSpecified()
    {
        $result = $this->applicationTester->run(array(
            'command' => 'loginldap:synchronize-users',
            '--login' => array('ironman'),
            '-v' => true
        ));

        $this->assertEquals(0, $result, $this->getCommandDisplayOutputErrorMessage());

        $users = $this->getLdapUserLogins();
        $this->assertEquals(array('ironman'), $users);
    }

    public function test_CommandSynchronizesMultipleUsers_WhenMultipleLoginsSpecified()
    {
        $result = $this->applicationTester->run(array(
            'command' => 'loginldap:synchronize-users',
            '--login' => array('ironman', 'blackwidow'),
            '-v' => true
        ));

        $this->assertEquals(0, $result, $this->getCommandDisplayOutputErrorMessage());

        $users = $this->getLdapUserLogins();
        $this->assertEquals(array('blackwidow', 'ironman'), $users);
    }

    public function test_CommandReportsUsersThatAreNotSynchronized_WhenUserMissing_AndUserInfoBrokenInLdap()
    {
        $this->markTestSkipped("Can't find a way to inject broken data into result of Ldap\\Client::fetchAll. Waiting for DI.");

        $result = $this->applicationTester->run(array(
            'command' => 'loginldap:synchronize-users',
            '--login' => array('ironman', 'blackwidow', 'missinguser', 'msmarvel'),
            '-v' => true
        ));

        $this->assertEquals(2, $result, $this->getCommandDisplayOutputErrorMessage());

        $users = $this->getLdapUserLogins();
        $this->assertEquals(array('blackwidow', 'ironman'), $users);

        $this->assertRegExp("/^.*missinguser.*User.*not found.*$/", $this->applicationTester->getDisplay());
        $this->assertRegExp("/^.*msmarvel.*LDAP entity missing required.*$/", $this->applicationTester->getDisplay());
    }

    public function test_CommandSkipsExisitingUsers_IfSkipExistingOptionUsed()
    {
        $this->addPreexistingSuperUser();

        $result = $this->applicationTester->run(array(
            'command' => 'loginldap:synchronize-users',
            '--login' => array('ironman', 'captainamerica'),
            '--skip-existing' => true,
            '-v' => true
        ));

        $this->assertEquals(0, $result, $this->getCommandDisplayOutputErrorMessage());
        $this->assertContains("Skipping 'captainamerica', already exists in Piwik...", $this->applicationTester->getDisplay());

        $users = $this->getLdapUserLogins();
        $this->assertEquals(array('ironman'), $users);
    }

    private function getLdapUserLogins()
    {
        $rows = Db::fetchAll("SELECT login from " . Common::prefixTable('user') . ' ORDER BY login');
        $userMapper = new UserMapper();

        $result = array();
        foreach ($rows as $row) {
            if ($userMapper->isUserLdapUser($row['login'])) {
                $result[] = $row['login'];
            }
        }
        return $result;
    }
}