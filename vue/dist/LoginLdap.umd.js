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

/***/ "fae3":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// EXPORTS
__webpack_require__.d(__webpack_exports__, "TestableField", function() { return /* reexport */ TestableField; });
__webpack_require__.d(__webpack_exports__, "Admin", function() { return /* reexport */ Admin; });
__webpack_require__.d(__webpack_exports__, "AdminPage", function() { return /* reexport */ AdminPage; });

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

// CONCATENATED MODULE: ./node_modules/@vue/cli-plugin-babel/node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/@vue/cli-plugin-babel/node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/@vue/cli-service/node_modules/vue-loader-v16/dist/templateLoader.js??ref--6!./node_modules/@vue/cli-service/node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/@vue/cli-service/node_modules/vue-loader-v16/dist??ref--0-1!./plugins/LoginLdap/vue/src/TestableField/TestableField.vue?vue&type=template&id=74c2fd9c

var _hoisted_1 = {
  class: "loginLdapTestableField"
};
var _hoisted_2 = ["innerHTML"];
function render(_ctx, _cache, $props, $setup, $data, $options) {
  var _component_Field = Object(external_commonjs_vue_commonjs2_vue_root_Vue_["resolveComponent"])("Field");

  var _component_SaveButton = Object(external_commonjs_vue_commonjs2_vue_root_Vue_["resolveComponent"])("SaveButton");

  return Object(external_commonjs_vue_commonjs2_vue_root_Vue_["openBlock"])(), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementBlock"])("div", _hoisted_1, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
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
      return _ctx.testInputValue();
    }),
    value: _ctx.translate('LoginLdap_Test')
  }, null, 8, ["saving", "value"]), [[external_commonjs_vue_commonjs2_vue_root_Vue_["vShow"], _ctx.actualInputValue]]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withDirectives"])(Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", {
    class: "test-config-option-success",
    innerHTML: _ctx.$sanitize(_ctx.successMessage)
  }, null, 8, _hoisted_2), [[external_commonjs_vue_commonjs2_vue_root_Vue_["vShow"], _ctx.testResult !== null]]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withDirectives"])(Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", {
    class: "test-config-option-error"
  }, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["toDisplayString"])(_ctx.testError), 513), [[external_commonjs_vue_commonjs2_vue_root_Vue_["vShow"], _ctx.testError]])]);
}
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/TestableField/TestableField.vue?vue&type=template&id=74c2fd9c

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
      if (this.testResult === null) {
        return '';
      }

      var usersTranslation = this.testResult === 1 ? Object(external_CoreHome_["translate"])('LoginLdap_OneUser') : Object(external_CoreHome_["translate"])('General_NUsers', "".concat(this.testResult));
      return Object(external_CoreHome_["translate"])(this.successTranslation, "<strong>".concat(usersTranslation, "</strong>"));
    }
  }
}));
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/TestableField/TestableField.vue?vue&type=script&lang=ts
 
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/TestableField/TestableField.vue



TestableFieldvue_type_script_lang_ts.render = render

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
// CONCATENATED MODULE: ./node_modules/@vue/cli-plugin-babel/node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/@vue/cli-plugin-babel/node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/@vue/cli-service/node_modules/vue-loader-v16/dist/templateLoader.js??ref--6!./node_modules/@vue/cli-service/node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/@vue/cli-service/node_modules/vue-loader-v16/dist??ref--0-1!./plugins/LoginLdap/vue/src/Admin/Admin.vue?vue&type=template&id=311910aa

var Adminvue_type_template_id_311910aa_hoisted_1 = {
  key: 0
};

var Adminvue_type_template_id_311910aa_hoisted_2 = /*#__PURE__*/Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("hr", null, null, -1);

var _hoisted_3 = /*#__PURE__*/Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("hr", null, null, -1);

var _hoisted_4 = ["innerHTML"];

var _hoisted_5 = /*#__PURE__*/Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("br", null, null, -1);

var _hoisted_6 = /*#__PURE__*/Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("br", null, null, -1);

var _hoisted_7 = /*#__PURE__*/Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("br", null, null, -1);

var _hoisted_8 = /*#__PURE__*/Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("br", null, null, -1);

var _hoisted_9 = ["innerHTML"];
var _hoisted_10 = ["innerHTML"];
var _hoisted_11 = ["innerHTML"];

var _hoisted_12 = /*#__PURE__*/Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("hr", null, null, -1);

var _hoisted_13 = {
  src: "plugins/Morpheus/images/loading-blue.gif"
};

var _hoisted_14 = /*#__PURE__*/Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("br", null, null, -1);

