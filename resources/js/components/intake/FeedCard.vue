<script setup lang="ts">
import { computed } from 'vue';
import { channelDisplayMap } from '@/components/intake/ChBadge.vue';
import ChBadge from '@/components/intake/ChBadge.vue';
import IntakeIconCheck from '@/components/intake/icons/IntakeIconCheck.vue';
import IntakeIconPin from '@/components/intake/icons/IntakeIconPin.vue';
import IntakeIconUser from '@/components/intake/icons/IntakeIconUser.vue';
import PriBadge from '@/components/intake/PriBadge.vue';
import type { Incident } from '@/types/incident';

type Props = {
    incident: Incident;
    active?: boolean;
    triaged?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    active: false,
    triaged: false,
});

const emit = defineEmits<{
    select: [incident: Incident];
}>();

const priorityColors: Record<string, string> = {
    P1: 'var(--t-p1)',
    P2: 'var(--t-p2)',
    P3: 'var(--t-p3)',
    P4: 'var(--t-p4)',
};

const borderColor = computed(
    () => priorityColors[props.incident.priority] ?? 'var(--t-border)',
);

const channelKey = computed(
    () => channelDisplayMap[props.incident.channel] ?? 'APP',
);

const priorityNumber = computed(() => {
    const num = parseInt(props.incident.priority.replace('P', ''), 10);

    return (num >= 1 && num <= 4 ? num : 4) as 1 | 2 | 3 | 4;
});

function timeElapsed(createdAt: string): string {
    const diff = Math.floor(
        (Date.now() - new Date(createdAt).getTime()) / 1000,
    );

    if (diff < 60) {
        return `${diff}s`;
    }

    if (diff < 3600) {
        return `${Math.floor(diff / 60)}m`;
    }

    return `${Math.floor(diff / 3600)}h ${Math.floor((diff % 3600) / 60)}m`;
}
</script>

<template>
    <div
        class="cursor-pointer rounded-lg border border-t-border transition-all"
        :class="[triaged ? 'opacity-55' : '']"
        :style="{
            backgroundColor: active
                ? 'rgba(37,99,235,0.08)'
                : 'var(--t-surface)',
            borderLeftWidth: '3px',
            borderLeftColor: borderColor,
            padding: '11px 12px',
            boxShadow: active
                ? '0 0 0 3px rgba(37,99,235,0.1)'
                : '0 1px 3px rgba(0,0,0,0.04)',
            borderColor: active ? 'var(--t-accent)' : undefined,
        }"
        @click="emit('select', incident)"
    >
        <!-- Top row: badges + timestamp -->
        <div class="flex items-center gap-1.5">
            <PriBadge :p="priorityNumber" size="sm" />
            <ChBadge :ch="channelKey" small />
            <span class="ml-auto font-mono text-[9.5px] text-t-text-faint">
                {{ timeElapsed(incident.created_at) }}
            </span>
        </div>

        <!-- Middle: type name + incident number -->
        <div class="mt-2">
            <p class="text-[13px] font-semibold text-t-text">
                {{ incident.incident_type?.name ?? 'Unclassified' }}
            </p>
            <p class="font-mono text-[10px] text-t-text-faint">
                {{ incident.incident_no }}
            </p>
        </div>

        <!-- Bottom: location + caller -->
        <div class="mt-2 flex flex-col gap-0.5">
            <div v-if="incident.location_text" class="flex items-center gap-1">
                <IntakeIconPin :size="11" color="var(--t-text-dim)" />
                <span class="truncate text-[11px] text-t-text-dim">
                    {{ incident.location_text }}
                </span>
            </div>
            <div v-if="incident.caller_name" class="flex items-center gap-1">
                <IntakeIconUser :size="11" color="var(--t-text-dim)" />
                <span class="truncate text-[11px] text-t-text-dim">
                    {{ incident.caller_name }}
                </span>
            </div>
        </div>

        <!-- Triaged badge -->
        <div
            v-if="triaged"
            class="mt-2 inline-flex items-center gap-1 rounded bg-t-online/10 px-1.5 py-[2px]"
        >
            <IntakeIconCheck :size="10" color="var(--t-online)" />
            <span class="text-[9px] font-bold text-t-online">TRIAGED</span>
        </div>
    </div>
</template>
