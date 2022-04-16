(function webpackUniversalModuleDefinition(root, factory) {
	if(typeof exports === 'object' && typeof module === 'object')
		module.exports = factory(require("CoreHome"), require("vue"), require("CorePluginsAdmin"));
	else if(typeof define === 'function' && define.amd)
		define(["CoreHome", , "CorePluginsAdmin"], factory);
	else if(typeof exports === 'object')
		exports["LoginLdap"] = factory(require("CoreHome"), require("vue"), require("CorePluginsAdmin"));
	else
		root["LoginLdap"] = factory(root["CoreHome"], root["Vue"], root["CorePluginsAdmin"]);
})((typeof self !== 'undefined' ? self : this), function(__WEBPACK_EXTERNAL_MODULE__19dc__, __WEBPACK_EXTERNAL_MODULE__8bbf__, __WEBPACK_EXTERNAL_MODULE_a5a2__) {
return /******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "plugins/LoginLdap/vue/dist/";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = "fae3");
/******/ })
/************************************************************************/
/******/ ({

/***/ "19dc":
/***/ (function(module, exports) {

module.exports = __WEBPACK_EXTERNAL_MODULE__19dc__;

/***/ }),

/***/ "8bbf":
/***/ (function(module, exports) {

module.exports = __WEBPACK_EXTERNAL_MODULE__8bbf__;

/***/ }),

/***/ "a5a2":
/***/ (function(module, exports) {

module.exports = __WEBPACK_EXTERNAL_MODULE_a5a2__;

/***/ }),

/***/ "b9a0":
/***/ (function(module, exports) {



/***/ }),

/***/ "fae3":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// EXPORTS
__webpack_require__.d(__webpack_exports__, "TestableField", function() { return /* reexport */ TestableField; });

// CONCATENATED MODULE: ./node_modules/@vue/cli-service/lib/commands/build/setPublicPath.js
// This file is imported into lib/wc client bundles.

if (typeof window !== 'undefined') {
  var currentScript = window.document.currentScript
  if (false) { var getCurrentScript; }

  var src = currentScript && currentScript.src.match(/(.+\/)[^/]+\.js(\?.*)?$/)
  if (src) {
    __webpack_require__.p = src[1] // eslint-disable-line
  }
}

// Indicate to webpack that this file can be concatenated
/* harmony default export */ var setPublicPath = (null);

// EXTERNAL MODULE: external {"commonjs":"vue","commonjs2":"vue","root":"Vue"}
var external_commonjs_vue_commonjs2_vue_root_Vue_ = __webpack_require__("8bbf");

// EXTERNAL MODULE: external "CoreHome"
var external_CoreHome_ = __webpack_require__("19dc");

// CONCATENATED MODULE: ./node_modules/@vue/cli-plugin-babel/node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/@vue/cli-plugin-babel/node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/@vue/cli-service/node_modules/vue-loader-v16/dist/templateLoader.js??ref--6!./node_modules/@vue/cli-service/node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/@vue/cli-service/node_modules/vue-loader-v16/dist??ref--0-1!./plugins/LoginLdap/vue/src/TestableField/TestableField.vue?vue&type=template&id=88f22f3e

var _hoisted_1 = ["innerHTML"];
function render(_ctx, _cache, $props, $setup, $data, $options) {
  var _component_Field = Object(external_commonjs_vue_commonjs2_vue_root_Vue_["resolveComponent"])("Field");

  var _component_SaveButton = Object(external_commonjs_vue_commonjs2_vue_root_Vue_["resolveComponent"])("SaveButton");

  return Object(external_commonjs_vue_commonjs2_vue_root_Vue_["openBlock"])(), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementBlock"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
    uicontrol: "text",
    onKeydown: _cache[0] || (_cache[0] = function ($event) {
      return _ctx.onKeydown($event);
    }),
    "model-value": _ctx.actualInputValue,
    "onUpdate:modelValue": _cache[1] || (_cache[1] = function ($event) {
      _ctx.actualInputValue = $event;
      _ctx.testResult = _ctx.testError = null;

      _ctx.$emit('update:modelValue', $event);
    }),
    name: _ctx.name,
    title: _ctx.title,
    "inline-help": _ctx.inlineHelp
  }, null, 8, ["model-value", "name", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withDirectives"])(Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_SaveButton, {
    saving: _ctx.isChecking,
    onConfirm: _cache[2] || (_cache[2] = function ($event) {
      return _ctx.testValue();
    }),
    value: _ctx.translate('LoginLdap_Test')
  }, null, 8, ["saving", "value"]), [[external_commonjs_vue_commonjs2_vue_root_Vue_["vShow"], _ctx.actualInputValue]]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withDirectives"])(Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", {
    class: "test-config-option-success",
    innerHTML: _ctx.$sanitize(_ctx.successMessage)
  }, null, 8, _hoisted_1), [[external_commonjs_vue_commonjs2_vue_root_Vue_["vShow"], _ctx.testResult !== null]]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withDirectives"])(Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", {
    class: "test-config-option-error"
  }, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["toDisplayString"])(_ctx.testError), 513), [[external_commonjs_vue_commonjs2_vue_root_Vue_["vShow"], _ctx.testError]])]);
}
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/TestableField/TestableField.vue?vue&type=template&id=88f22f3e

