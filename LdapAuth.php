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
use Piwik\Config;
use Piwik\Cookie;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\API;
use Piwik\ProxyHttp;
use Piwik\Session;

require_once PIWIK_INCLUDE_PATH . '/plugins/LoginLdap/LdapFunctions.php';

/**
 *
 * @package Login
 */
class LdapAuth implements \Piwik\Auth
{
    protected $login = null;
    protected $password = null;
    protected $token_auth = null;

    private $LdapLogFile = "/plugins/LoginLdap/data/ldap.log";

    /**
     * @return string
     */
    private function LdapGetLogPath()
    {
        return PIWIK_INCLUDE_PATH . $this->LdapLogFile;
    }

    /**
     * @param $text
     */
    private function LdapLog($text)
    {
        if ($this->LdapLogFile) {
            $path = $this->LdapGetLogPath();
            $f = fopen($path, 'a');
            if ($f != NULL) {
                fwrite($f, strftime('%F %T') . ": $text\n");
                fclose($f);
            }
        }
    }


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
     * Authenticates user
     *
     * @return AuthResult
     */
    public function authenticate()
    {
        $rootLogin = Config::getInstance()->superuser['login'];
        $rootPassword = Config::getInstance()->superuser['password'];
        $rootToken = API::getInstance()->getTokenAuth($rootLogin, $rootPassword);

        try {
            $kerberosEnabled = Config::getInstance()->LoginLdap['useKerberos'];
            if ($kerberosEnabled == "") {
                $kerberosEnabled = false;
            }
        } catch (Exception $ex) {
            $kerberosEnabled = false;
            $this->LdapLog("AUTH: kerberosEnabled: false");
        }
        if($kerberosEnabled && isset($_SERVER['REMOTE_USER']))
        {
            $kerbLogin = $_SERVER['REMOTE_USER'];
            $this->login = preg_replace('/@.*/', '', $kerbLogin);
            $this->password = '';
            $this->LdapLog("AUTH: REMOTE_USER: ".$this->login);
        }

        if (is_null($this->login)) {
            if ($this->token_auth === $rootToken) {
                return new AuthResult(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $rootLogin, $this->token_auth);
            }

            $login = Db::fetchOne(
                'SELECT login
                FROM ' . Common::prefixTable('user') . '
                    WHERE token_auth = ?',
                array($this->token_auth)
            );
            if (!empty($login)) {
                $this->LdapLog("AUTH: token login success");
                return new AuthResult(AuthResult::SUCCESS, $login, $this->token_auth);
            }
        } else if (!empty($this->login)) {
            if ($this->login === $rootLogin
                && ($this->getHashTokenAuth($rootLogin, $rootToken) === $this->token_auth)
                || $rootToken === $this->token_auth
            ) {
                $this->setTokenAuth($rootToken);
                return new AuthResult(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $rootLogin, $this->token_auth);
            } else {

                $ldapException = null;
                if ($this->login != "anonymous") {
                    try {
                        if($this->authenticateLDAP($this->login, $this->password, $kerberosEnabled))
                        {
                            $this->LdapLog("AUTH: piwik_auth_result ok");
                            return new AuthResult(AuthResult::SUCCESS, $this->login, $this->token_auth );
                        }
                    } catch (Exception $ex) {
                        $this->LdapLog("AUTH: exception: ".$ex);
                        $ldapException = $ex;
                    }
                
                    $this->LdapLog("AUTH: login: ".$this->login);
                    $login = $this->login;
                    $userToken = Db::fetchOne(
                        'SELECT token_auth
                        FROM ' . Common::prefixTable('user') . '
                            WHERE login = ?',
                        array($login)
                    );

                    if (!empty($userToken)
                        && (($this->getHashTokenAuth($login, $userToken) === $this->token_auth)
                            || $userToken === $this->token_auth)
                    ) {
                        $this->setTokenAuth($userToken);
                        $this->LdapLog("AUTH: setTokenAuth: ".$userToken);
                        return new AuthResult(AuthResult::SUCCESS, $login, $userToken);
                    }

                    if (!is_null($ldapException)) {
                        $this->LdapLog("AUTH: ldapException: ".$ldapException);
                        throw $ldapException;
                    }
                }
            }
        }

        return new AuthResult(AuthResult::FAILURE, $this->login, $this->token_auth);
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

    /**
     * Accessor to set login name
     *
     * @param string $login user login
     */
    public function setLogin($login)
    {
        $this->login = $login;
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
     * Accessor to set authentication token
     *
     * @param string $token_auth authentication token
     */
    public function setTokenAuth($token_auth)
    {
        $this->token_auth = $token_auth;
    }

    /**
     * Accessor to compute the hashed authentication token
     *
     * @param string $login user login
     * @param string $token_auth authentication token
     * @return string hashed authentication token
     */
    public function getHashTokenAuth($login, $token_auth)
    {
        return md5($login . $token_auth);
    }

    /**
     * This method is used for LDAP authentication.
     */
    private function authenticateLDAP($usr,$pwd,$sso)
    {
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
        } catch (Exception $e) {
            $this->LdapLog("AUTH: authenticateLDAP exception: ".$e);
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

        if ($sso == true && empty($pwd) && $useKerberos == TRUE) {
            if ($ldapF->kerbthenticate($usr)) {
                $user = Db::fetchOne("SELECT token_auth FROM ".Common::prefixTable('user')." WHERE login = '".$usr."'");
                if(!empty($user)) {
                    $returncode = true;
                    $this->token_auth = $user;
                }
            }
        } else {
            if ($ldapF->authenticateFu($usr, $pwd)) {
                $user = Db::fetchOne("SELECT token_auth FROM ".Common::prefixTable('user')." WHERE login = '".$usr."'");
                if(!empty($user)) {
                    $returncode = true;
                    $this->token_auth = $user;
                }
            }
        }
        return $returncode;
    }
}
