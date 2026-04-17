<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { usePushSubscription } from '@/composables/usePushSubscription';

const page = usePage();
const { isSubscribed, isSupported, subscribe } = usePushSubscription();

const dismissed = ref(false);

const user = computed(() => page.props.auth?.user);

const shouldShow = computed(() => {
    if (!user.value) {
        return false;
    }

    if (!isSupported.value) {
        return false;
    }

    if (isSubscribed.value) {
        return false;
    }

    if (dismissed.value) {
        return false;
    }

    if (
        typeof window !== 'undefined' &&
        (localStorage.getItem('push-prompt-dismissed') ||
            localStorage.getItem('push-subscribed'))
    ) {
        return false;
    }

    return true;
});

const explanationText = computed(() => {
    const role = user.value?.role;

    if (role === 'responder') {
        return 'Get notified of new assignments even when Sentinel is closed';
    }

    if (role === 'dispatcher') {
        return 'Get notified of critical P1 incidents even when Sentinel is closed';
    }

    return 'Get notified of critical events even when Sentinel is closed';
});

async function handleEnable() {
    dismissed.value = true;

    try {
        await subscribe();
    } catch (error) {
        console.error('Push subscription failed:', error);
        localStorage.setItem('push-prompt-dismissed', 'true');
    }
}

function handleDismiss() {
    dismissed.value = true;
    localStorage.setItem('push-prompt-dismissed', 'true');
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="shouldShow"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
        >
            <Transition
                appear
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="scale-95 opacity-0"
                enter-to-class="scale-100 opacity-100"
            >
                <div
                    class="shadow-4 mx-4 w-full max-w-sm rounded-xl border border-t-border bg-t-surface p-6"
                >
                    <div class="mb-4 flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-t-brand/10"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-5 w-5 text-t-brand"
                            >
                                <path
                                    d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"
                                />
                                <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-t-text">
                            Enable push notifications?
                        </h3>
                    </div>

                    <p class="text-t-muted mb-6 text-sm">
                        {{ explanationText }}
                    </p>

                    <div class="flex gap-3">
                        <button
                            class="flex-1 rounded-lg bg-t-brand px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-t-brand/90"
                            @click="handleEnable"
                        >
                            Enable Notifications
                        </button>
                        <button
                            class="text-t-muted hover:bg-t-surface-elevated flex-1 rounded-lg border border-t-border px-4 py-2.5 text-sm font-medium transition-colors hover:text-t-text"
                            @click="handleDismiss"
                        >
                            Not Now
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>
