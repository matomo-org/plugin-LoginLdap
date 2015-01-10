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
use Piwik\Plugins\LoginLdap\tests\Integration\LdapIntegrationTest;
use Piwik\Translate;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_RegenerateTokenAuthTest
 */
class RegenerateTokenAuthTest extends LdapIntegrationTest
{
    const PREEXISTING_LDAP_USER = 'preexistinguser';
    const PREEXISTING_PASSWORD = '{LDAP}preexistingpass';
    const PREEXISTING_TOKEN_AUTH = 'preexistingta';

    /**
     * @var ApplicationTester
     */
    protected $applicationTester = null;

    public function setUp()
    {
        parent::setUp();

        $this->addNonLdapUsers();
        $this->addPreexistingLdapUser();

        $plugins = Config::getInstance()->Plugins;
        $plugins['Plugins'][] = 'LoginLdap';
        Config::getInstance()->Plugins = $plugins;

        $application = new Console();
        $application->setAutoExit(false);

        $this->applicationTester = new ApplicationTester($application);

        Translate::loadEnglishTranslation(); // needed due to travis build that tests against minimum required piwik
    }

    protected function getCommandDisplayOutputErrorMessage()
    {
        return "Command did not behave as expected. Command output: " . $this->applicationTester->getDisplay();
    }

    public function test_CommandFails_WhenUserIsNotSynchronized()
    {
        Config::getInstance()->LoginLdap['enable_random_token_auth_generation'] = 1;

        $result = $this->applicationTester->run(array(
            'command' => 'loginldap:generate-token-auth',
            'login' => self::TEST_LOGIN
        ));
        $this->assertEquals(1, $result, $this->getCommandDisplayOutputErrorMessage());
        $this->assertContains('doesn\'t exist', $this->applicationTester->getDisplay());
    }

    public function test_CommandFails_WhenUserIsNotLdapUser()
    {
        Config::getInstance()->LoginLdap['enable_random_token_auth_generation'] = 1;

        $result = $this->applicationTester->run(array(
            'command' => 'loginldap:generate-token-auth',
            'login' => self::NON_LDAP_USER
        ));
        $this->assertEquals(1, $result, $this->getCommandDisplayOutputErrorMessage());
        $this->assertContains('is not an LDAP user', $this->applicationTester->getDisplay());
    }

    public function test_CommandFails_WhenGeneratingRandomTokenAuth_IsNotEnabled()
    {
        $result = $this->applicationTester->run(array(
            'command' => 'loginldap:generate-token-auth',
            'login' => self::PREEXISTING_LDAP_USER
        ));
        $this->assertEquals(1, $result, $this->getCommandDisplayOutputErrorMessage());
        $this->assertContains('Random token_auth generation is disabled', $this->applicationTester->getDisplay());
    }

    public function test_CommandResetsPassword_WhenUserIsLdapUserAndAlreadySynchronized()
    {
        Config::getInstance()->LoginLdap['enable_random_token_auth_generation'] = 1;

        $result = $this->applicationTester->run(array(
            'command' => 'loginldap:generate-token-auth',
            'login' => self::PREEXISTING_LDAP_USER
        ));
        $this->assertEquals(0, $result, $this->getCommandDisplayOutputErrorMessage());

        $user = $this->getUser(self::PREEXISTING_LDAP_USER);
        $this->assertNotEquals(self::PREEXISTING_PASSWORD, $user['password']);
        $this->assertNotEquals(self::PREEXISTING_TOKEN_AUTH, $user['token_auth']);
    }

    private function addPreexistingLdapUser()
    {
        $usersTable = Common::prefixTable('user');
        Db::query("INSERT INTO `$usersTable` (login, password, email, alias, token_auth) VALUES (?, ?, ?, ?, ?)",
            array(self::PREEXISTING_LDAP_USER, self::PREEXISTING_PASSWORD, 'email@email.com', 'alias', self::PREEXISTING_TOKEN_AUTH));
    }
}