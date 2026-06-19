(function(global, factory) {
  typeof exports === "object" && typeof module !== "undefined" ? factory(exports, require("vue"), require("CoreHome"), require("CorePluginsAdmin")) : typeof define === "function" && define.amd ? define(["exports", "vue", "CoreHome", "CorePluginsAdmin"], factory) : (global = typeof globalThis !== "undefined" ? globalThis : global || self, factory(global.LoginLdap = {}, global.Vue, global.CoreHome, global.CorePluginsAdmin));
})(this, (function(exports2, vue, CoreHome, CorePluginsAdmin) {
  "use strict";var __defProp = Object.defineProperty;
var __getOwnPropSymbols = Object.getOwnPropertySymbols;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __propIsEnum = Object.prototype.propertyIsEnumerable;
var __defNormalProp = (obj, key, value) => key in obj ? __defProp(obj, key, { enumerable: true, configurable: true, writable: true, value }) : obj[key] = value;
var __spreadValues = (a, b) => {
  for (var prop in b || (b = {}))
    if (__hasOwnProp.call(b, prop))
      __defNormalProp(a, prop, b[prop]);
  if (__getOwnPropSymbols)
    for (var prop of __getOwnPropSymbols(b)) {
      if (__propIsEnum.call(b, prop))
        __defNormalProp(a, prop, b[prop]);
    }
  return a;
};

  const _sfc_main$2 = vue.defineComponent({
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
      Field: CorePluginsAdmin.Field,
      SaveButton: CorePluginsAdmin.SaveButton
    },
    emits: ["update:modelValue"],
    setup(props) {
      let abortController = null;
      const sendRequestToTestValue = (actualInputValue) => {
        if (abortController) {
          abortController.abort();
          abortController = null;
        }
        abortController = new AbortController();
        return CoreHome.AjaxHelper.fetch(
          {
            method: props.testApiMethod,
            [props.testApiMethodArg]: actualInputValue
          },
          {
            abortController,
            createErrorNotification: false
          }
        ).finally(() => {
          abortController = null;
        });
      };
      return {
        sendRequestToTestValue
      };
    },
    data() {
      return {
        actualInputValue: this.modelValue,
        testError: null,
        testResult: null,
        testValue: null,
        isChecking: false
      };
    },
    methods: {
      testInputValue() {
        this.testError = null;
        this.testResult = null;
        if (!this.actualInputValue) {
          return;
        }
        this.sendRequestToTestValue(this.actualInputValue).then((response) => {
          this.testResult = response.value === null ? null : parseInt(response.value, 10);
        }).catch((error) => {
          this.testError = error.message || error;
          this.testResult = null;
        });
      },
      onKeydown(event) {
        if (event.key !== "Enter") {
          return;
        }
        this.testInputValue();
      }
    },
    computed: {
      successMessage() {
        if (this.testResult === null) {
          return "";
        }
        const usersTranslation = this.testResult === 1 ? CoreHome.translate("LoginLdap_OneUser") : CoreHome.translate("General_NUsers", `${this.testResult}`);
        return CoreHome.translate(this.successTranslation, `<strong>${usersTranslation}</strong>`);
      }
    }
  });
  const _export_sfc = (sfc, props) => {
    const target = sfc.__vccOpts || sfc;
    for (const [key, val] of props) {
      target[key] = val;
    }
    return target;
  };
  const _hoisted_1$1 = { class: "loginLdapTestableField" };
  const _hoisted_2$1 = ["innerHTML"];
  function _sfc_render$2(_ctx, _cache, $props, $setup, $data, $options) {
    const _component_Field = vue.resolveComponent("Field");
    const _component_SaveButton = vue.resolveComponent("SaveButton");
    return vue.openBlock(), vue.createElementBlock("div", _hoisted_1$1, [
      vue.createElementVNode("div", null, [
        vue.createVNode(_component_Field, {
          uicontrol: "text",
          onKeydown: _cache[0] || (_cache[0] = ($event) => _ctx.onKeydown($event)),
          "model-value": _ctx.actualInputValue,
          "onUpdate:modelValue": _cache[1] || (_cache[1] = ($event) => {
            _ctx.actualInputValue = $event;
            _ctx.testResult = _ctx.testError = null;
            _ctx.$emit("update:modelValue", $event);
          }),
          name: _ctx.name,
          title: _ctx.title,
          "inline-help": _ctx.inlineHelp
        }, null, 8, ["model-value", "name", "title", "inline-help"])
      ]),
      vue.withDirectives(vue.createVNode(_component_SaveButton, {
        saving: _ctx.isChecking,
        onConfirm: _cache[2] || (_cache[2] = ($event) => _ctx.testInputValue()),
        value: _ctx.translate("LoginLdap_Test")
      }, null, 8, ["saving", "value"]), [
        [vue.vShow, _ctx.actualInputValue]
      ]),
      vue.withDirectives(vue.createElementVNode("div", {
        class: "test-config-option-success",
        innerHTML: _ctx.$sanitize(_ctx.successMessage)
      }, null, 8, _hoisted_2$1), [
        [vue.vShow, _ctx.testResult !== null]
      ]),
      vue.withDirectives(vue.createElementVNode("div", { class: "test-config-option-error" }, vue.toDisplayString(_ctx.testError), 513), [
        [vue.vShow, _ctx.testError]
      ])
    ]);
  }
  const TestableField = /* @__PURE__ */ _export_sfc(_sfc_main$2, [["render", _sfc_render$2]]);
  function getSampleAccessAttribute(config, accessField, firstValue, secondValue) {
    let result = `${accessField}: `;
    if (config.instance_name) {
      result += config.instance_name;
    } else {
      result += window.location.hostname;
    }
    if (firstValue) {
      result += `${config.user_access_attribute_server_separator}${firstValue}`;
    }
    result += config.user_access_attribute_server_specification_delimiter;
    if (config.instance_name) {
      result += "piwikB";
    } else {
      result += "anotherhost.com";
    }
    if (secondValue) {
      result += `${config.user_access_attribute_server_separator}${secondValue}`;
    }
    return result;
  }
  const _sfc_main$1 = vue.defineComponent({
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
      AjaxForm: CoreHome.AjaxForm,
      ContentBlock: CoreHome.ContentBlock,
      Notification: CoreHome.Notification,
      PasswordConfirmation: CorePluginsAdmin.PasswordConfirmation,
      Field: CorePluginsAdmin.Field,
      TestableField,
      SaveButton: CorePluginsAdmin.SaveButton
    },
    data() {
      return {
        actualLdapConfig: __spreadValues({}, this.ldapConfig),
        userToSynchronize: "",
        actualServers: [...this.servers],
        synchronizeUserError: null,
        synchronizeUserDone: null,
        isSynchronizing: false,
        isSavingConfig: false,
        isSavingServers: false,
        showPasswordConfirmation: false,
        pendingSaveTarget: null
      };
    },
    methods: {
      addServer() {
        this.actualServers.push({
          name: `server${this.actualServers.length + 1}`,
          hostname: "",
          port: 389,
          base_dn: "",
          admin_user: "",
          admin_pass: ""
        });
      },
      synchronizeUser(userLogin) {
        this.synchronizeUserError = null;
        this.synchronizeUserDone = null;
        this.isSynchronizing = true;
        CoreHome.AjaxHelper.post(
          {
            method: "LoginLdap.synchronizeUser"
          },
          {
            login: userLogin
          },
          {
            createErrorNotification: false
          }
        ).then(() => {
          this.synchronizeUserDone = true;
        }).catch((error) => {
          this.synchronizeUserError = error.message || error;
        }).finally(() => {
          this.isSynchronizing = false;
        });
      },
      requestSaveLdapConfig() {
        this.pendingSaveTarget = "config";
        this.showPasswordConfirmation = true;
      },
      requestSaveServers() {
        this.pendingSaveTarget = "servers";
        this.showPasswordConfirmation = true;
      },
      confirmSaveAction(passwordConfirmation) {
        const { pendingSaveTarget } = this;
        this.showPasswordConfirmation = false;
        this.pendingSaveTarget = null;
        if (pendingSaveTarget === "config") {
          this.saveLdapConfig(passwordConfirmation);
        } else if (pendingSaveTarget === "servers") {
          this.saveServers(passwordConfirmation);
        }
      },
      saveLdapConfig(passwordConfirmation) {
        this.isSavingConfig = true;
        this.actualLdapConfig.password_confirmation = passwordConfirmation || "";
        const payload = {
          data: JSON.stringify(this.actualLdapConfig)
        };
        CoreHome.AjaxHelper.post(
          {
            module: "API",
            method: "LoginLdap.saveLdapConfig"
          },
          payload
        ).then(() => {
          this.showSaveSuccessNotification();
        }).finally(() => {
          this.actualLdapConfig.password_confirmation = "";
          this.isSavingConfig = false;
        });
      },
      saveServers(passwordConfirmation) {
        this.isSavingServers = true;
        const payload = {
          data: JSON.stringify(this.actualServers)
        };
        if (passwordConfirmation) {
          payload.passwordConfirmation = passwordConfirmation;
        }
        CoreHome.AjaxHelper.post(
          {
            module: "API",
            method: "LoginLdap.saveServersInfo"
          },
          payload
        ).then(() => {
          this.showSaveSuccessNotification();
        }).finally(() => {
          this.isSavingServers = false;
        });
      },
      showSaveSuccessNotification() {
        const notificationInstanceId = CoreHome.NotificationsStore.show({
          message: CoreHome.translate("General_YourChangesHaveBeenSaved"),
          context: "success",
          type: "toast",
          id: "ajaxHelper"
        });
        CoreHome.NotificationsStore.scrollToNotification(notificationInstanceId);
      }
    },
    computed: {
      sampleViewAttribute() {
        const config = this.actualLdapConfig;
        return getSampleAccessAttribute(config, config.ldap_view_access_field, "1,2", "3,4");
      },
      sampleAdminAttribute() {
        const config = this.actualLdapConfig;
        return getSampleAccessAttribute(config, config.ldap_admin_access_field, "all", "all");
      },
      sampleSuperuserAttribute() {
        const config = this.actualLdapConfig;
        return getSampleAccessAttribute(config, config.ldap_superuser_access_field);
      },
      readMoreAboutAccessSynchronization() {
        const link = "https://github.com/matomo-org/plugin-LoginLdap#matomo-access-synchronization";
        return CoreHome.translate(
          "LoginLdap_ReadMoreAboutAccessSynchronization",
          `<a target="_blank" href="${link}" rel="noreferrer noopener">`,
          "</a>"
        );
      },
      loadUserCommandDesc() {
        const link = "https://github.com/matomo-org/plugin-LoginLdap#commands";
        return CoreHome.translate(
          "LoginLdap_LoadUserCommandDesc",
          `<a target="_blank" href="${link}" rel="noreferrer noopener">loginldap:synchronize-users</a>`
        );
      },
      useLdapForAuthHelp() {
        const start = CoreHome.translate("LoginLdap_UseLdapForAuthenticationDescription");
        return `${start}<br /><br />${CoreHome.translate("LoginLdap_MobileAppIntegrationNote")}`;
      },
      ldapNetworkTimeoutHelp() {
        const start = CoreHome.translate("LoginLdap_NetworkTimeoutDescription");
        return `${start}<br />${CoreHome.translate("LoginLdap_NetworkTimeoutDescription2")}`;
      },
      memberOfCountHelp() {
        const start = CoreHome.translate("LoginLdap_MemberOfDescription");
        return `${start}<br />${CoreHome.translate("LoginLdap_MemberOfDescription2")}`;
      },
      ldapPasswordFieldHelp() {
        const start = CoreHome.translate("LoginLdap_PasswordFieldDescription");
        return `${start}<br /><br />${CoreHome.translate("LoginLdap_PasswordFieldDescription2")}`;
      }
    }
  });
  const _hoisted_1 = { key: 0 };
  const _hoisted_2 = /* @__PURE__ */ vue.createElementVNode("hr", null, null, -1);
  const _hoisted_3 = /* @__PURE__ */ vue.createElementVNode("hr", null, null, -1);
  const _hoisted_4 = ["innerHTML"];
  const _hoisted_5 = /* @__PURE__ */ vue.createElementVNode("br", null, null, -1);
  const _hoisted_6 = /* @__PURE__ */ vue.createElementVNode("br", null, null, -1);
  const _hoisted_7 = /* @__PURE__ */ vue.createElementVNode("br", null, null, -1);
  const _hoisted_8 = /* @__PURE__ */ vue.createElementVNode("br", null, null, -1);
  const _hoisted_9 = ["innerHTML"];
  const _hoisted_10 = ["innerHTML"];
  const _hoisted_11 = ["innerHTML"];
  const _hoisted_12 = /* @__PURE__ */ vue.createElementVNode("hr", null, null, -1);
  const _hoisted_13 = { src: "plugins/Morpheus/images/loading-blue.gif" };
  const _hoisted_14 = /* @__PURE__ */ vue.createElementVNode("br", null, null, -1);
  const _hoisted_15 = /* @__PURE__ */ vue.createElementVNode("br", null, null, -1);
  const _hoisted_16 = ["innerHTML"];
  const _hoisted_17 = { key: 1 };
  const _hoisted_18 = /* @__PURE__ */ vue.createElementVNode("br", null, null, -1);
  const _hoisted_19 = ["innerHTML"];
  const _hoisted_20 = /* @__PURE__ */ vue.createElementVNode("hr", null, null, -1);
  function _sfc_render$1(_ctx, _cache, $props, $setup, $data, $options) {
    const _component_Notification = vue.resolveComponent("Notification");
    const _component_Field = vue.resolveComponent("Field");
    const _component_TestableField = vue.resolveComponent("TestableField");
    const _component_SaveButton = vue.resolveComponent("SaveButton");
    const _component_ContentBlock = vue.resolveComponent("ContentBlock");
    const _component_AjaxForm = vue.resolveComponent("AjaxForm");
    const _component_PasswordConfirmation = vue.resolveComponent("PasswordConfirmation");
    return vue.openBlock(), vue.createElementBlock("div", null, [
      vue.createElementVNode("div", null, [
        vue.createVNode(_component_AjaxForm, {
          "submit-api-method": "LoginLdap.saveLdapConfig",
          "use-custom-data-binding": true,
          "send-json-payload": true,
          "form-data": _ctx.actualLdapConfig
        }, {
          default: vue.withCtx(() => [
            vue.createVNode(_component_ContentBlock, {
              id: "ldapSettings",
              "content-title": _ctx.translate("LoginLdap_Settings")
            }, {
              default: vue.withCtx(() => [
                _ctx.updatedFromPre30 ? (vue.openBlock(), vue.createElementBlock("div", _hoisted_1, [
                  vue.createVNode(_component_Notification, {
                    id: "pre300AlwaysUseLdapWarning",
                    context: "warning",
                    noclear: true
                  }, {
                    default: vue.withCtx(() => [
                      vue.createElementVNode("strong", null, vue.toDisplayString(_ctx.translate("General_Note")), 1),
                      vue.createTextVNode(": " + vue.toDisplayString(_ctx.translate("LoginLdap_UpdateFromPre300Warning")), 1)
                    ]),
                    _: 1
                  })
                ])) : vue.createCommentVNode("", true),
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_Field, {
                    uicontrol: "checkbox",
                    name: "synchronize_users_after_login",
                    modelValue: _ctx.actualLdapConfig.use_ldap_for_authentication,
                    "onUpdate:modelValue": _cache[0] || (_cache[0] = ($event) => _ctx.actualLdapConfig.use_ldap_for_authentication = $event),
                    title: _ctx.translate("LoginLdap_UseLdapForAuthentication"),
                    "inline-help": _ctx.useLdapForAuthHelp
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_Field, {
                    uicontrol: "checkbox",
                    name: "use_webserver_auth",
                    modelValue: _ctx.actualLdapConfig.use_webserver_auth,
                    "onUpdate:modelValue": _cache[1] || (_cache[1] = ($event) => _ctx.actualLdapConfig.use_webserver_auth = $event),
                    title: _ctx.translate("LoginLdap_Kerberos"),
                    "inline-help": _ctx.translate("LoginLdap_KerberosDescription")
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_Field, {
                    uicontrol: "checkbox",
                    name: "enable_password_confirmation",
                    modelValue: _ctx.actualLdapConfig.enable_password_confirmation,
                    "onUpdate:modelValue": _cache[2] || (_cache[2] = ($event) => _ctx.actualLdapConfig.enable_password_confirmation = $event),
                    title: _ctx.translate("LoginLdap_OptionsPWCONFIRMATION"),
                    "inline-help": _ctx.translate("LoginLdap_OptionsPWCONFIRMATIONDescription")
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                vue.withDirectives(vue.createElementVNode("div", null, [
                  vue.createElementVNode("div", null, [
                    vue.createVNode(_component_Field, {
                      uicontrol: "checkbox",
                      name: "strip_domain_from_web_auth",
                      modelValue: _ctx.actualLdapConfig.strip_domain_from_web_auth,
                      "onUpdate:modelValue": _cache[3] || (_cache[3] = ($event) => _ctx.actualLdapConfig.strip_domain_from_web_auth = $event),
                      title: _ctx.translate("LoginLdap_StripDomainFromWebAuth"),
                      "inline-help": _ctx.translate("LoginLdap_StripDomainFromWebAuthDescription")
                    }, null, 8, ["modelValue", "title", "inline-help"])
                  ])
                ], 512), [
                  [vue.vShow, _ctx.actualLdapConfig.use_webserver_auth]
                ]),
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_Field, {
                    uicontrol: "text",
                    name: "ldap_network_timeout",
                    modelValue: _ctx.actualLdapConfig.ldap_network_timeout,
                    "onUpdate:modelValue": _cache[4] || (_cache[4] = ($event) => _ctx.actualLdapConfig.ldap_network_timeout = $event),
                    title: _ctx.translate("LoginLdap_NetworkTimeout"),
                    "inline-help": _ctx.ldapNetworkTimeoutHelp
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_Field, {
                    uicontrol: "text",
                    name: "required_member_of_field",
                    modelValue: _ctx.actualLdapConfig.required_member_of_field,
                    "onUpdate:modelValue": _cache[5] || (_cache[5] = ($event) => _ctx.actualLdapConfig.required_member_of_field = $event),
                    title: _ctx.translate("LoginLdap_MemberOfField"),
                    "inline-help": _ctx.translate("LoginLdap_MemberOfFieldDescription")
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_TestableField, {
                    uicontrol: "text",
                    modelValue: _ctx.actualLdapConfig.required_member_of,
                    "onUpdate:modelValue": _cache[6] || (_cache[6] = ($event) => _ctx.actualLdapConfig.required_member_of = $event),
                    name: "required_member_of",
                    "test-api-method": "LoginLdap.getCountOfUsersMemberOf",
                    "test-api-method-arg": "memberOf",
                    "success-translation": "LoginLdap_MemberOfCount",
                    title: _ctx.translate("LoginLdap_MemberOf"),
                    "inline-help": _ctx.memberOfCountHelp
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_TestableField, {
                    uicontrol: "text",
                    modelValue: _ctx.actualLdapConfig.ldap_user_filter,
                    "onUpdate:modelValue": _cache[7] || (_cache[7] = ($event) => _ctx.actualLdapConfig.ldap_user_filter = $event),
                    name: "ldap_user_filter",
                    "test-api-method": "LoginLdap.getCountOfUsersMatchingFilter",
                    "test-api-method-arg": "filter",
                    "success-translation": "LoginLdap_FilterCount",
                    title: _ctx.translate("LoginLdap_Filter"),
                    "inline-help": _ctx.translate("LoginLdap_FilterDescription")
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                _hoisted_2,
                vue.createVNode(_component_SaveButton, {
                  saving: _ctx.isSavingConfig,
                  onConfirm: _cache[8] || (_cache[8] = ($event) => _ctx.requestSaveLdapConfig())
                }, null, 8, ["saving"])
              ]),
              _: 1
            }, 8, ["content-title"]),
            vue.createVNode(_component_ContentBlock, {
              id: "ldapUserMappingSettings",
              "content-title": _ctx.translate("LoginLdap_UserSyncSettings")
            }, {
              default: vue.withCtx(() => [
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_Field, {
                    uicontrol: "text",
                    name: "ldap_user_id_field",
                    modelValue: _ctx.actualLdapConfig.ldap_user_id_field,
                    "onUpdate:modelValue": _cache[9] || (_cache[9] = ($event) => _ctx.actualLdapConfig.ldap_user_id_field = $event),
                    title: _ctx.translate("LoginLdap_UserIdField"),
                    "inline-help": _ctx.translate("LoginLdap_UserIdFieldDescription")
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_Field, {
                    uicontrol: "text",
                    name: "ldap_password_field",
                    modelValue: _ctx.actualLdapConfig.ldap_password_field,
                    "onUpdate:modelValue": _cache[10] || (_cache[10] = ($event) => _ctx.actualLdapConfig.ldap_password_field = $event),
                    title: _ctx.translate("LoginLdap_PasswordField"),
                    "inline-help": _ctx.ldapPasswordFieldHelp
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_Field, {
                    uicontrol: "text",
                    name: "ldap_mail_field",
                    modelValue: _ctx.actualLdapConfig.ldap_mail_field,
                    "onUpdate:modelValue": _cache[11] || (_cache[11] = ($event) => _ctx.actualLdapConfig.ldap_mail_field = $event),
                    title: _ctx.translate("LoginLdap_MailField"),
                    "inline-help": _ctx.translate("LoginLdap_MailFieldDescription")
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_Field, {
                    uicontrol: "text",
                    name: "user_email_suffix",
                    modelValue: _ctx.actualLdapConfig.user_email_suffix,
                    "onUpdate:modelValue": _cache[12] || (_cache[12] = ($event) => _ctx.actualLdapConfig.user_email_suffix = $event),
                    title: _ctx.translate("LoginLdap_UsernameSuffix"),
                    "inline-help": _ctx.translate("LoginLdap_UsernameSuffixDescription")
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_Field, {
                    uicontrol: "text",
                    name: "new_user_default_sites_view_access",
                    modelValue: _ctx.actualLdapConfig.new_user_default_sites_view_access,
                    "onUpdate:modelValue": _cache[13] || (_cache[13] = ($event) => _ctx.actualLdapConfig.new_user_default_sites_view_access = $event),
                    title: _ctx.translate("LoginLdap_NewUserDefaultSitesViewAccess"),
                    "inline-help": _ctx.translate("LoginLdap_NewUserDefaultSitesViewAccessDescription")
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                _hoisted_3,
                vue.createVNode(_component_SaveButton, {
                  saving: _ctx.isSavingConfig,
                  onConfirm: _cache[14] || (_cache[14] = ($event) => _ctx.requestSaveLdapConfig())
                }, null, 8, ["saving"])
              ]),
              _: 1
            }, 8, ["content-title"]),
            vue.createVNode(_component_ContentBlock, {
              id: "ldapUserAccessMappingSettings",
              "content-title": _ctx.translate("LoginLdap_AccessSyncSettings")
            }, {
              default: vue.withCtx(() => [
                vue.createElementVNode("p", {
                  innerHTML: _ctx.$sanitize(_ctx.readMoreAboutAccessSynchronization)
                }, null, 8, _hoisted_4),
                vue.createElementVNode("div", null, [
                  vue.createVNode(_component_Field, {
                    uicontrol: "checkbox",
                    name: "enable_synchronize_access_from_ldap",
                    modelValue: _ctx.actualLdapConfig.enable_synchronize_access_from_ldap,
                    "onUpdate:modelValue": _cache[15] || (_cache[15] = ($event) => _ctx.actualLdapConfig.enable_synchronize_access_from_ldap = $event),
                    title: _ctx.translate("LoginLdap_EnableLdapAccessSynchronization"),
                    "inline-help": _ctx.translate("LoginLdap_EnableLdapAccessSynchronizationDescription")
                  }, null, 8, ["modelValue", "title", "inline-help"])
                ]),
                vue.withDirectives(vue.createElementVNode("div", null, [
                  vue.createElementVNode("div", null, [
                    vue.createVNode(_component_Notification, {
                      context: "info",
                      noclear: true
                    }, {
                      default: vue.withCtx(() => [
                        vue.createElementVNode("strong", null, vue.toDisplayString(_ctx.translate("LoginLdap_ExpectedLdapAttributes")), 1),
                        _hoisted_5,
                        _hoisted_6,
                        vue.createTextVNode(" " + vue.toDisplayString(_ctx.translate("LoginLdap_ExpectedLdapAttributesPrelude")) + ":", 1),
                        _hoisted_7,
                        _hoisted_8,
                        vue.createElementVNode("ul", null, [
                          vue.createElementVNode("li", {
                            innerHTML: _ctx.$sanitize(_ctx.sampleViewAttribute)
                          }, null, 8, _hoisted_9),
                          vue.createElementVNode("li", {
                            innerHTML: _ctx.$sanitize(_ctx.sampleAdminAttribute)
                          }, null, 8, _hoisted_10),
                          vue.createElementVNode("li", {
                            innerHTML: _ctx.$sanitize(_ctx.sampleSuperuserAttribute)
                          }, null, 8, _hoisted_11)
                        ])
                      ]),
                      _: 1
                    })
                  ]),
                  vue.createElementVNode("div", null, [
                    vue.createVNode(_component_Field, {
                      uicontrol: "text",
                      name: "ldap_view_access_field",
                      modelValue: _ctx.actualLdapConfig.ldap_view_access_field,
                      "onUpdate:modelValue": _cache[16] || (_cache[16] = ($event) => _ctx.actualLdapConfig.ldap_view_access_field = $event),
                      title: _ctx.translate("LoginLdap_LdapViewAccessField"),
                      "inline-help": _ctx.translate("LoginLdap_LdapViewAccessFieldDescription")
                    }, null, 8, ["modelValue", "title", "inline-help"])
                  ]),
                  vue.createElementVNode("div", null, [
                    vue.createVNode(_component_Field, {
                      uicontrol: "text",
                      name: "ldap_admin_access_field",
                      modelValue: _ctx.actualLdapConfig.ldap_admin_access_field,
                      "onUpdate:modelValue": _cache[17] || (_cache[17] = ($event) => _ctx.actualLdapConfig.ldap_admin_access_field = $event),
                      title: _ctx.translate("LoginLdap_LdapAdminAccessField"),
                      "inline-help": _ctx.translate("LoginLdap_LdapAdminAccessFieldDescription")
                    }, null, 8, ["modelValue", "title", "inline-help"])
                  ]),
                  vue.createElementVNode("div", null, [
                    vue.createVNode(_component_Field, {
                      uicontrol: "text",
                      name: "ldap_superuser_access_field",
                      modelValue: _ctx.actualLdapConfig.ldap_superuser_access_field,
                      "onUpdate:modelValue": _cache[18] || (_cache[18] = ($event) => _ctx.actualLdapConfig.ldap_superuser_access_field = $event),
                      title: _ctx.translate("LoginLdap_LdapSuperUserAccessField"),
                      "inline-help": _ctx.translate("LoginLdap_LdapSuperUserAccessFieldDescription")
                    }, null, 8, ["modelValue", "title", "inline-help"])
                  ]),
                  vue.createElementVNode("div", null, [
                    vue.createVNode(_component_Field, {
                      uicontrol: "text",
                      name: "user_access_attribute_server_specification_delimiter",
                      modelValue: _ctx.actualLdapConfig.user_access_attribute_server_specification_delimiter,
                      "onUpdate:modelValue": _cache[19] || (_cache[19] = ($event) => _ctx.actualLdapConfig.user_access_attribute_server_specification_delimiter = $event),
                      title: _ctx.translate("LoginLdap_LdapUserAccessAttributeServerSpecDelimiter"),
                      "inline-help": _ctx.translate(
                        "LoginLdap_LdapUserAccessAttributeServerSpecDelimiterDescription"
                      )
                    }, null, 8, ["modelValue", "title", "inline-help"])
                  ]),
                  vue.createElementVNode("div", null, [
                    vue.createVNode(_component_Field, {
                      uicontrol: "text",
                      name: "user_access_attribute_server_separator",
                      modelValue: _ctx.actualLdapConfig.user_access_attribute_server_separator,
                      "onUpdate:modelValue": _cache[20] || (_cache[20] = ($event) => _ctx.actualLdapConfig.user_access_attribute_server_separator = $event),
                      title: _ctx.translate("LoginLdap_LdapUserAccessAttributeServerSeparator"),
                      "inline-help": _ctx.translate(
                        "LoginLdap_LdapUserAccessAttributeServerSeparatorDescription"
                      )
                    }, null, 8, ["modelValue", "title", "inline-help"])
                  ]),
                  vue.createElementVNode("div", null, [
                    vue.createVNode(_component_Field, {
                      uicontrol: "text",
                      name: "instance_name",
                      modelValue: _ctx.actualLdapConfig.instance_name,
                      "onUpdate:modelValue": _cache[21] || (_cache[21] = ($event) => _ctx.actualLdapConfig.instance_name = $event),
                      title: _ctx.translate("LoginLdap_ThisMatomoInstanceName"),
                      "inline-help": _ctx.translate("LoginLdap_ThisMatomoInstanceNameDescription")
                    }, null, 8, ["modelValue", "title", "inline-help"])
                  ]),
                  _hoisted_12,
                  vue.createVNode(_component_SaveButton, {
                    saving: _ctx.isSavingConfig,
                    onConfirm: _cache[22] || (_cache[22] = ($event) => _ctx.requestSaveLdapConfig())
                  }, null, 8, ["saving"])
                ], 512), [
                  [vue.vShow, _ctx.actualLdapConfig.enable_synchronize_access_from_ldap]
                ])
              ]),
              _: 1
            }, 8, ["content-title"])
          ]),
          _: 1
        }, 8, ["form-data"])
      ]),
      vue.createVNode(_component_ContentBlock, {
        id: "ldapManualSynchronizeUser",
        "content-title": _ctx.translate("LoginLdap_LoadUser")
      }, {
        default: vue.withCtx(() => [
          vue.createElementVNode("p", null, vue.toDisplayString(_ctx.translate("LoginLdap_LoadUserDescription")), 1),
          vue.createElementVNode("div", null, [
            vue.createVNode(_component_Field, {
              uicontrol: "text",
              placeholder: "Enter a username...",
              modelValue: _ctx.userToSynchronize,
              "onUpdate:modelValue": _cache[23] || (_cache[23] = ($event) => _ctx.userToSynchronize = $event)
            }, null, 8, ["modelValue"])
          ]),
          vue.createVNode(_component_SaveButton, {
            onConfirm: _cache[24] || (_cache[24] = ($event) => _ctx.synchronizeUser(_ctx.userToSynchronize)),
            value: _ctx.translate("LoginLdap_Go"),
            style: { "margin-right": "7px" }
          }, null, 8, ["value"]),
          vue.withDirectives(vue.createElementVNode("img", _hoisted_13, null, 512), [
            [vue.vShow, _ctx.isSynchronizing]
          ]),
          _hoisted_14,
          _hoisted_15,
          vue.withDirectives(vue.createElementVNode("div", null, [
            _ctx.synchronizeUserError ? (vue.openBlock(), vue.createElementBlock("div", {
              key: 0,
              innerHTML: _ctx.$sanitize(_ctx.synchronizeUserError)
            }, null, 8, _hoisted_16)) : vue.createCommentVNode("", true),
            _ctx.synchronizeUserDone ? (vue.openBlock(), vue.createElementBlock("div", _hoisted_17, [
              vue.createElementVNode("strong", null, vue.toDisplayString(_ctx.translate("General_Done")) + "!", 1)
            ])) : vue.createCommentVNode("", true),
            _hoisted_18
          ], 512), [
            [vue.vShow, _ctx.synchronizeUserError || _ctx.synchronizeUserDone]
          ]),
          vue.createElementVNode("span", {
            innerHTML: _ctx.$sanitize(_ctx.loadUserCommandDesc)
          }, null, 8, _hoisted_19)
        ]),
        _: 1
      }, 8, ["content-title"]),
      vue.createVNode(_component_ContentBlock, {
        "content-title": _ctx.translate("LoginLdap_LDAPServers")
      }, {
        default: vue.withCtx(() => [
          vue.createElementVNode("div", null, [
            vue.createVNode(_component_AjaxForm, {
              "submit-api-method": "LoginLdap.saveServersInfo",
              "send-json-payload": true,
              "use-custom-data-binding": true,
              "form-data": _ctx.actualServers
            }, {
              default: vue.withCtx(() => [
                (vue.openBlock(true), vue.createElementBlock(vue.Fragment, null, vue.renderList(_ctx.actualServers, (serverInfo, index) => {
                  return vue.openBlock(), vue.createElementBlock("div", {
                    id: "ldapServersTable",
                    key: index
                  }, [
                    vue.createElementVNode("div", null, [
                      vue.createVNode(_component_Field, {
                        uicontrol: "text",
                        modelValue: serverInfo.name,
                        "onUpdate:modelValue": ($event) => serverInfo.name = $event,
                        title: _ctx.translate("LoginLdap_ServerName")
                      }, null, 8, ["modelValue", "onUpdate:modelValue", "title"])
                    ]),
                    vue.createElementVNode("div", null, [
                      vue.createVNode(_component_Field, {
                        uicontrol: "text",
                        modelValue: serverInfo.hostname,
                        "onUpdate:modelValue": ($event) => serverInfo.hostname = $event,
                        placeholder: "localhost",
                        title: _ctx.translate("LoginLdap_ServerUrl")
                      }, null, 8, ["modelValue", "onUpdate:modelValue", "title"])
                    ]),
                    vue.createElementVNode("div", null, [
                      vue.createVNode(_component_Field, {
                        uicontrol: "text",
                        modelValue: serverInfo.port,
                        "onUpdate:modelValue": ($event) => serverInfo.port = $event,
                        placeholder: "389",
                        title: _ctx.translate("LoginLdap_LdapPort"),
                        "inline-help": _ctx.translate("LoginLdap_LdapUrlPortWarning")
                      }, null, 8, ["modelValue", "onUpdate:modelValue", "title", "inline-help"])
                    ]),
                    vue.createElementVNode("div", null, [
                      vue.createVNode(_component_Field, {
                        uicontrol: "checkbox",
                        modelValue: serverInfo.start_tls,
                        "onUpdate:modelValue": ($event) => serverInfo.start_tls = $event,
                        title: _ctx.translate("LoginLdap_StartTLS"),
                        "inline-help": _ctx.translate("LoginLdap_StartTLSFieldHelp")
                      }, null, 8, ["modelValue", "onUpdate:modelValue", "title", "inline-help"])
                    ]),
                    vue.createElementVNode("div", null, [
                      vue.createVNode(_component_Field, {
                        uicontrol: "text",
                        placeholder: "dc=example,dc=site,dc=org",
                        modelValue: serverInfo.base_dn,
                        "onUpdate:modelValue": ($event) => serverInfo.base_dn = $event,
                        title: _ctx.translate("LoginLdap_BaseDn")
                      }, null, 8, ["modelValue", "onUpdate:modelValue", "title"])
                    ]),
                    vue.createElementVNode("div", null, [
                      vue.createVNode(_component_Field, {
                        uicontrol: "text",
                        placeholder: "cn=admin,dc=example,dc=site,dc=org",
                        modelValue: serverInfo.admin_user,
                        "onUpdate:modelValue": ($event) => serverInfo.admin_user = $event,
                        title: _ctx.translate("LoginLdap_AdminUser"),
                        "inline-help": _ctx.translate("LoginLdap_AdminUserDescription")
                      }, null, 8, ["modelValue", "onUpdate:modelValue", "title", "inline-help"])
                    ]),
                    vue.createElementVNode("div", null, [
                      vue.createVNode(_component_Field, {
                        modelValue: serverInfo.admin_pass,
                        "onUpdate:modelValue": ($event) => serverInfo.admin_pass = $event,
                        uicontrol: "password",
                        title: _ctx.translate("LoginLdap_AdminPass"),
                        "inline-help": _ctx.translate("LoginLdap_PasswordFieldHelp")
                      }, null, 8, ["modelValue", "onUpdate:modelValue", "title", "inline-help"])
                    ]),
                    vue.createVNode(_component_SaveButton, {
                      onConfirm: ($event) => _ctx.actualServers.splice(index, 1),
                      value: _ctx.translate("General_Delete")
                    }, null, 8, ["onConfirm", "value"])
                  ]);
                }), 128)),
                _hoisted_20,
                vue.createVNode(_component_SaveButton, {
                  onConfirm: _cache[25] || (_cache[25] = ($event) => _ctx.addServer()),
                  value: _ctx.translate("General_Add"),
                  style: { "margin-right": "3.5px" }
                }, null, 8, ["value"]),
                vue.createVNode(_component_SaveButton, {
                  saving: _ctx.isSavingServers,
                  onConfirm: _cache[26] || (_cache[26] = ($event) => _ctx.requestSaveServers())
                }, null, 8, ["saving"])
              ]),
              _: 1
            }, 8, ["form-data"])
          ])
        ]),
        _: 1
      }, 8, ["content-title"]),
      vue.createVNode(_component_PasswordConfirmation, {
        modelValue: _ctx.showPasswordConfirmation,
        "onUpdate:modelValue": _cache[27] || (_cache[27] = ($event) => _ctx.showPasswordConfirmation = $event),
        onConfirmed: _ctx.confirmSaveAction,
        onAborted: _cache[28] || (_cache[28] = ($event) => _ctx.pendingSaveTarget = null)
      }, null, 8, ["modelValue", "onConfirmed"])
    ]);
  }
  const Admin = /* @__PURE__ */ _export_sfc(_sfc_main$1, [["render", _sfc_render$1]]);
  const _sfc_main = vue.defineComponent({
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
      Admin
    }
  });
  function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
    const _component_Admin = vue.resolveComponent("Admin");
    return vue.openBlock(), vue.createElementBlock("div", null, [
      vue.createVNode(_component_Admin, {
        servers: _ctx.servers,
        "ldap-config": _ctx.ldapConfig,
        "updated-from-pre30": _ctx.updatedFromPre30
      }, null, 8, ["servers", "ldap-config", "updated-from-pre30"])
    ]);
  }
  const AdminPage = /* @__PURE__ */ _export_sfc(_sfc_main, [["render", _sfc_render]]);
  exports2.Admin = Admin;
  exports2.AdminPage = AdminPage;
  exports2.TestableField = TestableField;
  Object.defineProperty(exports2, Symbol.toStringTag, { value: "Module" });
}));
