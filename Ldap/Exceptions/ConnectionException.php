<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\Ldap\Exceptions;

use RuntimeException;

/**
 * Custom exception that can be thrown when connection to one or more LDAP servers fails.
 */
class ConnectionException extends RuntimeException
{
}