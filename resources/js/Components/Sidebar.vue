<script setup>
import { useI18n } from 'vue-i18n';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const { t } = useI18n();
const page = usePage();

const navItems = computed(() => ([
    { key: 'dashboard', route: 'dashboard' },
    { key: 'reservations', route: 'shell.dashboard' },
    { key: 'inventory', route: 'shell.dashboard' },
    { key: 'calendar', route: 'shell.dashboard' },
    { key: 'guests', route: 'shell.dashboard' },
    { key: 'reports', route: 'shell.dashboard' },
    { key: 'settings', route: 'shell.dashboard' },
]));

const appName = computed(() => page.props?.app?.name ?? t('shell.sidebar.brand'));
</script>

<template>
    <aside class="bg-white border-r border-gray-200 w-64" :aria-label="t('shell.sidebar.nav_label')">
        <div class="h-16 flex items-center px-5 border-b border-gray-200">
            <Link :href="route('dashboard')" class="text-lg font-semibold text-gray-900 hover:text-indigo-600">
                {{ appName }}
            </Link>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1">
            <template v-for="item in navItems" :key="item.key">
                <Link
                    :href="route(item.route)"
                    class="block px-3 py-2 text-sm font-medium rounded-md text-gray-700 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-gray-100"
                    active-class="bg-indigo-50 text-indigo-700"
                >
                    {{ t(`shell.sidebar.nav.${item.key}`) }}
                </Link>
            </template>
        </nav>

        <div class="border-t border-gray-200 px-5 py-3 text-xs text-gray-500">
            <div>{{ t('shell.sidebar.footer.version') }} 0.1.0</div>
            <div>{{ t('shell.sidebar.footer.tag') }}</div>
        </div>
    </aside>
</template>