var _hoisted_15 = /*#__PURE__*/Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("br", null, null, -1);

var _hoisted_16 = ["innerHTML"];
var _hoisted_17 = {
  key: 1
};

var _hoisted_18 = /*#__PURE__*/Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("br", null, null, -1);

var _hoisted_19 = ["innerHTML"];

var _hoisted_20 = /*#__PURE__*/Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("hr", null, null, -1);

function Adminvue_type_template_id_311910aa_render(_ctx, _cache, $props, $setup, $data, $options) {
  var _component_Notification = Object(external_commonjs_vue_commonjs2_vue_root_Vue_["resolveComponent"])("Notification");

  var _component_Field = Object(external_commonjs_vue_commonjs2_vue_root_Vue_["resolveComponent"])("Field");

  var _component_TestableField = Object(external_commonjs_vue_commonjs2_vue_root_Vue_["resolveComponent"])("TestableField");

  var _component_SaveButton = Object(external_commonjs_vue_commonjs2_vue_root_Vue_["resolveComponent"])("SaveButton");

  var _component_ContentBlock = Object(external_commonjs_vue_commonjs2_vue_root_Vue_["resolveComponent"])("ContentBlock");

  var _component_AjaxForm = Object(external_commonjs_vue_commonjs2_vue_root_Vue_["resolveComponent"])("AjaxForm");

  return Object(external_commonjs_vue_commonjs2_vue_root_Vue_["openBlock"])(), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementBlock"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_AjaxForm, {
    "submit-api-method": "LoginLdap.saveLdapConfig",
    "use-custom-data-binding": true,
    "send-json-payload": true,
    "form-data": _ctx.actualLdapConfig
  }, {
    default: Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withCtx"])(function (ajaxForm) {
      return [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_ContentBlock, {
        id: "ldapSettings",
        "content-title": _ctx.translate('LoginLdap_Settings')
      }, {
        default: Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withCtx"])(function () {
          return [_ctx.updatedFromPre30 ? (Object(external_commonjs_vue_commonjs2_vue_root_Vue_["openBlock"])(), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementBlock"])("div", Adminvue_type_template_id_311910aa_hoisted_1, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Notification, {
            id: "pre300AlwaysUseLdapWarning",
            context: "warning",
            noclear: true
          }, {
            default: Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withCtx"])(function () {
              return [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("strong", null, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["toDisplayString"])(_ctx.translate('General_Note')), 1), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createTextVNode"])(": " + Object(external_commonjs_vue_commonjs2_vue_root_Vue_["toDisplayString"])(_ctx.translate('LoginLdap_UpdateFromPre300Warning')), 1)];
            }),
            _: 1
          })])) : Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createCommentVNode"])("", true), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "checkbox",
            name: "synchronize_users_after_login",
            modelValue: _ctx.actualLdapConfig.use_ldap_for_authentication,
            "onUpdate:modelValue": _cache[0] || (_cache[0] = function ($event) {
              return _ctx.actualLdapConfig.use_ldap_for_authentication = $event;
            }),
            title: _ctx.translate('LoginLdap_UseLdapForAuthentication'),
            "inline-help": _ctx.useLdapForAuthHelp
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "checkbox",
            name: "use_webserver_auth",
            modelValue: _ctx.actualLdapConfig.use_webserver_auth,
            "onUpdate:modelValue": _cache[1] || (_cache[1] = function ($event) {
              return _ctx.actualLdapConfig.use_webserver_auth = $event;
            }),
            title: _ctx.translate('LoginLdap_Kerberos'),
            "inline-help": _ctx.translate('LoginLdap_KerberosDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withDirectives"])(Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "checkbox",
            name: "strip_domain_from_web_auth",
            modelValue: _ctx.actualLdapConfig.strip_domain_from_web_auth,
            "onUpdate:modelValue": _cache[2] || (_cache[2] = function ($event) {
              return _ctx.actualLdapConfig.strip_domain_from_web_auth = $event;
            }),
            title: _ctx.translate('LoginLdap_StripDomainFromWebAuth'),
            "inline-help": _ctx.translate('LoginLdap_StripDomainFromWebAuthDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])])], 512), [[external_commonjs_vue_commonjs2_vue_root_Vue_["vShow"], _ctx.actualLdapConfig.use_webserver_auth]]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "ldap_network_timeout",
            modelValue: _ctx.actualLdapConfig.ldap_network_timeout,
            "onUpdate:modelValue": _cache[3] || (_cache[3] = function ($event) {
              return _ctx.actualLdapConfig.ldap_network_timeout = $event;
            }),
            title: _ctx.translate('LoginLdap_NetworkTimeout'),
            "inline-help": _ctx.ldapNetworkTimeoutHelp
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "required_member_of_field",
            modelValue: _ctx.actualLdapConfig.required_member_of_field,
            "onUpdate:modelValue": _cache[4] || (_cache[4] = function ($event) {
              return _ctx.actualLdapConfig.required_member_of_field = $event;
            }),
            title: _ctx.translate('LoginLdap_MemberOfField'),
            "inline-help": _ctx.translate('LoginLdap_MemberOfFieldDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_TestableField, {
            uicontrol: "text",
            modelValue: _ctx.actualLdapConfig.required_member_of,
            "onUpdate:modelValue": _cache[5] || (_cache[5] = function ($event) {
              return _ctx.actualLdapConfig.required_member_of = $event;
            }),
            name: "required_member_of",
            "test-api-method": "LoginLdap.getCountOfUsersMemberOf",
            "test-api-method-arg": "memberOf",
            "success-translation": "LoginLdap_MemberOfCount",
            title: _ctx.translate('LoginLdap_MemberOf'),
            "inline-help": _ctx.memberOfCountHelp
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_TestableField, {
            uicontrol: "text",
            modelValue: _ctx.actualLdapConfig.ldap_user_filter,
            "onUpdate:modelValue": _cache[6] || (_cache[6] = function ($event) {
              return _ctx.actualLdapConfig.ldap_user_filter = $event;
            }),
            name: "ldap_user_filter",
            "test-api-method": "LoginLdap.getCountOfUsersMatchingFilter",
            "test-api-method-arg": "filter",
            "success-translation": "LoginLdap_FilterCount",
            title: _ctx.translate('LoginLdap_Filter'),
            "inline-help": _ctx.translate('LoginLdap_FilterDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), Adminvue_type_template_id_311910aa_hoisted_2, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_SaveButton, {
            saving: ajaxForm.isSubmitting,
            onConfirm: function onConfirm($event) {
              return ajaxForm.submitForm();
            }
          }, null, 8, ["saving", "onConfirm"])];
        }),
        _: 2
      }, 1032, ["content-title"]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_ContentBlock, {
        id: "ldapUserMappingSettings",
        "content-title": _ctx.translate('LoginLdap_UserSyncSettings')
      }, {
        default: Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withCtx"])(function () {
          return [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "ldap_user_id_field",
            modelValue: _ctx.actualLdapConfig.ldap_user_id_field,
            "onUpdate:modelValue": _cache[7] || (_cache[7] = function ($event) {
              return _ctx.actualLdapConfig.ldap_user_id_field = $event;
            }),
            title: _ctx.translate('LoginLdap_UserIdField'),
            "inline-help": _ctx.translate('LoginLdap_UserIdFieldDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "ldap_password_field",
            modelValue: _ctx.actualLdapConfig.ldap_password_field,
            "onUpdate:modelValue": _cache[8] || (_cache[8] = function ($event) {
              return _ctx.actualLdapConfig.ldap_password_field = $event;
            }),
            title: _ctx.translate('LoginLdap_PasswordField'),
            "inline-help": _ctx.ldapPasswordFieldHelp
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "ldap_mail_field",
            modelValue: _ctx.actualLdapConfig.ldap_mail_field,
            "onUpdate:modelValue": _cache[9] || (_cache[9] = function ($event) {
              return _ctx.actualLdapConfig.ldap_mail_field = $event;
            }),
            title: _ctx.translate('LoginLdap_MailField'),
            "inline-help": _ctx.translate('LoginLdap_MailFieldDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "user_email_suffix",
            modelValue: _ctx.actualLdapConfig.user_email_suffix,
            "onUpdate:modelValue": _cache[10] || (_cache[10] = function ($event) {
              return _ctx.actualLdapConfig.user_email_suffix = $event;
            }),
            title: _ctx.translate('LoginLdap_UsernameSuffix'),
            "inline-help": _ctx.translate('LoginLdap_UsernameSuffixDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "new_user_default_sites_view_access",
            modelValue: _ctx.actualLdapConfig.new_user_default_sites_view_access,
            "onUpdate:modelValue": _cache[11] || (_cache[11] = function ($event) {
              return _ctx.actualLdapConfig.new_user_default_sites_view_access = $event;
            }),
            title: _ctx.translate('LoginLdap_NewUserDefaultSitesViewAccess'),
            "inline-help": _ctx.translate('LoginLdap_NewUserDefaultSitesViewAccessDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), _hoisted_3, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_SaveButton, {
            saving: ajaxForm.isSubmitting,
            onConfirm: function onConfirm($event) {
              return ajaxForm.submitForm();
            }
          }, null, 8, ["saving", "onConfirm"])];
        }),
        _: 2
      }, 1032, ["content-title"]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_ContentBlock, {
        id: "ldapUserAccessMappingSettings",
        "content-title": _ctx.translate('LoginLdap_AccessSyncSettings')
      }, {
        default: Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withCtx"])(function () {
          return [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("p", {
            innerHTML: _ctx.$sanitize(_ctx.readMoreAboutAccessSynchronization)
          }, null, 8, _hoisted_4), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "checkbox",
            name: "enable_synchronize_access_from_ldap",
            modelValue: _ctx.actualLdapConfig.enable_synchronize_access_from_ldap,
            "onUpdate:modelValue": _cache[12] || (_cache[12] = function ($event) {
              return _ctx.actualLdapConfig.enable_synchronize_access_from_ldap = $event;
            }),
            title: _ctx.translate('LoginLdap_EnableLdapAccessSynchronization'),
            "inline-help": _ctx.translate('LoginLdap_EnableLdapAccessSynchronizationDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withDirectives"])(Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Notification, {
            context: "info",
            noclear: true
          }, {
            default: Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withCtx"])(function () {
              return [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("strong", null, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["toDisplayString"])(_ctx.translate('LoginLdap_ExpectedLdapAttributes')), 1), _hoisted_5, _hoisted_6, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createTextVNode"])(" " + Object(external_commonjs_vue_commonjs2_vue_root_Vue_["toDisplayString"])(_ctx.translate('LoginLdap_ExpectedLdapAttributesPrelude')) + ":", 1), _hoisted_7, _hoisted_8, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("ul", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("li", {
                innerHTML: _ctx.$sanitize(_ctx.sampleViewAttribute)
              }, null, 8, _hoisted_9), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("li", {
                innerHTML: _ctx.$sanitize(_ctx.sampleAdminAttribute)
              }, null, 8, _hoisted_10), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("li", {
                innerHTML: _ctx.$sanitize(_ctx.sampleSuperuserAttribute)
              }, null, 8, _hoisted_11)])];
            }),
            _: 1
          })]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "ldap_view_access_field",
            modelValue: _ctx.actualLdapConfig.ldap_view_access_field,
            "onUpdate:modelValue": _cache[13] || (_cache[13] = function ($event) {
              return _ctx.actualLdapConfig.ldap_view_access_field = $event;
            }),
            title: _ctx.translate('LoginLdap_LdapViewAccessField'),
            "inline-help": _ctx.translate('LoginLdap_LdapViewAccessFieldDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "ldap_admin_access_field",
            modelValue: _ctx.actualLdapConfig.ldap_admin_access_field,
            "onUpdate:modelValue": _cache[14] || (_cache[14] = function ($event) {
              return _ctx.actualLdapConfig.ldap_admin_access_field = $event;
            }),
            title: _ctx.translate('LoginLdap_LdapAdminAccessField'),
            "inline-help": _ctx.translate('LoginLdap_LdapAdminAccessFieldDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "ldap_superuser_access_field",
            modelValue: _ctx.actualLdapConfig.ldap_superuser_access_field,
            "onUpdate:modelValue": _cache[15] || (_cache[15] = function ($event) {
              return _ctx.actualLdapConfig.ldap_superuser_access_field = $event;
            }),
            title: _ctx.translate('LoginLdap_LdapSuperUserAccessField'),
            "inline-help": _ctx.translate('LoginLdap_LdapSuperUserAccessFieldDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "user_access_attribute_server_specification_delimiter",
            modelValue: _ctx.actualLdapConfig.user_access_attribute_server_specification_delimiter,
            "onUpdate:modelValue": _cache[16] || (_cache[16] = function ($event) {
              return _ctx.actualLdapConfig.user_access_attribute_server_specification_delimiter = $event;
            }),
            title: _ctx.translate('LoginLdap_LdapUserAccessAttributeServerSpecDelimiter'),
            "inline-help": _ctx.translate('LoginLdap_LdapUserAccessAttributeServerSpecDelimiterDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "user_access_attribute_server_separator",
            modelValue: _ctx.actualLdapConfig.user_access_attribute_server_separator,
            "onUpdate:modelValue": _cache[17] || (_cache[17] = function ($event) {
              return _ctx.actualLdapConfig.user_access_attribute_server_separator = $event;
            }),
            title: _ctx.translate('LoginLdap_LdapUserAccessAttributeServerSeparator'),
            "inline-help": _ctx.translate('LoginLdap_LdapUserAccessAttributeServerSeparatorDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
            uicontrol: "text",
            name: "instance_name",
            modelValue: _ctx.actualLdapConfig.instance_name,
            "onUpdate:modelValue": _cache[18] || (_cache[18] = function ($event) {
              return _ctx.actualLdapConfig.instance_name = $event;
            }),
            title: _ctx.translate('LoginLdap_ThisMatomoInstanceName'),
            "inline-help": _ctx.translate('LoginLdap_ThisMatomoInstanceNameDescription')
          }, null, 8, ["modelValue", "title", "inline-help"])]), _hoisted_12, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_SaveButton, {
            saving: ajaxForm.isSubmitting,
            onConfirm: function onConfirm($event) {
              return ajaxForm.submitForm();
            }
          }, null, 8, ["saving", "onConfirm"])], 512), [[external_commonjs_vue_commonjs2_vue_root_Vue_["vShow"], _ctx.actualLdapConfig.enable_synchronize_access_from_ldap]])];
        }),
        _: 2
      }, 1032, ["content-title"])];
    }),
    _: 1
  }, 8, ["form-data"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_ContentBlock, {
    id: "ldapManualSynchronizeUser",
    "content-title": _ctx.translate('LoginLdap_LoadUser')
  }, {
    default: Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withCtx"])(function () {
      return [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("p", null, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["toDisplayString"])(_ctx.translate('LoginLdap_LoadUserDescription')), 1), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
        uicontrol: "text",
        placeholder: "Enter a username...",
        modelValue: _ctx.userToSynchronize,
        "onUpdate:modelValue": _cache[19] || (_cache[19] = function ($event) {
          return _ctx.userToSynchronize = $event;
        })
      }, null, 8, ["modelValue"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_SaveButton, {
        onConfirm: _cache[20] || (_cache[20] = function ($event) {
          return _ctx.synchronizeUser(_ctx.userToSynchronize);
        }),
        value: _ctx.translate('LoginLdap_Go'),
        style: {
          "margin-right": "7px"
        }
      }, null, 8, ["value"]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withDirectives"])(Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("img", _hoisted_13, null, 512), [[external_commonjs_vue_commonjs2_vue_root_Vue_["vShow"], _ctx.isSynchronizing]]), _hoisted_14, _hoisted_15, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withDirectives"])(Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [_ctx.synchronizeUserError ? (Object(external_commonjs_vue_commonjs2_vue_root_Vue_["openBlock"])(), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementBlock"])("div", {
        key: 0,
        innerHTML: _ctx.$sanitize(_ctx.synchronizeUserError)
      }, null, 8, _hoisted_16)) : Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createCommentVNode"])("", true), _ctx.synchronizeUserDone ? (Object(external_commonjs_vue_commonjs2_vue_root_Vue_["openBlock"])(), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementBlock"])("div", _hoisted_17, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("strong", null, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["toDisplayString"])(_ctx.translate('General_Done')) + "!", 1)])) : Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createCommentVNode"])("", true), _hoisted_18], 512), [[external_commonjs_vue_commonjs2_vue_root_Vue_["vShow"], _ctx.synchronizeUserError || _ctx.synchronizeUserDone]]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("span", {
        innerHTML: _ctx.$sanitize(_ctx.loadUserCommandDesc)
      }, null, 8, _hoisted_19)];
    }),
    _: 1
  }, 8, ["content-title"]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_ContentBlock, {
    "content-title": _ctx.translate('LoginLdap_LDAPServers')
  }, {
    default: Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withCtx"])(function () {
      return [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_AjaxForm, {
        "submit-api-method": "LoginLdap.saveServersInfo",
        "send-json-payload": true,
        "use-custom-data-binding": true,
        "form-data": _ctx.actualServers
      }, {
        default: Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withCtx"])(function (ajaxForm) {
          return [(Object(external_commonjs_vue_commonjs2_vue_root_Vue_["openBlock"])(true), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementBlock"])(external_commonjs_vue_commonjs2_vue_root_Vue_["Fragment"], null, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["renderList"])(_ctx.actualServers, function (serverInfo, index) {
            return Object(external_commonjs_vue_commonjs2_vue_root_Vue_["openBlock"])(), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementBlock"])("div", {
              id: "ldapServersTable",
              key: index
            }, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
              uicontrol: "text",
              modelValue: serverInfo.name,
              "onUpdate:modelValue": function onUpdateModelValue($event) {
                return serverInfo.name = $event;
              },
              title: _ctx.translate('LoginLdap_ServerName')
            }, null, 8, ["modelValue", "onUpdate:modelValue", "title"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
              uicontrol: "text",
              modelValue: serverInfo.hostname,
              "onUpdate:modelValue": function onUpdateModelValue($event) {
                return serverInfo.hostname = $event;
              },
              placeholder: "localhost",
              title: _ctx.translate('LoginLdap_ServerUrl')
            }, null, 8, ["modelValue", "onUpdate:modelValue", "title"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
              uicontrol: "text",
              modelValue: serverInfo.port,
              "onUpdate:modelValue": function onUpdateModelValue($event) {
                return serverInfo.port = $event;
              },
              placeholder: "389",
              title: _ctx.translate('LoginLdap_LdapPort'),
              "inline-help": _ctx.translate('LoginLdap_LdapUrlPortWarning')
            }, null, 8, ["modelValue", "onUpdate:modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
              uicontrol: "checkbox",
              modelValue: serverInfo.start_tls,
              "onUpdate:modelValue": function onUpdateModelValue($event) {
                return serverInfo.start_tls = $event;
              },
              title: _ctx.translate('LoginLdap_StartTLS'),
              "inline-help": _ctx.translate('LoginLdap_StartTLSFieldHelp')
            }, null, 8, ["modelValue", "onUpdate:modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
              uicontrol: "text",
              placeholder: "dc=example,dc=site,dc=org",
              modelValue: serverInfo.base_dn,
              "onUpdate:modelValue": function onUpdateModelValue($event) {
                return serverInfo.base_dn = $event;
              },
              title: _ctx.translate('LoginLdap_BaseDn')
            }, null, 8, ["modelValue", "onUpdate:modelValue", "title"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
              uicontrol: "text",
              placeholder: "cn=admin,dc=example,dc=site,dc=org",
              modelValue: serverInfo.admin_user,
              "onUpdate:modelValue": function onUpdateModelValue($event) {
                return serverInfo.admin_user = $event;
              },
              title: _ctx.translate('LoginLdap_AdminUser'),
              "inline-help": _ctx.translate('LoginLdap_AdminUserDescription')
            }, null, 8, ["modelValue", "onUpdate:modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("div", null, [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Field, {
              modelValue: serverInfo.admin_pass,
              "onUpdate:modelValue": function onUpdateModelValue($event) {
                return serverInfo.admin_pass = $event;
              },
              uicontrol: "password",
              title: _ctx.translate('LoginLdap_AdminPass'),
              "inline-help": _ctx.translate('LoginLdap_PasswordFieldHelp')
            }, null, 8, ["modelValue", "onUpdate:modelValue", "title", "inline-help"])]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_SaveButton, {
              onConfirm: function onConfirm($event) {
                return _ctx.actualServers.splice(index, 1);
              },
              value: _ctx.translate('General_Delete')
            }, null, 8, ["onConfirm", "value"])]);
          }), 128)), _hoisted_20, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_SaveButton, {
            onConfirm: _cache[21] || (_cache[21] = function ($event) {
              return _ctx.addServer();
            }),
            value: _ctx.translate('General_Add'),
            style: {
              "margin-right": "3.5px"
            }
          }, null, 8, ["value"]), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_SaveButton, {
            saving: ajaxForm.isSubmitting,
            onConfirm: function onConfirm($event) {
              return ajaxForm.submitForm();
            }
          }, null, 8, ["saving", "onConfirm"])];
        }),
        _: 1
      }, 8, ["form-data"])])];
    }),
    _: 1
  }, 8, ["content-title"])]);
}
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/Admin/Admin.vue?vue&type=template&id=311910aa

