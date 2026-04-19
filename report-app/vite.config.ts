import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    base: '/citizen/',
    plugins: [
        vue(),
        tailwindcss(),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'src',
            filename: 'sw.ts',
            scope: '/citizen/',
            base: '/citizen/',
            registerType: 'autoUpdate',
            injectRegister: 'auto',
            manifest: {
                name: 'Sentinel - Report Emergency',
                short_name: 'Sentinel Report',
                description:
                    'Report emergencies to the Butuan City CDRRMO from your device.',
                theme_color: '#042C53',
                background_color: '#042C53',
                display: 'standalone',
                scope: '/citizen/',
                start_url: '/citizen/',
                id: '/citizen/',
                icons: [
                    {
                        src: 'pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: 'pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: 'maskable-icon-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            injectManifest: {
                globPatterns: [
                    '**/*.{js,css,html,ico,png,svg,woff,woff2}',
                ],
            },
            devOptions: {
                enabled: true,
                type: 'module',
                navigateFallback: '/citizen/index.html',
            },
        }),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'src'),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5174,
        proxy: {
            '/api': {
                target: 'http://irms.test',
                changeOrigin: true,
                secure: false,
            },
        },
    },
    build: {
        outDir: 'dist',
    },
});
