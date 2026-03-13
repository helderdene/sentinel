<script setup lang="ts">
import { computed, provide, ref } from 'vue';
import ResponderTabbar from '@/components/responder/ResponderTabbar.vue';
import ResponderTopbar from '@/components/responder/ResponderTopbar.vue';
import StatusButton from '@/components/responder/StatusButton.vue';
import { useWebSocket } from '@/composables/useWebSocket';
import type { BannerLevel } from '@/composables/useWebSocket';
import type {
    IncidentStatus,
    ResponderIncident,
    ResponderTab,
    ResponderUnit,
} from '@/types/responder';

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

const activeTab = ref<ResponderTab>('assignment');
const middleTab = ref<'nav' | 'scene'>('nav');
const unreadCount = ref(0);
const unit = ref<ResponderUnit>({
    id: '',
    callsign: '',
    type: '',
    status: '',
});
const incident = ref<ResponderIncident | null>(null);
const currentStatus = ref<IncidentStatus | null>(null);
const ackTimerRemaining = ref(0);

const onAdvance = ref<(() => void) | null>(null);
const onShowOutcomeSheet = ref<(() => void) | null>(null);

provide('connectionStatus', connectionStatus);
provide('activeTab', activeTab);
provide('middleTab', middleTab);
provide('unreadCount', unreadCount);
provide('unit', unit);
provide('incident', incident);
provide('currentStatus', currentStatus);
provide('ackTimerRemaining', ackTimerRemaining);
provide('onAdvance', onAdvance);
provide('onShowOutcomeSheet', onShowOutcomeSheet);

function handleAdvance(): void {
    onAdvance.value?.();
}

function handleShowOutcomeSheet(): void {
    onShowOutcomeSheet.value?.();
}
</script>

<template>
    <div class="flex min-h-dvh flex-col overflow-hidden bg-t-bg">
        <ResponderTopbar
            :unit="unit"
            :incident="incident"
            :connection-status="connectionStatus"
        />

        <div class="hide-scrollbar flex flex-1 flex-col overflow-hidden">
            <slot />
        </div>

        <StatusButton
            :current-status="currentStatus"
            :ack-timer-remaining="ackTimerRemaining"
            @advance="handleAdvance"
            @show-outcome-sheet="handleShowOutcomeSheet"
        />

        <ResponderTabbar
            :active-tab="activeTab"
            :middle-tab="middleTab"
            :unread-count="unreadCount"
            @update:active-tab="activeTab = $event"
        />
    </div>
</template>
