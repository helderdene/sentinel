<script setup lang="ts">
import { computed } from 'vue';
import type { FrasSeverity } from '@/types/fras';

const props = defineProps<{ severity: FrasSeverity }>();

interface BadgeConfig {
    label: string;
    tokenVar: string;
}

const config = computed<BadgeConfig>(() => {
    switch (props.severity) {
        case 'critical':
            return { label: 'CRITICAL', tokenVar: 'var(--t-p1)' };
        case 'warning':
            return { label: 'WARNING', tokenVar: 'var(--t-unit-onscene)' };
        default:
            return { label: 'INFO', tokenVar: 'var(--t-unit-offline)' };
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full border px-2 py-[2px] font-mono text-[10px] font-bold tracking-[1px] whitespace-nowrap uppercase"
        :style="{
            backgroundColor: `color-mix(in srgb, ${config.tokenVar} 15%, transparent)`,
            borderColor: `color-mix(in srgb, ${config.tokenVar} 40%, transparent)`,
            color: config.tokenVar,
        }"
    >
        <span aria-hidden="true">●</span>
        {{ config.label }}
    </span>
</template>
