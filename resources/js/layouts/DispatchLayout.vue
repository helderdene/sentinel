<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { computed, provide, ref } from 'vue';
import DispatchStatusbar from '@/components/dispatch/DispatchStatusbar.vue';
import DispatchTopbar from '@/components/dispatch/DispatchTopbar.vue';
import { useWebSocket } from '@/composables/useWebSocket';
import type { BannerLevel } from '@/composables/useWebSocket';
import type { TickerEvent } from '@/types/incident';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { bannerLevel } = useWebSocket();

const tickerEvents = ref<TickerEvent[]>([]);
const totalUnreadMessages = ref(0);

const dispatchStats: {
    activeIncidents: Ref<number>;
    criticalIncidents: Ref<number>;
    totalIncidents: Ref<number>;
    averageHandleTime: Ref<number | null>;
    unitsAvailable: Ref<number>;
    unitsTotal: Ref<number>;
} = {
    activeIncidents: ref(0),
    criticalIncidents: ref(0),
    totalIncidents: ref(0),
    averageHandleTime: ref(null),
    unitsAvailable: ref(0),
    unitsTotal: ref(0),
};

provide('tickerEvents', tickerEvents);
provide('dispatchStats', dispatchStats);
provide('totalUnreadMessages', totalUnreadMessages);

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
        <DispatchTopbar :user="user" />

        <div class="relative flex flex-1 overflow-hidden">
            <slot />
        </div>

        <DispatchStatusbar :connection-status="connectionStatus" :user="user" />
    </div>
</template>
