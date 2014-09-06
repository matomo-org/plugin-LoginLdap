<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\LdapInterop;

use Piwik\Piwik;
use Piwik\Plugins\UsersManager\API as UsersManagerApi;
use Exception;

/**
 * Synchronizes LDAP user information with the Piwik database.
 *
 * TODO: general information about the synchronization implementation
 */
class UserSynchronizer
{
    /**
     * UserMapper instance used to map LDAP users to Piwik user entities.
     *
     * @var UserMapper
     */
    private $userMapper;

    /**
     * Converts a supplied LDAP entity into a Piwik user that is persisted in
     * the MySQL DB.
     *
     * @param string[] $ldapUser The LDAP user, eg, `array('uid' => ..., 'objectclass' => array(...), ...)`.
     * @return string[] The Piwik user that was added. Will not contain the MD5 password
     *                  hash in order to prevent accidental leaks.
     */
    public function synchronizeLdapUser($ldapUser)
    {
        $user = $this->userMapper->createPiwikUserFromLdapUser($ldapUser);

        return $this->doAsSuperUser(function () use ($user) {
            UsersManagerApi::getInstance()->addUser($user['login'], $user['password'], $user['email'], $user['alias']);

            $addedUser = UsersManagerApi::getInstance()->getUser($user['login']);
            unset($addedUser['password']); // remove password since it shouldn't be needed by caller
            return $addedUser;
        });
    }

    private function doAsSuperUser($function)
    {
        $isSuperUser = Piwik::hasUserSuperUserAccess();

        Piwik::setUserHasSuperUserAccess();

        try {
            $result = $function();
        } catch (Exception $ex) {
            Piwik::setUserHasSuperUserAccess($isSuperUser);

            throw $ex;
        }

        Piwik::setUserHasSuperUserAccess($isSuperUser);

        return $result;
    }

    /**
     * Creates a UserSynchronizer using INI configuration.
     *
     * @return UserSynchronizer
     */
    public static function makeConfigured()
    {
        $result = new UserSynchronizer();
        $result->userMapper = UserMapper::makeConfigured();
        return $result;
    }
}