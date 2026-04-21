import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.ts',
            buildBase: '/build/',
            scope: '/',
            base: '/',
            registerType: 'prompt',
            injectRegister: false,
            swUrl: '/sw.js',
            manifest: {
                name: 'Sentinel - Incident Response Management System',
                short_name: 'Sentinel',
                description:
                    'Sentinel Incident Response Management System',
                theme_color: '#042C53',
                background_color: '#042C53',
                display: 'standalone',
                scope: '/',
                start_url: '/',
                id: '/',
                icons: [
                    {
                        src: '/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/maskable-icon-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            injectManifest: {
                globPatterns: ['**/*.{js,css,ico,png,svg,woff,woff2}'],
                // Main Inertia/Vue bundle has grown past the 2MB default as
                // the admin surfaces + FRAS/dispatch WebGL layers have landed;
                // raised ceiling to 3 MiB so the PWA precache build succeeds.
                // Re-evaluate in Phase 21 (Recognition bridge) if bundle
                // splitting becomes warranted.
                maximumFileSizeToCacheInBytes: 3 * 1024 * 1024,
            },
            devOptions: {
                enabled: true,
                type: 'module',
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'irms.test',
        },
    },
});
