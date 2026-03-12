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
        activeClass: 'bg-red-500 text-white border-red-500',
        inactiveClass:
            'border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950',
    },
    {
        value: 'P2',
        label: 'P2',
        activeClass: 'bg-orange-500 text-white border-orange-500',
        inactiveClass:
            'border-orange-300 text-orange-600 hover:bg-orange-50 dark:border-orange-700 dark:text-orange-400 dark:hover:bg-orange-950',
    },
    {
        value: 'P3',
        label: 'P3',
        activeClass: 'bg-amber-500 text-white border-amber-500',
        inactiveClass:
            'border-amber-300 text-amber-600 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-400 dark:hover:bg-amber-950',
    },
    {
        value: 'P4',
        label: 'P4',
        activeClass: 'bg-green-500 text-white border-green-500',
        inactiveClass:
            'border-green-300 text-green-600 hover:bg-green-50 dark:border-green-700 dark:text-green-400 dark:hover:bg-green-950',
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
