<script setup lang="ts">
import { computed } from 'vue';
import TriageForm from '@/components/intake/TriageForm.vue';
import type {
    Incident,
    IncidentChannel,
    IncidentPriority,
    IncidentType,
} from '@/types/incident';

type Props = {
    activeIncident?: Incident | null;
    isManualEntry: boolean;
    incidentTypes: Record<string, IncidentType[]>;
    channels: IncidentChannel[];
    priorities: IncidentPriority[];
    priorityConfig?: Record<string, unknown>;
};

const props = withDefaults(defineProps<Props>(), {
    activeIncident: null,
    priorityConfig: undefined,
});

const hasContent = computed(
    () => props.activeIncident !== null || props.isManualEntry,
);
</script>

<template>
    <div class="flex min-w-0 flex-1 flex-col bg-t-bg">
        <!-- Header -->
        <div class="border-b border-t-border bg-t-surface px-5 py-3">
            <h2 class="text-sm font-semibold text-t-text">TRIAGE FORM</h2>
            <p
                v-if="activeIncident && !isManualEntry"
                class="mt-0.5 text-[11px] text-t-text-dim"
            >
                {{ activeIncident.incident_no }} --
                {{ activeIncident.incident_type?.name ?? 'Unclassified' }}
            </p>
            <p
                v-else-if="isManualEntry"
                class="mt-0.5 text-[11px] text-t-text-dim"
            >
                Manual Entry -- New Incident
            </p>
            <p v-else class="mt-0.5 text-[11px] text-t-text-faint">
                Select an incoming report to begin triage
            </p>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto">
            <div v-if="hasContent" class="mx-auto max-w-xl px-5 py-5">
                <TriageForm
                    :active-incident="activeIncident"
                    :is-manual-entry="isManualEntry"
                    :incident-types="incidentTypes"
                    :channels="channels"
                    :priorities="priorities"
                    :priority-config="priorityConfig"
                />
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="flex h-full flex-col items-center justify-center"
            >
                <div
                    class="flex size-16 items-center justify-center rounded-2xl bg-t-surface shadow-sm"
                >
                    <svg
                        width="32"
                        height="32"
                        viewBox="0 0 32 32"
                        fill="none"
                        stroke="var(--t-text-faint)"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <rect x="8" y="4" width="16" height="24" rx="2" />
                        <path d="M12 2h8v4H12z" />
                        <line x1="12" y1="14" x2="20" y2="14" />
                        <line x1="12" y1="18" x2="18" y2="18" />
                        <line x1="12" y1="22" x2="16" y2="22" />
                    </svg>
                </div>
                <h3 class="mt-4 text-sm font-semibold text-t-text-mid">
                    Select an Incoming Report
                </h3>
                <p
                    class="mt-1 max-w-[200px] text-center text-[11px] leading-relaxed text-t-text-faint"
                >
                    Click a message from the feed to begin classifying and
                    triaging the incident.
                </p>
            </div>
        </div>
    </div>
</template>
