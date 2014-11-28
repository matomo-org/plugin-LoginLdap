/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

angular.module('piwikApp').controller('LoginLdapAdminController', function ($scope, $attrs, piwikApi) {
    // LDAP server info management
    $scope.servers = JSON.parse($attrs['servers']) || [];

    $scope.servers.addServer = function () {
        this.push({
            name: "server" + (this.length + 1),
            hostname: "",
            port: 389,
            base_dn: "",
            admin_user: "",
            admin_pass: ""
        });
    };

    $scope.getSampleViewAttribute = function (config) {
        return getSampleAccessAttribute(config, config.ldap_view_access_field, '1,2', '3,4');
    };

    $scope.getSampleAdminAttribute = function (config) {
        return getSampleAccessAttribute(config, config.ldap_admin_access_field, 'all', 'all');
    };

    $scope.getSampleSuperuserAttribute = function (config) {
        return getSampleAccessAttribute(config, config.ldap_superuser_access_field);
    };

    $scope.synchronizeUser = function (userLogin) {
        $scope.synchronizeUserError = $scope.synchronizeUserDone = null;

        $scope.currentSynchronizeUserRequest = piwikApi.post(
            {
                method: "LoginLdap.synchronizeUser"
            },
            {
                login: userLogin
            },
            {
                createErrorNotification: false
            }
        ).then(function (response) {
            $scope.synchronizeUserDone = true;
        }).catch(function (message) {
            $scope.synchronizeUserError = message;
        })['finally'](function () {
            $scope.currentSynchronizeUserRequest = null;
        });
    };

    function getSampleAccessAttribute(config, accessField, firstValue, secondValue) {
        var result = accessField + ': ';

        if (config.instance_name) {
            result += config.instance_name;
        } else {
            result += window.location.hostname;
        }
        if (firstValue) {
            result += config.user_access_attribute_server_separator + firstValue;
        }

        result += config.user_access_attribute_server_specification_delimiter;

        if (config.instance_name) {
            result += 'piwikB';
        } else {
            result += 'anotherhost.com';
        }
        if (secondValue) {
            result += config.user_access_attribute_server_separator + secondValue;
        }

        return result;
    }
});