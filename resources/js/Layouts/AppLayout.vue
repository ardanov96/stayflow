<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Sidebar from '@/Components/Sidebar.vue';
import Topbar from '@/Components/Topbar.vue';

defineProps({
    title: { type: String, default: '' },
});

const { t } = useI18n();

const sidebarOpen = ref(false);

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};

const pageTitle = computed(() => t('app.name'));
</script>

<template>
    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-indigo-600 focus:px-3 focus:py-2 focus:text-white"
    >
        {{ t('shell.common.skip_to_main') }}
    </a>

    <div class="min-h-screen flex bg-gray-50 text-gray-900">
        <!-- Sidebar -->
        <Sidebar :class="['hidden md:flex md:flex-shrink-0']" />

        <!-- Mobile drawer -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 flex md:hidden"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="fixed inset-0 bg-gray-600/75"
                aria-hidden="true"
                @click="closeSidebar"
            ></div>
            <Sidebar class="relative flex-1 flex flex-col max-w-xs w-full bg-white" @navigate="closeSidebar" />
        </div>

        <!-- Main column -->
        <div class="flex-1 flex flex-col min-w-0">
            <Topbar @toggle-sidebar="toggleSidebar" />

            <main id="main-content" tabindex="-1" class="flex-1 overflow-y-auto focus:outline-none">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <slot />
                </div>
            </main>

            <footer class="border-t border-gray-200 bg-white">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 text-xs text-gray-500">
                    {{ pageTitle }} - {{ t('shell.sidebar.footer.tag') }}
                </div>
            </footer>
        </div>
    </div>
</template>