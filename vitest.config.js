import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: { '@admin': fileURLToPath(new URL('./resources/js/admin', import.meta.url)) },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/admin/**/*.test.ts'],
    },
});
