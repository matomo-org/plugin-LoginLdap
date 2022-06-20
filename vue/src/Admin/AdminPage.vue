<!--
  Matomo - free/libre analytics platform
  @link https://matomo.org
  @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div>
    <Notification v-if="isLoginControllerActivated" context="warning" :noclear="true">
      <strong>{{ translate('General_Warning') }}</strong>:
      {{ translate('LoginLdap_LoginPluginEnabledWarning', 'Login', 'LoginLdap') }}
    </Notification>

    <Admin
      :servers="servers"
      :ldap-config="ldapConfig"
      :updated-from-pre30="updatedFromPre30"
    />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { Notification } from 'CoreHome';
import Admin from './Admin.vue';

export default defineComponent({
  props: {
    isLoginControllerActivated: Boolean,
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
    Notification,
    Admin,
  },
});
</script>
