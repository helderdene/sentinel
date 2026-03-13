<script setup lang="ts">
import { PRIORITY_BG, PRIORITY_COLORS, PRIORITY_LABELS } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    priority: number;
    small?: boolean;
}>();

const color = computed(() => PRIORITY_COLORS[props.priority] ?? '#64748b');
const label = computed(() => PRIORITY_LABELS[props.priority] ?? 'UNKNOWN');
const bg = computed(() => PRIORITY_BG[props.priority] ?? 'rgba(100,116,139,.08)');
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full font-mono uppercase tracking-wider"
        :class="small ? 'px-1.5 py-0.5 text-[9px]' : 'px-2 py-0.5 text-[10px]'"
        :style="{
            color: color,
            backgroundColor: bg,
            border: `1px solid ${color}20`,
        }"
    >
        P{{ priority }}
        <span v-if="!small">{{ label }}</span>
    </span>
</template>
