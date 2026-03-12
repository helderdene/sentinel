<script setup lang="ts">
import { computed } from 'vue';
import type { Ref } from 'vue';

import PriorityBreakdown from '@/components/intake/PriorityBreakdown.vue';
import QueueRow from '@/components/intake/QueueRow.vue';
import SessionMetrics from '@/components/intake/SessionMetrics.vue';
import type { UserPermissions } from '@/types/auth';
import type { Incident, IncidentPriority } from '@/types/incident';

const props = defineProps<{
    triagedIncidents: Ref<Incident[]> | Incident[];
    received: number;
    triaged: number;
    pending: number;
    avgHandleTime: number;
    userCan: UserPermissions;
}>();

const emit = defineEmits<{
    overridden: [incidentId: string, newPriority: IncidentPriority];
    recalled: [incidentId: string];
}>();

const incidents = computed(() => {
    const list = Array.isArray(props.triagedIncidents)
        ? props.triagedIncidents
        : props.triagedIncidents.value;

    return [...list].sort((a, b) => {
        const pa = parseInt(a.priority.replace('P', ''));
        const pb = parseInt(b.priority.replace('P', ''));

        if (pa !== pb) {
            return pa - pb;
        }

        return (
            new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
        );
    });
});

const queueCount = computed(() => incidents.value.length);
const hasTriaged = computed(() => queueCount.value > 0);
</script>

<template>
    <div
        class="flex w-[304px] shrink-0 flex-col border-l border-t-border bg-t-surface"
    >
        <!-- Header -->
        <div class="flex items-center gap-2 px-4 py-3">
            <span
                class="font-mono text-[9px] font-medium tracking-[2px] text-t-text-faint uppercase"
            >
                Dispatch Queue
            </span>
            <span
                class="inline-flex min-w-[20px] items-center justify-center rounded-full bg-t-accent px-1.5 py-0.5 font-mono text-[10px] font-bold text-white"
            >
                {{ queueCount }}
            </span>
        </div>

        <!-- Scrollable queue list -->
        <div class="flex-1 space-y-2 overflow-y-auto px-3">
            <TransitionGroup name="slide-in">
                <QueueRow
                    v-for="incident in incidents"
                    :key="incident.id"
                    :incident="incident"
                    :can-override="userCan.override_priority"
                    :can-recall="userCan.recall_incident"
                    @overridden="(id, p) => emit('overridden', id, p)"
                    @recalled="(id) => emit('recalled', id)"
                />
            </TransitionGroup>

            <div
                v-if="!hasTriaged"
                class="flex flex-col items-center justify-center py-12 text-center"
            >
                <p class="text-[11px] text-t-text-faint">
                    No triaged incidents
                </p>
                <p class="mt-0.5 text-[10px] text-t-text-faint">
                    Triage incidents from the feed to populate the queue
                </p>
            </div>
        </div>

        <!-- Session Metrics -->
        <div class="shrink-0 px-3 py-2">
            <SessionMetrics
                :received="received"
                :triaged="triaged"
                :pending="pending"
                :avg-handle-time="avgHandleTime"
            />

            <!-- Priority Breakdown (conditional) -->
            <PriorityBreakdown
                v-if="hasTriaged"
                :triaged-incidents="incidents"
            />
        </div>

        <!-- Session Log slot -->
        <div class="shrink-0">
            <slot name="session-log" />
        </div>
    </div>
</template>

<style scoped>
.slide-in-enter-active {
    transition: all 0.3s ease-out;
}

.slide-in-leave-active {
    transition: all 0.2s ease-in;
}

.slide-in-enter-from {
    opacity: 0;
    transform: translateX(20px);
}

.slide-in-leave-to {
    opacity: 0;
    transform: translateX(-20px);
}

.slide-in-move {
    transition: transform 0.3s ease;
}
</style>
