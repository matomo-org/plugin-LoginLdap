<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\Exception\NoPrivilegesException;
use Piwik\Exception\ThingNotFoundException;
use Piwik\Http\BadRequestException;
use Piwik\Plugins\LoginLdap\API;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\LoginLdap\LoginLdap;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
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

    public function testSynchronizingAnUnknownLdapUserAnswers404()
    {
        FakeAccess::$superUser = true;

        $ldapUsers = $this->createMock(LdapUsers::class);
        $ldapUsers->method('getUser')->willReturn(null);

        $api = new API();
        $property = new \ReflectionProperty(API::class, 'ldapUsers');
        $property->setAccessible(true);
        $property->setValue($api, $ldapUsers);

        $this->expectException(LoginLdap::getClientErrorCode(404) ? ThingNotFoundException::class : \Exception::class);
        $this->expectExceptionCode(LoginLdap::getClientErrorCode(404));

        $api->synchronizeUser('unknownldapuser');
    }

    public function testInvalidSearchFilterAnswers400()
    {
        FakeAccess::$superUser = true;

        $ldapUsers = $this->createMock(LdapUsers::class);
        $ldapUsers->method('getCountOfUsersMatchingFilter')->willThrowException(new \Exception('Bad search filter'));

        $api = new API();
        $property = new \ReflectionProperty(API::class, 'ldapUsers');
        $property->setAccessible(true);
        $property->setValue($api, $ldapUsers);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionCode(LoginLdap::getClientErrorCode(400));

        $api->getCountOfUsersMatchingFilter('(bad');
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
