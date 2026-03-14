import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import mapboxgl from 'mapbox-gl';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import PushPermissionPrompt from '@/components/PushPermissionPrompt.vue';
import ReloadPrompt from '@/components/ReloadPrompt.vue';
import { initializeTheme } from '@/composables/useAppearance';

mapboxgl.accessToken =
    'pk.eyJ1IjoiaGVsZGVyZGVuZSIsImEiOiJjbWw5aTJldmwwMzlqM2VzN3dqYjhkcDB3In0.fi2hg9_Q-qoaG4UHihTepw';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => [
                h(App, props),
                h(ReloadPrompt),
                h(PushPermissionPrompt),
            ],
        })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// Register service worker — manual registration needed because
// vite-plugin-pwa's HTML injection doesn't apply to Inertia/Laravel apps
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { scope: '/' });
}
