import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import typescript from 'typescript-eslint';

/** @type {import('eslint').Linter.Config[]} */
export default [
    js.configs.recommended,
    ...typescript.configs.recommended,
    {
        ...react.configs.flat.recommended,
        ...react.configs.flat['jsx-runtime'], // Required for React 17+
        languageOptions: {
            globals: {
                ...globals.browser,
            },
        },
        rules: {
            'react/react-in-jsx-scope': 'off',
            'react/prop-types': 'off',
            'react/no-unescaped-entities': 'off',
        },
        settings: {
            react: {
                version: 'detect',
            },
        },
    },
    {
        plugins: {
            'react-hooks': reactHooks,
        },
        rules: {
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'warn',
        },
    },
    {
        // navigator.clipboard only exists in a secure context, so on a plain-HTTP
        // install it is undefined and a copy button silently does nothing — no
        // copy, no error, no clue. That shipped three times before it was noticed.
        // resources/js/lib/clipboard.ts wraps it with an execCommand fallback and
        // returns whether the text actually landed; everything else goes through
        // that, so the exemption below is the single place allowed to touch the API.
        files: ['resources/js/**/*.{ts,tsx}'],
        ignores: ['resources/js/lib/clipboard.ts'],
        rules: {
            'no-restricted-properties': [
                'error',
                {
                    object: 'navigator',
                    property: 'clipboard',
                    message: "Use copyText() from '@/lib/clipboard' — navigator.clipboard is undefined over plain HTTP.",
                },
            ],
        },
    },
    {
        ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'tailwind.config.js'],
    },
    prettier, // Turn off all rules that might conflict with Prettier
];
