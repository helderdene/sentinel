<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed } from 'vue';

import type { FilterOptions } from '@/types/analytics';

defineProps<{
    filterOptions: FilterOptions;
}>();

const emit = defineEmits<{
    applyPreset: [preset: string];
    applyCustomDates: [start: string, end: string];
    setIncidentType: [id: number | null];
    setPriority: [priority: string | null];
    setBarangay: [id: number | null];
    clearFilters: [];
}>();

const presetModel = defineModel<string | null>('preset', {
    default: '30d',
});
const startDateModel = defineModel<string | null>('startDate', {
    default: null,
});
const endDateModel = defineModel<string | null>('endDate', {
    default: null,
});
const incidentTypeModel = defineModel<number | null>('incidentTypeId', {
    default: null,
});
const priorityModel = defineModel<string | null>('priority', {
    default: null,
});
const barangayModel = defineModel<number | null>('barangayId', {
    default: null,
});

const PRESETS = ['7d', '30d', '90d', '365d'] as const;
const PRIORITIES = ['P1', 'P2', 'P3', 'P4'] as const;

function handlePreset(p: string): void {
    presetModel.value = p;
    startDateModel.value = null;
    endDateModel.value = null;
    emit('applyPreset', p);
}

function handleCustom(): void {
    presetModel.value = null;
}

function handleCustomDateChange(): void {
    if (startDateModel.value && endDateModel.value) {
        emit('applyCustomDates', startDateModel.value, endDateModel.value);
    }
}

function handleTypeChange(e: Event): void {
    const target = e.target as HTMLSelectElement;
    const val = target.value ? parseInt(target.value, 10) : null;
    incidentTypeModel.value = val;
    emit('setIncidentType', val);
}

function handlePriority(p: string): void {
    const newVal = priorityModel.value === p ? null : p;
    priorityModel.value = newVal;
    emit('setPriority', newVal);
}

function handleBarangayChange(e: Event): void {
    const target = e.target as HTMLSelectElement;
    const val = target.value ? parseInt(target.value, 10) : null;
    barangayModel.value = val;
    emit('setBarangay', val);
}

const hasActiveFilters = computed(() => {
    return (
        presetModel.value !== '30d' ||
        incidentTypeModel.value !== null ||
        priorityModel.value !== null ||
        barangayModel.value !== null ||
        startDateModel.value !== null
    );
});
</script>

<template>
    <div
        class="sticky top-0 z-10 border-b border-border bg-card/80 px-4 py-2.5 backdrop-blur"
    >
        <div class="flex flex-wrap items-center gap-3">
            <!-- Date Presets -->
            <div class="flex items-center gap-1">
                <button
                    v-for="p in PRESETS"
                    :key="p"
                    class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                    :class="
                        presetModel === p
                            ? 'bg-t-accent text-white'
                            : 'bg-secondary text-secondary-foreground hover:bg-accent'
                    "
                    @click="handlePreset(p)"
                >
                    {{ p }}
                </button>
                <button
                    class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                    :class="
                        presetModel === null
                            ? 'bg-t-accent text-white'
                            : 'bg-secondary text-secondary-foreground hover:bg-accent'
                    "
                    @click="handleCustom"
                >
                    Custom
                </button>
            </div>

            <!-- Custom Date Inputs -->
            <div v-if="presetModel === null" class="flex items-center gap-1.5">
                <input
                    v-model="startDateModel"
                    type="date"
                    class="rounded border border-border px-2 py-1 text-xs dark:border-border dark:bg-card dark:text-foreground"
                    @change="handleCustomDateChange"
                />
                <span class="text-xs text-t-text-faint">to</span>
                <input
                    v-model="endDateModel"
                    type="date"
                    class="rounded border border-border px-2 py-1 text-xs dark:border-border dark:bg-card dark:text-foreground"
                    @change="handleCustomDateChange"
                />
            </div>

            <div class="h-4 w-px bg-border" aria-hidden="true" />

            <!-- Incident Type -->
            <select
                class="rounded border border-border px-2 py-1 text-xs dark:border-border dark:bg-card dark:text-foreground"
                :value="incidentTypeModel ?? ''"
                @change="handleTypeChange"
            >
                <option value="">All Types</option>
                <option
                    v-for="t in filterOptions.incident_types"
                    :key="t.id"
                    :value="t.id"
                >
                    {{ t.name }}
                </option>
            </select>

            <!-- Priority Pills -->
            <div class="flex items-center gap-1">
                <button
                    v-for="p in PRIORITIES"
                    :key="p"
                    class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                    :class="
                        priorityModel === p
                            ? 'bg-t-accent text-white'
                            : 'bg-secondary text-secondary-foreground hover:bg-accent'
                    "
                    @click="handlePriority(p)"
                >
                    {{ p }}
                </button>
            </div>

            <!-- Barangay Dropdown -->
            <select
                class="max-w-[180px] rounded border border-border px-2 py-1 text-xs dark:border-border dark:bg-card dark:text-foreground"
                :value="barangayModel ?? ''"
                @change="handleBarangayChange"
            >
                <option value="">All Barangays</option>
                <option
                    v-for="b in filterOptions.barangays"
                    :key="b.id"
                    :value="b.id"
                >
                    {{ b.name }}
                </option>
            </select>

            <!-- Clear -->
            <button
                v-if="hasActiveFilters"
                class="flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium text-t-text-dim transition-colors hover:bg-secondary hover:text-foreground"
                @click="emit('clearFilters')"
            >
                <X class="h-3 w-3" />
                Clear
            </button>
        </div>
    </div>
</template>
