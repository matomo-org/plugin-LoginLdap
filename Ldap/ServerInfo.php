<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\Ldap;

use Exception;
use Piwik\Plugins\LoginLdap\Config;

/**
 * Describes an LDAP server LoginLdap can connect to.
 */
class ServerInfo
{
    const DEFAULT_LDAP_PORT = 389;

    /**
     * The LDAP server hostname.
     *
     * @var string
     */
    private $serverHostname;

    /**
     * The port to use when connecting to the LDAP server.
     *
     * @var int
     */
    private $serverPort;

    /**
     * The base DN to use when searching the LDAP server. Determines which specific
     * LDAP database is searched.
     *
     * @var string
     */
    private $baseDn;

    /**
     * The 'admin' LDAP user to use when authenticating. This user must have read
     * access to other users so we can search for the person attempting login.
     *
     * @var string
     */
    private $adminUsername;

    /**
     * The password to use when binding w/ the 'admin' LDAP user.
     *
     * @var string
     */
    private $adminPassword;

    /**
     * Constructor.
     *
     * @param string $serverHostname See {@link $serverHostname}.
     * @param string $baseDn See {@link $baseDn}.
     * @param int $serverPort See {@link $serverPort}.
     * @param string|null $adminUsername See {@link $adminUsername}.
     * @param string|null $adminPassword See {@link $adminPassword}.
     */
    public function __construct($serverHostname, $baseDn, $serverPort = self::DEFAULT_LDAP_PORT, $adminUsername = null,
                                $adminPassword = null)
    {
        $this->serverHostname = $serverHostname;
        $this->baseDn = $baseDn;
        $this->serverPort = $serverPort;
        $this->adminUsername = $adminUsername;
        $this->adminPassword = $adminPassword;
    }

    /**
     * Gets the {@link $serverHostname} property.
     *
     * @return string
     */
    public function getServerHostname() {
        return $this->serverHostname;
    }

    /**
     * Sets the {@link $serverHostname} property.
     *
     * @param string $serverHostname
     */
    public function setServerHostname($serverHostname) {
        $this->serverHostname = $serverHostname;
    }

    /**
     * Gets the {@link $serverPort} property.
     *
     * @return int
     */
    public function getServerPort() {
        return $this->serverPort;
    }

    /**
     * Sets the {@link $serverPort} property.
     *
     * @param int $serverPort
     */
    public function setServerPort($serverPort) {
        $this->serverPort = $serverPort;
    }

    /**
     * Gets the {@link $baseDn} property.
     *
     * @return string
     */
    public function getBaseDn() {
        return $this->baseDn;
    }

    /**
     * Sets the {@link $baseDn} property.
     *
     * @param string $baseDn
     */
    public function setBaseDn($baseDn) {
        $this->baseDn = $baseDn;
    }

    /**
     * Gets the {@link $adminUsername} property.
     *
     * @return string
     */
    public function getAdminUsername() {
        return $this->adminUsername;
    }

    /**
     * Sets the {@link $adminUsername} property.
     *
     * @param string $adminUsername
     */
    public function setAdminUsername($adminUsername) {
        $this->adminUsername = $adminUsername;
    }

    /**
     * Gets the {@link $adminPassword} property.
     *
     * @return string
     */
    public function getAdminPassword() {
        return $this->adminPassword;
    }

    /**
     * Sets the {@link $adminPassword} property.
     *
     * @param string $adminPassword
     */
    public function setAdminPassword($adminPassword) {
        $this->adminPassword = $adminPassword;
    }

    /**
     * Returns ServerInfo properties as an array. Array keys are the same keys used in INI
     * config.
     *
     * @return array
     */
    public function getProperties()
    {
        return array(
            'hostname' => $this->getServerHostname(),
            'port' => $this->getServerPort(),
            'base_dn' => $this->getBaseDn(),
            'admin_user' => $this->getAdminUsername(),
            'admin_pass' => $this->getAdminPassword()
        );
    }

