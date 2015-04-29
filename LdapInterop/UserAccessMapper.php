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
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Psr\Log\LoggerInterface;

/**
 * Uses custom LDAP attributes to determine an LDAP user's Piwik permissions
 * (ie, access to what sites and level of access).
 *
 * Note: This class does not set user access in the DB, it only determines what
 * an LDAP user's access should be.
 *
 * See {@link UserSynchronizer} for more information on LDAP user synchronization.
 *
 * ### Custom LDAP Attributes
 *
 * LDAP has no knowledge of Piwik, so the attributes used by this class to determine
 * Piwik access levels are non-standard. The implications of this are different
 * for each LDAP server implementation, but it means if you try to just add
 * a **view** or **superuser** attribute to an LDAP entry, it will probably fail.
 *
 * For OpenLDAP, you will have to modify the schema to allow these attributes.
 */
class UserAccessMapper
{
    /**
     * The name of the LDAP attribute that holds the list of sites the user has
     * view access to.
     *
     * @var string
     */
    private $viewAttributeName = 'view';

    /**
     * The name of the LDAP attribute that holds the list of sites the user has
     * superuser access to.
     *
     * @var string
     */
    private $adminAttributeName = 'admin';

    /**
     * The name of the LDAP attribute that marks a user as a superuser. If the attribute
     * is present but set to nothing (ie, `superuser: `) it will still cause the user to
     * be a super user.
     *
     * @var string
     */
    private $superuserAttributeName = 'superuser';

    /**
     * The UserAccessAttributeParser instance used to the values of LDAP attributes that
     * describe Piwik user access.
     *
     * @var UserAccessAttributeParser
     */
    private $userAccessAttributeParser;