// CONCATENATED MODULE: ./node_modules/@vue/cli-plugin-typescript/node_modules/cache-loader/dist/cjs.js??ref--14-0!./node_modules/babel-loader/lib!./node_modules/@vue/cli-plugin-typescript/node_modules/ts-loader??ref--14-2!./node_modules/@vue/cli-service/node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/@vue/cli-service/node_modules/vue-loader-v16/dist??ref--0-1!./plugins/LoginLdap/vue/src/Admin/Admin.vue?vue&type=script&lang=ts
function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }

function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }

function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }

function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && iter[Symbol.iterator] != null || iter["@@iterator"] != null) return Array.from(iter); }

function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }

function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }






function getSampleAccessAttribute(config, accessField, firstValue, secondValue) {
  var result = "".concat(accessField, ": ");

  if (config.instance_name) {
    result += config.instance_name;
  } else {
    result += window.location.hostname;
  }

  if (firstValue) {
    result += "".concat(config.user_access_attribute_server_separator).concat(firstValue);
  }

  result += config.user_access_attribute_server_specification_delimiter;

  if (config.instance_name) {
    result += 'piwikB';
  } else {
    result += 'anotherhost.com';
  }

  if (secondValue) {
    result += "".concat(config.user_access_attribute_server_separator).concat(secondValue);
  }

  return result;
}

