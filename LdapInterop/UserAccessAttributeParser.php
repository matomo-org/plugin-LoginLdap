<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\LdapInterop;

use Piwik\Access;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\LoginLdap\Config;
use Piwik\Site;
use Piwik\Url;
use Piwik\SettingsPiwik;
use Psr\Log\LoggerInterface;

/**
 * Parses the values of LDAP attributes that describe an LDAP user's Piwik access.
 *
 * ### Access Attribute Format
 *
 * Access attributes can have different formats, the simplest is simply a list of IDs
 * or `'all'`, eg:
 *
 *     view: 1,2,3
 *     admin: all
 *
 * ### Managing Multiple Piwik Instances
 *
 * If the LDAP server in question manages access for only a single Piwik instance, this
 * will suffice. To support multiple Piwik instances, it is allowed to identify the
 * server instance within the attributes, eg:
 *
 *     view: piwikServerA:1,2,3
 *     view: piwikServerB:1,2,3
 *     admin: piwikServerA:all;piwikServerB:2,3
 *
 * In this example, the user is granted view access for sites 1, 2 & 3 for Piwik instance
 * 'A' and Piwik instance 'B', and is granted admin access for all sites in Piwik instance 'A',
 * but only sites 2 & 3 in Piwik instance 'B'.
 *
 * As demonstrated above, instance ID/site list pairs (ie, `"piwikServerA:1,2,3"`) can be in
 * multiple values, or in a single value separated by a delimiter.
 *
 * The seaparator used to split instance ID/site list pairs and the delimiter used to
 * separate pair from other pairs can both be customized through INI config options.
 *
 * ### Identifying Piwik Instances
 *
 * In the above example, Piwik instances are identified by a special name, ie,
 * `"piwikServerA"` or `"piwikServerB"`. By default, however, instances are identified by
 * the instance's host, port and url. For example:
 *
 *     view: piwikA.myhost.com/path/to/piwik:1,2,3
 *     view: piwikB.myhost.com/path/to/piwik:all
 *     admin: piwikC.com:all
 *     superuser: piwikC.com;piwikD.com
 *
 * If you want to use a specific name, you would have to set the `[LoginLdap] instance_name`
 * INI config option for each of your Piwik instances.
 *
 * _Note: If identifying by URLs with port values, the `[LoginLdap] user\_access\_attribute\_server\_separator`
 * config option should be set to something other than `':'`._
 *
 * ### Access Attribute Flexibility
 *
 * In order to make error conditions as rare as possible, this parser has been coded
 * to be flexible when identifying instance IDs. Any malformed looking access values are
 * logged with at least DEBUG level.
 */
class UserAccessAttributeParser
{
    /**
     * The delimiter that separates individual instance ID/site list pairs from other pairs.
     *
     * For example, if `'#'` is used, the access attribute will be expected to be like:
     *
     *     piwikServerA:1#piwikServerB:2#piwikServerC:3
     *
     * @var string
     */
    private $serverSpecificationDelimiter = ';';

    /**
     * The separator used to separate instance IDs from site ID lists.
     *
     * For example, if `'#'` is used, the access attribute will be expected be like:
     *
     *     piwikServerA#1;piwikServerB#2,3;piwikServerC#3
     *
     * @var string
     */
    private $serverIdsSeparator = ':';

