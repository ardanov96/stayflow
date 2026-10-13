<script setup>
import { useI18n } from 'vue-i18n';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import PropertySwitcher from '@/Components/PropertySwitcher.vue';

defineEmits(['toggle-sidebar']);

const { t } = useI18n();
</script>

<template>
    <header class="h-16 bg-white border-b border-gray-200 flex items-center px-4 sm:px-6 lg:px-8">
        <button
            type="button"
            class="md:hidden -ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
            :aria-label="t('shell.common.open_menu')"
            @click="$emit('toggle-sidebar')"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <div class="flex-1 flex items-center justify-end space-x-3">
            <div class="hidden sm:block flex-1 max-w-md">
                <label for="global-search" class="sr-only">{{ t('shell.common.search') }}</label>
                <input
                    id="global-search"
                    type="search"
                    :placeholder="t('shell.topbar.search_placeholder')"
                    class="block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <PropertySwitcher />

            <LocaleSwitcher />
        </div>
    </header>
</template>