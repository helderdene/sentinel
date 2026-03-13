<script setup lang="ts">
import { computed, ref } from 'vue';
import QueueCard from '@/components/dispatch/QueueCard.vue';
import type { DispatchIncident } from '@/types/dispatch';
import type { IncidentStatus } from '@/types/incident';

const props = defineProps<{
    incidents: DispatchIncident[];
    selectedIncidentId: string | null;
}>();

const emit = defineEmits<{
    'select-incident': [id: string];
}>();

type FilterTab = 'ALL' | 'P1' | 'P1-2' | 'ACTIVE';

const activeTab = ref<FilterTab>('ALL');

const tabs: { key: FilterTab; label: string }[] = [
    { key: 'ALL', label: 'ALL' },
    { key: 'P1', label: 'P1' },
    { key: 'P1-2', label: 'P1-2' },
    { key: 'ACTIVE', label: 'ACTIVE' },
];

const DISPATCH_STATUSES: IncidentStatus[] = [
    'TRIAGED',
    'DISPATCHED',
    'EN_ROUTE',
    'ON_SCENE',
];

const ACTIVE_STATUSES: IncidentStatus[] = [
    'DISPATCHED',
    'EN_ROUTE',
    'ON_SCENE',
];

const PRIORITY_RANK: Record<string, number> = {
    P1: 1,
    P2: 2,
    P3: 3,
    P4: 4,
};

const queueIncidents = computed(() =>
    props.incidents.filter((i) => DISPATCH_STATUSES.includes(i.status)),
);

const filteredIncidents = computed(() => {
    let list = queueIncidents.value;

    if (activeTab.value === 'P1') {
        list = list.filter((i) => i.priority === 'P1');
    } else if (activeTab.value === 'P1-2') {
        list = list.filter((i) => i.priority === 'P1' || i.priority === 'P2');
    } else if (activeTab.value === 'ACTIVE') {
        list = list.filter((i) => ACTIVE_STATUSES.includes(i.status));
    }

    return [...list].sort((a, b) => {
        const pa = PRIORITY_RANK[a.priority] ?? 5;
        const pb = PRIORITY_RANK[b.priority] ?? 5;

        if (pa !== pb) {
            return pa - pb;
        }

        return (
            new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
        );
    });
});

const incidentCount = computed(() => queueIncidents.value.length);
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Header -->
        <div class="flex items-center gap-2 border-b border-t-border px-3 py-2">
            <span
                class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
            >
                QUEUE
            </span>
            <span
                class="flex size-5 items-center justify-center rounded-full bg-t-accent/15 font-mono text-[10px] font-bold text-t-accent"
            >
                {{ incidentCount }}
            </span>
        </div>

        <!-- Filter tabs -->
        <div
            class="flex items-center gap-1 border-b border-t-border px-2 py-1.5"
        >
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="rounded px-2 py-1 font-mono text-[9px] font-bold tracking-wide transition-colors"
                :class="
                    activeTab === tab.key
                        ? 'bg-t-accent/15 text-t-accent'
                        : 'text-t-text-faint hover:bg-t-surface-alt hover:text-t-text-dim'
                "
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Incident list -->
        <div class="flex-1 overflow-y-auto">
            <QueueCard
                v-for="incident in filteredIncidents"
                :key="incident.id"
                :incident="incident"
                :selected="incident.id === selectedIncidentId"
                @select="emit('select-incident', $event)"
            />
            <div
                v-if="filteredIncidents.length === 0"
                class="px-3 py-6 text-center text-xs text-t-text-faint"
            >
                No incidents in queue
            </div>
        </div>
    </div>
</template>
