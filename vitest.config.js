import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        setupFiles: ['resources/js/test/setup.js'],
        include: ['resources/js/**/*.test.js'],
    },
});
