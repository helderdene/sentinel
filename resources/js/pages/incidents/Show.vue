<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { downloadReport } from '@/actions/App/Http/Controllers/IncidentController';
import EscalateToP1Button from '@/components/incidents/EscalateToP1Button.vue';
import IncidentTimeline from '@/components/incidents/IncidentTimeline.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

const reportReady = computed(
    () =>
        props.incident.status === 'RESOLVED' &&
        props.incident.report_pdf_url !== null,
);
const reportPending = computed(
    () =>
        props.incident.status === 'RESOLVED' &&
        props.incident.report_pdf_url === null,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Incidents', href: incidentsIndex() },
    { title: props.incident.incident_no, href: '#' },
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
                <h1 class="text-2xl font-semibold text-foreground">
                    {{ incident.incident_no }}
                </h1>
                <EscalateToP1Button :incident="incident" />
                <Badge :class="priorityBadgeClass[incident.priority]">
                    {{ incident.priority }}
                </Badge>
                <Badge
                    :class="statusBadgeClass[incident.status as IncidentStatus]"
                >
                    {{ incident.status.replace('_', ' ') }}
                </Badge>
                <div class="ml-auto">
                    <Button
                        v-if="reportReady"
                        as="a"
                        :href="downloadReport(incident.id).url"
                        variant="default"
                        size="sm"
                    >
                        Download Report
                    </Button>
                    <Button
                        v-else-if="reportPending"
                        variant="outline"
                        size="sm"
                        disabled
                        title="Report generation is still running in the background. Refresh in a moment."
                    >
                        Report generating…
                    </Button>
                </div>
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
                                    <dd class="mt-1 text-sm text-foreground">
                                        {{
                                            incident.incident_type?.name ?? '--'
                                        }}
                                        <span
                                            v-if="incident.incident_type?.code"
                                            class="ml-1 font-mono text-[10px] text-t-text-faint"
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
                                    <dd class="mt-1 text-sm text-foreground">
                                        {{ channelLabels[incident.channel] }}
                                    </dd>
                                </div>

                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Location
                                    </dt>
                                    <dd class="mt-1 text-sm text-foreground">
                                        {{ incident.location_text }}
                                    </dd>
                                </div>

                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Barangay
                                    </dt>
                                    <dd class="mt-1 text-sm text-foreground">
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
                                    <dd class="mt-1 text-sm text-foreground">
                                        {{ incident.caller_name ?? '--' }}
                                    </dd>
                                </div>

                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Caller Contact
                                    </dt>
                                    <dd class="mt-1 text-sm text-foreground">
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
                                    class="mt-1 text-sm whitespace-pre-wrap text-foreground"
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
