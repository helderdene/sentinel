<script setup lang="ts">
import type { IncidentTimelineEntry } from '@/types/incident';

defineProps<{
    timeline: IncidentTimelineEntry[];
}>();

const eventLabels: Record<string, string> = {
    incident_created: 'Incident Created',
    priority_override: 'Priority Override',
    status_change: 'Status Changed',
    unit_assigned: 'Unit Assigned',
    unit_arrived: 'Unit Arrived',
    note_added: 'Note Added',
    resolved: 'Incident Resolved',
};

function getEventLabel(eventType: string): string {
    return eventLabels[eventType] ?? eventType.replace(/_/g, ' ');
}

function formatEventData(
    eventType: string,
    eventData: Record<string, unknown> | null,
): string {
    if (!eventData) {
        return '';
    }

    if (eventType === 'priority_override') {
        return `Priority changed from ${eventData.suggested ?? '--'} to ${eventData.selected ?? '--'} (${eventData.confidence ?? 0}% confidence)`;
    }

    if (eventType === 'status_change') {
        return `${eventData.from ?? '--'} \u2192 ${eventData.to ?? '--'}`;
    }

    return JSON.stringify(eventData);
}

function formatTimestamp(dateStr: string): string {
    return new Date(dateStr).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}
</script>

<template>
    <div class="space-y-0">
        <div
            v-for="(entry, idx) in timeline"
            :key="entry.id"
            class="relative flex gap-4 pb-6"
        >
            <!-- Vertical line -->
            <div class="flex flex-col items-center">
                <div
                    class="size-3 shrink-0 rounded-full border-2 border-primary bg-background"
                />
                <div
                    v-if="idx < timeline.length - 1"
                    class="w-px grow bg-border"
                />
            </div>

            <!-- Content -->
            <div class="-mt-0.5 min-w-0 flex-1">
                <div class="flex items-baseline gap-2">
                    <span
                        class="text-sm font-medium text-neutral-900 dark:text-neutral-100"
                    >
                        {{ getEventLabel(entry.event_type) }}
                    </span>
                    <span class="text-xs text-muted-foreground">
                        {{ formatTimestamp(entry.created_at) }}
                    </span>
                </div>

                <p
                    v-if="entry.event_data"
                    class="mt-0.5 text-sm text-neutral-600 dark:text-neutral-400"
                >
                    {{ formatEventData(entry.event_type, entry.event_data) }}
                </p>

                <p
                    v-if="entry.notes"
                    class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-500"
                >
                    {{ entry.notes }}
                </p>

                <p
                    v-if="entry.actor"
                    class="mt-0.5 text-xs text-muted-foreground"
                >
                    by {{ entry.actor.name }}
                </p>
            </div>
        </div>

        <div
            v-if="timeline.length === 0"
            class="text-center text-sm text-muted-foreground"
        >
            No timeline entries yet.
        </div>
    </div>
</template>
