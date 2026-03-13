<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { computed, provide, ref } from 'vue';
import DispatchStatusbar from '@/components/dispatch/DispatchStatusbar.vue';
import DispatchTopbar from '@/components/dispatch/DispatchTopbar.vue';
import { useWebSocket } from '@/composables/useWebSocket';
import type { BannerLevel } from '@/composables/useWebSocket';
import type { DispatchMetrics, DispatchUnit } from '@/types/dispatch';
import type { TickerEvent } from '@/types/incident';

const props = defineProps<{
    metrics: DispatchMetrics;
    units: DispatchUnit[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const { bannerLevel } = useWebSocket();

const tickerEvents = ref<TickerEvent[]>([]);

const dispatchStats: {
    activeIncidents: Ref<number>;
    criticalIncidents: Ref<number>;
    totalIncidents: Ref<number>;
    averageHandleTime: Ref<number | null>;
    unitsAvailable: Ref<number>;
    unitsTotal: Ref<number>;
} = {
    activeIncidents: ref(props.metrics.activeIncidents),
    criticalIncidents: ref(props.metrics.criticalIncidents),
    totalIncidents: ref(props.metrics.totalIncidents),
    averageHandleTime: ref(props.metrics.averageHandleTime),
    unitsAvailable: ref(props.metrics.unitsAvailable),
    unitsTotal: ref(props.metrics.unitsTotal),
};

provide('tickerEvents', tickerEvents);
provide('dispatchStats', dispatchStats);

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
            <!-- Left panel slot -->
            <div
                class="absolute top-0 bottom-0 left-0 z-10 w-80 overflow-y-auto bg-t-bg/95 backdrop-blur-sm dark:bg-[#0f172a]/95"
            >
                <slot name="left-panel" />
            </div>

            <!-- Center map area -->
            <div class="flex-1">
                <slot />
            </div>

            <!-- Right panel slot -->
            <div
                class="absolute top-0 right-0 bottom-0 z-10 w-[360px] overflow-y-auto bg-t-bg/95 backdrop-blur-sm dark:bg-[#0f172a]/95"
            >
                <slot name="right-panel" />
            </div>
        </div>

        <DispatchStatusbar :connection-status="connectionStatus" :user="user" />
    </div>
</template>