// EXTERNAL MODULE: external "CorePluginsAdmin"
var external_CorePluginsAdmin_ = __webpack_require__("a5a2");

// CONCATENATED MODULE: ./node_modules/@vue/cli-plugin-typescript/node_modules/cache-loader/dist/cjs.js??ref--14-0!./node_modules/babel-loader/lib!./node_modules/@vue/cli-plugin-typescript/node_modules/ts-loader??ref--14-2!./node_modules/@vue/cli-service/node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/@vue/cli-service/node_modules/vue-loader-v16/dist??ref--0-1!./plugins/LoginLdap/vue/src/TestableField/TestableField.vue?vue&type=script&lang=ts
function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }




/* harmony default export */ var TestableFieldvue_type_script_lang_ts = (Object(external_commonjs_vue_commonjs2_vue_root_Vue_["defineComponent"])({
  props: {
    modelValue: String,
    name: String,
    successTranslation: {
      type: String,
      required: true
    },
    testApiMethod: {
      type: String,
      required: true
    },
    testApiMethodArg: {
      type: String,
      required: true
    },
    inlineHelp: String,
    title: String
  },
  components: {
    Field: external_CorePluginsAdmin_["Field"],
    SaveButton: external_CorePluginsAdmin_["SaveButton"]
  },
  emits: ['update:modelValue'],
  setup: function setup(props) {
    var abortController = null;

    var sendRequestToTestValue = function sendRequestToTestValue(actualInputValue) {
      if (abortController) {
        abortController.abort();
        abortController = null;
      }

      abortController = new AbortController();
      return external_CoreHome_["AjaxHelper"].fetch(_defineProperty({
        method: props.testApiMethod
      }, props.testApiMethodArg, actualInputValue), {
        abortController: abortController,
        createErrorNotification: false
      }).finally(function () {
        abortController = null;
      });
    };

    return {
      sendRequestToTestValue: sendRequestToTestValue
    };
  },
  data: function data() {
    return {
      actualInputValue: this.modelValue,
      testError: null,
      testResult: null,
      testValue: null,
      isChecking: false
    };
  },
  methods: {
    testInputValue: function testInputValue() {
      var _this = this;

      this.testError = null;
      this.testResult = null;

      if (!this.actualInputValue) {
        return;
      }

      this.sendRequestToTestValue(this.actualInputValue).then(function (response) {
        _this.testResult = response.value === null ? null : parseInt(response.value, 10);
      }).catch(function (error) {
        _this.testError = error.message || error;
        _this.testResult = null;
      });
    },
    onKeydown: function onKeydown(event) {
      if (event.key !== 'Enter') {
        return;
      }

      this.testInputValue();
    }
  },
  computed: {
    successMessage: function successMessage() {
      var usersTranslation = this.testResult === 1 ? 'LoginLdap_OneUser' : 'General_NUsers';
      return Object(external_CoreHome_["translate"])(this.successTranslation, "<strong>".concat(Object(external_CoreHome_["translate"])(usersTranslation), "</strong>"));
    }
  }
}));
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/TestableField/TestableField.vue?vue&type=script&lang=ts
 
