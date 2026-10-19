<script setup>
import { useI18n } from 'vue-i18n';
import { useForm, Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

defineProps({
    status: { type: String, default: '' },
});

const { t } = useI18n();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post('/forgot-password');
};
</script>

<template>
    <AuthLayout :title="t('auth.forgot.title')">
        <p class="mt-1 text-sm text-gray-600">{{ t('auth.forgot.subtitle') }}</p>

        <div v-if="status" class="mt-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-md p-3">
            {{ status }}
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">{{ t('auth.forgot.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
                {{ t('auth.forgot.submit') }}
            </button>

            <div class="text-center">
                <Link href="/login" class="text-sm text-indigo-600 hover:text-indigo-700">
                    {{ t('auth.forgot.back') }}
                </Link>
            </div>
        </form>
    </AuthLayout>
</template>