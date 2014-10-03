<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\LdapInterop;

use Piwik\Access;
use Piwik\Config;
use Piwik\Log;
use Piwik\Site;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;

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
        if (isset($ldapUser[$this->superuserAttributeName])) {
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
        $this->viewAttributeName = $viewAttributeName;
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
        $this->adminAttributeName = $adminAttributeName;
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
        $this->superuserAttributeName = $superuserAttributeName;
    }

    private function addSiteAccess(&$sitesByAccess, $accessLevel, $viewAttributeValues)
    {
        if (!is_array($viewAttributeValues)) {
            $viewAttributeValues = array($viewAttributeValues);
        }

        $siteIds = array();
        Access::doAsSuperUser(function () use (&$siteIds, $viewAttributeValues) {
            foreach ($viewAttributeValues as $value) {
                $siteIds = array_merge($siteIds, Site::getIdSitesFromIdSitesString($value));
            }
        });

        $allSitesSet = $this->getSetOfAllSites();
        foreach ($siteIds as $idSite) {
            if (!isset($allSitesSet[$idSite])) {
                continue;
            }

            $sitesByAccess[$idSite] = $accessLevel;
        }
    }

    private function getSetOfAllSites()
    {
        static $allSites = null;

        if ($allSites === null) {
            Access::doAsSuperUser(function () {
                return SitesManagerAPI::getInstance()->getSitesIdWithAtLeastViewAccess();
            });
        }

        return $allSites;
    }

    /**
     * Returns a configured UserAccessMapper instance. The instance is configured
     * using INI config option values.
     *
     * @return UserAccessMapper
     */
    public static function makeConfigured()
    {
        $loginLdapConfig = Config::getInstance()->LoginLdap;

        $result = new UserAccessMapper();

        $viewAttributeName = @$loginLdapConfig['ldap_view_access_field']; // TODO: rename 'field' in config option names to attribute?
        if (!empty($viewAttributeName)) {
            $result->setViewAttributeName($viewAttributeName);
        }

        $adminAttributeName = @$loginLdapConfig['ldap_admin_access_field'];
        if (!empty($adminAttributeName)) {
            $result->setAdminAttributeName($adminAttributeName);
        }

        $superuserAttributeName = @$loginLdapConfig['ldap_superuser_access_field'];
        if (!empty($superuserAttributeName)) {
            $result->setSuperuserAttributeName($superuserAttributeName);
        }

        // TODO: add this logging to all makeConfigured methods that access Config, may help diagnose config errors...
        Log::debug("UserAccessMapper::%s: configuring with viewAttributeName = '%s', adminAttributeName = '%s', superuserAttributeName = '%s'",
            $viewAttributeName, $adminAttributeName, $superuserAttributeName);
    }
}