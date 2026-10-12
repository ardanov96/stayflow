<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

// Per P1-06 the property switcher will be wired to the OrganizationContext
// the user belongs to. For now it is a disabled placeholder that exists in
// every locale — accessibility-conscious and consistent.
</script>

<template>
    <label class="hidden sm:inline-flex items-center space-x-2">
        <span class="sr-only">{{ t('shell.topbar.property_placeholder') }}</span>
        <select
            disabled
            class="text-sm rounded-md border-gray-300 bg-gray-100 text-gray-500 cursor-not-allowed focus:outline-none"
        >
            <option>{{ t('shell.topbar.property_placeholder') }}</option>
        </select>
    </label>
</template>