import js from '@eslint/js';
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting';
import pluginVue from 'eslint-plugin-vue';
import { defineConfig } from 'eslint/config';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default defineConfig(
    {
        // The Alpine panel is only maintained until the Vue panel replaces it.
        ignores: ['vendor/**', 'public/**', 'storage/**', 'bootstrap/cache/**', 'resources/js/admin.js'],
    },
    js.configs.recommended,
    // Type-aware rules (e.g. no-floating-promises) catch a forgotten await before an API call.
    tseslint.configs.recommendedTypeChecked,
    pluginVue.configs['flat/recommended'],
    {
        languageOptions: {
            parserOptions: {
                projectService: true,
                tsconfigRootDir: import.meta.dirname,
                extraFileExtensions: ['.vue'],
            },
        },
    },
    {
        files: ['**/*.vue'],
        languageOptions: {
            parserOptions: { parser: tseslint.parser },
        },
        rules: {
            // vue-tsc reports unknown identifiers; no-undef would flag global types such as ImportMetaEnv.
            'no-undef': 'off',
        },
    },
    {
        files: ['resources/js/**'],
        languageOptions: { globals: globals.browser },
        rules: {
            // AGENTS.md: never render API data as HTML.
            'vue/no-v-html': 'error',
        },
    },
    {
        // Plain JS outside tsconfig.json: no type information available.
        files: ['*.config.js', 'resources/js/app.js'],
        extends: [tseslint.configs.disableTypeChecked],
        languageOptions: { globals: globals.node },
    },
    // Prettier owns formatting; ESLint rules that would fight it are switched off.
    skipFormatting,
);
