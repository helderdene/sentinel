<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { updateChecklist } from '@/actions/App/Http/Controllers/ResponderController';
import type { ChecklistTemplate, ResponderIncident } from '@/types/responder';

const props = defineProps<{
    incident: ResponderIncident;
}>();

const CHECKLIST_TEMPLATES: ChecklistTemplate[] = [
    {
        id: 'cardiac',
        label: 'Cardiac Emergency',
        items: [
            { key: 'scene_secured', label: 'Scene secured' },
            { key: 'patient_responsive', label: 'Patient responsive check' },
            { key: 'abc_assessment', label: 'ABC assessment' },
            { key: 'vital_signs', label: 'Vital signs taken' },
            { key: 'aed_monitor', label: 'AED/monitor attached' },
            { key: 'iv_access', label: 'IV access established' },
            { key: 'medication', label: 'Medication administered' },
        ],
    },
    {
        id: 'road_accident',
        label: 'Road Accident',
        items: [
            { key: 'scene_secured', label: 'Scene secured' },
            { key: 'traffic_control', label: 'Traffic control established' },
            { key: 'vehicle_stability', label: 'Vehicle stability assessed' },
            { key: 'extrication', label: 'Patient extrication (if needed)' },
            {
                key: 'spinal_immobilization',
                label: 'Spinal immobilization applied',
            },
            { key: 'bleeding_control', label: 'Bleeding controlled' },
            { key: 'patient_assessed', label: 'Patient assessed' },
        ],
    },
    {
        id: 'structure_fire',
        label: 'Structure Fire',
        items: [
            { key: 'scene_secured', label: 'Scene secured' },
            { key: 'fire_suppression', label: 'Fire suppression confirmed' },
            { key: 'search_completed', label: 'Search completed' },
            { key: 'hazmat_assessment', label: 'Hazmat assessment' },
            { key: 'ventilation_status', label: 'Ventilation status' },
            { key: 'patient_triage', label: 'Patient triage' },
            { key: 'decontamination', label: 'Decontamination (if needed)' },
        ],
    },
    {
        id: 'default',
        label: 'General',
        items: [
            { key: 'scene_secured', label: 'Scene secured' },
            { key: 'area_assessment', label: 'Area assessment complete' },
            { key: 'hazards_identified', label: 'Hazards identified' },
            { key: 'patient_contacted', label: 'Patient contacted' },
            { key: 'initial_assessment', label: 'Initial assessment' },
            { key: 'treatment_provided', label: 'Treatment provided' },
            { key: 'documentation_complete', label: 'Documentation complete' },
        ],
    },
];

function selectTemplate(incident: ResponderIncident): ChecklistTemplate {
    const category = incident.incident_type.category.toLowerCase();
    const code = incident.incident_type.code.toLowerCase();

    if (
        category.includes('fire') ||
        code.includes('fire') ||
        code.includes('blaze')
    ) {
        return CHECKLIST_TEMPLATES[2]; // structure fire
    }

    if (
        (category.includes('medical') &&
            (code.includes('cardiac') || code.includes('heart'))) ||
        code.includes('cardiac')
    ) {
        return CHECKLIST_TEMPLATES[0]; // cardiac
    }

    if (
        code.includes('accident') ||
        code.includes('collision') ||
        code.includes('vehicular')
    ) {
        return CHECKLIST_TEMPLATES[1]; // road accident
    }

    return CHECKLIST_TEMPLATES[3]; // default
}

const template = computed(() => selectTemplate(props.incident));

const checklistState = reactive<Record<string, boolean>>({});

function initChecklist(): void {
    const items = template.value.items;

    for (const item of items) {
        checklistState[item.key] =
            props.incident.checklist_data?.[item.key] ?? false;
    }
}

initChecklist();

watch(
    () => props.incident.id,
    () => initChecklist(),
);

const completedCount = computed(
    () =>
        template.value.items.filter((item) => checklistState[item.key]).length,
);

const totalCount = computed(() => template.value.items.length);

const progressPercent = computed(() =>
    totalCount.value > 0
        ? Math.round((completedCount.value / totalCount.value) * 100)
        : 0,
);

async function toggleItem(key: string): Promise<void> {
    checklistState[key] = !checklistState[key];

    const items: Record<string, boolean> = {};

    for (const item of template.value.items) {
        items[item.key] = checklistState[item.key] ?? false;
    }

    const xsrfToken = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

    try {
        await fetch(
            updateChecklist.url({
                incident: String(props.incident.id),
            }),
            {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken,
                },
                body: JSON.stringify({ items }),
            },
        );
    } catch {
        // Revert on failure
        checklistState[key] = !checklistState[key];
    }
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Progress bar -->
        <div class="flex items-center gap-3">
            <div class="h-2 flex-1 overflow-hidden rounded-full bg-t-border">
                <div
                    class="h-full rounded-full bg-t-accent transition-all duration-300 ease-out"
                    :style="{ width: `${progressPercent}%` }"
                ></div>
            </div>

            <span class="font-mono text-[11px] text-t-text-dim">
                {{ completedCount }}/{{ totalCount }}
            </span>
        </div>

        <!-- Checklist items -->
        <ul class="flex flex-col gap-1">
            <li
                v-for="item in template.items"
                :key="item.key"
                class="active:bg-t-bg-dim/30 flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg px-3 py-2 transition-colors"
                role="checkbox"
                :aria-checked="checklistState[item.key]"
                tabindex="0"
                @click="toggleItem(item.key)"
                @keydown.enter.prevent="toggleItem(item.key)"
                @keydown.space.prevent="toggleItem(item.key)"
            >
                <!-- Animated checkbox -->
                <div
                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded border-2 transition-all duration-200"
                    :class="
                        checklistState[item.key]
                            ? 'border-t-accent bg-t-accent'
                            : 'border-t-text-dim/40 bg-transparent'
                    "
                >
                    <svg
                        v-if="checklistState[item.key]"
                        class="h-3 w-3 text-white"
                        viewBox="0 0 12 12"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <polyline points="2.5 6 5 8.5 9.5 3.5" />
                    </svg>
                </div>

                <!-- Label -->
                <span
                    class="text-[13px] transition-all duration-200"
                    :class="
                        checklistState[item.key]
                            ? 'text-t-text-dim line-through'
                            : 'text-t-text'
                    "
                >
                    {{ item.label }}
                </span>
            </li>
        </ul>
    </div>
</template>
