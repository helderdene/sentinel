<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { playAlertSound, useWebSocket } from '@/composables/useWebSocket';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { create, show } from '@/routes/incidents';
import type { BreadcrumbItem } from '@/types';
import type {
    IncidentChannel,
    IncidentCreatedPayload,
    IncidentForQueue,
    IncidentPriority,
    IncidentStatusChangedPayload,
    IncidentType,
} from '@/types/incident';

const props = defineProps<{
    incidents: IncidentForQueue[];
    channelCounts: Record<string, number>;
}>();

const localIncidents = ref<IncidentForQueue[]>([...props.incidents]);
const localChannelCounts = ref<Record<string, number>>({
    ...props.channelCounts,
});
const highlightedId = ref<string | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Incident Queue', href: '#' },
];

const priorityBorderClass: Record<IncidentPriority, string> = {
    P1: 'border-l-4 border-l-t-p1',
    P2: 'border-l-4 border-l-t-p2',
    P3: 'border-l-4 border-l-t-p3',
    P4: 'border-l-4 border-l-t-p4',
};

const priorityBadgeClass: Record<IncidentPriority, string> = {
    P1: 'bg-[color-mix(in_srgb,var(--t-p1)_12%,transparent)] text-t-p1',
    P2: 'bg-[color-mix(in_srgb,var(--t-p2)_12%,transparent)] text-t-p2',
    P3: 'bg-[color-mix(in_srgb,var(--t-p3)_12%,transparent)] text-t-p3',
    P4: 'bg-[color-mix(in_srgb,var(--t-p4)_12%,transparent)] text-t-p4',
};

const channelLabels: Record<IncidentChannel, string> = {
    phone: 'Phone',
    sms: 'SMS',
    app: 'App',
    iot: 'IoT',
    radio: 'Radio',
};

const priorityOrder: Record<IncidentPriority, number> = {
    P1: 1,
    P2: 2,
    P3: 3,
    P4: 4,
};

const hasIncidents = computed(() => localIncidents.value.length > 0);

useEcho<IncidentCreatedPayload>(
    'dispatch.incidents',
    'IncidentCreated',
    (e) => {
        if (localIncidents.value.some((inc) => inc.id === e.id)) {
            return;
        }

        const newIncident: IncidentForQueue = {
            id: e.id,
            incident_no: e.incident_no,
            incident_type: {
                name: e.incident_type ?? '',
            } as IncidentType,
            priority: e.priority,
            status: e.status,
            channel: e.channel,
            location_text: e.location_text,
            barangay: e.barangay ? { id: 0, name: e.barangay } : null,
            caller_name: null,
            created_at: e.created_at,
        };

        const insertIndex = localIncidents.value.findIndex(
            (inc) =>
                priorityOrder[inc.priority] >
                priorityOrder[newIncident.priority],
        );

        if (insertIndex === -1) {
            localIncidents.value.push(newIncident);
        } else {
            localIncidents.value.splice(insertIndex, 0, newIncident);
        }

        highlightedId.value = newIncident.id;
        setTimeout(() => {
            highlightedId.value = null;
        }, 3000);

        localChannelCounts.value[e.channel] =
            (localChannelCounts.value[e.channel] ?? 0) + 1;

        if (e.priority === 'P1' || e.priority === 'P2') {
            playAlertSound();
        }
    },
);

