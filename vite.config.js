import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/charts.js',
                'resources/css/halaman-publik.css',
                'resources/js/halaman-publik.js',
                'resources/css/tka-statistik.css',
            ],
            refresh: true,
        }),
    ],
});
