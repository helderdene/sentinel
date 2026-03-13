<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as incidentsIndex, show } from '@/routes/incidents';
import type { BreadcrumbItem } from '@/types';
import type {
    Incident,
    IncidentChannel,
    IncidentPriority,
    IncidentStatus,
} from '@/types/incident';

type CursorPaginatedData = {
    data: Incident[];
    next_cursor: string | null;
    prev_cursor: string | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

defineProps<{
    incidents: CursorPaginatedData;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Incidents', href: '#' },
];

const priorityBadgeClass: Record<IncidentPriority, string> = {
    P1: 'bg-[color-mix(in_srgb,var(--t-p1)_12%,transparent)] text-t-p1',
    P2: 'bg-[color-mix(in_srgb,var(--t-p2)_12%,transparent)] text-t-p2',
    P3: 'bg-[color-mix(in_srgb,var(--t-p3)_12%,transparent)] text-t-p3',
    P4: 'bg-[color-mix(in_srgb,var(--t-p4)_12%,transparent)] text-t-p4',
};

const statusBadgeClass: Record<IncidentStatus, string> = {
    PENDING: 'bg-[color-mix(in_srgb,var(--t-p3)_12%,transparent)] text-t-p3',
    TRIAGED:
        'bg-[color-mix(in_srgb,var(--t-accent)_12%,transparent)] text-t-accent',
    DISPATCHED:
        'bg-[color-mix(in_srgb,var(--t-unit-dispatched)_12%,transparent)] text-t-unit-dispatched',
    ACKNOWLEDGED:
        'bg-[color-mix(in_srgb,var(--t-role-supervisor)_12%,transparent)] text-t-role-supervisor',
    EN_ROUTE:
        'bg-[color-mix(in_srgb,var(--t-unit-enroute)_12%,transparent)] text-t-unit-enroute',
    ON_SCENE:
        'bg-[color-mix(in_srgb,var(--t-unit-onscene)_12%,transparent)] text-t-unit-onscene',
    RESOLVING: 'bg-[color-mix(in_srgb,var(--t-p2)_12%,transparent)] text-t-p2',
    RESOLVED:
        'bg-[color-mix(in_srgb,var(--t-online)_12%,transparent)] text-t-online',
};

const channelLabels: Record<IncidentChannel, string> = {
    phone: 'Phone',
    sms: 'SMS',
    app: 'App',
    iot: 'IoT',
    radio: 'Radio',
};

const statusOptions: { value: string; label: string }[] = [
    { value: '', label: 'All Statuses' },
    { value: 'PENDING', label: 'Pending' },
    { value: 'DISPATCHED', label: 'Dispatched' },
    { value: 'ACKNOWLEDGED', label: 'Acknowledged' },
    { value: 'EN_ROUTE', label: 'En Route' },
    { value: 'ON_SCENE', label: 'On Scene' },
    { value: 'RESOLVING', label: 'Resolving' },
    { value: 'RESOLVED', label: 'Resolved' },
];

const currentStatus = computed(() => {
    const url = new URL(window.location.href);

    return url.searchParams.get('status') ?? '';
});

function filterByStatus(status: string): void {
    if (status) {
        router.visit(incidentsIndex.url({ query: { status } }), {
            preserveState: true,
        });
    } else {
        router.visit(incidentsIndex.url(), { preserveState: true });
    }
}

function navigateToIncident(id: string): void {
    router.visit(show.url(id));
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Incidents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <Heading
                title="All Incidents"
                description="Browse and filter all incidents"
            />

            <!-- Status Filter -->
            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="opt in statusOptions"
                    :key="opt.value"
                    :variant="
                        currentStatus === opt.value ? 'default' : 'outline'
                    "
                    size="sm"
                    @click="filterByStatus(opt.value)"
                >
                    {{ opt.label }}
                </Button>
            </div>

            <div
                v-if="incidents.data.length > 0"
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
                                Status
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Created
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="incident in incidents.data"
                            :key="incident.id"
                            class="cursor-pointer border-b border-border transition-colors hover:bg-accent"
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
                            <td class="px-4 py-3">
                                <Badge
                                    :class="
                                        statusBadgeClass[
                                            incident.status as IncidentStatus
                                        ]
                                    "
                                >
                                    {{ incident.status.replace('_', ' ') }}
                                </Badge>
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-[10px] text-t-text-faint"
                            >
                                {{ formatDate(incident.created_at) }}
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
                    No incidents found
                </p>
                <p class="mt-1 text-sm text-t-text-faint">
                    No incidents match the current filter.
                </p>
            </div>

            <!-- Cursor Pagination -->
            <div
                v-if="incidents.prev_page_url || incidents.next_page_url"
                class="flex items-center justify-between"
            >
                <Link
                    v-if="incidents.prev_page_url"
                    :href="incidents.prev_page_url"
                >
                    <Button variant="outline" size="sm">Previous</Button>
                </Link>
                <div v-else />
                <Link
                    v-if="incidents.next_page_url"
                    :href="incidents.next_page_url"
                >
                    <Button variant="outline" size="sm">Next</Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
