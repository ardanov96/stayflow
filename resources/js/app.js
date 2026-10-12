import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createI18n } from 'vue-i18n';

import en from './locales/en.json';
import id from './locales/id.json';
import zh from './locales/zh.json';

const appName = window.document?.title || 'Stayflow';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const initialLocale = props.initialProps?.app?.currentLocale ?? 'en';

        const i18n = createI18n({
            legacy: false,
            locale: initialLocale,
            fallbackLocale: 'en',
            messages: { en, id, zh },
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .mount(el);
    },
}).then(() => {
    document.documentElement.lang = (window.__INITIAL_LOCALE__ ?? 'en').replace('_', '-');
});

window.__INITIAL_LOCALE__ = document.documentElement.lang;