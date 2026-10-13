// ESLint v9 flat config. CI runs the npm lint script; this file must
// therefore be present and the project must pass with --max-warnings=0.
//
// Scope: resources/js (PMS UI). The booking widget under resources/js/widget
// (added in P3) will be a separate config so it can ship independently.

import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';

export default [
    {
        ignores: [
            'node_modules/**',
            'vendor/**',
            'public/**',
            'storage/**',
            'bootstrap/cache/**',
            // Future: booking widget bundle, separate concern.
            'resources/js/widget/**',
        ],
    },
    js.configs.recommended,
    ...vue.configs['flat/recommended'],
    {
        files: ['resources/js/**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 2024,
            sourceType: 'module',
            parser: vueParser,
            parserOptions: {
                ecmaVersion: 2024,
                sourceType: 'module',
            },
            globals: {
                // Browser globals.
                window: 'readonly',
                document: 'readonly',
                console: 'readonly',
                navigator: 'readonly',
                location: 'readonly',
                history: 'readonly',
                setTimeout: 'readonly',
                clearTimeout: 'readonly',
                setInterval: 'readonly',
                clearInterval: 'readonly',
                Intl: 'readonly',
                // Inertia Blade directive @routes exposes a route() helper.
                route: 'readonly',
            },
        },
        rules: {
            // We use console.error in the locale switcher; keep that allowed.
            'no-console': 'off',
            // Allow the occasional unused destructured var in Vue templates
            // (Vue 3 SFC + vue-i18n often uses a few that ESLint cannot see).
            'no-unused-vars': [
                'warn',
                { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
            ],
            // Pinia/Vue idiom; recommended rule is fine.
            'vue/multi-word-component-names': 'off',
            // The existing shell templates use 4-space indentation per level
            // (matching Laravel Breeze / Jetstream), not the default 2-space
            // rule. Honour that and stop fighting prettier-style enforcement.
            'vue/html-indent': ['warn', 4, { baseIndent: 1 }],
            'vue/html-closing-bracket-newline': 'off',
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            'vue/first-attribute-linebreak': 'off',
            'vue/html-self-closing': 'off',
            'vue/attributes-order': 'off',
            'vue/attribute-hyphenation': 'off',
            'vue/v-on-event-hyphenation': 'off',
        },
    },
];
