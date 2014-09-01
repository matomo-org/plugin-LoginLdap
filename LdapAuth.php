<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package Piwik_LoginLdap
 */
namespace Piwik\Plugins\LoginLdap;

use Exception;
use Piwik\AuthResult;
use Piwik\Common;
use Piwik\Cookie;
use Piwik\Config;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\API;
use Piwik\Plugins\UsersManager\Model as UserModel;
use Piwik\ProxyHttp;
use Piwik\Session;
use Piwik\SettingsPiwik;
use Piwik\Log;

require_once PIWIK_INCLUDE_PATH . '/plugins/LoginLdap/LdapFunctions.php';

/**
 *
 * @package Login
 */
class LdapAuth extends \Piwik\Plugins\Login\Auth
{
    protected $login = null;
    protected $password = null;
    protected $token_auth = null;

    const LDAP_LOG_FILE = "/tmp/logs/ldap.log";

    /**
     * Authentication module's name, e.g., "Login"
     *
     * @return string
     */
    public function getName()
    {
        return 'LoginLdap';
    }

    /**
     * @param $text
     */
    private function LdapLog($text, $isDebug = 0)
    {
        // empty
    }

    /**
     * Authenticates user
     *
     * @return AuthResult
     */
    public function authenticate()
    {
        $kerberosEnabled = @Config::getInstance()->LoginLdap['useKerberos'] == 1;
        if ($kerberosEnabled) {
            $httpAuthUser = $this->getAlreadyAuthenticatedLogin();

            if (!empty($httpAuthUser)) {
                $this->login = preg_replace('/@.*/', '', $httpAuthUser);
                $this->password = '';

                Log::info("Performing authentication with HTTP auth user '%s'.", $this->login);
            }
        }

        if ($this->login === null) {
            return $this->authenticateByTokenAuth();
        } else {
            return $this->authenticateByLoginAndPassword($kerberosEnabled);
        }
    }

    /**
     * Accessor to set password
     *
     * @param string $password password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * This method is used for LDAP authentication.
     */
    private function authenticateLDAP($usr, $pwd, $sso)
    {
        $this->LdapLog("INFO: ldapauth authenticateLDAP() - function called and started.", 1);
        $returncode = false;
        try {
            $serverUrl = Config::getInstance()->LoginLdap['serverUrl'];
            $ldapPort = Config::getInstance()->LoginLdap['ldapPort'];
            $baseDn = Config::getInstance()->LoginLdap['baseDn'];
            $uidField = Config::getInstance()->LoginLdap['userIdField'];
            $usernameSuffix = Config::getInstance()->LoginLdap['usernameSuffix'];
            $adminUser = Config::getInstance()->LoginLdap['adminUser'];
            $adminPass = Config::getInstance()->LoginLdap['adminPass'];
            $mailField = Config::getInstance()->LoginLdap['mailField'];
            $aliasField = Config::getInstance()->LoginLdap['aliasField'];
            $memberOf = Config::getInstance()->LoginLdap['memberOf'];
            $filter = Config::getInstance()->LoginLdap['filter'];
            $useKerberos = Config::getInstance()->LoginLdap['useKerberos'];
            $debugEnabled = @Config::getInstance()->LoginLdap['debugEnabled'];
            $autoCreateUser = @Config::getInstance()->LoginLdap['autoCreateUser'];
        } catch (Exception $e) {
            $this->LdapLog("WARN: ldapauth authenticateLDAP() - exception: " . $e->getMessage(), 0);
            return false;
        }

        $ldapF = new LdapFunctions();
        $ldapF->setServerUrl($serverUrl);
        $ldapF->setLdapPort($ldapPort);
        $ldapF->setBaseDn($baseDn);
        $ldapF->setUserIdField($uidField);
        $ldapF->setUsernameSuffix($usernameSuffix);
        $ldapF->setAdminUser($adminUser);
        $ldapF->setAdminPass($adminPass);
        $ldapF->setMailField($mailField);
        $ldapF->setAliasField($aliasField);
        $ldapF->setMemberOf($memberOf);
        $ldapF->setFilter($filter);
        $ldapF->setKerberos($useKerberos);
        $ldapF->setDebug($debugEnabled);
        $ldapF->setAutoCreateUser($autoCreateUser);

        $useWebServerAuth = $sso == true && empty($pwd) && $useKerberos == true;
        if ($ldapF->authenticateFu($usr, $pwd, $useWebServerAuth)) {
            $user = Db::fetchOne("SELECT token_auth FROM " . Common::prefixTable('user') . " WHERE login = '" . $usr . "'");

            if (!empty($user)) {
                $returncode = true;
                $this->token_auth = $user;
                $this->LdapLog("INFO: ldapauth authenticateLDAP() - token for kerberos user found.", 1);
            } else {
                $this->LdapLog("WARN: ldapauth authenticateLDAP() - token for ldap user not found in DB!", 1);

                if (!$useKerberos
                    && $autoCreateUser == true
                ) {
                    $this->LdapLog("DEBUG: ldapauth authenticateLDAP() - autoCreateUser enabled - Trying to create user!", 1);
                    $isSuperUser = Piwik::hasUserSuperUserAccess();
                    Piwik::setUserHasSuperUserAccess();
                    $controller = new \Piwik\Plugins\LoginLdap\Controller;
                    $controller->autoCreateUser($usr);
                    Piwik::setUserHasSuperUserAccess($isSuperUser);
                }
            }
        } else {
            $this->LdapLog("WARN: ldapauth authenticateLDAP() - authenticateFu called and failed!", 1);
        }

        return $returncode;
    }