    /**
     * A special name for this Piwik instance. If not null, we check if a specification in
     * an LDAP attribute value applies to this instance if the instance ID contains this value.
     *
     * If null, the instance ID is expected to be this Piwik instance's URL.
     *
     * @var string
     */
    private $thisPiwikInstanceName = null;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: StaticContainer::get('Psr\Log\LoggerInterface');
    }

    /**
     * Parses an LDAP access attribute value and returns the list of site IDs that apply to
     * this specific Piwik instance.
     *
     * @var string $attributeValue eg `"piwikServerA:1,2,3;piwikServerB:4,5,6"`.
     * @return array
     */
    public function getSiteIdsFromAccessAttribute($attributeValue)
    {
        $result = array();

        $instanceSpecs = explode($this->serverSpecificationDelimiter, $attributeValue);
        foreach ($instanceSpecs as $spec) {
            list($instanceId, $sitesSpec) = $this->getInstanceIdAndSitesFromSpec($spec);
            if ($this->isInstanceIdForThisInstance($instanceId)) {
                $result = array_merge($result, $this->getSitesFromSitesList($sitesSpec));
            }
        }

        return $result;
    }

    /**
     * Returns true if an LDAP access attribute value marks a user as a superuser.
     *
     * The superuser attribute doesn't need to have a site list so it just contains
     * a list of instances.
     */
    public function getSuperUserAccessFromSuperUserAttribute($attributeValue)
    {
        $attributeValue = trim($attributeValue);

        if ($attributeValue == 1
            || strtolower($attributeValue) == 'true'
            || empty($attributeValue)
        ) { // special case when not managing multiple Piwik instances
            return true;
        }

        $instanceIds = $this->getSuperUserInstancesFromAttribute($attributeValue);
        foreach ($instanceIds as $instanceId) {
            if ($this->isInstanceIdForThisInstance($instanceId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the {@link $serverSpecificationDelimiter} property value.
     *
     * @return string
     */
    public function getServerSpecificationDelimiter()
    {
        return $this->serverSpecificationDelimiter;
    }

    /**
     * Sets the {@link $serverSpecificationDelimiter} property.
     *
     * @param string $serverSpecificationDelimiter
     */
    public function setServerSpecificationDelimiter($serverSpecificationDelimiter)
    {
        $this->serverSpecificationDelimiter = $serverSpecificationDelimiter;
    }

    /**
     * Returns the {@link $serverIdsSeparator} property value.
     *
     * @return string
     */
    public function getServerIdsSeparator()
    {
        return $this->serverIdsSeparator;
    }

    /**
     * Sets the {@link $serverIdsSeparator} property value.
     *
     * @param string $serverIdsSeparator
     */
    public function setServerIdsSeparator($serverIdsSeparator)
    {
        $this->serverIdsSeparator = $serverIdsSeparator;
    }

    /**
     * Returns the {@link $thisPiwikInstanceName} property value.
     *
     * @return string
     */
    public function getThisPiwikInstanceName()
    {
        return $this->thisPiwikInstanceName;
    }

    /**
     * Sets the {@link $thisPiwikInstanceName} property value.
     *
     * @param string $thisPiwikInstanceName
     */
    public function setThisPiwikInstanceName($thisPiwikInstanceName)
    {
        $this->thisPiwikInstanceName = $thisPiwikInstanceName;
    }

    /**
     * Returns the instance ID and list of sites from an instance ID/sites list pair.
     *
     * @param string $spec eg, `"piwikServerA:1,2,3"`
     * @return string[] contains two string elements
     */
    protected function getInstanceIdAndSitesFromSpec($spec)
    {
        $parts = explode($this->serverIdsSeparator, $spec);

        if (count($parts) == 1) { // there is no instanceId
            $parts = array(null, $parts[0]);
        } else if (count($parts) >= 2) { // malformed server access specification
            $this->logger->debug("UserAccessAttributeParser::{func}: Improper server specification in LDAP access attribute: '{value}'",
                array('func' => __FUNCTION__, 'value' => $spec));

            $parts = array($parts[0], $parts[1]);
        }

        return array_map('trim', $parts);
    }

    /**
     * Returns true if an instance ID string found in LDAP refers to this instance or not.
     *
     * If not instance ID is specified, will always return `true`.
     *
     * @param string $instanceId eg, `"piwikServerA"` or `"piwikA.mysite.com"`
     * @return bool
     */
    protected function isInstanceIdForThisInstance($instanceId)
    {
        if (empty($instanceId)) {
            return true;
        }

        if ($this->thisPiwikInstanceName === null) {
            $result = $this->isUrlThisInstanceUrl($instanceId);
        } else {
            preg_match("/\\b" . preg_quote($this->thisPiwikInstanceName) . "\\b/", $instanceId, $matches);

            if (empty($matches)) {
                $result = false;
            } else {
                if (strlen($matches[0]) != strlen($instanceId)) {
                    $this->logger->debug("UserAccessAttributeParser::{func}: Found extra characters in Piwik instance ID. Whole ID entry = {id}.",
                        array('func' => __FUNCTION__, 'id' => $instanceId));
                }

                $result = true;
            }
        }

        if ($result) {
            $this->logger->debug("UserAccessAttributeParser::{func}: Matched this instance with '{id}'.", array(
                'func' => __FUNCTION__,
                'id' => $instanceId
            ));
        }

        return $result;
    }

    /**
     * Returns list of int site IDs from site list found in LDAP.
     *
     * @param string $sitesSpec eg, `"1,2,3"` or `"all"`
     * @return int[]
     */
    protected function getSitesFromSitesList($sitesSpec)
    {
        return Access::doAsSuperUser(function () use ($sitesSpec) {
            return Site::getIdSitesFromIdSitesString($sitesSpec);
        });
    }

    /**
     * Returns the list of instance IDs in a superuser access attribute value.
     *
     * @return string[]
     */
    protected function getSuperUserInstancesFromAttribute($attributeValue)
    {
        $delimiters = $this->serverIdsSeparator . $this->serverSpecificationDelimiter;
        $result = preg_split("/[" . preg_quote($delimiters) . "]/", $attributeValue);
        return array_map('trim', $result);
    }

    /**
     * Returns true if the supplied instance ID refers to this Piwik instance, false if otherwise.
     * Assumes the instance ID is the base URL to the Piwik instance.
     *
     * @param string $instanceIdUrl
     * @return bool
     */
    protected function isUrlThisInstanceUrl($instanceIdUrl)
    {
        $thisPiwikUrl = SettingsPiwik::getPiwikUrl();
        $thisPiwikUrl = $this->getNormalizedUrl($thisPiwikUrl, $isThisPiwikUrl = true);

        $instanceIdUrl = $this->getNormalizedUrl($instanceIdUrl);

        return $thisPiwikUrl == $instanceIdUrl;
    }

    private function getNormalizedUrl($url, $isThisPiwikUrl = false)
    {
        $parsed = @parse_url($url);
        if (empty($parsed)) {
            if ($isThisPiwikUrl) {
                $this->logger->warning("UserAccessAttributeParser::{func}: Invalid Piwik URL found for this instance '{url}'.",
                    array('func' => __FUNCTION__, 'url' => $url));
            } else {
                $this->logger->debug("UserAccessAttributeParser::{func}: Invalid instance ID URL found '{url}'.",
                    array('func' => __FUNCTION__, 'url' => $url));
            }

            return false;
        }

        if (empty($parsed['scheme'])
            && empty($parsed['host'])
        ) { // parse_url will consider www.example.com the path if there is no protocol
            $url = 'http://' . $url;
            $parsed = @parse_url($url);
        }

        if (empty($parsed['host'])) {
            $this->logger->debug("UserAccessAttributeParser::{func}: Found strange URL - '{url}'.", array(
                'func' => __FUNCTION__,
                'url' => $url
            ));
        }

        if (!isset($parsed['port'])) {
            $parsed['port'] = 80;
        }

        if (substr(@$parsed['path'], -1) !== '/') {
            $parsed['path'] = @$parsed['path'] . '/';
        }

        return $parsed['host'] . ':' . $parsed['port'] . $parsed['path'];
    }

    /**
     * Creates a UserAccessAttributeParser instance using INI configuration.
     *
     * @return UserAccessAttributeParser
     */
    public static function makeConfigured()
    {
        $result = new UserAccessAttributeParser();

        $serverSpecificationDelimiter = Config::getUserAccessAttributeServerSpecificationDelimiter();
        if (!empty($serverSpecificationDelimiter)) {
            $result->setServerSpecificationDelimiter($serverSpecificationDelimiter);
        }

        $serverListSeparator = Config::getUserAccessAttributeServerSiteListSeparator();
        if (!empty($serverListSeparator)) {
            $result->setServerIdsSeparator($serverListSeparator);
        }

        $thisPiwikInstanceName = Config::getDesignatedPiwikInstanceName();
        if (!empty($thisPiwikInstanceName)) {
            $result->setThisPiwikInstanceName($thisPiwikInstanceName);
        } else {
            if ($result->getServerIdsSeparator() == ':') {
                // TODO: remove this warning and move it to the settings page.
                /** @var LoggerInterface $logger */
                $logger = StaticContainer::get('Psr\Log\LoggerInterface');
                $logger->info("UserAttributesParser::{func}: Configured with no instance name so matching by URL, but server/site IDs"
                        . " separator set to special ':' character. This character may show up in URLs in LDAP, which will "
                        . "cause problems. We recommend you use a character not often found in URLs, such as '|'.",
                    array('func' => __FUNCTION__));
            }
        }

        return $result;
    }
}