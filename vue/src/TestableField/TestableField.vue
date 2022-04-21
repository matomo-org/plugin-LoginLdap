<!--
  Matomo - free/libre analytics platform
  @link https://matomo.org
  @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div class="loginLdapTestableField">
    <div>
      <Field
        uicontrol="text"
        @keydown="onKeydown($event)"
        :model-value="actualInputValue"
        @update:model-value="actualInputValue = $event; testResult = testError = null;
          $emit('update:modelValue', $event)"
        :name="name"
        :title="title"
        :inline-help="inlineHelp"
      />
    </div>
    <SaveButton
      v-show="actualInputValue"
      :saving="isChecking"
      @confirm="testInputValue()"
      :value="translate('LoginLdap_Test')"
    />
    <div
      class="test-config-option-success"
      v-show="testResult !== null"
      v-html="$sanitize(successMessage)"
    ></div>
    <div
      class="test-config-option-error"
      v-show="testError"
    >
      {{ testError }}
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { translate, AjaxHelper } from 'CoreHome';
import { Field, SaveButton } from 'CorePluginsAdmin';

interface LoginLdapTestableFieldState {
  actualInputValue?: null|string;
  testError: null|string;
  testResult: null|number;
  testValue: null|string;
  isChecking: boolean;
}

export default defineComponent({
  props: {
    modelValue: String,
    name: String,
    successTranslation: {
      type: String,
      required: true,
    },
    testApiMethod: {
      type: String,
      required: true,
    },
    testApiMethodArg: {
      type: String,
      required: true,
    },
    inlineHelp: String,
    title: String,
  },
  components: {
    Field,
    SaveButton,
  },
  emits: ['update:modelValue'],
  setup(props) {
    let abortController: AbortController|null = null;

    const sendRequestToTestValue = (actualInputValue: string) => {
      if (abortController) {
        abortController.abort();
        abortController = null;
      }

      abortController = new AbortController();

      return AjaxHelper.fetch<{ value: string }>(
        {
          method: props.testApiMethod,
          [props.testApiMethodArg]: actualInputValue,
        },
        {
          abortController,
          createErrorNotification: false,
        },
      ).finally(() => {
        abortController = null;
      });
    };

    return {
      sendRequestToTestValue,
    };
  },
  data(): LoginLdapTestableFieldState {
    return {
      actualInputValue: this.modelValue,
      testError: null,
      testResult: null,
      testValue: null,
      isChecking: false,
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
    onKeydown(event: KeyboardEvent) {
      if (event.key !== 'Enter') {
        return;
      }

      this.testInputValue();
    },
  },
  computed: {
    successMessage() {
      if (this.testResult === null) {
        return '';
      }

      const usersTranslation = this.testResult === 1
        ? translate('LoginLdap_OneUser')
        : translate('General_NUsers', `${this.testResult}`);
      return translate(this.successTranslation, `<strong>${usersTranslation}</strong>`);
    },
  },
});
</script>
