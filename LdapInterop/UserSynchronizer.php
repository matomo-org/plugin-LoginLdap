<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\LdapInterop;

use Piwik\Piwik;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
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
     * UsersManager API instance used to add and get users.
     *
     * @var \Piwik\Plugins\UsersManager\API
     */
    private $usersManagerApi;

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

        $usersManagerApi = $this->usersManagerApi;
        return $this->doAsSuperUser(function () use ($user, $usersManagerApi) {
            $usersManagerApi->addUser($user['login'], $user['password'], $user['email'], $user['alias']);

            $addedUser = $usersManagerApi->getUser($user['login']);
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
     * Returns the {@link $userMapper} password.
     *
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * Sets the {@link $userMapper} password.
     *
     * @param UserMapper $userMapper
     */
    public function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Gets the {@link $usersManagerApi} property.
     *
     * @return UsersManagerAPI
     */
    public function getUsersManagerApi()
    {
        return $this->usersManagerApi;
    }

    /**
     * Sets the {@link $usersManagerApi} property.
     *
     * @param UsersManagerAPI $usersManagerApi
     */
    public function setUsersManagerApi(UsersManagerAPI $usersManagerApi)
    {
        $this->usersManagerApi = $usersManagerApi;
    }

    /**
     * Creates a UserSynchronizer using INI configuration.
     *
     * @return UserSynchronizer
     */
    public static function makeConfigured()
    {
        $result = new UserSynchronizer();
        $result->setUserMapper(UserMapper::makeConfigured());
        $result->setUsersManagerApi(UsersManagerAPI::getInstance());
        return $result;
    }
}