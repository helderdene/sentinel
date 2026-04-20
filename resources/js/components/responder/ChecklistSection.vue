<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { updateChecklist } from '@/actions/App/Http/Controllers/ResponderController';
import type { ChecklistTemplate, ResponderIncident } from '@/types/responder';

const props = defineProps<{
    incident: ResponderIncident;
    template: ChecklistTemplate | null;
}>();

const FALLBACK_ITEMS: Array<{ key: string; label: string }> = [
    { key: 'scene_secured', label: 'Scene secured' },
    { key: 'area_assessment', label: 'Area assessment complete' },
    { key: 'hazards_identified', label: 'Hazards identified' },
    { key: 'patient_contacted', label: 'Patient contacted' },
    { key: 'initial_assessment', label: 'Initial assessment' },
    { key: 'treatment_provided', label: 'Treatment provided' },
    { key: 'documentation_complete', label: 'Documentation complete' },
];

const items = computed(() =>
    props.template?.items && props.template.items.length > 0
        ? props.template.items
        : FALLBACK_ITEMS,
);

const checklistState = reactive<Record<string, boolean>>({});

function initChecklist(): void {
    for (const key of Object.keys(checklistState)) {
        delete checklistState[key];
    }

    for (const item of items.value) {
        checklistState[item.key] =
            props.incident.checklist_data?.[item.key] ?? false;
    }
}

initChecklist();

watch(
    () => props.incident.id,
    () => initChecklist(),
);

watch(
    () => props.template?.id,
    () => initChecklist(),
);

const completedCount = computed(
    () => items.value.filter((item) => checklistState[item.key]).length,
);

const totalCount = computed(() => items.value.length);

const progressPercent = computed(() =>
    totalCount.value > 0
        ? Math.round((completedCount.value / totalCount.value) * 100)
        : 0,
);

async function toggleItem(key: string): Promise<void> {
    checklistState[key] = !checklistState[key];

    const payload: Record<string, boolean> = {};

    for (const item of items.value) {
        payload[item.key] = checklistState[item.key] ?? false;
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
                body: JSON.stringify({ items: payload }),
            },
        );
    } catch {
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

        <!-- Template name (subtle) -->
        <p
            v-if="template"
            class="font-mono text-[9px] tracking-[1.5px] text-t-text-faint uppercase"
        >
            {{ template.name }}
        </p>

        <!-- Checklist items -->
        <ul class="flex flex-col gap-1">
            <li
                v-for="item in items"
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
