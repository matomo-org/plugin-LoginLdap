<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap\LdapInterop;

use Piwik\Access;
use Piwik\ArchiveProcessor\PluginsArchiver;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Db;
use Piwik\Plugins\LoginLdap\Config;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\UsersManager\Model as UserModel;
use Piwik\Plugins\UsersManager\UserUpdater;
use Piwik\Site;
use Piwik\Log\LoggerInterface;

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
     * @var UserUpdater
     */
    private $userUpdater;

    /**
     * The site IDs to grant view access to for every new LDAP user that is synchronized.
     * Defaults to the `[LoginLdap] new_user_default_sites_view_access` INI config option.
     *
     * @var int[]
     */
    private $newUserDefaultSitesWithViewAccess = array();

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    public static $skipPasswordConfirmation = false;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: StaticContainer::get(LoggerInterface::class);
    }

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
        $userUpdater = $this->userUpdater;
        $newUserDefaultSitesWithViewAccess = $this->newUserDefaultSitesWithViewAccess;
        $logger = $this->logger;
        return Access::doAsSuperUser(function () use ($piwikLogin, $ldapUser, $userMapper, $usersManagerApi, $userModel, $newUserDefaultSitesWithViewAccess, $logger, $userUpdater) {
            $piwikLogin = $userMapper->getExpectedLdapUsername($piwikLogin);

            $existingUser = $userModel->getUser($piwikLogin);

            $user = $userMapper->createPiwikUserFromLdapUser($ldapUser, $existingUser);

            $logger->debug("UserSynchronizer::{func}: synchronizing user [ piwik login = {piwikLogin}, ldap login = {ldapLogin} ]", array(
                'func' => 'synchronizeLdapUser',
                'piwikLogin' => $piwikLogin,
                'ldapLogin' => $user['login']
            ));

            if (empty($existingUser)) {
                //Need to set this to ensure we can add a new user without any password confirmation, refer skipPasswordConfirmation() in LoginLdap.php for further usage
                self::$skipPasswordConfirmation = true;
                $usersManagerApi->addUser($user['login'], $user['password'], $user['email'], $isPasswordHashed = true);
                self::$skipPasswordConfirmation = false;

                // set new user view access
                if (!empty($newUserDefaultSitesWithViewAccess)) {
                    $usersManagerApi->setUserAccess($user['login'], 'view', $newUserDefaultSitesWithViewAccess);
                }
            } else {
                if (!$userMapper->isUserLdapUser($existingUser['login'])) {
                    $logger->warning("Unable to synchronize LDAP user '{user}', non-LDAP user with same name exists.", array('user' => $existingUser['login']));
                } else {
                    if (Config::getShouldSynchronizeUsersAfterLogin()) {
                        $usersManagerApi::$UPDATE_USER_REQUIRE_PASSWORD_CONFIRMATION = false;
                        $usersManagerApi->updateUser($user['login'], $user['password'], $user['email'], $isPasswordHashed = true, true);
                        $usersManagerApi::$UPDATE_USER_REQUIRE_PASSWORD_CONFIRMATION = true;
                    } else {
                        $userUpdater->updateUserWithoutCurrentPassword($user['login'], $user['password'], $user['email'], $isPasswordHashed = true);
                    }

                    // manually reset ts_password_modified to user creation date since it will just cause sessions to prematurely expire
                    // (note: it is not possible to change LDAP passwords through Matomo)
                    $this->resetUserTsPassword($user['login']);
                }
            }

            $userMapper->markUserAsLdapUser($user['login']);
            if (!empty($existingUser['invite_token'])) {
                $this->autoAcceptInvite($user['login']);
            }

            return $userModel->getUser($user['login']);
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
            $this->logger->warning("UserSynchronizer::{func}: User '{user}' has no access in LDAP, but access synchronization is enabled.", array(
                'func' => __FUNCTION__,
                'user' => $piwikLogin
            ));

            return;
        }

        // for the synchronization, need to reset all user accesses
        $this->userModel->deleteUserAccess($piwikLogin);
        $this->userModel->setSuperUserAccess($piwikLogin, false);

        $usersManagerApi = $this->usersManagerApi;
        foreach ($userAccess as $userAccessLevel => $sites) {
            Access::doAsSuperUser(function () use ($usersManagerApi, $userAccessLevel, $sites, $piwikLogin) {
                if ($userAccessLevel == 'superuser') {
                    if (method_exists('Piwik\Plugins\UsersManager\UserUpdater', 'setSuperUserAccessWithoutCurrentPassword')) {
                        $this->userUpdater->setSuperUserAccessWithoutCurrentPassword($piwikLogin, true);
                    } else {
                        $usersManagerApi->setSuperUserAccess($piwikLogin, true);
                    }
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
     * Sets the {@link $$userUpdater} property.
     *
     * @param UserUpdater $userUpdater
     */
    public function setUserUpdater($userUpdater)
    {
        $this->userUpdater = $userUpdater;
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
        $result->setUserUpdater(new UserUpdater());

        /** @var LoggerInterface $logger */
        $logger = StaticContainer::get(LoggerInterface::class);

        if (Config::isAccessSynchronizationEnabled()) {
            $result->setUserAccessMapper(UserAccessMapper::makeConfigured());

            if (PluginsArchiver::isArchivingProcessActive()) {
                $logger->debug("UserSynchronizer::{func}(): Using UserAccessMapper when synchronizing users.", array('func' => __FUNCTION__));
            }
        } elseif (PluginsArchiver::isArchivingProcessActive()) {
            $logger->debug("UserSynchronizer::{func}(): LDAP access synchronization not enabled.", array('func' => __FUNCTION__));
        }

        $defaultSitesWithViewAccess = Config::getDefaultSitesToGiveViewAccessTo();
        if (!empty($defaultSitesWithViewAccess)) {
            $siteIds = Access::doAsSuperUser(function () use ($defaultSitesWithViewAccess) {
                return Site::getIdSitesFromIdSitesString($defaultSitesWithViewAccess);
            });

            if (empty($siteIds) && PluginsArchiver::isArchivingProcessActive()) {
                $logger->warning("UserSynchronizer::{func}(): new_user_default_sites_view_access INI config option has no "
                    . "entries. Newly synchronized users will not have any access.", array('func' => __FUNCTION__));
            }

            $result->setNewUserDefaultSitesWithViewAccess($siteIds);
        }

        if (PluginsArchiver::isArchivingProcessActive()) {
            $logger->debug("UserSynchronizer::{func}: configuring with defaultSitesWithViewAccess = {sites}", array(
                'func' => __FUNCTION__,
                'sites' => $defaultSitesWithViewAccess
            ));
        }

        return $result;
    }

    private function resetUserTsPassword($login)
    {
        $sql = "UPDATE " . Common::prefixTable('user') . " SET ts_password_modified = date_registered WHERE login = ?";
        Db::query($sql, [$login]);
    }

    private function autoAcceptInvite($login)
    {
        $sql = "UPDATE " . Common::prefixTable('user') . " SET invite_token = null, invite_link_token = null, invite_expired_at = null, invite_accept_at = ?  WHERE login = ?";
        Db::query($sql, [Date::now()->getDatetime(), $login]);
    }
}
