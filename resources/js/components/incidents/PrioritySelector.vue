<script setup lang="ts">
import type { IncidentPriority, PrioritySuggestion } from '@/types/incident';

defineProps<{
    modelValue: IncidentPriority;
    suggestion?: PrioritySuggestion | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: IncidentPriority];
}>();

const priorities: {
    value: IncidentPriority;
    label: string;
    activeClass: string;
    inactiveClass: string;
}[] = [
    {
        value: 'P1',
        label: 'P1',
        activeClass: 'bg-t-p1 text-white border-t-p1',
        inactiveClass:
            'border-[color-mix(in_srgb,var(--t-p1)_40%,transparent)] text-t-p1 hover:bg-[color-mix(in_srgb,var(--t-p1)_8%,transparent)]',
    },
    {
        value: 'P2',
        label: 'P2',
        activeClass: 'bg-t-p2 text-white border-t-p2',
        inactiveClass:
            'border-[color-mix(in_srgb,var(--t-p2)_40%,transparent)] text-t-p2 hover:bg-[color-mix(in_srgb,var(--t-p2)_8%,transparent)]',
    },
    {
        value: 'P3',
        label: 'P3',
        activeClass: 'bg-t-p3 text-white border-t-p3',
        inactiveClass:
            'border-[color-mix(in_srgb,var(--t-p3)_40%,transparent)] text-t-p3 hover:bg-[color-mix(in_srgb,var(--t-p3)_8%,transparent)]',
    },
    {
        value: 'P4',
        label: 'P4',
        activeClass: 'bg-t-p4 text-white border-t-p4',
        inactiveClass:
            'border-[color-mix(in_srgb,var(--t-p4)_40%,transparent)] text-t-p4 hover:bg-[color-mix(in_srgb,var(--t-p4)_8%,transparent)]',
    },
];
</script>

<template>
    <div class="flex items-center gap-2">
        <button
            v-for="p in priorities"
            :key="p.value"
            type="button"
            :class="[
                'inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm font-semibold transition-colors',
                modelValue === p.value ? p.activeClass : p.inactiveClass,
            ]"
            @click="emit('update:modelValue', p.value)"
        >
            {{ p.label }}
            <span
                v-if="
                    suggestion &&
                    suggestion.priority === p.value &&
                    suggestion.confidence > 0
                "
                class="text-xs font-normal opacity-80"
            >
                {{ Math.round(suggestion.confidence) }}%
            </span>
        </button>
    </div>
</template>
