<script setup lang="ts">
import { computed } from 'vue';

type Props = {
    p: 1 | 2 | 3 | 4;
    size?: 'sm' | 'lg';
};

const props = withDefaults(defineProps<Props>(), {
    size: 'sm',
});

const priorityColors: Record<number, string> = {
    1: 'var(--t-p1)',
    2: 'var(--t-p2)',
    3: 'var(--t-p3)',
    4: 'var(--t-p4)',
};

const color = computed(() => priorityColors[props.p] ?? priorityColors[4]);

const isLarge = computed(() => props.size === 'lg');
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded font-mono font-bold whitespace-nowrap"
        :class="
            isLarge
                ? 'px-2.5 py-1 text-[11px]'
                : 'px-[7px] py-[2px] text-[10px]'
        "
        :style="{
            backgroundColor: `color-mix(in srgb, ${color} 8%, transparent)`,
            color: color,
        }"
    >
        <span
            class="shrink-0 rounded-full"
            :class="isLarge ? 'size-2' : 'size-1.5'"
            :style="{ backgroundColor: color }"
        />
        P{{ p }}
    </span>
</template>
