<?php
/**
 * Piwik - Open source web analytics
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
     * @return string
     */
    public static function getLogPath()
    {
        return PIWIK_INCLUDE_PATH . self::LDAP_LOG_FILE;
    }

    /**
     * @param $text
     */
    private function LdapLog($text, $isDebug = 0)
    {
        $debugEnabled = @Config::getInstance()->LoginLdap['debugEnabled'];
        if ($debugEnabled == "" || $debugEnabled == "false") {
            $debugEnabled = false;
        }
        if ($isDebug == 0 or ($isDebug == 1 and $debugEnabled == true)) {
            if (self::LDAP_LOG_FILE) {
                $path = $this->getLogPath();
                $f = fopen($path, 'a');
                if ($f != null) {
                    fwrite($f, strftime('%F %T') . ": $text\n");
                    fclose($f);
                }
            }
        }
    }


    /**
     * Authenticates user
     *
     * @return AuthResult
     */
    public function authenticate()
    {
        try {
            $kerberosEnabled = Config::getInstance()->LoginLdap['useKerberos'];
            if ($kerberosEnabled == "" || $kerberosEnabled == "false") {
                $kerberosEnabled = false;
                $this->LdapLog("INFO: ldapauth authenticate() - try kerberosEnabled: false.", 1);
            }
        } catch (Exception $ex) {
            $kerberosEnabled = false;
            $this->LdapLog("WARN: ldapauth authenticate() - catch kerberosEnabled: false. " . $ex->getMessage(), 1);
        }
        if ($kerberosEnabled && isset($_SERVER['REMOTE_USER'])) {
            if (strlen($_SERVER['REMOTE_USER']) > 1) {
                $kerbLogin = $_SERVER['REMOTE_USER'];
                $this->login = preg_replace('/@.*/', '', $kerbLogin);
                $this->password = '';
                $this->LdapLog("INFO: ldapauth authenticate() - REMOTE_USER: " . $this->login, 0);
            } else {
                $this->LdapLog("WARN: ldapauth authenticate() - REMOTE_USER string too short!", 1);
            }
        } else {
            $this->LdapLog("INFO: ldapauth authenticate() - kerberos not enabled or REMOTE_USER not set!", 1);
        }

        if (is_null($this->login)) {

            $model = new UserModel();
            $user = $model->getUserByTokenAuth($this->token_auth);

            if (!empty($user['login'])) {
                $this->LdapLog("INFO: ldapauth authenticate() - token login success.", 0);
                $code = $user['superuser_access'] ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;

                return new AuthResult($code, $user['login'], $this->token_auth);
            } else {
                $this->LdapLog("WARN: ldapauth authenticate() - token login tried, but user info missing!", 1);
            }
        } else if (!empty($this->login)) {

            $ldapException = null;
            if ($this->login != "anonymous") {
                try {
                    if ($this->authenticateLDAP($this->login, $this->password, $kerberosEnabled)) {
                        $this->LdapLog("INFO: ldapauth authenticate() - not anonymous login ok by authenticateLDAP().", 0);
                        $model = new UserModel();
                        $user = $model->getUserByTokenAuth($this->token_auth);
                        $code = $user['superuser_access'] ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;
                        return new AuthResult($code, $this->login, $this->token_auth);
                    } else {
                        $this->LdapLog("WARN: ldapauth authenticate() - not anonymous login failed by authenticateLDAP()!", 1);
                    }
                } catch (Exception $ex) {
                    $this->LdapLog("WARN: ldapauth authenticate() - not anonymous login exception: " . $ex->getMessage(), 1);
                    $ldapException = $ex;
                }

                $this->LdapLog("INFO: ldapauth authenticate() - login: " . $this->login, 0);
                $login = $this->login;

                $model = new UserModel();
                $user = $model->getUser($login);

                $userToken = null;
                if (!empty($user['token_auth'])) {
                    $userToken = $user['token_auth'];
                }

                if (!empty($userToken)
                    && (($this->getHashTokenAuth($login, $userToken) === $this->token_auth)
                        || $userToken === $this->token_auth)
                ) {
                    $this->setTokenAuth($userToken);
                    $this->LdapLog("INFO: ldapauth authenticate() - success, setTokenAuth: " . $userToken, 0);

                    $code = !empty($user['superuser_access']) ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;

                    return new AuthResult($code, $login, $userToken);
                } else {
                    $this->LdapLog("WARN: ldapauth authenticate() - userToken empty or does not match!", 1);
                }

                if (!is_null($ldapException)) {
                    $this->LdapLog("WARN: ldapauth authenticate() - ldapException: " . $ldapException->getMessage(), 0);
                    throw $ldapException;
                }
            } else {
                $this->LdapLog("WARN: ldapauth authenticate() - login variable is set to anonymous and this is not expected!", 1);
            }
        } else {
            $this->LdapLog("WARN: ldapauth authenticate() - problem with login variable, this should not happen!", 1);
        }

        return new AuthResult(AuthResult::FAILURE, $this->login, $this->token_auth);
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

        if ($sso == true && empty($pwd) && $useKerberos == true) {
            if ($ldapF->kerbthenticate($usr)) {
                $user = Db::fetchOne("SELECT token_auth FROM " . Common::prefixTable('user') . " WHERE login = '" . $usr . "'");
                if (!empty($user)) {
                    $returncode = true;
                    $this->token_auth = $user;
                    $this->LdapLog("INFO: ldapauth authenticateLDAP() - token for kerberos user found.", 1);
                } else {
                    $this->LdapLog("WARN: ldapauth authenticateLDAP() - token for kerberos user not found in DB!", 1);
                }
            } else {
                $this->LdapLog("WARN: ldapauth authenticateLDAP() - kerbthenticate() called and failed!", 1);
            }
        } else {
            if ($ldapF->authenticateFu($usr, $pwd)) {
                $user = Db::fetchOne("SELECT token_auth FROM " . Common::prefixTable('user') . " WHERE login = '" . $usr . "'");
                if (!empty($user)) {
                    $returncode = true;
                    $this->token_auth = $user;
                    $this->LdapLog("INFO: ldapauth authenticateLDAP() - token for ldap user found.", 1);
                } else {
                    $this->LdapLog("WARN: ldapauth authenticateLDAP() - token for ldap user not found in DB!", 1);
                    if ( $autoCreateUser == true) {
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
        }
        return $returncode;
    }


    /**
    * Authenticates the user and initializes the session.
    */
    public function initSession($login, $password, $rememberMe)
    {
        $md5Password = md5($password);
        $tokenAuth = API::getInstance()->getTokenAuth($login, $md5Password);

        $this->setLogin($login);
        $this->setTokenAuth($tokenAuth);
        $this->setPassword($password);
        $authResult = $this->authenticate();

        $authCookieName = Config::getInstance()->General['login_cookie_name'];
        $authCookieExpiry = $rememberMe ? time() + Config::getInstance()->General['login_cookie_expire'] : 0;
        $authCookiePath = Config::getInstance()->General['login_cookie_path'];
        $cookie = new Cookie($authCookieName, $authCookieExpiry, $authCookiePath);
        if (!$authResult->wasAuthenticationSuccessful()) {
            $cookie->delete();
            $this->LdapLog("initSession LoginPasswordNotCorrect");
            throw new Exception(Piwik::translate('Login_LoginPasswordNotCorrect'));
        }

        $cookie->set('login', $login);
        $cookie->set('token_auth', $this->getHashTokenAuth($login, $authResult->getTokenAuth()));
        $cookie->setSecure(ProxyHttp::isHttps());
        $cookie->setHttpOnly(true);
        $cookie->save();
        @Session::regenerateId();

        // remove password reset entry if it exists
        LoginLdap::removePasswordResetInfo($login);
    }
}
