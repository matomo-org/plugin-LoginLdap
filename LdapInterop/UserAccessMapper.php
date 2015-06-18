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
     * The ldap group memberships, that defines the super access status of a user
     *
     * @var array of groups
     */
    private $membershipSuperAccess = array();

    /**
     * The ldap group memberships, that define view access rights to sites
     *
     * @var multikey key is ldap group and value is an array of site-ids
     */
    private $membershipSiteViewAccess = array();

    /**
     * The ldap group memberships, that define admin access rights to sites
     *
     * @var multikey key is ldap group and value is an array of site-ids
     */
    private $membershipSiteAdminAccess = array();

    /**
     * Cache for all site IDs. Set once by {@link getAllSites()}.
     *
     * Maps int site IDs w/ the site names.
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

        // site access by group memberships
        if( key_exists("groups", $ldapUser)) {
            $groups = $ldapUser["groups"];
            foreach( $groups as $group) {
                if( key_exists($group, $this->membershipSiteViewAccess)) {
                    foreach( $this->membershipSiteViewAccess[$group] as $siteId) {
                        $sitesByAccess[$siteId] = 'view';
                    }
                }
            }
            foreach( $groups as $group) {
                if( key_exists($group, $this->membershipSiteAdminAccess)) {
                    foreach( $this->membershipSiteAdminAccess[$group] as $siteId) {
                        $sitesByAccess[$siteId] = 'admin';
                    }
                }
            }
        }

        // invert siteByAccess to accessBySite meaning siteId => accesslevel to accessLevel => array(siteIds)
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

    /**
     * Return the {@link $membershipSuperAccess} property
     *
     * @return \Piwik\Plugins\LoginLdap\LdapInterop\array
     */
    public function getMembershipSuperAccess()
    {
        return $this->membershipSuperAccess;
    }

    /**
     * Set the {@link $membershipSuperAccess} property from a ";" separated string
     *
     * @param string $membershipSuperAccess group names separated by ";"
     */
    public function setMembershipSuperAccess($membershipSuperAccess)
    {
        $this->membershipSuperAccess = explode(";", $membershipSuperAccess);
    }

    /**
     * Return the {@link $membershipSiteViewAccess} property as multikey array
     *
     * @return \Piwik\Plugins\LoginLdap\LdapInterop\multikey
     */
    public function getMembershipSiteViewAccess()
    {
        return $this->membershipSiteViewAccess;
    }

    /**
     * Set the {@link $membershipSiteViewAccess} property from a ";" separated string
     *
     * @param unknown $membershipSiteViewAccess
     */
    public function setMembershipSiteViewAccess($membershipSiteViewAccess)
    {
        $this->membershipSiteViewAccess = $this->parseMembershipSiteAccessString($membershipSiteViewAccess);
    }

    /**
     * Return the {@link $membershipSiteAdminAccess} property as multikey array
     *
     * @return \Piwik\Plugins\LoginLdap\LdapInterop\multikey
     */
    public function getMembershipSiteAdminAccess()
    {
        return $this->membershipSiteAdminAccess;
    }

    /**
     * Set the {@link $membershipSiteAdminAccess} property from a ";" separated string
     *
     * @param unknown $membershipSiteAdminAccess
     */
    public function setMembershipSiteAdminAccess($membershipSiteAdminAccess)
    {
        $this->membershipSiteAdminAccess = $this->parseMembershipSiteAccessString($membershipSiteAdminAccess);
    }

    /**
     * Parses membershipSiteViewAccess or membershipSiteAdminAccess string into an easily handled multikey array with group as key and array of siteIDs as value
     *
     * @param string $membershipSiteAccessString a string of the form {group1}:1,test.org;{group2}:2,www.example.com defining the ldap group and associated site (by id or name)
     * @return multitype:multitype: array
     */
    private function parseMembershipSiteAccessString($membershipSiteAccessString) {
        // set of all sites with siteId and siteName
        $setOfAllSites = $this->getSetOfAllSites();

        // array with group as key and an array of siteIds as value
        $membershipSiteAccess = array();

        $groups_sites = explode( ";", $membershipSiteAccessString);
        foreach( $groups_sites as $group_sites) {
            list($group, $sites) = explode(":", $group_sites);
            if( $group == NULL || $sites == NULL) {
                continue;
            }
            if( !key_exists($group, $membershipSiteAccess)) {
                $membershipSiteAccess[$group] = array();
            }
            $siteIds = explode(",", $sites);
            foreach( $siteIds as $site) {
                // if @site is a siteId
                if( key_exists($site, $setOfAllSites)) {
                    array_push($membershipSiteAccess[$group], $site);
                } else { // the site could be a site name
                    $siteId = array_search($site, $setOfAllSites);
                    if ($siteId !== false) { // there is a site with that name
                        array_push($membershipSiteAccess[$group], $siteId);
                    } else {
                        $this->logger->debug("UserAccessMapper::{func}(): site with id or name of '{site}' does not ".
                                "exist as defined in membership site access string '{membershipSiteAccessString}', ignoring",
                           array(
                            'func' => __FUNCTION__,
                            'site' => $site,
                            'membershipSiteAccessString' => $membershipSiteAccessString
                        ));
                    }
                }
            }
        }

        return $membershipSiteAccess;
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
            $this->allSites = Access::doAsSuperUser(function () {
                $siteIds = SitesManagerAPI::getInstance()->getSitesIdWithAtLeastViewAccess();
                $sites = array();
                foreach( $siteIds as $siteId){
                    $site = SitesManagerAPI::getInstance()->getSiteFromId($siteId);
                    $sites[$siteId] = $site["name"];
                }
                return $sites;
            });
        }

        return $this->allSites;
    }

    private function isSuperUserAccessGrantedForLdapUser($ldapUser)
    {
        if ($this->isSuperUserAccessGrantedForLdapUserThroughMembership($ldapUser)) {
            return true;
        }

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
     * ldap group memberships defined in the config param 'membership_super_access' defines a super user
     * test if $ldapUser has a key 'groups' and if this contains a group that is also defined in the
     * 'membership_super_access' config param
     *
     * @param array|multikey $ldapUser
     * @return boolean
     */
    private function isSuperUserAccessGrantedForLdapUserThroughMembership($ldapUser)
    {
        // memberships are defined through groups key
        if (!array_key_exists('groups', $ldapUser)) {
            return false;
        }
        // there should be at least one group in the intersect
        return ( count( array_intersect( $ldapUser['groups'], $this->membershipSuperAccess)) != 0);
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

        $membershipSuperAccess = Config::getMembershipSuperAccess();
        if (!empty($membershipSuperAccess)) {
            $result->setMembershipSuperAccess($membershipSuperAccess);
        }

        $membershipSiteViewAccess = Config::getMembershipSiteViewAccess();
        if (!empty($membershipSiteViewAccess)) {
            $result->setMembershipSiteViewAccess($membershipSiteViewAccess);
        }

        $membershipSiteAdminAccess = Config::getMembershipSiteAdminAccess();
        if (!empty($membershipSiteAdminAccess)) {
            $result->setMembershipSiteAdminAccess($membershipSiteAdminAccess);
        }

        return $result;
    }
}