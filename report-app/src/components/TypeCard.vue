<script setup lang="ts">
import type { IncidentType } from '@/types';
import { PRIORITY_BG, PRIORITY_COLORS } from '@/types';
import { computed } from 'vue';
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

/**
 * SVG path data keyed by incident category.
 * Each entry is [d-path, fill-rule?] for a 24x24 viewBox.
 */
const CATEGORY_ICONS: Record<string, string> = {
    // Fire — flame
    Fire: 'M12 2c.5 3-1.5 5-1.5 5s3-1 4 2c1 3-1 5.5-1 5.5s2-.5 2.5-2.5c2 3 0 7-3.5 8.5C9 22 6 19 6 15.5c0-3 2-5.5 3-7C10 7 11.5 4 12 2Z',
    // Medical — cross/plus
    Medical:
        'M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7V3Z',
    // Vehicular — car
    Vehicular:
        'M5 17h1a2 2 0 1 0 4 0h4a2 2 0 1 0 4 0h1a1 1 0 0 0 1-1v-4a1 1 0 0 0-.3-.7L17.4 9H16l-3-4H8L5 9h-.6L3.1 11.3A1 1 0 0 0 3 12v4a1 1 0 0 0 1 1h1Z',
    // Natural Disaster — earthquake/lightning bolt
    'Natural Disaster':
        'M13 2L4.1 12.9a.5.5 0 0 0 .4.8H11l-1 8.3 8.9-10.9a.5.5 0 0 0-.4-.8H13l1-8.3Z',
    // Crime / Security — shield
    'Crime / Security':
        'M12 3L4 7v5c0 5 3.5 9.7 8 11 4.5-1.3 8-6 8-11V7l-8-4Zm0 2.2L18 9v3c0 4-2.8 7.7-6 8.9-3.2-1.2-6-4.9-6-8.9V9l6-3.8Z',
    // Hazmat — biohazard/toxic circle with exclamation
    Hazmat:
        'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm0 3a1 1 0 0 0-1 1v5a1 1 0 0 0 2 0V8a1 1 0 0 0-1-1Zm0 9a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5Z',
    // Water Rescue — wave/water
    'Water Rescue':
        'M2 16c1.5-1.5 3-2 4.5-2s3 .5 4.5 2c1.5 1.5 3 2 4.5 2s3-.5 4.5-2M2 10c1.5-1.5 3-2 4.5-2s3 .5 4.5 2c1.5 1.5 3 2 4.5 2s3-.5 4.5-2',
    // Public Disturbance — megaphone/speaker
    'Public Disturbance':
        'M18 8a6 6 0 0 1 0 8M13 3L7 8H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h3l6 5V3Zm2 5a3 3 0 0 1 0 4',
};

const iconPath = computed(
    () => CATEGORY_ICONS[props.type.category] ?? CATEGORY_ICONS['Hazmat']
);

const isStrokeIcon = computed(() => {
    const strokeCategories = [
        'Water Rescue',
        'Public Disturbance',
        'Vehicular',
    ];

    return strokeCategories.includes(props.type.category);
});
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
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                :style="{ color: color }"
            >
                <path
                    :d="iconPath"
                    :stroke="isStrokeIcon ? 'currentColor' : undefined"
                    :stroke-width="isStrokeIcon ? '2' : undefined"
                    :stroke-linecap="isStrokeIcon ? 'round' : undefined"
                    :stroke-linejoin="isStrokeIcon ? 'round' : undefined"
                    :fill="isStrokeIcon ? 'none' : 'currentColor'"
                    fill-rule="evenodd"
                />
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
