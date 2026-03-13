<script setup lang="ts">
import { computed } from 'vue';
import type { ResponderIncident } from '@/types/responder';

const props = defineProps<{
    incident: ResponderIncident;
}>();

const emit = defineEmits<{
    done: [];
}>();

const OUTCOME_LABELS: Record<string, string> = {
    TREATED_ON_SCENE: 'Treated On Scene',
    TRANSPORTED_TO_HOSPITAL: 'Transported to Hospital',
    REFUSED_TREATMENT: 'Patient Refused Treatment',
    DECLARED_DOA: 'Declared DOA',
    FALSE_ALARM: 'False Alarm / Stand Down',
};

const outcomeLabel = computed(
    () =>
        OUTCOME_LABELS[props.incident.outcome ?? ''] ??
        props.incident.outcome ??
        'Unknown',
);

const sceneTime = computed(() => {
    if (!props.incident.on_scene_at || !props.incident.resolved_at) {
        return null;
    }

    const onScene = new Date(props.incident.on_scene_at).getTime();
    const resolved = new Date(props.incident.resolved_at).getTime();
    const totalSeconds = Math.max(0, Math.floor((resolved - onScene) / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes} min ${seconds} sec`;
});

const checklistPct = computed(() => {
    const data = props.incident.checklist_data;

    if (!data) {
        return 0;
    }

    const total = Object.keys(data).length;

    if (total === 0) {
        return 0;
    }

    return Math.round(
        (Object.values(data).filter(Boolean).length / total) * 100,
    );
});

const hasVitals = computed(() => props.incident.vitals !== null);
</script>

<template>
    <div class="fixed inset-0 z-50 flex flex-col bg-t-bg dark:bg-[#0f172a]">
        <div class="flex flex-1 flex-col items-center justify-center px-6">
            <div
                class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30"
            >
                <svg
                    width="32"
                    height="32"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="text-green-600 dark:text-green-400"
                >
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
            </div>

            <h1
                class="mb-6 text-center font-sans text-xl font-bold text-t-text"
            >
                Incident Resolved
            </h1>

            <div
                class="w-full max-w-sm space-y-3 rounded-xl border border-t-border bg-t-surface p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-t-text-dim">
                        Incident
                    </span>
                    <span class="font-mono text-sm font-bold text-t-text">
                        {{ incident.incident_no }}
                    </span>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-t-text-dim">
                        Outcome
                    </span>
                    <span class="text-sm font-semibold text-t-text">
                        {{ outcomeLabel }}
                    </span>
                </div>

                <div
                    v-if="incident.hospital"
                    class="flex items-center justify-between"
                >
                    <span class="text-xs font-semibold text-t-text-dim">
                        Hospital
                    </span>
                    <span class="text-sm text-t-text">
                        {{ incident.hospital }}
                    </span>
                </div>

                <div v-if="sceneTime" class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-t-text-dim">
                        Scene Time
                    </span>
                    <span class="font-mono text-sm text-t-text">
                        {{ sceneTime }}
                    </span>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-t-text-dim">
                        Checklist
                    </span>
                    <div class="flex items-center gap-2">
                        <div
                            class="h-2 w-20 overflow-hidden rounded-full bg-t-border"
                        >
                            <div
                                class="h-full rounded-full bg-green-500 transition-all"
                                :style="{ width: `${checklistPct}%` }"
                            />
                        </div>
                        <span class="font-mono text-xs text-t-text-dim">
                            {{ checklistPct }}%
                        </span>
                    </div>
                </div>

                <template v-if="hasVitals">
                    <div class="border-t border-t-border pt-2">
                        <p class="mb-1.5 text-xs font-semibold text-t-text-dim">
                            Vitals
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            <div
                                v-if="incident.vitals?.systolic_bp !== null"
                                class="rounded-lg bg-t-bg px-2.5 py-1.5"
                            >
                                <p class="text-[10px] text-t-text-faint">BP</p>
                                <p
                                    class="font-mono text-sm font-bold text-t-text"
                                >
                                    {{ incident.vitals!.systolic_bp }}/{{
                                        incident.vitals!.diastolic_bp
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="incident.vitals?.heart_rate !== null"
                                class="rounded-lg bg-t-bg px-2.5 py-1.5"
                            >
                                <p class="text-[10px] text-t-text-faint">HR</p>
                                <p
                                    class="font-mono text-sm font-bold text-t-text"
                                >
                                    {{ incident.vitals!.heart_rate }} bpm
                                </p>
                            </div>
                            <div
                                v-if="incident.vitals?.spo2 !== null"
                                class="rounded-lg bg-t-bg px-2.5 py-1.5"
                            >
                                <p class="text-[10px] text-t-text-faint">
                                    SpO2
                                </p>
                                <p
                                    class="font-mono text-sm font-bold text-t-text"
                                >
                                    {{ incident.vitals!.spo2 }}%
                                </p>
                            </div>
                            <div
                                v-if="incident.vitals?.gcs !== null"
                                class="rounded-lg bg-t-bg px-2.5 py-1.5"
                            >
                                <p class="text-[10px] text-t-text-faint">GCS</p>
                                <p
                                    class="font-mono text-sm font-bold text-t-text"
                                >
                                    {{ incident.vitals!.gcs }}/15
                                </p>
                            </div>
                        </div>
                    </div>
                </template>

                <div
                    v-if="incident.assessment_tags.length > 0"
                    class="border-t border-t-border pt-2"
                >
                    <p class="mb-1.5 text-xs font-semibold text-t-text-dim">
                        Assessment
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="tag in incident.assessment_tags"
                            :key="tag"
                            class="rounded-full bg-t-accent/10 px-2 py-0.5 text-[11px] font-medium text-t-accent"
                        >
                            {{ tag }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="shrink-0 px-4 pb-6">
            <button
                type="button"
                class="flex min-h-[52px] w-full items-center justify-center rounded-xl bg-t-accent font-sans text-sm font-bold text-white shadow-lg transition-transform active:scale-[0.98]"
                @click="emit('done')"
            >
                Done
            </button>
        </div>
    </div>
</template>