    /**
     * Cache for all site IDs. Set once by {@link getAllSites()}.
     *
     * Maps int site IDs w/ unspecified data.
     *
     * @var array
     */
    private $allSites = null;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: StaticContainer::get('Psr\Log\LoggerInterface');
    }

    /**
     * Returns an array describing an LDAP user's access to Piwik sites.
     *
     * The array will either mark the user as a superuser, in which case it will look like
     * this:
     *
     *     array('superuser' => true)
     *
     * Or it will map user access levels with lists of site IDs, for example:
     *
     *     array(
     *         'view' => array(1,2,3),
     *         'admin' => array(3,4,5)
     *     )
     *
     * @param string[] $ldapUser The LDAP entity information.
     * @return array
     */
    public function getPiwikUserAccessForLdapUser($ldapUser)
    {
        // if the user is a superuser, we don't need to check the other attributes
        if ($this->isSuperUserAccessGrantedForLdapUser($ldapUser)) {
            $this->logger->debug("UserAccessMapper::{func}: user '{user}' found to be superuser", array(
                'func' => __FUNCTION__,
                'user' => array_keys($ldapUser)
            ));

            return array('superuser' => true);
        }

        $sitesByAccess = array();

        if (!empty($ldapUser[$this->viewAttributeName])) {
            $this->addSiteAccess($sitesByAccess, 'view', $ldapUser[$this->viewAttributeName]);
        }

        if (!empty($ldapUser[$this->adminAttributeName])) {
            $this->addSiteAccess($sitesByAccess, 'admin', $ldapUser[$this->adminAttributeName]);
        }

        $accessBySite = array();
        foreach ($sitesByAccess as $site => $access) {
            $accessBySite[$access][] = $site;
        }
        return $accessBySite;
    }

    /**
     * Returns the {@link $viewAttributeName} property.
     *
     * @return string
     */
    public function getViewAttributeName()
    {
        return $this->viewAttributeName;
    }

    /**
     * Sets the {@link $viewAttributeName} property.
     *
     * @param string $viewAttributeName
     */
    public function setViewAttributeName($viewAttributeName)
    {
        $this->viewAttributeName = strtolower($viewAttributeName);
    }

    /**
     * Returns the {@link $viewAttributeName} property.
     *
     * @return string
     */
    public function getAdminAttributeName()
    {
        return $this->adminAttributeName;
    }

    /**
     * Sets the {@link $viewAttributeName} property.
     *
     * @param string $adminAttributeName
     */
    public function setAdminAttributeName($adminAttributeName)
    {
        $this->adminAttributeName = strtolower($adminAttributeName);
    }

    /**
     * Returns the {@link $superuserAttributeName} property.
     *
     * @return string
     */
    public function getSuperuserAttributeName()
    {
        return $this->superuserAttributeName;
    }

    /**
     * Sets the {@link $superuserAttributeName} property.
     *
     * @param string $superuserAttributeName
     */
    public function setSuperuserAttributeName($superuserAttributeName)
    {
        $this->superuserAttributeName = strtolower($superuserAttributeName);
    }

    /**
     * Returns the {@link $userAccessAttributeParser} property.
     *
     * @return UserAccessAttributeParser
     */
    public function getUserAccessAttributeParser()
    {
        return $this->userAccessAttributeParser;
    }

    /**
     * Sets the {@link $userAccessAttributeParser} property.
     *
     * @param UserAccessAttributeParser $userAccessAttributeParser
     */
    public function setUserAccessAttributeParser($userAccessAttributeParser)
    {
        $this->userAccessAttributeParser = $userAccessAttributeParser;
    }

    private function addSiteAccess(&$sitesByAccess, $accessLevel, $viewAttributeValues)
    {
        if (!is_array($viewAttributeValues)) {
            $viewAttributeValues = array($viewAttributeValues);
        }

        $this->logger->debug("UserAccessMapper::{func}(): attribute value for {accessLevel} access is {values}", array(
            'func' => __FUNCTION__,
            'accessLevel' => $accessLevel,
            'values' => $viewAttributeValues
        ));

        $siteIds = array();

        $attributeParser = $this->userAccessAttributeParser;
        Access::doAsSuperUser(function () use (&$siteIds, $viewAttributeValues, $attributeParser) {
            foreach ($viewAttributeValues as $value) {
                $siteIds = array_merge($siteIds, $attributeParser->getSiteIdsFromAccessAttribute($value));
            }
        });

        $this->logger->debug("UserAccessMapper::{func}(): adding {accessLevel} access for sites {sites}", array(
            'func' => __FUNCTION__,
            'accessLevel' => $accessLevel,
            'sites' => $siteIds
        ));

        $allSitesSet = $this->getSetOfAllSites();
        foreach ($siteIds as $idSite) {
            if (!isset($allSitesSet[$idSite])) {
                $this->logger->debug("UserAccessMapper::{func}(): site [ id = {id} ] does not exist, ignoring", array(
                    'func' => __FUNCTION__,
                    'id' => $idSite
                ));

                continue;
            }

            $sitesByAccess[$idSite] = $accessLevel;
        }
    }

    private function getSetOfAllSites()
    {
        if ($this->allSites === null) {
            $this->allSites = array_flip(Access::doAsSuperUser(function () {
                return SitesManagerAPI::getInstance()->getSitesIdWithAtLeastViewAccess();
            }));
        }

        return $this->allSites;
    }

    private function isSuperUserAccessGrantedForLdapUser($ldapUser)
    {
        if (!array_key_exists($this->superuserAttributeName, $ldapUser)) {
            return false;
        }

        $attributeValue = $ldapUser[$this->superuserAttributeName];
        if (!is_array($attributeValue)) {
            $attributeValue = array($attributeValue);
        }

        foreach ($attributeValue as $value) {
            if ($this->userAccessAttributeParser->getSuperUserAccessFromSuperUserAttribute($value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns a configured UserAccessMapper instance. The instance is configured
     * using INI config option values.
     *
     * @return UserAccessMapper
     */
    public static function makeConfigured()
    {
        $result = new UserAccessMapper();
        $result->setUserAccessAttributeParser(UserAccessAttributeParser::makeConfigured());

        $viewAttributeName = Config::getLdapViewAccessField();
        if (!empty($viewAttributeName)) {
            $result->setViewAttributeName($viewAttributeName);
        }

        $adminAttributeName = Config::getLdapAdminAccessField();
        if (!empty($adminAttributeName)) {
            $result->setAdminAttributeName($adminAttributeName);
        }

        $superuserAttributeName = Config::getSuperUserAccessField();
        if (!empty($superuserAttributeName)) {
            $result->setSuperuserAttributeName($superuserAttributeName);
        }

        return $result;
    }
}