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
    P1: 'bg-red-500 text-white border-red-500',
    P2: 'bg-orange-500 text-white border-orange-500',
    P3: 'bg-amber-500 text-white border-amber-500',
    P4: 'bg-green-500 text-white border-green-500',
};

const statusBadgeClass: Record<IncidentStatus, string> = {
    PENDING:
        'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-900/30 dark:text-yellow-400 dark:border-yellow-700',
    TRIAGED:
        'bg-teal-100 text-teal-800 border-teal-300 dark:bg-teal-900/30 dark:text-teal-400 dark:border-teal-700',
    DISPATCHED:
        'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-700',
    ACKNOWLEDGED:
        'bg-indigo-100 text-indigo-800 border-indigo-300 dark:bg-indigo-900/30 dark:text-indigo-400 dark:border-indigo-700',
    EN_ROUTE:
        'bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-700',
    ON_SCENE:
        'bg-cyan-100 text-cyan-800 border-cyan-300 dark:bg-cyan-900/30 dark:text-cyan-400 dark:border-cyan-700',
    RESOLVING:
        'bg-orange-100 text-orange-800 border-orange-300 dark:bg-orange-900/30 dark:text-orange-400 dark:border-orange-700',
    RESOLVED:
        'bg-green-100 text-green-800 border-green-300 dark:bg-green-900/30 dark:text-green-400 dark:border-green-700',
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
                class="overflow-hidden rounded-lg border dark:border-neutral-800"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b bg-neutral-50 text-left dark:border-neutral-800 dark:bg-neutral-900"
                        >
                            <th class="px-4 py-3 font-medium">Incident #</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Priority</th>
                            <th class="px-4 py-3 font-medium">
                                Location / Barangay
                            </th>
                            <th class="px-4 py-3 font-medium">Channel</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="incident in incidents.data"
                            :key="incident.id"
                            class="cursor-pointer border-b transition-colors last:border-0 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/50"
                            @click="navigateToIncident(incident.id)"
                        >
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ incident.incident_no }}
                            </td>
                            <td class="px-4 py-3">
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
                            <td class="px-4 py-3">
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
                            <td class="px-4 py-3 text-xs">
                                {{ formatDate(incident.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="flex flex-col items-center justify-center rounded-lg border border-dashed p-12 text-center dark:border-neutral-800"
            >
                <p
                    class="text-lg font-medium text-neutral-900 dark:text-neutral-100"
                >
                    No incidents found
                </p>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
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
