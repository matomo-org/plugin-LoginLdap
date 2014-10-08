/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

angular.module('piwikApp').controller('LoginLdapAdminController', function ($scope, $attrs) {
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
});