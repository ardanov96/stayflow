<script setup>
import { useI18n } from 'vue-i18n';
import { useForm, Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({
    alreadyEnrolled: { type: Boolean, default: false },
    secret: { type: String, default: '' },
    otpauthUrl: { type: String, default: '' },
});

const { t } = useI18n();

const beginForm = useForm({});

const begin = () => {
    beginForm.post('/two-factor/setup');
};

const confirmForm = useForm({
    code: '',
});

const confirm = () => {
    confirmForm.post('/two-factor/setup/confirm', {
        onSuccess: () => confirmForm.reset('code'),
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.two_factor.setup_title')">
        <div v-if="alreadyEnrolled" class="mt-6 space-y-4">
            <p class="text-sm text-gray-700">{{ t('auth.two_factor.status_enabled') }}</p>
            <Link
                href="/two-factor/recovery-codes"
                class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
                {{ t('auth.two_factor.codes_title') }}
            </Link>
            <form method="post" action="/two-factor/disable" class="inline">
                <button
                    type="submit"
                    class="ml-2 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700"
                >
                    {{ t('auth.two_factor.disable') }}
                </button>
            </form>
        </div>

        <div v-else-if="!secret" class="mt-6 space-y-4">
            <p class="text-sm text-gray-600">{{ t('auth.two_factor.setup_subtitle') }}</p>
            <button
                type="button"
                @click="begin"
                :disabled="beginForm.processing"
                class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
                {{ t('auth.two_factor.confirm_submit') }}
            </button>
        </div>

        <div v-else class="mt-6 space-y-4">
            <p class="text-sm text-gray-600">{{ t('auth.two_factor.setup_subtitle') }}</p>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ t('auth.two_factor.scan_label') }}</label>
                <textarea
                    readonly
                    rows="2"
                    :value="otpauthUrl"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-xs font-mono bg-gray-50"
                ></textarea>
            </div>

            <form @submit.prevent="confirm" class="space-y-3">
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">{{ t('auth.two_factor.confirm_code_label') }}</label>
                    <input
                        id="code"
                        v-model="confirmForm.code"
                        type="text"
                        inputmode="numeric"
                        required
                        pattern="[0-9]{6}"
                        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono tracking-widest focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="confirmForm.errors.code" class="mt-1 text-sm text-red-600">{{ confirmForm.errors.code }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="confirmForm.processing"
                    class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                >
                    {{ t('auth.two_factor.confirm_submit') }}
                </button>
            </form>
        </div>
    </AuthLayout>
</template>