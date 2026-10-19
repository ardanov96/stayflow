<script setup>
import { useI18n } from 'vue-i18n';
import { useForm, Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({
    codes: { type: Array, default: () => [] },
    justGenerated: { type: Boolean, default: false },
    count: { type: Number, default: 0 },
});

const { t } = useI18n();

const regenerateForm = useForm({});

const regenerate = () => {
    if (! confirm(t('auth.two_factor.regenerate_confirm'))) {
        return;
    }
    regenerateForm.post('/two-factor/recovery-codes/regenerate');
};
</script>

<template>
    <AuthLayout :title="t('auth.two_factor.codes_title')">
        <p class="mt-1 text-sm text-gray-600">{{ t('auth.two_factor.codes_subtitle') }}</p>

        <ul v-if="justGenerated" class="mt-6 grid grid-cols-2 gap-2">
            <li
                v-for="(code, i) in codes"
                :key="code + i"
                class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-center font-mono text-sm"
            >
                {{ code }}
            </li>
        </ul>
        <p v-else class="mt-6 text-sm text-gray-700">
            {{ count }} {{ t('auth.two_factor.codes_title') }}
        </p>

        <div class="mt-6 flex items-center justify-between">
            <Link href="/" class="text-sm text-indigo-600 hover:text-indigo-700">
                {{ t('shell.common.back') }}
            </Link>
            <button
                type="button"
                @click="regenerate"
                :disabled="regenerateForm.processing"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
                {{ t('auth.two_factor.regenerate') }}
            </button>
        </div>
    </AuthLayout>
</template>