<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import type { Ref } from 'vue';
import { computed, inject, ref, watch, watchEffect } from 'vue';

import ChannelFeed from '@/components/intake/ChannelFeed.vue';
import DispatchQueuePanel from '@/components/intake/DispatchQueuePanel.vue';
import FrasEventDetailModal from '@/components/intake/FrasEventDetailModal.vue';
import SessionLog from '@/components/intake/SessionLog.vue';
import TriagePanel from '@/components/intake/TriagePanel.vue';
import { useFrasRail } from '@/composables/useFrasRail';
import { useIntakeFeed } from '@/composables/useIntakeFeed';
import { useIntakeSession } from '@/composables/useIntakeSession';
import IntakeLayout from '@/layouts/IntakeLayout.vue';
import type { Auth } from '@/types/auth';
import type { FrasRailEvent } from '@/types/fras';
import type {
    Incident,
    IncidentChannel,
    IncidentCreatedPayload,
    IncidentPriority,
    IncidentType,
    TickerEvent,
} from '@/types/incident';

defineOptions({ layout: IntakeLayout });

type SessionLogEntry = {
    timestamp: string;
    action: string;
    priority?: IncidentPriority;
};

const props = defineProps<{
    incidentTypes: Record<string, IncidentType[]>;
    channels: IncidentChannel[];
    priorities: IncidentPriority[];
    pendingIncidents: Incident[];
    triagedIncidents: Incident[];
    priorityConfig?: Record<string, unknown>;
    recentActivity?: SessionLogEntry[];
    recentFrasEvents?: FrasRailEvent[];
}>();

const page = usePage<{ auth: Auth }>();
const userCan = computed(() => page.props.auth.user.can);

const {
    feedIncidents,
    pendingIncidents,
    triagedIncidents,
    pendingCount,
    triagedCount,
    activeFilter,
    activeIncident,
    channelCounts: baseChannelCounts,
    setFilter,
    selectIncident,
} = useIntakeFeed(props.pendingIncidents, props.triagedIncidents);

const { frasEvents, frasCount } = useFrasRail(props.recentFrasEvents ?? []);

// Merge FRAS count into the ChannelFeed's channelCounts bar so the 6th rail
// reflects the live ring buffer length rather than the incident-only 0.
const channelCounts = computed(() => ({
    ...baseChannelCounts.value,
    FRAS: frasCount.value,
}));

const initialTotal =
    props.pendingIncidents.length + props.triagedIncidents.length;
const session = useIntakeSession(initialTotal, props.triagedIncidents.length);

const isManualEntry = ref(false);
const sessionLogRef = ref<InstanceType<typeof SessionLog> | null>(null);

// FRAS modal state — opened when a rail card without incident_id is clicked.
const frasModalOpen = ref(false);
const frasModalEvent = ref<FrasRailEvent | null>(null);

function onOpenFrasModal(event: FrasRailEvent): void {
    frasModalEvent.value = event;
    frasModalOpen.value = true;
}

// When Inertia refreshes page props after triage redirect, the triaged
// incident disappears from pendingIncidents. Detect this and clear the
// active selection so the form resets to the empty state.
watch(
    () => props.pendingIncidents,
    (newPending) => {
        if (activeIncident.value && !isManualEntry.value) {
            const stillPending = newPending.some(
                (i) => i.id === activeIncident.value!.id,
            );

            if (!stillPending) {
                const incident = activeIncident.value;

                selectIncident(null);
                isManualEntry.value = false;
                session.recordTriaged(0);
                sessionLogRef.value?.addEntry({
                    action: `Triaged ${incident.incident_no} as ${incident.priority}`,
                    priority: incident.priority,
                });
            }
        }
    },
);

function onSelectIncident(incident: Incident): void {
    if (incident.status === 'TRIAGED') {
        return;
    }

    isManualEntry.value = false;
    selectIncident(incident);
}

function onManualEntry(): void {
    selectIncident(null);
    isManualEntry.value = true;
}

// When manual entry succeeds, the new incident appears in triagedIncidents
// (it was never in pendingIncidents). Detect this and clear the form.
watch(
    () => props.triagedIncidents,
    () => {
        if (isManualEntry.value) {
            isManualEntry.value = false;
            selectIncident(null);
            session.recordTriaged(0);
            sessionLogRef.value?.addEntry({
                action: 'Manual entry triaged',
            });
        }
    },
);

function onOverridden(incidentId: string, newPriority: IncidentPriority): void {
    const incident = triagedIncidents.value.find((i) => i.id === incidentId);

    if (incident) {
        const oldPriority = incident.priority;
        incident.priority = newPriority;

        sessionLogRef.value?.addEntry({
            action: `Override ${incident.incident_no} priority ${oldPriority} -> ${newPriority}`,
            priority: newPriority,
        });
    }
}

function onRecalled(incidentId: string): void {
    const index = triagedIncidents.value.findIndex((i) => i.id === incidentId);

    if (index !== -1) {
        const [recalled] = triagedIncidents.value.splice(index, 1);
        recalled.status = 'PENDING';
        pendingIncidents.value.unshift(recalled);

        sessionLogRef.value?.addEntry({
            action: `Recalled ${recalled.incident_no} from queue`,
        });
    }
}

const tickerEvents = inject<Ref<TickerEvent[]>>(
    'tickerEvents',
    ref<TickerEvent[]>([]),
);

useEcho<IncidentCreatedPayload>(
    'dispatch.incidents',
    'IncidentCreated',
    (e) => {
        session.recordReceived();
        tickerEvents.value.unshift({
            incident_no: e.incident_no,
            priority: e.priority,
            channel: e.channel,
            incident_type: e.incident_type,
            location_text: e.location_text,
            created_at: e.created_at,
        });

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

const topbarStats = inject<{
    incoming: Ref<number>;
    pending: Ref<number>;
    triaged: Ref<number>;
    avgResp: Ref<string>;
}>('topbarStats');

watchEffect(() => {
    if (topbarStats) {
        topbarStats.incoming.value = session.received.value;
        topbarStats.pending.value = pendingCount.value;
        topbarStats.triaged.value = session.triaged.value;
        topbarStats.avgResp.value = avgRespLabel.value;
    }
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
            :fras-events="frasEvents"
            @select-incident="onSelectIncident"
            @manual-entry="onManualEntry"
            @set-filter="setFilter"
            @open-fras-modal="onOpenFrasModal"
        />

        <!-- Center: Triage Panel (flex-1) -->
        <TriagePanel
            :active-incident="activeIncident"
            :is-manual-entry="isManualEntry"
            :incident-types="incidentTypes"
            :channels="channels"
            :priorities="priorities"
            :priority-config="priorityConfig"
        />

        <!-- Right: Dispatch Queue Panel (304px) -->
        <DispatchQueuePanel
            :triaged-incidents="triagedIncidents"
            :received="session.received.value"
            :triaged="session.triaged.value"
            :pending="session.pending.value"
            :avg-handle-time="session.avgHandleTime.value"
            :user-can="userCan"
            @overridden="onOverridden"
            @recalled="onRecalled"
        >
            <template v-if="userCan.view_session_log" #session-log>
                <SessionLog
                    ref="sessionLogRef"
                    :initial-entries="props.recentActivity ?? []"
                />
            </template>
        </DispatchQueuePanel>

        <!-- FRAS Recognition Event detail modal (read-only, opened from
             FrasRailCard when the event did not create an incident). -->
        <FrasEventDetailModal
            v-model:open="frasModalOpen"
            :event="frasModalEvent"
        />
    </div>
</template>
