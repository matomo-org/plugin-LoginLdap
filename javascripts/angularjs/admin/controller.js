/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

angular.module('piwikApp').controller('LoginLdapAdminController', function ($scope, $attrs, $filter, piwikApi) {
    $scope.isSynchronizingUser = false;
    $scope.userNameToSync = "";

    $scope.loginLdapConfig = JSON.parse($attrs['loginldapconfig']);

    // TODO: use nonces as in old controller method way
    $scope.synchronizeLdapUser = function (ldapUserName) {
        $scope.isSynchronizingUser = true;
        piwikApi.fetch({
            method: "LoginLdap.synchronizeLdapUser",
            ldapUserName: ldapUserName
        }).then(function (response) {
            var UI = require('piwik/UI');
            var notification = new UI.Notification();
            notification.show(response.message, {
                context: 'success',
                type: 'toast',
                id: 'ajaxHelper'
            });
            notification.scrollToNotification();
        })['finally'](function () {
            $scope.isSynchronizingUser = false;
        });
    };

    $scope.isSavingLdapConfig = false;
    $scope.saveLoginLdapConfig = function (config) {
        $scope.isSavingLdapConfig = true;
        piwikApi.fetch({
            method: "LoginLdap.saveLdapConfig",
            config: JSON.stringify(config)
        }).then(function (response) {
            var UI = require('piwik/UI');
            var notification = new UI.Notification();
            notification.show(response.message, {
                context: 'success',
                type: 'toast',
                id: 'ajaxHelper'
            });
            notification.scrollToNotification();
        })['finally'](function () {
            $scope.isSavingLdapConfig = false;
        });
    };
});