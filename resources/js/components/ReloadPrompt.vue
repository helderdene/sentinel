<script setup lang="ts">
import { useRegisterSW } from 'virtual:pwa-register/vue';
import { watch } from 'vue';

const { needRefresh, offlineReady, updateServiceWorker } = useRegisterSW();

// Auto-dismiss offline-ready toast after 3 seconds
watch(offlineReady, (ready) => {
    if (ready) {
        setTimeout(() => {
            offlineReady.value = false;
        }, 3000);
    }
});

function dismiss() {
    needRefresh.value = false;
}
</script>

<template>
    <!-- Update banner -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0"
    >
        <div
            v-if="needRefresh"
            class="shadow-2 fixed right-4 bottom-4 z-50 flex items-center gap-3 rounded-lg border border-t-border bg-t-surface px-4 py-3"
            role="alert"
        >
            <span class="text-sm text-t-text">New version available</span>
            <button
                class="rounded-md bg-t-brand px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-t-brand/90"
                @click="updateServiceWorker(true)"
            >
                Reload
            </button>
            <button
                class="text-t-muted rounded-md px-3 py-1.5 text-xs font-medium transition-colors hover:text-t-text"
                @click="dismiss"
            >
                Dismiss
            </button>
        </div>
    </Transition>

    <!-- Offline-ready toast -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0"
    >
        <div
            v-if="offlineReady"
            class="shadow-2 fixed right-4 bottom-4 z-50 rounded-lg border border-t-border bg-t-surface px-4 py-3"
            role="status"
        >
            <span class="text-t-muted text-sm">Ready to work offline</span>
        </div>
    </Transition>
</template>
