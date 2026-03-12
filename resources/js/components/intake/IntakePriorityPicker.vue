<script setup lang="ts">
import { computed } from 'vue';
import type { IncidentPriority, PrioritySuggestion } from '@/types/incident';

type Props = {
    modelValue: IncidentPriority;
    suggestion?: PrioritySuggestion | null;
};

const props = withDefaults(defineProps<Props>(), {
    suggestion: null,
});

const emit = defineEmits<{
    'update:modelValue': [value: IncidentPriority];
}>();

interface PriorityOption {
    value: IncidentPriority;
    label: string;
    level: string;
    color: string;
}

const priorities: PriorityOption[] = [
    { value: 'P1', label: 'P1', level: 'CRITICAL', color: 'var(--t-p1)' },
    { value: 'P2', label: 'P2', level: 'HIGH', color: 'var(--t-p2)' },
    { value: 'P3', label: 'P3', level: 'MEDIUM', color: 'var(--t-p3)' },
    { value: 'P4', label: 'P4', level: 'LOW', color: 'var(--t-p4)' },
];

const suggestedValue = computed(() => props.suggestion?.priority ?? null);
</script>

<template>
    <div class="grid grid-cols-4 gap-2">
        <button
            v-for="p in priorities"
            :key="p.value"
            type="button"
            class="flex flex-col items-center gap-1 rounded-lg border px-3 py-2.5 transition-all"
            :class="[
                modelValue === p.value
                    ? 'border-solid shadow-sm'
                    : 'border-t-border bg-t-surface-alt',
            ]"
            :style="
                modelValue === p.value
                    ? {
                          backgroundColor: `color-mix(in srgb, ${p.color} 6%, transparent)`,
                          borderColor: p.color,
                      }
                    : {}
            "
            @click="emit('update:modelValue', p.value)"
        >
            <span class="flex items-center gap-1.5">
                <span
                    class="size-2 rounded-full"
                    :style="{
                        backgroundColor:
                            modelValue === p.value ? p.color : 'transparent',
                        borderWidth: '1.5px',
                        borderStyle: 'solid',
                        borderColor: p.color,
                    }"
                />
                <span
                    class="font-mono text-xs font-bold"
                    :style="{
                        color:
                            modelValue === p.value
                                ? p.color
                                : 'var(--t-text-mid)',
                    }"
                >
                    {{ p.label }}
                </span>
            </span>
            <span
                class="text-[9px] font-medium tracking-wide"
                :style="{
                    color:
                        modelValue === p.value
                            ? p.color
                            : 'var(--t-text-faint)',
                }"
            >
                {{ p.level }}
            </span>
            <span
                v-if="suggestedValue === p.value"
                class="mt-0.5 rounded px-1 py-[1px] text-[8px] font-semibold"
                :style="{
                    backgroundColor: `color-mix(in srgb, ${p.color} 10%, transparent)`,
                    color: p.color,
                }"
            >
                SUGGESTED
            </span>
        </button>
    </div>
</template>
