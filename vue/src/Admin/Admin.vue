<!--
  Matomo - free/libre analytics platform
  @link https://matomo.org
  @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div>
    <div>
      <AjaxForm
        submit-api-method="LoginLdap.saveLdapConfig"
        :use-custom-data-binding="true"
        :send-json-payload="true"
        :form-data="actualLdapConfig"
      >
        <template #default="ajaxForm">
          <ContentBlock
            id="ldapSettings"
            :content-title="translate('LoginLdap_Settings')"
          >
            <div v-if="updatedFromPre30">
              <Notification
                id="pre300AlwaysUseLdapWarning"
                context="warning"
                :noclear="true"
              >
                <strong>{{ translate('General_Note') }}</strong>:
                {{ translate('LoginLdap_UpdateFromPre300Warning') }}
              </Notification>
            </div>
            <div>
              <Field
                uicontrol="checkbox"
                name="synchronize_users_after_login"
                v-model="actualLdapConfig.use_ldap_for_authentication"
                :title="translate('LoginLdap_UseLdapForAuthentication')"
                :inline-help="useLdapForAuthHelp"
              >
              </Field>
            </div>
            <div>
              <Field
                uicontrol="checkbox"
                name="use_webserver_auth"
                v-model="actualLdapConfig.use_webserver_auth"
                :title="translate('LoginLdap_Kerberos')"
                :inline-help="translate('LoginLdap_KerberosDescription')"
              >
              </Field>
            </div>
            <div>
              <Field
                uicontrol="checkbox"
                name="enable_password_confirmation"
                v-model="actualLdapConfig.enable_password_confirmation"
                :title="translate('LoginLdap_OptionsPWCONFIRMATION')"
                :inline-help="translate('LoginLdap_OptionsPWCONFIRMATIONDescription')"
              >
              </Field>
            </div>
            <div v-show="actualLdapConfig.use_webserver_auth">
              <div>
                <Field
                  uicontrol="checkbox"
                  name="strip_domain_from_web_auth"
                  v-model="actualLdapConfig.strip_domain_from_web_auth"
                  :title="translate('LoginLdap_StripDomainFromWebAuth')"
                  :inline-help="translate('LoginLdap_StripDomainFromWebAuthDescription')"
                >
                </Field>
              </div>
            </div>
            <div>
              <Field
                uicontrol="text"
                name="ldap_network_timeout"
                v-model="actualLdapConfig.ldap_network_timeout"
                :title="translate('LoginLdap_NetworkTimeout')"
                :inline-help="ldapNetworkTimeoutHelp"
              >
              </Field>
            </div>
            <div>
              <Field
                uicontrol="text"
                name="required_member_of_field"
                v-model="actualLdapConfig.required_member_of_field"
                :title="translate('LoginLdap_MemberOfField')"
                :inline-help="translate('LoginLdap_MemberOfFieldDescription')"
              >
              </Field>
            </div>
            <div>
              <TestableField
                uicontrol="text"
                v-model="actualLdapConfig.required_member_of"
                name="required_member_of"
                test-api-method="LoginLdap.getCountOfUsersMemberOf"
                test-api-method-arg="memberOf"
                success-translation="LoginLdap_MemberOfCount"
                :title="translate('LoginLdap_MemberOf')"
                :inline-help="memberOfCountHelp"
              >
              </TestableField>
            </div>
            <div>
              <TestableField
                uicontrol="text"
                v-model="actualLdapConfig.ldap_user_filter"
                name="ldap_user_filter"
                test-api-method="LoginLdap.getCountOfUsersMatchingFilter"
                test-api-method-arg="filter"
                success-translation="LoginLdap_FilterCount"
                :title="translate('LoginLdap_Filter')"
                :inline-help="translate('LoginLdap_FilterDescription')"
              >
              </TestableField>
            </div>
            <hr />
            <SaveButton
              :saving="ajaxForm.isSubmitting"
              @confirm="ajaxForm.submitForm()"
            />
          </ContentBlock>
          <ContentBlock
            id="ldapUserMappingSettings"
            :content-title="translate('LoginLdap_UserSyncSettings')"
          >
            <div>
              <Field
                uicontrol="text"
                name="ldap_user_id_field"
                v-model="actualLdapConfig.ldap_user_id_field"
                :title="translate('LoginLdap_UserIdField')"
                :inline-help="translate('LoginLdap_UserIdFieldDescription')"
              >
              </Field>
            </div>
            <div>
              <Field
                uicontrol="text"
                name="ldap_password_field"
                v-model="actualLdapConfig.ldap_password_field"
                :title="translate('LoginLdap_PasswordField')"
                :inline-help="ldapPasswordFieldHelp"
              >
              </Field>
            </div>
            <div>
              <Field
                uicontrol="text"
                name="ldap_mail_field"
                v-model="actualLdapConfig.ldap_mail_field"
                :title="translate('LoginLdap_MailField')"
                :inline-help="translate('LoginLdap_MailFieldDescription')"
              >
              </Field>
            </div>
            <div>
              <Field
                uicontrol="text"
                name="user_email_suffix"
                v-model="actualLdapConfig.user_email_suffix"
                :title="translate('LoginLdap_UsernameSuffix')"
                :inline-help="translate('LoginLdap_UsernameSuffixDescription')"
              >
              </Field>
            </div>
            <div>
              <Field
                uicontrol="text"
                name="new_user_default_sites_view_access"
                v-model="actualLdapConfig.new_user_default_sites_view_access"
                :title="translate('LoginLdap_NewUserDefaultSitesViewAccess')"
                :inline-help="translate('LoginLdap_NewUserDefaultSitesViewAccessDescription')"
              >
              </Field>
            </div>
            <hr />
            <SaveButton
              :saving="ajaxForm.isSubmitting"
              @confirm="ajaxForm.submitForm()"
            />
          </ContentBlock>
          <ContentBlock
            id="ldapUserAccessMappingSettings"
            :content-title="translate('LoginLdap_AccessSyncSettings')"
          >
            <p v-html="$sanitize(readMoreAboutAccessSynchronization)">
            </p>
            <div>
              <Field
                uicontrol="checkbox"
                name="enable_synchronize_access_from_ldap"
                v-model="actualLdapConfig.enable_synchronize_access_from_ldap"
                :title="translate('LoginLdap_EnableLdapAccessSynchronization')"
                :inline-help="translate('LoginLdap_EnableLdapAccessSynchronizationDescription')"
              >
              </Field>
            </div>
            <div v-show="actualLdapConfig.enable_synchronize_access_from_ldap">
              <div>
                <Notification context="info" :noclear="true">
                  <strong>{{ translate('LoginLdap_ExpectedLdapAttributes') }}</strong><br />
                  <br />
                  {{ translate('LoginLdap_ExpectedLdapAttributesPrelude') }}:<br />
                  <br />
                  <ul>
                    <li v-html="$sanitize(sampleViewAttribute)"></li>
                    <li v-html="$sanitize(sampleAdminAttribute)"></li>
                    <li v-html="$sanitize(sampleSuperuserAttribute)"></li>
                  </ul>
                </Notification>
              </div>
              <div>
                <Field
                  uicontrol="text"
                  name="ldap_view_access_field"
                  v-model="actualLdapConfig.ldap_view_access_field"
                  :title="translate('LoginLdap_LdapViewAccessField')"
                  :inline-help="translate('LoginLdap_LdapViewAccessFieldDescription')"
                >
                </Field>
              </div>
              <div>
                <Field
                  uicontrol="text"
                  name="ldap_admin_access_field"
                  v-model="actualLdapConfig.ldap_admin_access_field"
                  :title="translate('LoginLdap_LdapAdminAccessField')"
                  :inline-help="translate('LoginLdap_LdapAdminAccessFieldDescription')"
                >
                </Field>
              </div>
              <div>
                <Field
                  uicontrol="text"
                  name="ldap_superuser_access_field"
                  v-model="actualLdapConfig.ldap_superuser_access_field"
                  :title="translate('LoginLdap_LdapSuperUserAccessField')"
                  :inline-help="translate('LoginLdap_LdapSuperUserAccessFieldDescription')"
                >
                </Field>
              </div>
              <div>
                <Field
                  uicontrol="text"
                  name="user_access_attribute_server_specification_delimiter"
                  v-model="actualLdapConfig.user_access_attribute_server_specification_delimiter"
                  :title="translate('LoginLdap_LdapUserAccessAttributeServerSpecDelimiter')"
                  :inline-help="translate(
                    'LoginLdap_LdapUserAccessAttributeServerSpecDelimiterDescription')"
                >
                </Field>
              </div>
              <div>
                <Field
                  uicontrol="text"
                  name="user_access_attribute_server_separator"
                  v-model="actualLdapConfig.user_access_attribute_server_separator"
                  :title="translate('LoginLdap_LdapUserAccessAttributeServerSeparator')"
                  :inline-help="translate(
                    'LoginLdap_LdapUserAccessAttributeServerSeparatorDescription')"
                >
                </Field>
              </div>
              <div>
                <Field
                  uicontrol="text"
                  name="instance_name"
                  v-model="actualLdapConfig.instance_name"
                  :title="translate('LoginLdap_ThisMatomoInstanceName')"
                  :inline-help="translate('LoginLdap_ThisMatomoInstanceNameDescription')"
                >
                </Field>
              </div>
              <hr />
              <SaveButton
                :saving="ajaxForm.isSubmitting"
                @confirm="ajaxForm.submitForm()"
              />
            </div>
          </ContentBlock>
        </template>
      </AjaxForm>
    </div>
    <ContentBlock
      id="ldapManualSynchronizeUser"
      :content-title="translate('LoginLdap_LoadUser')"
    >
      <p>{{ translate('LoginLdap_LoadUserDescription') }}</p>
      <div>
        <Field
          uicontrol="text"
          placeholder="Enter a username..."
          v-model="userToSynchronize"
        >
        </Field>
      </div>
      <SaveButton
        @confirm="synchronizeUser(userToSynchronize)"
        :value="translate('LoginLdap_Go')"
        style="margin-right:7px"
      />
      <img
        src="plugins/Morpheus/images/loading-blue.gif"
        v-show="isSynchronizing"
      /><br />
      <br />
      <div v-show="synchronizeUserError || synchronizeUserDone">
        <div v-if="synchronizeUserError" v-html="$sanitize(synchronizeUserError)"></div>
        <div v-if="synchronizeUserDone">
          <strong>{{ translate('General_Done') }}!</strong>
        </div>
        <br />
      </div>
      <span v-html="$sanitize(loadUserCommandDesc)"></span>
    </ContentBlock>
    <ContentBlock :content-title="translate('LoginLdap_LDAPServers')">
      <div>
        <AjaxForm
          submit-api-method="LoginLdap.saveServersInfo"
          :send-json-payload="true"
          :use-custom-data-binding="true"
          :form-data="actualServers"
        >
          <template v-slot:default="ajaxForm">
            <div
              id="ldapServersTable"
              v-for="(serverInfo, index) in actualServers"
              :key="index"
            >
              <div>
                <Field
                  uicontrol="text"
                  v-model="serverInfo.name"
                  :title="translate('LoginLdap_ServerName')"
                >
                </Field>
              </div>
              <div>
                <Field
                  uicontrol="text"
                  v-model="serverInfo.hostname"
                  placeholder="localhost"
                  :title="translate('LoginLdap_ServerUrl')"
                >
                </Field>
              </div>
              <div>
                <Field
                  uicontrol="text"
                  v-model="serverInfo.port"
                  placeholder="389"
                  :title="translate('LoginLdap_LdapPort')"
                  :inline-help="translate('LoginLdap_LdapUrlPortWarning')"
                >
                </Field>
              </div>
              <div>
                <Field
                  uicontrol="checkbox"
                  v-model="serverInfo.start_tls"
                  :title="translate('LoginLdap_StartTLS')"
                  :inline-help="translate('LoginLdap_StartTLSFieldHelp')"
                >
                </Field>
              </div>
              <div>
                <Field
                  uicontrol="text"
                  placeholder="dc=example,dc=site,dc=org"
                  v-model="serverInfo.base_dn"
                  :title="translate('LoginLdap_BaseDn')"
                >
                </Field>
              </div>
              <div>
                <Field
                  uicontrol="text"
                  placeholder="cn=admin,dc=example,dc=site,dc=org"
                  v-model="serverInfo.admin_user"
                  :title="translate('LoginLdap_AdminUser')"
                  :inline-help="translate('LoginLdap_AdminUserDescription')"
                >
                </Field>
              </div>
              <div>
                <Field
                  v-model="serverInfo.admin_pass"
                  uicontrol="password"
                  :title="translate('LoginLdap_AdminPass')"
                  :inline-help="translate('LoginLdap_PasswordFieldHelp')"
                >
                </Field>
              </div>
              <SaveButton
                @confirm="actualServers.splice(index, 1)"
                :value="translate('General_Delete')"
              />
            </div>
            <hr />
            <SaveButton
              @confirm="addServer()"
              :value="translate('General_Add')"
              style="margin-right:3.5px"
            />
            <SaveButton
              :saving="ajaxForm.isSubmitting"
              @confirm="ajaxForm.submitForm()"
            />
          </template>
        </AjaxForm>
      </div>
    </ContentBlock>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import {
  translate,
  AjaxHelper,
  AjaxForm,
  ContentBlock,
  Notification,
} from 'CoreHome';
import { Field, SaveButton } from 'CorePluginsAdmin';
import TestableField from '../TestableField/TestableField.vue';

