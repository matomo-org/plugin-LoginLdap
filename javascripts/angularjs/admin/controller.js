/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

angular.module('piwikApp').controller('LoginLdapAdminController', function ($scope, $attrs, $filter, piwikApi) {
    $scope.isSynchronizingUser = false;
    $scope.userNameToSync = "";

    $scope.loginLdapConfig = JSON.parse($attrs['loginldapconfig']) || {};

    var UI = require('piwik/UI'),
        displaySuccessMessage = function (response) {
            var notification = new UI.Notification();
            notification.show(response.message, {
                context: 'success',
                type: 'toast',
                id: 'ajaxHelper'
            });
            notification.scrollToNotification();
        };

    // TODO: use nonces as in old controller method way
    $scope.synchronizeLdapUser = function (ldapUserName) {
        $scope.isSynchronizingUser = true;
        piwikApi.fetch({
            method: "LoginLdap.synchronizeLdapUser",
            ldapUserName: ldapUserName
        }).then(displaySuccessMessage)['finally'](function () {
            $scope.isSynchronizingUser = false;
        });
    };

    $scope.isSavingLdapConfig = false;
    $scope.saveLoginLdapConfig = function (config) {
        $scope.isSavingLdapConfig = true;
        piwikApi.fetch({
            method: "LoginLdap.saveLdapConfig",
            config: JSON.stringify(config)
        }).then(displaySuccessMessage)['finally'](function () {
            $scope.isSavingLdapConfig = false;
        });
    };

    // LDAP server info management
    $scope.servers = JSON.parse($attrs['servers']) || [];

    $scope.deleteServer = function (name) {
        $scope.servers = $scope.servers.filter(function (server) { return server.name != name; });
    };

    $scope.addServer = function () {
        $scope.servers.push({
            name: "server" + $scope.servers.length,
            hostname: "",
            port: 389,
            base_dn: "",
            admin_user: "",
            admin_pass: ""
        });
    };

    $scope.isSavingLdapServers = false;
    $scope.saveServers = function (servers) {
        $scope.isSavingLdapServers = true;
        piwikApi.fetch({
            method: "LoginLdap.saveServersInfo",
            servers: JSON.stringify(servers)
        }).then(displaySuccessMessage)['finally'](function () {
            $scope.isSavingLdapServers = false;
        });
    };
});