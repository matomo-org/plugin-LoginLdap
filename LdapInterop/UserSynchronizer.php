<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\LdapInterop;

use Exception;
use Piwik\Access;
use Piwik\Config;
use Piwik\Log;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\UsersManager\Model as UserModel;
use Piwik\Site;

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
     * UserModel instance used to access user data. We don't go through the API in
     * order to avoid thrown exceptions.
     *
     * @var UserModel
     */
    private $userModel;

    /**
     * The site IDs to grant view access to for every new LDAP user that is synchronized.
     * Defaults to the `[LoginLdap] new_user_default_sites_view_access` INI config option.
     *
     * @var int[]
     */
    private $newUserDefaultSitesWithViewAccess = array();

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
        $userModel = $this->userModel;
        $newUserDefaultSitesWithViewAccess = $this->newUserDefaultSitesWithViewAccess;
        return Access::doAsSuperUser(function () use ($user, $usersManagerApi, $userModel, $newUserDefaultSitesWithViewAccess) {
            $existingUser = $userModel->getUser($user['login']);
            if (empty($existingUser)) {
                $usersManagerApi->addUser($user['login'], $user['password'], $user['email'], $user['alias'], $isPasswordHashed = true);

                // set new user view access
                $usersManagerApi->setUserAccess($user['login'], 'view', $newUserDefaultSitesWithViewAccess);
            } else {
                if (!UserMapper::isUserLdapUser($existingUser)) {
                    Log::warning("Unable to synchronize LDAP user '%s', Piwik user with same name exists.", $existingUser['login']);
                } else {
                    $usersManagerApi->updateUser($user['login'], $user['password'], $user['email'], $user['alias'], $isPasswordHashed = true);
                }
            }
            return $usersManagerApi->getUser($user['login']);
        });
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
     * Gets the {@link $newUserDefaultSitesWithViewAccess} property.
     *
     * @return int[]
     */
    public function getNewUserDefaultSitesWithViewAccess()
    {
        return $this->newUserDefaultSitesWithViewAccess;
    }

    /**
     * Sets the {@link $newUserDefaultSitesWithViewAccess} property.
     *
     * @param int[] $newUserDefaultSitesWithViewAccess
     */
    public function setNewUserDefaultSitesWithViewAccess(array $newUserDefaultSitesWithViewAccess)
    {
        $this->newUserDefaultSitesWithViewAccess = $newUserDefaultSitesWithViewAccess;
    }

    /**
     * Gets the {@link $userModel} property.
     *
     * @return UserModel
     */
    public function getUserModel()
    {
        return $this->userModel;
    }

    /**
     * Sets the {@link $userModel} property.
     *
     * @param UserModel $userModel
     */
    public function setUserModel($userModel)
    {
        $this->userModel = $userModel;
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
        $result->setUserModel(new UserModel());

        $loginLdap = Config::getInstance()->LoginLdap;
        if (!empty($loginLdap['new_user_default_sites_view_access'])) {
            $siteIds = Site::getIdSitesFromIdSitesString($loginLdap['new_user_default_sites_view_access']);
            if (empty($siteIds)) {
                Log::warning("UserSynchronizer::%s(): new_user_default_sites_view_access INI config option has no "
                           . "entries. Newly synchronized users will not have any access.", __FUNCTION__);
            }

            $result->setNewUserDefaultSitesWithViewAccess($siteIds);
        }

        return $result;
    }
}