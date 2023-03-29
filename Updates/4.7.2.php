<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap;

use Piwik\Common;
use Piwik\Db;
use Piwik\Option;
use Piwik\Updater;
use Piwik\Updater\Migration\Factory as MigrationFactory;
use Piwik\Updates;
use Piwik\Plugins\UsersManager\Model as UsersModel;
use Piwik\Plugins\LoginLdap\LdapInterop\UserMapper;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;

/**
 */
class Updates_4_7_2 extends Updates
{
    /**
     * @var MigrationFactory
     */
    private $migration;

    public function __construct(MigrationFactory $factory)
    {
        $this->migration = $factory;
    }

    public function doUpdate(Updater $updater)
    {

        $userModel = new UsersModel();
        $logins = $userModel->getUsers([]);

        $loginNames = [];

        $optionTable = Common::prefixTable('option');

        $searchPattern = UsersManagerAPI::OPTION_NAME_PREFERENCE_SEPARATOR . UserMapper::USER_PREFERENCE_NAME_IS_LDAP_USER;
        $db = Db::get();
        $optionValues = $db->fetchAll("Select option_name from `$optionTable` where option_name like '%$searchPattern'");
        foreach ($logins as $login) {
            $loginNames[$login['login']] = 1;
        }

        foreach ($optionValues as $optionValue) {
            $userLogin = str_replace($searchPattern, '', $optionValue['option_name']);
            if (!isset($loginNames[$userLogin])) {
                $key = $userLogin . $searchPattern;
                Option::delete($key);
            }
        }
    }
}
