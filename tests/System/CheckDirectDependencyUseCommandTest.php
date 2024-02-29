<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\tests\System;

use Piwik\Plugins\TestRunner\Commands\CheckDirectDependencyUse;
use Piwik\Tests\Framework\TestCase\SystemTestCase;
use Piwik\Version;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class CheckDirectDependencyUseCommandTest extends SystemTestCase
{
    public function testCommand()
    {
        if (version_compare(Version::VERSION, '5.0.2', '<=') && !\Piwik\file_exists(PIWIK_INCLUDE_PATH . '/plugins/TestRunner/Commands/CheckDirectDependencyUse.php')) {
            $this->markTestSkipped('tests:check-direct-dependency-use is not available in this version');
        }
        $pluginName = 'LoginLdap';
        $console = new \Piwik\Console(self::$fixture->piwikEnvironment);
        $checkDirectDependencyUse = new CheckDirectDependencyUse();
        $console->addCommands([$checkDirectDependencyUse]);
        $command = $console->find('tests:check-direct-dependency-use');
        $arguments = array(
            'command'    => 'tests:check-direct-dependency-use',
            '--plugin' => $pluginName
        );
        $inputObject = new ArrayInput($arguments);
        $command->run($inputObject, new NullOutput());

        $this->assertEquals([
            'DI\\' => [
                'LoginLdap/tests/Integration/Commands/SynchronizeUsersTest.php'
            ],
            'Symfony\Component\Console\\' => [
                'LoginLdap/tests/Integration/Commands/SynchronizeUsersTest.php',
                'LoginLdap/tests/System/CheckDirectDependencyUseCommandTest.php',
            ]
        ], $checkDirectDependencyUse->usesFoundList[$pluginName]);
    }
}