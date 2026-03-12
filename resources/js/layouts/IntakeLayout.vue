<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import IntakeStatusbar from '@/components/intake/IntakeStatusbar.vue';
import IntakeTopbar from '@/components/intake/IntakeTopbar.vue';
import { useWebSocket } from '@/composables/useWebSocket';
import type { BannerLevel } from '@/composables/useWebSocket';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { bannerLevel } = useWebSocket();

type ConnectionStatus = 'online' | 'reconnecting' | 'disconnected';

const connectionStatus = computed<ConnectionStatus>(() => {
    const levelMap: Record<BannerLevel, ConnectionStatus> = {
        none: 'online',
        green: 'online',
        amber: 'reconnecting',
        red: 'disconnected',
    };

    return levelMap[bannerLevel.value];
});
</script>

<template>
    <div
        class="flex h-screen flex-col overflow-hidden bg-t-bg dark:bg-[#0f172a]"
    >
        <IntakeTopbar :user="user" />

        <div class="flex flex-1 flex-row overflow-hidden">
            <slot />
        </div>

        <IntakeStatusbar :connection-status="connectionStatus" :user="user" />
    </div>
</template>

<style>
@keyframes slideIn {
    from {
        transform: translateY(-8px);
        opacity: 0;
    }
    to {
        transform: none;
        opacity: 1;
    }
}

@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}
</style>
