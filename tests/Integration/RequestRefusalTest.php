<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\Exception\NoPrivilegesException;
use Piwik\Http\BadRequestException;
use Piwik\Plugins\LoginLdap\API;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\LoginLdap\LoginLdap;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 */
class RequestRefusalTest extends IntegrationTestCase
{
    public function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    public function testChangingIsLdapUserPreferenceAnswers403()
    {
        $this->expectException(NoPrivilegesException::class);
        $this->expectExceptionCode(403);

        (new LoginLdap())->checkIfUserPreferenceChangeIsAllowed([
            'preferenceName' => UserMapper::USER_PREFERENCE_NAME_IS_LDAP_USER,
        ]);
    }

    /**
     * @dataProvider getSaveMethods
     */
    public function testSaveMethodsAnswer400WhenNotPosted(string $method, array $args)
    {
        FakeAccess::$superUser = true;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->expectException(BadRequestException::class);
        $this->expectExceptionCode(400);

        API::getInstance()->$method(...$args);
    }

    public function getSaveMethods(): array
    {
        return [
            ['saveLdapConfig', ['{}']],
            ['saveServersInfo', ['[]']],
        ];
    }

    public function provideContainerConfig()
    {
        return [
            'Piwik\Access' => new FakeAccess(),
        ];
    }
}
