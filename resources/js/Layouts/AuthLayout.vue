<script setup>
import { useI18n } from 'vue-i18n';
import { Link } from '@inertiajs/vue3';

const { t } = useI18n();

defineProps({
    title: { type: String, required: true },
});
</script>

<template>
    <div class="min-h-full flex flex-col items-center justify-center bg-gray-50 px-4 py-12">
        <Link href="/" class="mb-6 flex items-center gap-2 text-2xl font-semibold text-gray-900">
            <span aria-hidden="true">🏨</span>
            <span>{{ t('app.name') }}</span>
        </Link>
        <div class="w-full max-w-md bg-white shadow-sm border border-gray-200 rounded-lg p-6">
            <h1 class="text-xl font-semibold text-gray-900">{{ title }}</h1>
            <slot />
        </div>
    </div>
</template>