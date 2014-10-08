/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Input field w/ a test link that calls an AJAX method when click. The result (or error message)
 * is displayed in a notificationnext to the input.
 *
 * <div piwik-piwik-login-ldap-testable-field>
 */
(function () {
    angular.module('piwikApp').directive('piwikLoginLdapTestableField', piwikLoginLdapTestableField);

    piwikLoginLdapTestableField.$inject = ['piwik'];

    function piwikLoginLdapTestableField(piwik) {
        return {
            restrict: 'A',
            scope: {
                inputValue: '@value',
                successTranslation: '@',
                testApiMethod: '=',
                testApiMethodArg: '='
            },
            templateUrl: 'plugins/LoginLdap/angularjs/login-ldap-testable-field/login-ldap-testable-field.directive.html?cb=' + piwik.cacheBuster,
            controller: 'LoginLdapTestableFieldController',
            controllerAs: 'testableField',
            compile: function (element, attrs) {
                element.find('[piwik-translate]').attr('piwik-translate', attrs.successTranslation);

                return function (scope, element, attrs) {
                    scope.testableField.inputValue = scope.inputValue;
                    scope.testableField.successTranslation = scope.successTranslation;
                    scope.testableField.testApiMethod = scope.testApiMethod;
                    scope.testableField.testApiMethodArg = scope.testApiMethodArg;

                    scope.testableField.inputId = attrs.inputId;
                    scope.testableField.inputName = attrs.name;
                    scope.testableField.inputType = attrs.type || 'text';
                };
            }
        };
    }
})();