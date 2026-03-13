<script setup lang="ts">
import { computed } from 'vue';
import { STATUS_COLORS } from '@/types';

const props = defineProps<{
    status: string;
}>();

const color = computed(() => STATUS_COLORS[props.status] ?? '#64748b');
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[10px] uppercase tracking-wider"
        :style="{
            color: color,
            backgroundColor: `${color}14`,
            border: `1px solid ${color}30`,
        }"
    >
        {{ status }}
    </span>
</template>
