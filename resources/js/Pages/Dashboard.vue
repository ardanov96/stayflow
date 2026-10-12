<script setup>
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    stats: {
        type: Object,
        default: () => ({
            arrivals: 0,
            departures: 0,
            inHouse: 0,
            occupancy: 0,
        }),
    },
});

const { t, locale } = useI18n();

// `statKey` is the exact key on the `stats` prop. It is declared explicitly
// on each card so we do not have to translate between card id and stat name
// at render time (a previous ternary chain here was broken and rendered
// the wrong value for the in-house card).
const cards = [
    { id: 'arrivals', statKey: 'arrivals', icon: '↓' },
    { id: 'departures', statKey: 'departures', icon: '↑' },
    { id: 'in_house', statKey: 'inHouse', icon: '◉' },
    { id: 'occupancy', statKey: 'occupancy', icon: '%' },
];

const fmt = (value, currentLocale) => {
    if (typeof value !== 'number') return '—';
    if (currentLocale === 'id') return new Intl.NumberFormat('id-ID').format(value);
    if (currentLocale === 'zh') return new Intl.NumberFormat('zh-CN').format(value);
    return new Intl.NumberFormat('en-US').format(value);
};
</script>

<template>
    <AppLayout>
        <header class="mb-8">
            <h1 class="text-2xl font-semibold text-gray-900">{{ t('shell.dashboard.title') }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ t('shell.dashboard.subtitle') }}</p>
        </header>

        <section aria-label="Key performance indicators" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="card in cards"
                :key="card.id"
                class="bg-white overflow-hidden border border-gray-200 rounded-lg"
            >
                <div class="p-5">
                    <div class="flex items-center">
                        <span class="text-2xl text-indigo-500 mr-3" aria-hidden="true">{{ card.icon }}</span>
                        <h2 class="text-sm font-medium text-gray-500">
                            {{ t(`shell.dashboard.card_${card.id}`) }}
                        </h2>
                    </div>
                    <p class="mt-3 text-3xl font-semibold text-gray-900">
                        {{ fmt(stats[card.statKey], locale) }}<span v-if="card.id === 'occupancy'" class="text-base font-medium text-gray-500">%</span>
                    </p>
                </div>
            </div>
        </section>

        <section class="mt-10 bg-white border border-gray-200 rounded-lg p-6">
            <h2 class="text-base font-semibold text-gray-900">{{ t('shell.dashboard.empty_title') }}</h2>
            <p class="mt-2 text-sm text-gray-600">{{ t('shell.dashboard.empty_body') }}</p>
            <button
                type="button"
                class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                {{ t('shell.dashboard.cta_settings') }}
            </button>
        </section>
    </AppLayout>
</template>