<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\Commands;

use Exception;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generates a new token auth for an LDAP synchronized user.
 */
class RegenerateTokenAuth extends ConsoleCommand
{
    /**
     * @var UsersManagerAPI
     */
    private $usersManagerApi;

    /**
     * @var UserMapper
     */
    private $userMapper;

    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->usersManagerApi = UsersManagerAPI::getInstance();
        $this->userMapper = UserMapper::makeConfigured();
    }

    protected function configure()
    {
        $this->setName('loginldap:generate-token-auth');
        $this->setDescription('Generates a new token auth for an LDAP user. The LDAP user must have been synchronized already.');
        $this->addArgument('login', InputArgument::REQUIRED, 'The login of the user to regenerate a token auth for.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $login = $input->getArgument('login');

        $user = $this->usersManagerApi->getUser($login);

        if (!UserMapper::isUserLdapUser($user)) {
            throw new Exception("User '$login' is not an LDAP user. To regenerate this user's token_auth, change the user's password.");
        }

        if (!$this->userMapper->isRandomTokenAuthGenerationEnabled()) {
            throw new Exception("Random token_auth generation is disabled in [LoginLdap] config. This means any changes made by this "
                              . "command will be overwritten when the user logs in. Aborting.");
        }

        $newPassword = $this->userMapper->generateRandomPassword();
        $this->usersManagerApi->updateUser($login, $newPassword, $email = false, $alias = false, $isPasswordHash = true);

        $user = $this->usersManagerApi->getUser($login);

        $this->writeSuccessMessage($output, array("token_auth for '$login' regenerated successfully, new token_auth = '{$user['token_auth']}'"));
    }
}