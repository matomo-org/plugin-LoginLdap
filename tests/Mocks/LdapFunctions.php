<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\Ldap;

// mocks ldap_* functions for Ldap\Client class
class LdapFunctions
{
    public static $phpUnitMock;

    public static function __callStatic($name, $arguments)
    {
        if (isset(self::$phpUnitMock)) {
            return call_user_func_array(array(self::$phpUnitMock, $name), $arguments);
        } else {
            return call_user_func_array("\\" . $name, $arguments);
        }
    }
}

function ldap_connect($hostname, $port) {
    return LdapFunctions::ldap_connect($hostname, $port);
}

function ldap_set_option($connection, $optionName, $optionValue) {
    return LdapFunctions::ldap_set_option($connection, $optionName, $optionValue);
}

function ldap_bind($connection, $resourceDn, $password) {
    return LdapFunctions::ldap_bind($connection, $resourceDn, $password);
}

function ldap_search($connection, $baseDn, $filter) {
    return LdapFunctions::ldap_search($connection, $baseDn, $filter);
}

function ldap_get_entries($connection, $searchResultResource) {
    return LdapFunctions::ldap_get_entries($connection, $searchResultResource);
}

function ldap_close($connection) {
    return LdapFunctions::ldap_close($connection);
}