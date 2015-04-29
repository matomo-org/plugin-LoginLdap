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
use Piwik\Container\StaticContainer;
use Psr\Log\LoggerInterface;

/**
 * LDAP Client. Supports connecting to LDAP servers, binding to resource DNs and executing
 * LDAP queries.
 */
class Client
{
    const DEFAULT_TIMEOUT_SECS = 15;

    private static $initialBindErrorCodesToIgnore = array(
        7, // LDAP_AUTH_METHOD_NOT_SUPPORTED
        8, // LDAP_STRONG_AUTH_REQUIRED
        48, // LDAP_INAPPROPRIATE_AUTH
        49, // LDAP_INVALID_CREDENTIALS
        50, // LDAP_INSUFFICIENT_ACCESS
    );

    /**
     * The LDAP connection resource. Set to the result of `ldap_connect`.
     *
     * @var resource
     */
    private $connectionResource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param string|null $serverHostName The hostname of the LDAP server. If not null, an attempt
     *                                    to connect is made.
     * @param int $port The server port to use.
     * @throws Exception if a connection is attempted and it fails.
     */
    public function __construct($serverHostName = null, $port = ServerInfo::DEFAULT_LDAP_PORT, $timeout = self::DEFAULT_TIMEOUT_SECS,
                                LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: StaticContainer::get('Psr\Log\LoggerInterface');

        if (!empty($serverHostName)) {
            $this->connect($serverHostName, $port, $timeout);
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
     * @param int $timeout The timeout in seconds of the network connection.
     * @throws Exception If an error occurs during the `ldap_connect` call or if there is a connection
     *                   issue during the subsequent anonymous bind.
     */
    public function connect($serverHostName, $port = ServerInfo::DEFAULT_LDAP_PORT, $timeout = self::DEFAULT_TIMEOUT_SECS)
    {
        $this->closeIfCurrentlyOpen();

        $this->logger->debug("Calling ldap_connect('{host}', {port})", array('host' => $serverHostName, 'port' => $port));

        $this->connectionResource = ldap_connect($serverHostName, $port);

        ldap_set_option($this->connectionResource, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->connectionResource, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($this->connectionResource, LDAP_OPT_NETWORK_TIMEOUT, $timeout);

        $this->logger->debug("ldap_connect result is {result}", array('result' => $this->connectionResource));

        // ldap_connect will not always try to connect to the server, so execute a bind
        // to test the connection
        try {
            ldap_bind($this->connectionResource);

            $this->logger->debug("anonymous ldap_bind call finished; connection ok");
        } catch (Exception $ex) {
            // if the error was due to a connection error, rethrow, otherwise ignore it
            $errno = ldap_errno($this->connectionResource);

            $this->logger->debug("anonymous ldap_bind returned error '{err}'", array('err' => $errno));

            if (!in_array($errno, self::$initialBindErrorCodesToIgnore)) {
                throw $ex;
            }
        }

        if (!$this->isOpen()) { // sanity check
            throw new Exception("sanity check failed: ldap_connect did not return a connection resource!");
        }
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
     * @return bool
     */
    public function bind($resourceDn, $password)
    {
        $connectionResource = $this->connectionResource;

        $this->logger->debug("Calling ldap_bind({conn}, '{dn}', <password[length={passlen}]>)", array(
            'conn' => $connectionResource,
            'dn' => $resourceDn,
            'passlen' => strlen($password)
        ));

        $result = ldap_bind($connectionResource, $resourceDn, $password);

        $this->logger->debug("ldap_bind result is '{result}'", array('result' => (int)$result));

        return $result;
    }

    /**
     * Performs a search of LDAP entities on the currently bound LDAP connection and
     * returns the result.
     *
     * All PHP errors triggered by ldap_* calls are wrapped in exceptions and thrown.
     *
     * @param string $baseDn The base DN to use.
     * @param string $ldapFilter The LDAP filter string, ie, `"(&(...)(...))"`. This client allows you to use
     *                           `"?"` placeholders in the string.
     * @param array $filterBind Bind parameters for $ldapFilter.
     * @param array $attributes The LDAP entry attributes to fetch. If empty, selects all of them.
     * @return array|null The result of `ldap_get_entries` or null if `ldap_search` fails somehow.
     * @throws Exception If an error occurs during the `ldap_search` or `ldap_get_entries` calls.
     */
    public function fetchAll($baseDn, $ldapFilter, $filterBind = array(), $attributes = array())
    {
        $ldapFilter = $this->bindFilterParameters($ldapFilter, $filterBind);

        $searchResultResource = $this->initiateSearch($baseDn, $ldapFilter, $attributes);

        if (!empty($searchResultResource)) {
            $connectionResource = $this->connectionResource;

            $this->logger->debug("Calling ldap_get_entries({conn}, {result})", array(
                'conn' => $connectionResource,
                'result' => $searchResultResource
            ));

            $ldapInfo = ldap_get_entries($connectionResource, $searchResultResource);

            $this->logger->debug("ldap_get_entries result is {result}", array('result' => $ldapInfo === null ? 'null' : 'not null'));

            return $this->transformLdapInfo($ldapInfo);
        } else {
            return null;
        }
    }

    /**
     * Returns the count of LDAP entries that match a filter.
     *
     * All PHP errors triggered by ldap_* calls are wrapped in exceptions and thrown.
     *
     * @param string $baseDn The base DN to use.
     * @param string $ldapFilter The LDAP filter string, ie, `"(&(...)(...))"`. This client allows you to use
     *                           `"?"` placeholders in the string.
     * @param array $filterBind Bind parameters for $ldapFilter.
     * @return int The count of matched entries.
     * @throws Exception If an error occurs during the `ldap_search` or `ldap_count_entries` calls, or if
     *                   `ldap_search` returns null.
     */
    public function count($baseDn, $ldapFilter, $filterBind = array())
    {
        $ldapFilter = $this->bindFilterParameters($ldapFilter, $filterBind);

        $searchResultResource = $this->initiateSearch($baseDn, $ldapFilter);

        if (!empty($searchResultResource)) {
            $connectionResource = $this->connectionResource;

            $this->logger->debug("Calling ldap_count_entries({conn}, {result})", array(
                'conn' => $connectionResource,
                'result' => $searchResultResource
            ));

            $result = ldap_count_entries($connectionResource, $searchResultResource);

            $this->logger->debug("ldap_count_entries returned {result}", array('result' => $result));

            return $result;
        } else {
            $this->logger->warning("Unexpected error: ldap_search returned null, extra info: {err}", array('err' => ldap_error($this->connectionResource)));

            throw new Exception("Unexpected error: ldap_search returned null.");
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

        $this->logger->debug("Calling ldap_close({conn})", array('conn' => $connectionResource));

        $result = ldap_close($connectionResource);

        $this->logger->debug("ldap_close returned {result}", array('result' => $result ? 'true' : 'false'));

        return$result;
    }

    private function closeIfCurrentlyOpen()
    {
        if ($this->isOpen()) {
            $this->doClose();

            $this->connectionResource = null;
        }
    }

    private function bindFilterParameters($ldapFilter, $bind)
    {
        $idx = 0;
        return preg_replace_callback("/(?<!\\\\)[?]/", function ($matches) use (&$idx, $bind) {
            if (!isset($bind[$idx])) {
                return "?";
            }

            $result = Client::escapeFilterParameter($bind[$idx]);

            ++$idx;

            return $result;
        }, $ldapFilter);
    }

    /**
     * Converts information returned by `ldap_search` into a normal PHP array.
     *
     * `ldap_search` returns results like this:
     *
     *     array(
     *         'count' => '2',
     *         '0' => array(
     *             'count' => 1,
     *             'cn' => array(
     *                 'count' => 1,
     *                 '0' => 'value'
     *             )
     *         ),
     *         '1' => array(
     *             'count' => 1,
     *             'objectclass => array(
     *                 'count' => 2,
     *                 '0' => 'inetOrgPerson',
     *                 '1' => 'top'
     *             )
     *         )
     *     )
     *
     * This method will convert that to:
     *
     *     array(
     *         'cn' => 'value',
     *         'objectclass' => array('inetOrgPerson', 'top')
     *     )
     *
     */
    private function transformLdapInfo($ldapInfo)
    {
        $result = array();

        $processedKeys = array('count');

        $count = @$ldapInfo['count'];
        for ($i = 0; $i < $count; ++$i) {
            if (!isset($ldapInfo[$i])) {
                continue;
            }

            $value = $ldapInfo[$i];

            if (is_array($value)) { // index is for array, ie 0 => array(...)
                $result[$i] = $this->transformLdapInfo($value);
            } else if (!is_numeric($value)
                && isset($ldapInfo[$value])
            ) { // index is for name of attribute, ie 0 => 'cn', 'cn' => array(...)
                $key = strtolower($value);

                if (is_array($ldapInfo[$value])) {
                    $result[$key] = $this->transformLdapInfo($ldapInfo[$value]);

                    if (count($result[$key]) == 1) {
                        $result[$key] = reset($result[$key]);
                    }
                } else {
                    $result[$key] = $ldapInfo[$value];
                }

                $processedKeys[] = $key;
            } else { // index is for attribute value
                $result[$i] = $value;
            }
        }

        // process keys that have no associated index (ie, a 'dn' => that has no N => 'dn')
        foreach ($ldapInfo as $key => $value) {
            if (is_int($key)) {
                continue;
            }

            $key = strtolower($key);
            if (in_array($key, $processedKeys)) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function initiateSearch($baseDn, $ldapFilter, $attributes = array())
    {
        $connectionResource = $this->connectionResource;

        $this->logger->debug("Calling ldap_search({conn}, '{dn}', '{filter}')", array(
            'conn' => $connectionResource,
            'dn' => $baseDn,
            'filter' => $ldapFilter
        ));

        $result = ldap_search($connectionResource, $baseDn, $ldapFilter, $attributes);

        $this->logger->debug("ldap_search result is {result}", array('result' => $result));

        return $result;
    }

    /**
     * Escapes an LDAP string for use in a filter.
     *
     * @param mixed $value The value that should be inserted into an LDAP filter. Converted to
     *                     a string before being escaped.
     * @return string The escaped string.
     */
    public static function escapeFilterParameter($value)
    {
        $value = (string) $value;

        if (function_exists('ldap_escape')) { // available in PHP 5.6
            return ldap_escape($value, $ignoreChars = "", LDAP_ESCAPE_FILTER);
        } else {
            return preg_replace_callback("/[*()\\\\]/", function ($matches) { // replace special filter characters
                return "\\" . bin2hex($matches[0]);
            }, $value);
        }
    }
}