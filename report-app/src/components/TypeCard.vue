<script setup lang="ts">
import { computed } from 'vue';
import type { IncidentType } from '@/types';
import { PRIORITY_BG, PRIORITY_COLORS } from '@/types';
import PriorityBadge from './PriorityBadge.vue';

const props = defineProps<{
    type: IncidentType;
    selected: boolean;
}>();

const emit = defineEmits<{
    select: [];
}>();

const color = computed(
    () => PRIORITY_COLORS[props.type.default_priority] ?? '#64748b'
);
const bg = computed(
    () => PRIORITY_BG[props.type.default_priority] ?? 'rgba(100,116,139,.08)'
);
</script>

<template>
    <button
        class="flex cursor-pointer flex-col gap-2 rounded-xl border bg-t-surface p-3 text-left transition-all duration-200"
        :class="
            selected
                ? 'scale-[0.97] shadow-lg'
                : 'hover:shadow-md'
        "
        :style="{
            borderColor: selected ? color : 'var(--t-border)',
            boxShadow: selected ? `0 0 0 1px ${color}, 0 4px 12px ${color}20` : undefined,
        }"
        @click="emit('select')"
    >
        <!-- Icon area -->
        <div
            class="flex h-11 w-11 items-center justify-center rounded-lg"
            :style="{ backgroundColor: bg }"
        >
            <svg
                width="28"
                height="28"
                viewBox="0 0 28 28"
                fill="none"
                :style="{ color: color }"
            >
                <path
                    d="M14 3L26 25H2L14 3Z"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linejoin="round"
                />
                <line
                    x1="14"
                    y1="11"
                    x2="14"
                    y2="18"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />
                <circle cx="14" cy="21.5" r="1.3" fill="currentColor" />
            </svg>
        </div>

        <!-- Type name -->
        <span class="text-[13px] font-semibold leading-tight text-t-text">
            {{ type.name }}
        </span>

        <!-- Description (truncated) -->
        <span
            v-if="type.description"
            class="line-clamp-2 text-[11px] leading-snug text-t-text-dim"
        >
            {{ type.description }}
        </span>

        <!-- Priority badge -->
        <PriorityBadge :priority="type.default_priority" small />
    </button>
</template>
