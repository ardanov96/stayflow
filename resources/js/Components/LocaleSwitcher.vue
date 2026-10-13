<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';

const { locale, t } = useI18n();
const page = usePage();

const locales = ref((page.props?.app?.locales ?? ['en']).slice().sort((a, b) => a.localeCompare(b)));

const change = async (next) => {
    if (!next || next === locale.value) {
        return;
    }
    try {
        await router.post('/locale', { locale: next }, {
            preserveScroll: true,
            preserveState: false,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        // Re-render with new strings: a full reload is the simplest reliable
        // way to re-evaluate server-side properties (currentLocale, etc.).
        window.location.reload();
    } catch (error) {
        console.error('Locale switch failed', error);
    }
};

watch(locale, (next) => {
    document.documentElement.lang = next.replace('_', '-');
});
</script>

<template>
    <label class="inline-flex items-center space-x-2">
        <span class="sr-only">{{ t('shell.topbar.locale_label') }}</span>
        <select
            :value="locale"
            class="text-sm rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
            @change="change($event.target.value)"
        >
            <option v-for="code in locales" :key="code" :value="code">
                {{ t(`shell.locales.${code}`) }}
            </option>
        </select>
    </label>
</template>