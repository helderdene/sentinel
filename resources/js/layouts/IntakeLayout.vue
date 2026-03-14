<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { computed, provide, ref } from 'vue';
import IntakeStatusbar from '@/components/intake/IntakeStatusbar.vue';
import IntakeTopbar from '@/components/intake/IntakeTopbar.vue';
import { useWebSocket } from '@/composables/useWebSocket';
import type { BannerLevel } from '@/composables/useWebSocket';
import type { TickerEvent } from '@/types/incident';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { bannerLevel } = useWebSocket();

const tickerEvents = ref<TickerEvent[]>([]);
const topbarStats: {
    incoming: Ref<number>;
    pending: Ref<number>;
    triaged: Ref<number>;
    avgResp: Ref<string>;
} = {
    incoming: ref(0),
    pending: ref(0),
    triaged: ref(0),
    avgResp: ref('0m'),
};

provide('tickerEvents', tickerEvents);
provide('topbarStats', topbarStats);

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
        class="flex h-screen flex-col overflow-hidden bg-t-bg dark:bg-[#05101E]"
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
