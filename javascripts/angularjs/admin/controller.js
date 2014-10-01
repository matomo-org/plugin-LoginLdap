/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

// TODO: refactor filter into different controllers/directives
angular.module('piwikApp').controller('LoginLdapAdminController', function ($scope, $attrs, $filter, piwikApi) {
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

    // count of users matching memberof
    $scope.countUsersFoundWithMemberOf = null;
    $scope.currentMemberOfRequestError = null;
    $scope.currentMemberOfRequest = null;
    $scope.queryMemberOfCount = function () {
        $scope.currentMemberOfRequestError = null;

        if ($scope.currentMemberOfRequest) {
            $scope.currentMemberOfRequest.abort();
        }

        if (!$scope.loginLdapConfig.memberOf) {
            $scope.countUsersFoundWithMemberOf = null;
            return;
        }

        var requestOptions = {createErrorNotification: false};

        $scope.currentMemberOfRequest = piwikApi.fetch({
            method: "LoginLdap.getCountOfUsersMemberOf",
            memberOf: $scope.loginLdapConfig.memberOf
        }, requestOptions).then(function (response) {
            $scope.currentMemberOfRequest = null;

            $scope.countUsersFoundWithMemberOf = response.value === null ? null : parseInt(response.value);
        }).catch(function (message) {
            $scope.currentMemberOfRequestError = message;
            $scope.countUsersFoundWithMemberOf = null;
        });
    };

    // count of users matching filter
    $scope.countUsersFoundMatchingFilter = null;
    $scope.currentFilterCountRequestError = null;
    $scope.currentFilterCountRequest = null;
    $scope.queryFilterCount = function () {
        $scope.currentFilterCountRequestError = null;

        if ($scope.currentFilterCountRequest) {
            $scope.currentFilterCountRequest.abort();
        }

        if (!$scope.loginLdapConfig.filter) {
            $scope.countUsersFoundMatchingFilter = null;
            return;
        }

        var requestOptions = {createErrorNotification: false};

        $scope.currentFilterCountRequest = piwikApi.fetch({
            method: "LoginLdap.getCountOfUsersMatchingFilter",
            filter: $scope.loginLdapConfig.filter
        }, requestOptions).then(function (response){
            $scope.currentFilterCountRequest = null;

            $scope.countUsersFoundMatchingFilter = response.value === null ? null : parseInt(response.value);
        }).catch(function (message) {
            $scope.currentFilterCountRequestError = message;
            $scope.countUsersFoundMatchingFilter = null;
        });
    };

    // TODO: use nonces as in old controller method way

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