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
use Piwik\Plugins\LoginLdap\API as LoginLdapAPI;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to synchronize multiple users in LDAP w/ Piwik's MySQL DB. Can be used
 * to make sure user information is synchronized before the user logs in the first time.
 */
class SynchronizeUsers extends ConsoleCommand
{
    /**
     * @var LoginLdapAPI
     */
    private $loginLdapAPI;

    /**
     * @var LdapUsers
     */
    private $ldapUsers;

    /**
     * @var UsersManagerAPI
     */
    private $usersManagerAPI;

    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->loginLdapAPI = LoginLdapAPI::getInstance();
        $this->ldapUsers = LdapUsers::makeConfigured();
        $this->usersManagerAPI = UsersManagerAPI::getInstance();
    }

    protected function configure()
    {
        $this->setName('loginldap:synchronize-users');
        $this->setDescription('Synchronizes one, multiple or all LDAP users that the current config has access to.');
        $this->addOption('login', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'List of users to synchronize. If not specified, all users in LDAP are synchronized.');
        $this->addOption('skip-existing', null, InputOption::VALUE_NONE,
            "Skip users that have been synchronized at least once. Using this option will be much faster, but will not "
            . "update user info if it has changed in LDAP.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logins = $input->getOption('login');
        $skipExisting = $input->getOption('skip-existing');

        if (empty($logins)) {
            $logins = $this->ldapUsers->getAllUserLogins();
        }

        $count = 0;
        $failed = array();

        foreach ($logins as $login) {
            if ($skipExisting
                && $this->userExistsInPiwik($login)
            ) {
                $output->write("Skipping '$login', already exists in Piwik...");
                continue;
            }

            $output->write("Synchronizing '$login'...  ");

            try {
                $this->loginLdapAPI->synchronizeUser($login);

                ++$count;

                $output->writeln("<info>success!</info>");
            } catch (Exception $ex) {
                $failed[] = array('login' => $login, 'reason' => $ex->getMessage());

                $output->writeln("<error>failed!</error>");
            }
        }

        $this->writeSuccessMessage($output, array("Synchronized $count users!"));

        if (!empty($failed)) {
            $output->writeln("<info>Could not synchronize the following users in LDAP:</info>");
            foreach ($failed as $missingLogin) {
                $output->writeln($missingLogin['login'] . "\t\t<comment>{$missingLogin['reason']}</comment>");
            }
        }

        return count($failed);
    }

    private function userExistsInPiwik($login)
    {
        return $this->usersManagerAPI->userExists($login);
    }
}