useEcho<IncidentStatusChangedPayload>(
    'dispatch.incidents',
    'IncidentStatusChanged',
    (e) => {
        // Add newly triaged incidents to the dispatch queue
        if (e.new_status === 'TRIAGED') {
            if (!localIncidents.value.some((inc) => inc.id === e.id)) {
                const newIncident: IncidentForQueue = {
                    id: e.id,
                    incident_no: e.incident_no,
                    incident_type: { name: '' } as IncidentType,
                    priority: e.priority,
                    status: 'TRIAGED' as IncidentStatusChangedPayload['new_status'],
                    channel: '' as IncidentChannel,
                    location_text: '',
                    barangay: null,
                    caller_name: null,
                    created_at: new Date().toISOString(),
                };

                const insertIndex = localIncidents.value.findIndex(
                    (inc) =>
                        priorityOrder[inc.priority] >
                        priorityOrder[newIncident.priority],
                );

                if (insertIndex === -1) {
                    localIncidents.value.push(newIncident);
                } else {
                    localIncidents.value.splice(insertIndex, 0, newIncident);
                }

                highlightedId.value = newIncident.id;
                setTimeout(() => {
                    highlightedId.value = null;
                }, 3000);
            }

            return;
        }

        // Remove incidents that leave PENDING/TRIAGED
        if (e.old_status === 'PENDING' || e.old_status === 'TRIAGED') {
            const index = localIncidents.value.findIndex(
                (inc) => inc.id === e.id,
            );

            if (index !== -1) {
                const removed = localIncidents.value[index];
                localIncidents.value.splice(index, 1);

                if (
                    removed.channel &&
                    localChannelCounts.value[removed.channel] > 0
                ) {
                    localChannelCounts.value[removed.channel]--;
                }
            }
        }
    },
);

const { onStateSync } = useWebSocket();
onStateSync((data) => {
    localIncidents.value = data.incidents;
    localChannelCounts.value = data.channelCounts;
});

function timeElapsed(createdAt: string): string {
    const diff = Math.floor(
        (Date.now() - new Date(createdAt).getTime()) / 1000,
    );

    if (diff < 60) {
        return `${diff}s ago`;
    }

    if (diff < 3600) {
        return `${Math.floor(diff / 60)}m ago`;
    }

    return `${Math.floor(diff / 3600)}h ${Math.floor((diff % 3600) / 60)}m ago`;
}

function navigateToIncident(id: string): void {
    router.visit(show.url(id));
}
</script>

<template>
    <Head title="Dispatch Queue" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <div class="flex items-center justify-between">
                <Heading
                    title="Dispatch Queue"
                    description="PENDING incidents ordered by priority"
                />
                <Link :href="create.url()">
                    <Button>
                        <Plus class="mr-1 size-4" />
                        New Incident
                    </Button>
                </Link>
            </div>

            <div
                v-if="hasIncidents"
                class="overflow-hidden rounded-[7px] border border-border bg-card shadow-[var(--shadow-1)]"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-card text-left">
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Incident #
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Type
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Priority
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Location / Barangay
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Channel
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Time Elapsed
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="incident in localIncidents"
                            :key="incident.id"
                            :class="[
                                'cursor-pointer border-b border-border transition-colors hover:bg-accent',
                                priorityBorderClass[incident.priority],
                                highlightedId === incident.id
                                    ? 'animate-highlight'
                                    : '',
                            ]"
                            @click="navigateToIncident(incident.id)"
                        >
                            <td
                                class="px-4 py-3 font-mono text-[10px] text-t-text-faint"
                            >
                                {{ incident.incident_no }}
                            </td>
                            <td class="px-4 py-3 font-medium text-foreground">
                                {{ incident.incident_type?.name ?? '--' }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :class="
                                        priorityBadgeClass[incident.priority]
                                    "
                                >
                                    {{ incident.priority }}
                                </Badge>
                            </td>
                            <td class="max-w-[200px] truncate px-4 py-3">
                                {{ incident.location_text }}
                                <span
                                    v-if="incident.barangay"
                                    class="ml-1 text-xs text-muted-foreground"
                                >
                                    ({{ incident.barangay.name }})
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ channelLabels[incident.channel] }}
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-[10px] text-t-text-faint"
                            >
                                {{ timeElapsed(incident.created_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge variant="secondary">
                                    {{ incident.status }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="flex flex-col items-center justify-center rounded-[7px] border border-dashed border-border bg-card p-12 text-center shadow-[var(--shadow-3)]"
            >
                <p class="text-lg font-medium text-foreground">
                    No pending incidents
                </p>
                <p class="mt-1 text-sm text-t-text-faint">
                    The queue is empty. Create a new incident to get started.
                </p>
                <Link :href="create.url()" class="mt-4">
                    <Button>
                        <Plus class="mr-1 size-4" />
                        New Incident
                    </Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes highlight {
    0% {
        background-color: rgb(253 224 71 / 0.4);
    }
    100% {
        background-color: transparent;
    }
}
.animate-highlight {
    animation: highlight 3s ease-out;
}
</style>