/* harmony default export */ var Adminvue_type_script_lang_ts = (Object(external_commonjs_vue_commonjs2_vue_root_Vue_["defineComponent"])({
  props: {
    ldapConfig: {
      type: Object,
      required: true
    },
    servers: {
      type: Array,
      required: true
    },
    updatedFromPre30: Boolean
  },
  components: {
    AjaxForm: external_CoreHome_["AjaxForm"],
    ContentBlock: external_CoreHome_["ContentBlock"],
    Notification: external_CoreHome_["Notification"],
    Field: external_CorePluginsAdmin_["Field"],
    TestableField: TestableField,
    SaveButton: external_CorePluginsAdmin_["SaveButton"]
  },
  data: function data() {
    return {
      actualLdapConfig: Object.assign({}, this.ldapConfig),
      userToSynchronize: '',
      actualServers: _toConsumableArray(this.servers),
      synchronizeUserError: null,
      synchronizeUserDone: null,
      isSynchronizing: false
    };
  },
  methods: {
    addServer: function addServer() {
      this.actualServers.push({
        name: "server".concat(this.actualServers.length + 1),
        hostname: '',
        port: 389,
        base_dn: '',
        admin_user: '',
        admin_pass: ''
      });
    },
    synchronizeUser: function synchronizeUser(userLogin) {
      var _this = this;

      this.synchronizeUserError = null;
      this.synchronizeUserDone = null;
      this.isSynchronizing = true;
      external_CoreHome_["AjaxHelper"].post({
        method: 'LoginLdap.synchronizeUser'
      }, {
        login: userLogin
      }, {
        createErrorNotification: false
      }).then(function () {
        _this.synchronizeUserDone = true;
      }).catch(function (error) {
        _this.synchronizeUserError = error.message || error;
      }).finally(function () {
        _this.isSynchronizing = false;
      });
    }
  },
  computed: {
    sampleViewAttribute: function sampleViewAttribute() {
      var config = this.actualLdapConfig;
      return getSampleAccessAttribute(config, config.ldap_view_access_field, '1,2', '3,4');
    },
    sampleAdminAttribute: function sampleAdminAttribute() {
      var config = this.actualLdapConfig;
      return getSampleAccessAttribute(config, config.ldap_admin_access_field, 'all', 'all');
    },
    sampleSuperuserAttribute: function sampleSuperuserAttribute() {
      var config = this.actualLdapConfig;
      return getSampleAccessAttribute(config, config.ldap_superuser_access_field);
    },
    readMoreAboutAccessSynchronization: function readMoreAboutAccessSynchronization() {
      var link = 'https://github.com/matomo-org/plugin-LoginLdap#matomo-access-synchronization';
      return Object(external_CoreHome_["translate"])('LoginLdap_ReadMoreAboutAccessSynchronization', "<a target=\"_blank\" href=\"".concat(link, "\" rel=\"noreferrer noopener\">"), '</a>');
    },
    loadUserCommandDesc: function loadUserCommandDesc() {
      var link = 'https://github.com/matomo-org/plugin-LoginLdap#commands';
      return Object(external_CoreHome_["translate"])('LoginLdap_LoadUserCommandDesc', "<a target=\"_blank\" href=\"".concat(link, "\" rel=\"noreferrer noopener\">loginldap:synchronize-users</a>"));
    },
    useLdapForAuthHelp: function useLdapForAuthHelp() {
      var start = Object(external_CoreHome_["translate"])('LoginLdap_UseLdapForAuthenticationDescription');
      return "".concat(start, "<br /><br />").concat(Object(external_CoreHome_["translate"])('LoginLdap_MobileAppIntegrationNote'));
    },
    ldapNetworkTimeoutHelp: function ldapNetworkTimeoutHelp() {
      var start = Object(external_CoreHome_["translate"])('LoginLdap_NetworkTimeoutDescription');
      return "".concat(start, "<br />").concat(Object(external_CoreHome_["translate"])('LoginLdap_NetworkTimeoutDescription2'));
    },
    memberOfCountHelp: function memberOfCountHelp() {
      var start = Object(external_CoreHome_["translate"])('LoginLdap_MemberOfDescription');
      return "".concat(start, "<br />").concat(Object(external_CoreHome_["translate"])('LoginLdap_MemberOfDescription2'));
    },
    ldapPasswordFieldHelp: function ldapPasswordFieldHelp() {
      var start = Object(external_CoreHome_["translate"])('LoginLdap_PasswordFieldDescription');
      return "".concat(start, "<br /><br />").concat(Object(external_CoreHome_["translate"])('LoginLdap_PasswordFieldDescription2'));
    }
  }
}));
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/Admin/Admin.vue?vue&type=script&lang=ts
 
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/Admin/Admin.vue