    /**
     * Creates a ServerInfo instance from an array of old LoginLdap config data.
     *
     * @return ServerInfo
     */
    public static function makeFromOldConfig()
    {
        $hostname = Config::getConfigOption('serverUrl');
        $baseDn = Config::getConfigOption('baseDn');

        $result = new ServerInfo($hostname, $baseDn);

        $ldapPort = Config::getConfigOption('ldapPort');
        if (!empty($ldapPort)) {
            $result->setServerPort((int) $ldapPort);
        }

        $result->setAdminUsername(Config::getConfigOption('adminUser'));
        $result->setAdminPassword(Config::getConfigOption('adminPass'));

        return $result;
    }

    /**
     * Returns a ServerInfo instance created using options in an INI config section.
     * The INI config section's name is determined by prefixing `'LoginLdap_'` to the
     * server name.
     *
     * The INI config section can have the following information:
     *
     * - **hostname** _(Required)_ The server's hostname.
     * - **base_dn** _(Required)_ The base DN to use with this server.
     * - **port** The port to use when connecting to the server.
     * - **admin_user** The name of an admin user that has read access to other users.
     * - **admin_pass** The password to use when binding with the admin user.
     *
     * @param string $name The name of the LDAP server in config. This value can be
     *                     used in the `[LoginLdap] servers[] = ` config option to
     *                     add an LDAP server to the list of servers LoginLdap will
     *                     connect to.
     * @return ServerInfo
     * @throws Exception if the LDAP server config cannot be found or is missing
     *                   required information.
     */
    public static function makeConfigured($name)
    {
        $config = Config::getServerConfig($name);

        if (empty($config)) {
            throw new Exception("No configuration section [$name] found.");
        }

        if (empty($config['hostname'])) {
            throw new Exception("Required config option 'hostname' not found in [$name] section.");
        }

        if (empty($config['base_dn'])) {
            throw new Exception("Required config option 'base_dn' not found in [$name] section.");
        }

        $hostname = $config['hostname'];
        $baseDn = $config['base_dn'];

        $result = new ServerInfo($hostname, $baseDn);

        $port = $config['port'];
        if (!empty($port)) {
            $result->setServerPort((int) $port);
        }

        $adminUser = $config['admin_user'];
        if (!empty($adminUser)) {
            $result->setAdminUsername($adminUser);
        }

        $adminPass = $config['admin_pass'];
        if (!empty($adminPass)) {
            $result->setAdminPassword($adminPass);
        }

        return $result;
    }

    /**
     * Sets an INI config section using an array of LDAP server info.
     *
     * @param string[] $serverInfo
     * @param bool $forceSave If true, configuration changes are saved before this method exits.
     * @throws Exception if hostname or base_dn are missing from $serverInfo.
     */
    public static function saveServerConfig($serverInfo, $forceSave = true)
    {
        if (empty($serverInfo['name'])) {
            throw new Exception("Server info array has no name!");
        }

        if (empty($serverInfo['hostname'])) {
            throw new Exception("'hostname' property is required for server '{$serverInfo['name']}'.");
        }

        if (empty($serverInfo['base_dn'])) {
            throw new Exception("'base_dn' property is required for server '{$serverInfo['name']}'.");
        }

        $config = \Piwik\Config::getInstance();

        $configSectionName = 'LoginLdap_' . $serverInfo['name'];
        $existingServerInfo = $config->__get($configSectionName);

        $existingPassword = !empty($existingServerInfo['admin_pass']) ? $existingServerInfo['admin_pass'] : "";
        $passwordToSet = !empty($serverInfo['admin_pass']) ? $serverInfo['admin_pass'] : $existingPassword;
        $configSection = array(
            'hostname' => $serverInfo['hostname'],
            'port' => @$serverInfo['port'],
            'base_dn' => $serverInfo['base_dn'],
            'admin_user' => @$serverInfo['admin_user'],
            'admin_pass' => $passwordToSet,
        );

        $config->__set($configSectionName, $configSection);

        if ($forceSave) {
            $config->forceSave();
        }
    }
}