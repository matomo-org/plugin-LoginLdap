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

    piwikLoginLdapTestableField.$inject = ['piwik', 'piwikApi', "$compile"];

    function piwikLoginLdapTestableField(piwik, piwikApi, $compile) {
        return {
            restrict: 'A',
            scope: {
                value: '@',
                name: '@',
                successTranslation: '@',
                testApiMethod: '=',
                testApiMethodArg: '=',
                inlineHelp: '@',
                ngModel: '@',
                title: '@'
            },
            templateUrl: 'plugins/LoginLdap/angularjs/login-ldap-testable-field/login-ldap-testable-field.directive.html?cb=' + piwik.cacheBuster,
            controller: function($scope, $element)
            {
                var testableField = {};
                testableField.inputValue = $scope.value;
                testableField.testApiMethod = $scope.testApiMethod;
                testableField.testApiMethodArg = $scope.testApiMethodArg;
                testableField.inputName = $scope.name;
                testableField.inlineHelp = $scope.inlineHelp;
                testableField.title = $scope.title;

                testableField.testResult = null;
                testableField.testError = null;
                testableField.testValue = null;
                testableField.currentRequest = null;

                $element.find('.test-config-option-success').attr('piwik-translate', $scope.successTranslation);
                $compile($element.find('.test-config-option-success'));

                function testValue() {
                    if (testableField.currentRequest) {
                        testableField.currentRequest.abort();
                    }

                    testableField.testError = null;
                    testableField.testResult = null;

                    if (!testableField.inputValue) {
                        return;
                    }

                    var requestOptions = {createErrorNotification: false},
                        getParams = {method: $scope.testApiMethod};
                    getParams[$scope.testApiMethodArg] = testableField.inputValue;

                    testableField.currentRequest = piwikApi.fetch(
                        getParams,
                        requestOptions
                    ).then(function (response) {
                        testableField.testResult = response.value === null ? null : parseInt(response.value);
                    }).catch(function (message) {
                        testableField.testError = message;
                        testableField.testResult = null;
                    })['finally'](function () {
                        testableField.currentRequest = null;
                    });
                }

                function setDescendantProp (obj, desc, value) {
                    var arr = desc.split('.');
                    if (arr.length > 0 && arr[0] != '') {
                        var prop = arr.shift();
                        obj[prop] = setDescendantProp(obj[prop], arr.length ? arr.join('.') : '', value);
                    } else {
                        obj = value;
                    }
                    return obj;
                }

                testableField.testValue = testValue;
                $scope.testableField = testableField;

                // set changed values to ngModel
                $scope.$watch("testableField.inputValue",
                    function(value){
                        setDescendantProp($scope.$parent, $scope.ngModel, value);
                    }
                );
            }
        };
    }
})();