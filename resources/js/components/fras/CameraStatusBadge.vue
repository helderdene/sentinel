<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

type CameraStatus = 'online' | 'degraded' | 'offline' | 'decommissioned';

const props = defineProps<{
    status: CameraStatus;
}>();

const labelMap: Record<CameraStatus, string> = {
    online: 'Online',
    degraded: 'Degraded',
    offline: 'Offline',
    decommissioned: 'Decommissioned',
};

const colorClass = computed(() => {
    switch (props.status) {
        case 'online':
            return 'bg-[color-mix(in_srgb,var(--t-online)_12%,transparent)] text-t-online';

        case 'degraded':
            return 'bg-[color-mix(in_srgb,var(--t-unit-onscene)_12%,transparent)] text-t-unit-onscene';

        case 'offline':
            return 'bg-[color-mix(in_srgb,var(--t-unit-offline)_12%,transparent)] text-t-unit-offline';

        case 'decommissioned':
            return 'bg-[color-mix(in_srgb,var(--t-unit-offline)_12%,transparent)] text-t-unit-offline';
    }

    return '';
});

const label = computed(() => labelMap[props.status]);
// Pair amber degraded badge with a leading dot so the state is carried by
// more than just color (UI-SPEC WCAG AA mitigation — text alone fails AA
// at 12% tint for --t-unit-onscene).
const showDot = computed(() => props.status === 'degraded');
</script>

<template>
    <Badge variant="secondary" :class="colorClass">
        <span v-if="showDot" aria-hidden="true" class="mr-1">●</span>
        {{ label }}
    </Badge>
</template>