Adminvue_type_script_lang_ts.render = Adminvue_type_template_id_311910aa_render

/* harmony default export */ var Admin = (Adminvue_type_script_lang_ts);
// CONCATENATED MODULE: ./node_modules/@vue/cli-plugin-babel/node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/@vue/cli-plugin-babel/node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/@vue/cli-service/node_modules/vue-loader-v16/dist/templateLoader.js??ref--6!./node_modules/@vue/cli-service/node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/@vue/cli-service/node_modules/vue-loader-v16/dist??ref--0-1!./plugins/LoginLdap/vue/src/Admin/AdminPage.vue?vue&type=template&id=30c5d858

function AdminPagevue_type_template_id_30c5d858_render(_ctx, _cache, $props, $setup, $data, $options) {
  var _component_Notification = Object(external_commonjs_vue_commonjs2_vue_root_Vue_["resolveComponent"])("Notification");

  var _component_Admin = Object(external_commonjs_vue_commonjs2_vue_root_Vue_["resolveComponent"])("Admin");

  return Object(external_commonjs_vue_commonjs2_vue_root_Vue_["openBlock"])(), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementBlock"])("div", null, [_ctx.isLoginControllerActivated ? (Object(external_commonjs_vue_commonjs2_vue_root_Vue_["openBlock"])(), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createBlock"])(_component_Notification, {
    key: 0,
    context: "warning",
    noclear: true
  }, {
    default: Object(external_commonjs_vue_commonjs2_vue_root_Vue_["withCtx"])(function () {
      return [Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createElementVNode"])("strong", null, Object(external_commonjs_vue_commonjs2_vue_root_Vue_["toDisplayString"])(_ctx.translate('General_Warning')), 1), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createTextVNode"])(": " + Object(external_commonjs_vue_commonjs2_vue_root_Vue_["toDisplayString"])(_ctx.translate('LoginLdap_LoginPluginEnabledWarning', 'Login', 'LoginLdap')), 1)];
    }),
    _: 1
  })) : Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createCommentVNode"])("", true), Object(external_commonjs_vue_commonjs2_vue_root_Vue_["createVNode"])(_component_Admin, {
    servers: _ctx.servers,
    "ldap-config": _ctx.ldapConfig,
    "updated-from-pre30": _ctx.updatedFromPre30
  }, null, 8, ["servers", "ldap-config", "updated-from-pre30"])]);
}
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/Admin/AdminPage.vue?vue&type=template&id=30c5d858

