<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\LdapInterop;

use Piwik\Access;
use Piwik\Log;
use Piwik\Plugins\LoginLdap\Config;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\UsersManager\Model as UserModel;
use Piwik\Site;

/**
 * Synchronizes LDAP user information with the Piwik database.
 *
 * LDAP user information is synchronized with the Piwik database every time a user
 * logs in.
 *
 * ### Synchronizing User Information
 *
 * In order to display and use LDAP information without having to connect to LDAP
 * on every request, some LDAP information is synchronized with Piwik's database.
 *
 * This information includes:
 *
 * - first name
 * - last name
 * - alias
 * - email address
 *
 * **Allowing token_auth authentication**
 *
 * To allow authenticating by token auth for LDAP users, a dummy password is generated
 * and stored in Piwik's database. Token auth authentication is then done in the same
 * way as w/o any special Login.
 *
 * The generated password is prefixed with `{LDAP}` so LDAP users can be differentiated
 * from normal users.
 *
 * ### Synchronizing User Access
 *
 * User access can be specified in custom LDAP attributes. To learn more, read the
 * {@link UserAccessMapper} and {@link UserAccessAttributeParser} docs.
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
     * UserAccessMapper instance used to determine Piwik user access using LDAP user entities.
     *
     * @var UserAccessMapper
     */
    private $userAccessMapper;

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
     * @param string $piwikLogin The username of the user who will be synchronized.
     * @param string[] $ldapUser The LDAP user, eg, `array('uid' => ..., 'objectclass' => array(...), ...)`.
     * @return string[] The Piwik user that was added. Will not contain the MD5 password
     *                  hash in order to prevent accidental leaks.
     */
    public function synchronizeLdapUser($piwikLogin, $ldapUser)
    {
        $userMapper = $this->userMapper;
        $usersManagerApi = $this->usersManagerApi;
        $userModel = $this->userModel;
        $newUserDefaultSitesWithViewAccess = $this->newUserDefaultSitesWithViewAccess;
        return Access::doAsSuperUser(function () use ($piwikLogin, $ldapUser, $userMapper, $usersManagerApi, $userModel, $newUserDefaultSitesWithViewAccess) {
            $piwikLogin = $userMapper->getExpectedLdapUsername($piwikLogin);

            $existingUser = $userModel->getUser($piwikLogin);

            $user = $userMapper->createPiwikUserFromLdapUser($ldapUser, $existingUser);

            Log::debug("UserSynchronizer::synchronizeLdapUser: synchronizing user [ piwik login = %s, ldap login = %s ]", $piwikLogin, $user['login']);

            if (empty($existingUser)) {
                $usersManagerApi->addUser($user['login'], $user['password'], $user['email'], $user['alias'], $isPasswordHashed = true);

                // set new user view access
                if (!empty($newUserDefaultSitesWithViewAccess)) {
                    $usersManagerApi->setUserAccess($user['login'], 'view', $newUserDefaultSitesWithViewAccess);
                }
            } else {
                if (!UserMapper::isUserLdapUser($existingUser)) {
                    Log::warning("Unable to synchronize LDAP user '%s', non-LDAP user with same name exists.", $existingUser['login']);
                } else {
                    $usersManagerApi->updateUser($user['login'], $user['password'], $user['email'], $user['alias'], $isPasswordHashed = true);
                }
            }

            return $usersManagerApi->getUser($user['login']);
        });
    }

    /**
     * Uses information in LDAP user entity to set access levels in Piwik.
     *
     * @param string $piwikLogin The username of the Piwik user whose access will be set.
     * @param string[] $ldapUser The LDAP entity to use when synchronizing.
     */
    public function synchronizePiwikAccessFromLdap($piwikLogin, $ldapUser)
    {
        if (empty($this->userAccessMapper)) {
            return;
        }

        $userAccess = $this->userAccessMapper->getPiwikUserAccessForLdapUser($ldapUser);
        if (empty($userAccess)) {
            Log::warning("UserSynchronizer::%s: User '%s' has no access in LDAP, but access synchronization is enabled.",
                __FUNCTION__, $piwikLogin);

            return;
        }

        $this->userModel->deleteUserAccess($piwikLogin);

        $usersManagerApi = $this->usersManagerApi;
        foreach ($userAccess as $userAccessLevel => $sites) {
            Access::doAsSuperUser(function () use ($usersManagerApi, $userAccessLevel, $sites, $piwikLogin) {
                if ($userAccessLevel == 'superuser') {
                    $usersManagerApi->setSuperUserAccess($piwikLogin, true);
                } else {
                    $usersManagerApi->setUserAccess($piwikLogin, $userAccessLevel, $sites);
                }
            });
        }
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
     * Gets the {@link $userAccessMapper} property.
     *
     * @return UserAccessMapper
     */
    public function getUserAccessMapper()
    {
        return $this->userAccessMapper;
    }

    /**
     * Sets the {@link $userAccessMapper} property.
     *
     * @param UserAccessMapper $userAccessMapper
     */
    public function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
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

        if (Config::isAccessSynchronizationEnabled()) {
            $result->setUserAccessMapper(UserAccessMapper::makeConfigured());

            Log::debug("UserSynchronizer::%s(): Using UserAccessMapper when synchronizing users.", __FUNCTION__);
        } else {
            Log::debug("UserSynchronizer::%s(): LDAP access synchronization not enabled.", __FUNCTION__);
        }

        $defaultSitesWithViewAccess = Config::getDefaultSitesToGiveViewAccessTo();
        if (!empty($defaultSitesWithViewAccess)) {
            $siteIds = Access::doAsSuperUser(function () use ($defaultSitesWithViewAccess) {
                return Site::getIdSitesFromIdSitesString($defaultSitesWithViewAccess);
            });

            if (empty($siteIds)) {
                Log::warning("UserSynchronizer::%s(): new_user_default_sites_view_access INI config option has no "
                           . "entries. Newly synchronized users will not have any access.", __FUNCTION__);
            }

            $result->setNewUserDefaultSitesWithViewAccess($siteIds);
        }

        Log::debug("UserSynchronizer::%s: configuring with defaultSitesWithViewAccess = %s", __FUNCTION__, $defaultSitesWithViewAccess);

        return $result;
    }
}