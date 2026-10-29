import js from '@eslint/js';
import globals from 'globals';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';

export default [
    {
        ignores: ['node_modules/**', 'public/build/**', 'vendor/**'],
    },
    {
        ...js.configs.recommended,
        files: ['resources/js/**/*.{js,jsx}'],
    },
    {
        ...reactHooks.configs.flat.recommended,
        files: ['resources/js/**/*.{js,jsx}'],
    },
    {
        ...reactRefresh.configs.vite,
        files: ['resources/js/**/*.{js,jsx}'],
    },
    {
        files: ['resources/js/**/*.{js,jsx}'],
        languageOptions: {
            ecmaVersion: 'latest',
            globals: globals.browser,
            parserOptions: {
                ecmaFeatures: { jsx: true },
                sourceType: 'module',
            },
        },
    },
];