// CONCATENATED MODULE: ./node_modules/@vue/cli-plugin-typescript/node_modules/cache-loader/dist/cjs.js??ref--14-0!./node_modules/babel-loader/lib!./node_modules/@vue/cli-plugin-typescript/node_modules/ts-loader??ref--14-2!./node_modules/@vue/cli-service/node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/@vue/cli-service/node_modules/vue-loader-v16/dist??ref--0-1!./plugins/LoginLdap/vue/src/Admin/AdminPage.vue?vue&type=script&lang=ts



/* harmony default export */ var AdminPagevue_type_script_lang_ts = (Object(external_commonjs_vue_commonjs2_vue_root_Vue_["defineComponent"])({
  props: {
    isLoginControllerActivated: Boolean,
    ldapConfig: {
      type: Object,
      required: true
    },
    servers: {
      type: Array,
      required: true
    },
    updatedFromPre30: Boolean
  },
  components: {
    Notification: external_CoreHome_["Notification"],
    Admin: Admin
  }
}));
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/Admin/AdminPage.vue?vue&type=script&lang=ts
 
// CONCATENATED MODULE: ./plugins/LoginLdap/vue/src/Admin/AdminPage.vue



AdminPagevue_type_script_lang_ts.render = AdminPagevue_type_template_id_30c5d858_render

/* harmony default export */ var AdminPage = (AdminPagevue_type_script_lang_ts);
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