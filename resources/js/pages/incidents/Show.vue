<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import IncidentTimeline from '@/components/incidents/IncidentTimeline.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as incidentsIndex } from '@/routes/incidents';
import type { BreadcrumbItem } from '@/types';
import type {
    Incident,
    IncidentChannel,
    IncidentPriority,
    IncidentStatus,
    IncidentTimelineEntry,
} from '@/types/incident';

const props = defineProps<{
    incident: Incident;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Incidents', href: incidentsIndex() },
    { title: props.incident.incident_no, href: '#' },
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
    app: 'App (Walk-in/Web)',
    iot: 'IoT Sensor',
    radio: 'Radio',
};

const timeline = (props.incident.timeline ?? []) as IncidentTimelineEntry[];
const reversedTimeline = [...timeline].reverse();
</script>

<template>
    <Head :title="incident.incident_no" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <div class="flex items-center gap-3">
                <h1
                    class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100"
                >
                    {{ incident.incident_no }}
                </h1>
                <Badge :class="priorityBadgeClass[incident.priority]">
                    {{ incident.priority }}
                </Badge>
                <Badge
                    :class="statusBadgeClass[incident.status as IncidentStatus]"
                >
                    {{ incident.status.replace('_', ' ') }}
                </Badge>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left: Incident Details -->
                <div class="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Incident Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Type
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm text-neutral-900 dark:text-neutral-100"
                                    >
                                        {{
                                            incident.incident_type?.name ?? '--'
                                        }}
                                        <span
                                            v-if="incident.incident_type?.code"
                                            class="ml-1 font-mono text-xs text-muted-foreground"
                                        >
                                            ({{ incident.incident_type.code }})
                                        </span>
                                    </dd>
                                </div>

                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Channel
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm text-neutral-900 dark:text-neutral-100"
                                    >
                                        {{ channelLabels[incident.channel] }}
                                    </dd>
                                </div>

                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Location
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm text-neutral-900 dark:text-neutral-100"
                                    >
                                        {{ incident.location_text }}
                                    </dd>
                                </div>

                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Barangay
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm text-neutral-900 dark:text-neutral-100"
                                    >
                                        {{
                                            incident.barangay?.name ??
                                            'Not assigned'
                                        }}
                                    </dd>
                                </div>

                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Caller Name
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm text-neutral-900 dark:text-neutral-100"
                                    >
                                        {{ incident.caller_name ?? '--' }}
                                    </dd>
                                </div>

                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Caller Contact
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm text-neutral-900 dark:text-neutral-100"
                                    >
                                        {{ incident.caller_contact ?? '--' }}
                                    </dd>
                                </div>
                            </dl>

                            <div v-if="incident.notes" class="mt-6">
                                <dt
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    Notes
                                </dt>
                                <dd
                                    class="mt-1 text-sm whitespace-pre-wrap text-neutral-900 dark:text-neutral-100"
                                >
                                    {{ incident.notes }}
                                </dd>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right: Timeline -->
                <div>
                    <Card>
                        <CardHeader>
                            <CardTitle>Timeline</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <IncidentTimeline :timeline="reversedTimeline" />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