// EXTERNAL MODULE: ./plugins/LoginLdap/vue/src/TestableField/TestableField.vue?vue&type=custom&index=0&blockType=todo
var TestableFieldvue_type_custom_index_0_blockType_todo = __webpack_require__("b9a0");
var TestableFieldvue_type_custom_index_0_blockType_todo_default = /*#__PURE__*/__webpack_require__.n(TestableFieldvue_type_custom_index_0_blockType_todo);

// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/TestableField/TestableField.vue



TestableFieldvue_type_script_lang_ts.render = render
/* custom blocks */

if (typeof TestableFieldvue_type_custom_index_0_blockType_todo_default.a === 'function') TestableFieldvue_type_custom_index_0_blockType_todo_default()(TestableFieldvue_type_script_lang_ts)


/* harmony default export */ var TestableField = (TestableFieldvue_type_script_lang_ts);
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/TestableField/TestableField.adapter.ts
/*!
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */



/* harmony default export */ var TestableField_adapter = (Object(external_CoreHome_["createAngularJsAdapter"])({
  component: TestableField,
  scope: {
    value: {
      angularJsBind: '@'
    },
    name: {
      angularJsBind: '@'
    },
    successTranslation: {
      angularJsBind: '@'
    },
    testApiMethod: {
      angularJsBind: '='
    },
    testApiMethodArg: {
      angularJsBind: '='
    },
    inlineHelp: {
      angularJsBind: '@'
    },
    title: {
      angularJsBind: '@'
    }
  },
  directiveName: 'piwikLoginLdapTestableField',
  $inject: ['$timeout'],
  events: {
    'update:modelValue': function updateModelValue(newValue, vm, scope, element, attrs, ngModel, $timeout) {
      var currentValue = ngModel ? ngModel.$viewValue : scope.value;

      if (newValue !== currentValue) {
        $timeout(function () {
          if (!ngModel) {
            scope.value = newValue;
            return;
          } // ngModel being used


          ngModel.$setViewValue(newValue);
          ngModel.$render(); // not detected by the watch for some reason
        });
      }
    }
  },
  postCreate: function postCreate(vm, scope, element, attrs, controller) {
    var ngModel = controller;

    if (!ngModel) {
      scope.$watch('value', function (newVal) {
        if (newVal !== vm.modelValue) {
          Object(external_commonjs_vue_commonjs2_vue_root_Vue_["nextTick"])(function () {
            vm.modelValue = newVal;
          });
        }
      });
      return;
    } // ngModel being used


    ngModel.$render = function () {
      Object(external_commonjs_vue_commonjs2_vue_root_Vue_["nextTick"])(function () {
        vm.modelValue = Object(external_CoreHome_["removeAngularJsSpecificProperties"])(ngModel.$viewValue);
      });
    };

    if (typeof scope.value !== 'undefined') {
      ngModel.$setViewValue(scope.value);
    } else {
      ngModel.$setViewValue(vm.modelValue);
    }
  }
}));
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/index.ts
/*!
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */


// CONCATENATED MODULE: ./node_modules/@vue/cli-service/lib/commands/build/entry-lib-no-default.js




/***/ })

/******/ });
});
//# sourceMappingURL=LoginLdap.umd.js.map