interface LoginLdapConfig {
  use_ldap_for_authentication: string|number;
  use_webserver_auth: string|number;
  enable_password_confirmation: number;
  strip_domain_from_web_auth: string|number;
  ldap_network_timeout: string|number;
  required_member_of_field: string;
  required_member_of: string;
  ldap_user_filter: string;
  ldap_user_id_field: string;
  ldap_password_field: string;
  ldap_mail_field: string;
  user_email_suffix: string;
  new_user_default_sites_view_access: string;
  enable_synchronize_access_from_ldap: string|number;
  ldap_view_access_field: string;
  ldap_admin_access_field: string;
  ldap_superuser_access_field: string;
  user_access_attribute_server_specification_delimiter: string;
  user_access_attribute_server_separator: string;
  instance_name: string;
}

interface ServerInfo {
  name: string;
  hostname: string;
  port: string|number;
  start_tls?: boolean;
  base_dn: string;
  admin_user: string;
  admin_pass: string;
}

interface AdminState {
  actualLdapConfig: LoginLdapConfig;
  userToSynchronize: string;
  actualServers: ServerInfo[];
  synchronizeUserError: null|string;
  synchronizeUserDone: null|boolean;
  isSynchronizing: boolean;
}

function getSampleAccessAttribute(
  config: LoginLdapConfig,
  accessField: string,
  firstValue?: string,
  secondValue?: string,
) {
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
    result += 'piwikB';
  } else {
    result += 'anotherhost.com';
  }

  if (secondValue) {
    result += `${config.user_access_attribute_server_separator}${secondValue}`;
  }

  return result;
}

