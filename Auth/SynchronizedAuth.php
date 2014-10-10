<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\Auth;

use Piwik\Log;
use Piwik\Plugins\LoginLdap\Config;
use Piwik\Plugins\LoginLdap\LdapInterop\UserSynchronizer;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\UsersManager\Model as UserModel;

/**
 * TODO
 */
class SynchronizedAuth extends Base
{
    /**
     * TODO
     *
     * @var bool
     */
    private $synchronizeUsersAfterSuccessfulLogin = true;

    /**
     * TODO
     */
    public function authenticate()
    {
        $result = $this->tryNormalAuth($onlySuperUsers = false);
        if ($result->wasAuthenticationSuccessful()) {
            return $result;
        }

        if ($this->synchronizeUsersAfterSuccessfulLogin) {
            Log::debug("SynchronizedAuth::%s: synchronizing users after login disabled, not attempting LDAP authenticate for '%s'.",
                __FUNCTION__, $this->login);

            return $this->makeAuthFailure();
        }

        if (empty($this->password)) {
            Log::debug("SynchronizedAuth::%s: cannot attempt fallback LDAP login for '%s', password not set.",
                __FUNCTION__, $this->login);

            return $this->makeAuthFailure();
        }

        $successful = $this->authenticateByLdap();
        if ($successful) {
            $this->updateUserPassword();

            return $this->makeSuccessLogin($this->getUserForLogin());
        } else {
            return $this->makeAuthFailure();
        }
    }

    /**
     * Gets {@link $synchronizeUsersAfterSuccessfulLogin} property.
     *
     * @return boolean
     */
    public function isSynchronizeUsersAfterSuccessfulLogin()
    {
        return $this->synchronizeUsersAfterSuccessfulLogin;
    }

    /**
     * Sets {@link $synchronizeUsersAfterSuccessfulLogin} property.
     *
     * @param boolean $synchronizeUsersAfterSuccessfulLogin
     */
    public function setSynchronizeUsersAfterSuccessfulLogin($synchronizeUsersAfterSuccessfulLogin)
    {
        $this->synchronizeUsersAfterSuccessfulLogin = $synchronizeUsersAfterSuccessfulLogin;
    }

    private function updateUserPassword()
    {
        $user = $this->getUserForLogin();

        $this->usersModel->updateUser($this->login, $this->password, $user['email'], $user['alias'], $user['token_auth']);
    }

    public static function makeConfigured()
    {
        $result = new SynchronizedAuth();
        $result->setLdapUsers(LdapUsers::makeConfigured());
        $result->setUsersManagerAPI(UsersManagerAPI::getInstance());
        $result->setUsersModel(new UserModel());
        $result->setUserSynchronizer(UserSynchronizer::makeConfigured());
        $result->setSynchronizeUsersAfterSuccessfulLogin(Config::getShouldSynchronizeUsersAfterLogin());
        return $result;
    }
}