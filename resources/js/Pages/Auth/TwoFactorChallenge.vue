<script setup>
import { useI18n } from 'vue-i18n';
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

defineProps({
    status: { type: String, default: '' },
});

const { t } = useI18n();

const form = useForm({
    code: '',
});

const submit = () => {
    form.post('/two-factor-challenge');
};
</script>

<template>
    <AuthLayout :title="t('auth.two_factor.challenge_title')">
        <p class="mt-1 text-sm text-gray-600">{{ t('auth.two_factor.challenge_subtitle') }}</p>

        <div v-if="status" class="mt-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-md p-3">
            {{ status }}
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="code" class="block text-sm font-medium text-gray-700">{{ t('auth.two_factor.code_label') }}</label>
                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    required
                    minlength="6"
                    maxlength="10"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono tracking-widest focus:border-indigo-500 focus:ring-indigo-500"
                />
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
                {{ t('auth.two_factor.submit') }}
            </button>
        </form>
    </AuthLayout>
</template>