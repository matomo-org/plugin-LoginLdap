<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\Ldap;

use Exception;
use Piwik\Log;
use Piwik\Error;

/**
 * LDAP Client. Supports connecting to LDAP servers, binding to resource DNs and executing
 * LDAP queries.
 */
class Client
{
    const DEFAULT_LDAP_PORT = 389;

    /**
     * The LDAP connection resource ID. Set to the result of `ldap_connect`.
     *
     * @var resource
     */
    private $connectionResource;

    /**
     * Constructor.
     *
     * @param string|null $serverHostName The hostname of the LDAP server. If not null, an attempt
     *                                    to connect is made.
     * @param int $port The server port to use.
     * @throws Exception if a connection is attempted and it fails.
     */
    public function __construct($serverHostName = null, $port = self::DEFAULT_LDAP_PORT)
    {
        if (!empty($serverHostName)) {
            $this->connect($serverHostName, $port);
        }
    }

    /**
     * Tries to connect to an LDAP server.
     *
     * If a connection is currently open, it is closed.
     *
     * All PHP errors triggered by ldap_* calls are wrapped in exceptions and thrown.
     *
     * @param string $serverHostName The hostname of the LDAP server.
     * @param int $port The server port to use.
     * @throws Exception If an error occurs during the `ldap_connect` call.
     */
    public function connect($serverHostName, $port = self::DEFAULT_LDAP_PORT)
    {
        $this->closeIfCurrentlyOpen();

        $this->connectionResource = $this->throwPhpErrors(function () use ($serverHostName, $port) {
            Log::debug("Calling ldap_connect('%s', %s)", $serverHostName, $port);

            $result = ldap_connect($serverHostName, $port);

            Log::debug("ldap_connect result is %s", empty($result) ? "empty" : "not empty");

            return $result;
        });

        if (!$this->isOpen()) { // sanity check
            throw new Exception("sanity check failed: ldap_connect did not return a connection resource!");
        }

        ldap_set_option($this->connectionResource, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->connectionResource, LDAP_OPT_REFERRALS, 0);
    }

    /**
     * Closes a currently open LDAP server connection.
     *
     * If a connection is not open, nothing is done.
     *
     * All PHP errors triggered by ldap_* calls are wrapped in exceptions and thrown.
     *
     * @throws Exception If an error occurs during the `ldap_close` call.
     */
    public function close()
    {
        if ($this->isOpen()) {
            $this->doClose();
        }
    }

    /**
     * Binds to the LDAP server using a resource DN and a password.
     *
     * All PHP errors triggered by ldap_* calls are wrapped in exceptions and thrown.
     *
     * @param string $resourceDn The LDAP resource DN to use when binding.
     * @param string $password The resource's associated password.
     * @throws Exception If an error occurs during the `ldap_bind` call.
     */
    public function bind($resourceDn, $password)
    {
        $connectionResource = $this->connectionResource;
        return $this->throwPhpErrors(function () use ($connectionResource, $resourceDn, $password) {
            Log::debug("Calling ldap_bind(%s, '%s', <password[length=%s]>)", $connectionResource, $resourceDn, strlen($password));

            $result = ldap_bind($connectionResource, $resourceDn, $password);

            Log::debug("ldap_bind result is '%s'", (int)$result);

            return $result;
        });
    }

    /**
     * Performs a search of LDAP entities on the currently bound LDAP connection and
     * returns the result.
     *
     * All PHP errors triggered by ldap_* calls are wrapped in exceptions and thrown.
     *
     * @param string $baseDn The base DN to use.
     * @param string $ldapQuery The LDAP query string, ie, `"(&(...)(...))"`.
     * @return array|null The result of `ldap_get_entries` or null if `ldap_search` fails somehow.
     * @throws Exception If an error occurs during the `ldap_search` or `ldap_get_entries` calls.
     */
    public function fetchAll($baseDn, $ldapQuery)
    {
        $connectionResource = $this->connectionResource;
        $searchResultResource = $this->throwPhpErrors(function () use ($connectionResource, $baseDn, $ldapQuery) {
            Log::debug("Calling ldap_search(%s, '%s', '%s')", $connectionResource, $baseDn, $ldapQuery);

            $result = ldap_search($connectionResource, $baseDn, $ldapQuery);

            Log::debug("ldap_search result is %s", empty($result) ? "empty" : "not empty");

            return $result;
        });

        if (!empty($searchResultResource)) {
            return $this->throwPhpErrors(function () use ($connectionResource, $searchResultResource) {
                Log::debug("Calling ldap_get_entries(%s, %s)", $connectionResource, $searchResultResource);

                $result = ldap_get_entries($connectionResource, $searchResultResource);

                Log::debug("ldap_get_entries result is %s", $result);

                return $result;
            });
        } else {
            return null;
        }
    }

    /**
     * Returns true if there is currently an open connection being managed, false if otherwise.
     *
     * @return bool
     */
    public function isOpen()
    {
        return $this->connectionResource !== null
            && $this->connectionResource !== false;
    }

    private function doClose()
    {
        $connectionResource = $this->connectionResource;
        return $this->throwPhpErrors(function () use ($connectionResource) {
            Log::debug("Calling ldap_close(%s)", $connectionResource);

            $result = ldap_close($connectionResource);

            Log::debug("ldap_close returned %s", $result ? 'true' : 'false');

            return $result;
        });
    }

    private function closeIfCurrentlyOpen()
    {
        if ($this->isOpen()) {
            $this->doClose();

            $this->connectionResource = null;
        }
    }

    private function throwPhpErrors($callback)
    {
        // set an error handler that will catch PHP errors for this function execution
        $errorException = null;
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$errorException) {
            $errorType = Error::getErrNoString($errno);
            $errorException = new Exception("$errorType: $errstr in $errfile on line $errline");

            return true;
        });

        // execute the callback and restore the old event handler before the method exists
        try {
            $result = $callback($this);
        } catch (Exception $ex) {
            restore_error_handler();

            throw $ex;
        }

        restore_error_handler();

        // if a PHP error was caught, throw an exception
        if ($errorException !== null) {
            throw $errorException;
        }

        return $result;
    }
}