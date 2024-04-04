<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap\tests\System;

use Piwik\Plugins\LoginLdap\Commands\SynchronizeUsers;
use Piwik\Plugins\LoginLdap\tests\System\Output\TestOutput;
use Piwik\Tests\Framework\TestCase\SystemTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class SynchronizeUsersCommandTest extends SystemTestCase
{

    public function test_sync_command_should_sync_without_deletion()
    {
        $console = new \Piwik\Console(self::$fixture->piwikEnvironment);
        $synchronizeUsers = new SynchronizeUsers();
        $console->addCommands([$synchronizeUsers]);
        $command = $console->find('loginldap:synchronize-users');
        $arguments = array(
            'command'    => 'loginldap:synchronize-users',
        );
        $inputObject = new ArrayInput($arguments);
        $output = new TestOutput();
        $command->run($inputObject, $output);
        $outputAsString = json_encode($output->output);
        $this->assertStringNotContainsString('Purging user', $outputAsString);

        $this->runApiTests(['LoginLdap.getExistingLdapUsersFromDb'], ['testSuffix' => '_no_purge']);
    }

    public static function getPathToTestDirectory()
    {
        return dirname(__FILE__);
    }
}