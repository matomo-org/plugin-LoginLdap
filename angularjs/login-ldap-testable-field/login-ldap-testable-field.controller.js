/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
(function () {
    angular.module('piwikApp').controller('LoginLdapTestableFieldController', LoginLdapTestableFieldController);

    LoginLdapTestableFieldController.$inject = ['piwikApi'];

    function LoginLdapTestableFieldController(piwikApi) {
        var vm = this;

        /**
         * The value returned by the testing AJAX call (if any).
         *
         * @var {string|null}
         */
        vm.testResult = null;

        /**
         * The error message returned by the testing AJAX call (if any).
         *
         * @var {string|null}
         */
        vm.testError = null;

        /**
         * The current testing AJAX request or null if there is none.
         *
         * @var {null|Promise}
         */
        vm.currentRequest = null;

        vm.testValue = testValue;

        function testValue() {
            if (vm.currentRequest) {
                vm.currentRequest.abort();
            }

            vm.testError = null;
            vm.testResult = null;

            if (!vm.inputValue) {
                return;
            }

            var requestOptions = {createErrorNotification: false},
                getParams = {method: vm.testApiMethod};
            getParams[vm.testApiMethodArg] = vm.inputValue;

            vm.currentRequest = piwikApi.fetch(
                getParams,
                requestOptions
            ).then(function (response) {
                vm.testResult = response.value === null ? null : parseInt(response.value);
            }).catch(function (message) {
                vm.testError = message;
                vm.testResult = null;
            })['finally'](function () {
                vm.currentRequest = null;
            });
        }
    }
})();