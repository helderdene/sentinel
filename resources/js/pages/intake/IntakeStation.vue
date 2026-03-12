<script setup lang="ts">
import { useEcho } from '@laravel/echo-vue';
import { computed, provide, ref } from 'vue';
import ChannelFeed from '@/components/intake/ChannelFeed.vue';
import TriagePanel from '@/components/intake/TriagePanel.vue';
import { useIntakeFeed } from '@/composables/useIntakeFeed';
import { useIntakeSession } from '@/composables/useIntakeSession';
import IntakeLayout from '@/layouts/IntakeLayout.vue';
import type {
    Incident,
    IncidentChannel,
    IncidentCreatedPayload,
    IncidentPriority,
    IncidentType,
} from '@/types/incident';

defineOptions({ layout: IntakeLayout });

const props = defineProps<{
    incidentTypes: Record<string, IncidentType[]>;
    channels: IncidentChannel[];
    priorities: IncidentPriority[];
    pendingIncidents: Incident[];
    triagedIncidents: Incident[];
    priorityConfig?: Record<string, unknown>;
}>();

const {
    feedIncidents,
    pendingCount,
    triagedCount,
    activeFilter,
    activeIncident,
    channelCounts,
    setFilter,
    selectIncident,
} = useIntakeFeed(props.pendingIncidents, props.triagedIncidents);

const session = useIntakeSession();

const isManualEntry = ref(false);

function onSelectIncident(incident: Incident): void {
    isManualEntry.value = false;
    selectIncident(incident);
}

function onManualEntry(): void {
    selectIncident(null);
    isManualEntry.value = true;
}

function onTriageSubmitted(): void {
    selectIncident(null);
    isManualEntry.value = false;
    session.recordTriaged(0);
}

const tickerEvents = ref<string[]>([]);

useEcho<IncidentCreatedPayload>(
    'dispatch.incidents',
    'IncidentCreated',
    (e) => {
        session.recordReceived();
        tickerEvents.value.unshift(
            `${e.incident_no} -- ${e.incident_type ?? 'New incident'} (${e.channel})`,
        );

        if (tickerEvents.value.length > 20) {
            tickerEvents.value.pop();
        }
    },
);

const avgRespLabel = computed(() => {
    const seconds = session.avgHandleTime.value;

    if (seconds === 0) {
        return '0m';
    }

    if (seconds < 60) {
        return `${seconds}s`;
    }

    return `${Math.floor(seconds / 60)}m`;
});

provide('topbarStats', {
    incoming: session.received,
    pending: pendingCount,
    triaged: session.triaged,
    avgResp: avgRespLabel,
});
</script>

<template>
    <div class="flex h-full w-full overflow-hidden">
        <!-- Left: Channel Feed (296px) -->
        <ChannelFeed
            :feed-incidents="feedIncidents"
            :active-incident="activeIncident"
            :channel-counts="channelCounts"
            :active-filter="activeFilter"
            :pending-count="pendingCount"
            :triaged-count="triagedCount"
            @select-incident="onSelectIncident"
            @manual-entry="onManualEntry"
            @set-filter="setFilter"
        />

        <!-- Center: Triage Panel (flex-1) -->
        <TriagePanel
            :active-incident="activeIncident"
            :is-manual-entry="isManualEntry"
            :incident-types="incidentTypes"
            :channels="channels"
            :priorities="priorities"
            :priority-config="priorityConfig"
            @triage-submitted="onTriageSubmitted"
        />

        <!-- Right: Queue Panel Placeholder (304px) -->
        <div
            class="flex w-[304px] shrink-0 flex-col items-center justify-center border-l border-t-border bg-t-surface"
        >
            <div
                class="flex size-12 items-center justify-center rounded-xl bg-t-surface-alt"
            >
                <svg
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="var(--t-text-faint)"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <rect x="3" y="3" width="7" height="7" rx="1" />
                    <rect x="14" y="3" width="7" height="7" rx="1" />
                    <rect x="3" y="14" width="7" height="7" rx="1" />
                    <rect x="14" y="14" width="7" height="7" rx="1" />
                </svg>
            </div>
            <p class="mt-3 text-[12px] font-medium text-t-text-faint">
                Queue panel coming soon
            </p>
            <p class="mt-0.5 text-[10px] text-t-text-faint">Plan 04</p>
        </div>
    </div>
</template>