    /**
    * Authenticates the user and initializes the session.
    */
    public function initSession($login, $password, $rememberMe)
    {
        $this->setPassword($password);
        
        parent::initSession($login, md5($password), $rememberMe);

        // remove password reset entry if it exists
        LoginLdap::removePasswordResetInfo($login);
    }

    private function getAlreadyAuthenticatedLogin()
    {
        if (!isset($_SERVER['REMOTE_USER'])) {
            Log::debug("useKerberos set to 1, but REMOTE_USER not found.");
            return null;
        }

        $remoteUser = $_SERVER['REMOTE_USER'];
        if (strlen($remoteUser) <= 1) {
            Log::debug("REMOTE_USER string length too short (== %s).", strlen($remoteUser));
        }

        return $remoteUser;
    }

    private function authenticateByTokenAuth()
    {
        if (empty($this->token_auth)) {
            Log::debug("authenticateByTokenAuth: token auth is empty.");

            return $this->makeAuthFailure();
        }

        $user = $this->getUserByTokenAuth();

        if (empty($user['login'])) {
            Log::debug("authenticateByTokenAuth failed: no user found for given token auth.");

            return $this->makeAuthFailure();
        }

        return $this->makeSuccessLogin($user);
    }

    private function authenticateByLoginAndPassword($usingWebServerAuth)
    {
        if (empty($this->login)) { // sanity check
            Log::warning("authenticateByLoginAndPassword: empty login encountered.");

            return $this->makeAuthFailure();
        }

        if ($this->login == 'anonymous') { // sanity check
            Log::warning("authenticateByLoginAndPassword: login is 'anonymous', this is not expected.");

            return $this->makeAuthFailure();
        }

        try {
            if ($this->authenticateLDAP($this->login, $this->password, $usingWebServerAuth)) {
                $user = $this->getUserByTokenAuth();

                return $this->makeSuccessLogin($user);
            } else {
                return $this->makeAuthFailure();
            }
        } catch (Exception $ex) {
            Log::debug($ex);

            throw $ex;
        }

        // TODO: removed code that authenticates based on token auth of user. mimics Login Auth's no-password authentication.
        //       not sure if this should be mimiced, but shouldn't be able to login if LDAP fails...
        //       if LDAP login fails but token auth == expected token auth, then there is a syncing error.
    }

    private function getUserByTokenAuth()
    {
        $model = new UserModel();
        return $model->getUserByTokenAuth($this->token_auth);
    }

    private function makeSuccessLogin($userInfo)
    {
        $successCode = $userInfo['superuser_access'] ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;
        return new AuthResult($successCode, $userInfo['login'], $this->token_auth);
    }

    private function makeAuthFailure()
    {
        return new AuthResult(AuthResult::FAILURE, $this->login, $this->token_auth);
    }
}