export default defineComponent({
  props: {
    ldapConfig: {
      type: Object,
      required: true,
    },
    servers: {
      type: Array,
      required: true,
    },
    updatedFromPre30: Boolean,
  },
  components: {
    AjaxForm,
    ContentBlock,
    Notification,
    Field,
    TestableField,
    SaveButton,
  },
  data(): AdminState {
    return {
      actualLdapConfig: { ...(this.ldapConfig as LoginLdapConfig) },
      userToSynchronize: '',
      actualServers: [...(this.servers as ServerInfo[])],
      synchronizeUserError: null,
      synchronizeUserDone: null,
      isSynchronizing: false,
    };
  },
  methods: {
    addServer() {
      this.actualServers.push({
        name: `server${this.actualServers.length + 1}`,
        hostname: '',
        port: 389,
        base_dn: '',
        admin_user: '',
        admin_pass: '',
      });
    },
    synchronizeUser(userLogin: string) {
      this.synchronizeUserError = null;
      this.synchronizeUserDone = null;
      this.isSynchronizing = true;
      AjaxHelper.post(
        {
          method: 'LoginLdap.synchronizeUser',
        },
        {
          login: userLogin,
        },
        {
          createErrorNotification: false,
        },
      ).then(() => {
        this.synchronizeUserDone = true;
      }).catch((error) => {
        this.synchronizeUserError = error.message || error;
      }).finally(() => {
        this.isSynchronizing = false;
      });
    },
  },
  computed: {
    sampleViewAttribute() {
      const config = this.actualLdapConfig;
      return getSampleAccessAttribute(config, config.ldap_view_access_field, '1,2', '3,4');
    },
    sampleAdminAttribute() {
      const config = this.actualLdapConfig;
      return getSampleAccessAttribute(config, config.ldap_admin_access_field, 'all', 'all');
    },
    sampleSuperuserAttribute() {
      const config = this.actualLdapConfig;
      return getSampleAccessAttribute(config, config.ldap_superuser_access_field);
    },
    readMoreAboutAccessSynchronization() {
      const link = 'https://github.com/matomo-org/plugin-LoginLdap#matomo-access-synchronization';
      return translate(
        'LoginLdap_ReadMoreAboutAccessSynchronization',
        `<a target="_blank" href="${link}" rel="noreferrer noopener">`,
        '</a>',
      );
    },
    loadUserCommandDesc() {
      const link = 'https://github.com/matomo-org/plugin-LoginLdap#commands';
      return translate(
        'LoginLdap_LoadUserCommandDesc',
        `<a target="_blank" href="${link}" rel="noreferrer noopener">loginldap:synchronize-users</a>`,
      );
    },
    useLdapForAuthHelp() {
      const start = translate('LoginLdap_UseLdapForAuthenticationDescription');
      return `${start}<br /><br />${translate('LoginLdap_MobileAppIntegrationNote')}`;
    },
    ldapNetworkTimeoutHelp() {
      const start = translate('LoginLdap_NetworkTimeoutDescription');
      return `${start}<br />${translate('LoginLdap_NetworkTimeoutDescription2')}`;
    },
    memberOfCountHelp() {
      const start = translate('LoginLdap_MemberOfDescription');
      return `${start}<br />${translate('LoginLdap_MemberOfDescription2')}`;
    },
    ldapPasswordFieldHelp() {
      const start = translate('LoginLdap_PasswordFieldDescription');
      return `${start}<br /><br />${translate('LoginLdap_PasswordFieldDescription2')}`;
    },
  },
});
</script>
