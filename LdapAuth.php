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
use Piwik\Plugins\UsersManager\Model as UserModel;

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
        try {
            $kerberosEnabled = Config::getInstance()->LoginLdap['useKerberos'];
            if ($kerberosEnabled == "" || $kerberosEnabled == "false") {
                $kerberosEnabled = false;
            }
        } catch (Exception $ex) {
            $kerberosEnabled = false;
            $this->LdapLog("AUTH: kerberosEnabled: false");
        }
        if($kerberosEnabled && isset($_SERVER['REMOTE_USER']))
        {
            if (strlen($_SERVER['REMOTE_USER']) > 1) {
                $kerbLogin = $_SERVER['REMOTE_USER'];
                $this->login = preg_replace('/@.*/', '', $kerbLogin);
                $this->password = '';
                $this->LdapLog("AUTH: REMOTE_USER: ".$this->login);
            }
        }

        if (is_null($this->login)) {

            $model = new UserModel();
            $user  = $model->getUserByTokenAuth($this->token_auth);

            if (!empty($user['login'])) {
                $this->LdapLog("AUTH: token login success");
                $code = $user['superuser_access'] ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;

                return new AuthResult($code, $user['login'], $this->token_auth);
            }
        } else if (!empty($this->login)) {

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

                $model = new UserModel();
                $user  = $model->getUser($login);

                $userToken = null;
                if (!empty($user['token_auth'])) {
                    $userToken = $user['token_auth'];
                }

                if (!empty($userToken)
                    && (($this->getHashTokenAuth($login, $userToken) === $this->token_auth)
                        || $userToken === $this->token_auth)
                ) {
                    $this->setTokenAuth($userToken);
                    $this->LdapLog("AUTH: setTokenAuth: ".$userToken);

                    $code = !empty($user['superuser_access']) ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;

                    return new AuthResult($code, $login, $userToken);
                }

                if (!is_null($ldapException)) {
                    $this->LdapLog("AUTH: ldapException: ".$ldapException);
                    throw $ldapException;
                }
            }
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
