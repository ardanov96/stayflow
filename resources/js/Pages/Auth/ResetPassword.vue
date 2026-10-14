<script setup>
import { useI18n } from 'vue-i18n';
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const { t } = useI18n();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onSuccess: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.reset.title')">
        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <input v-model="form.token" type="hidden" />

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">{{ t('auth.reset.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50"
                    readonly
                />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">{{ t('auth.reset.password') }}</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                    minlength="8"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">{{ t('auth.reset.password_confirmation') }}</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    minlength="8"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
                {{ t('auth.reset.submit') }}
            </button>
        </form>
    </AuthLayout>
</template>