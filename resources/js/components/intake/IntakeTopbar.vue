<script setup lang="ts">
import type { Ref } from 'vue';
import { computed, inject, onMounted, onUnmounted, ref } from 'vue';
import { channelDisplayMap } from '@/components/intake/ChBadge.vue';
import ChBadge from '@/components/intake/ChBadge.vue';
import IntakeIconIntake from '@/components/intake/icons/IntakeIconIntake.vue';
import IntakeIconPin from '@/components/intake/icons/IntakeIconPin.vue';
import PriBadge from '@/components/intake/PriBadge.vue';
import UserChip from '@/components/intake/UserChip.vue';
import type { User } from '@/types/auth';
import type { TickerEvent } from '@/types/incident';

defineProps<{
    user: User;
}>();

const stats = inject<{
    incoming: Ref<number>;
    pending: Ref<number>;
    triaged: Ref<number>;
    avgResp: Ref<string>;
}>('topbarStats', {
    incoming: ref(0),
    pending: ref(0),
    triaged: ref(0),
    avgResp: ref('0m'),
});

const tickerEvents = inject<Ref<TickerEvent[]>>(
    'tickerEvents',
    ref<TickerEvent[]>([]),
);

const latestEvent = computed(() =>
    tickerEvents.value.length > 0 ? tickerEvents.value[0] : null,
);

const priorityNumber = computed(() => {
    if (!latestEvent.value) {
        return 4 as const;
    }

    const num = parseInt(latestEvent.value.priority.replace('P', ''), 10);

    return (num >= 1 && num <= 4 ? num : 4) as 1 | 2 | 3 | 4;
});

const channelKey = computed(
    () =>
        (latestEvent.value
            ? channelDisplayMap[latestEvent.value.channel]
            : undefined) ?? 'APP',
);

const priorityColors: Record<string, string> = {
    P1: 'var(--t-p1)',
    P2: 'var(--t-p2)',
    P3: 'var(--t-p3)',
    P4: 'var(--t-p4)',
};

const borderColor = computed(
    () =>
        (latestEvent.value
            ? priorityColors[latestEvent.value.priority]
            : undefined) ?? 'var(--t-border)',
);

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

    return `${Math.floor(diff / 3600)}h`;
}

const clock = ref('');
let clockInterval: ReturnType<typeof setInterval> | null = null;

function updateClock(): void {
    const now = new Date();
    clock.value = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });
}

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);
});

onUnmounted(() => {
    if (clockInterval) {
        clearInterval(clockInterval);
    }
});
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center border-b border-t-border bg-t-surface px-4 shadow-[0_1px_4px_rgba(0,0,0,.06)] dark:border-t-border dark:bg-t-surface"
    >
        <!-- Brand -->
        <div class="flex items-center gap-2.5">
            <div
                class="flex size-[34px] items-center justify-center rounded-lg bg-t-brand/10"
            >
                <IntakeIconIntake :size="18" color="var(--t-brand)" />
            </div>
            <div class="flex items-baseline gap-1 font-mono text-sm font-bold">
                <span class="text-t-brand">SENTINEL</span>
                <span class="text-t-accent">INTAKE</span>
            </div>
        </div>

        <!-- Divider -->
        <div class="mx-4 h-7 w-px bg-t-border" />

        <!-- Stat pills -->
        <div class="flex items-center">
            <div
                class="flex flex-col items-center border-r border-t-border px-4"
            >
                <span class="font-mono text-[21px] font-bold text-t-accent">
                    {{ stats.incoming }}
                </span>
                <span
                    class="font-mono text-[9px] font-bold tracking-[1.2px] text-t-text-faint uppercase"
                >
                    INCOMING
                </span>
            </div>
            <div
                class="flex flex-col items-center border-r border-t-border px-4"
            >
                <span class="font-mono text-[21px] font-bold text-t-p2">
                    {{ stats.pending }}
                </span>
                <span
                    class="font-mono text-[9px] font-bold tracking-[1.2px] text-t-text-faint uppercase"
                >
                    PENDING
                </span>
            </div>
            <div
                class="flex flex-col items-center border-r border-t-border px-4"
            >
                <span class="font-mono text-[21px] font-bold text-t-online">
                    {{ stats.triaged }}
                </span>
                <span
                    class="font-mono text-[9px] font-bold tracking-[1.2px] text-t-text-faint uppercase"
                >
                    TRIAGED
                </span>
            </div>
            <div class="flex flex-col items-center px-4">
                <span class="font-mono text-[21px] font-bold text-t-text-dim">
                    {{ stats.avgResp }}
                </span>
                <span
                    class="font-mono text-[9px] font-bold tracking-[1.2px] text-t-text-faint uppercase"
                >
                    AVG RESP
                </span>
            </div>
        </div>

        <!-- Live ticker -->
        <div class="mx-4 flex flex-1 items-center gap-2 overflow-hidden">
            <span
                class="shrink-0 rounded border px-1.5 py-[1px] font-mono text-[9px] font-bold tracking-[2px]"
                :style="{
                    backgroundColor:
                        'color-mix(in srgb, var(--t-accent) 8%, transparent)',
                    borderColor:
                        'color-mix(in srgb, var(--t-accent) 25%, transparent)',
                    color: 'var(--t-accent)',
                }"
            >
                LIVE
            </span>
            <span v-if="!latestEvent" class="text-xs text-t-text-faint">
                Awaiting events...
            </span>
            <div
                v-else
                class="flex min-w-0 items-center gap-2 rounded-lg border border-t-border bg-t-surface px-2.5 py-1.5 shadow-[0_1px_3px_rgba(0,0,0,0.04)]"
                :style="{
                    borderLeftWidth: '3px',
                    borderLeftColor: borderColor,
                }"
            >
                <PriBadge :p="priorityNumber" size="sm" />
                <ChBadge :ch="channelKey" small />
                <span class="truncate text-[12px] font-semibold text-t-text">
                    {{ latestEvent.incident_type ?? 'Unclassified' }}
                </span>
                <span class="shrink-0 font-mono text-[10px] text-t-text-faint">
                    {{ latestEvent.incident_no }}
                </span>
                <div
                    v-if="latestEvent.location_text"
                    class="flex shrink-0 items-center gap-0.5"
                >
                    <IntakeIconPin :size="10" color="var(--t-text-dim)" />
                    <span
                        class="max-w-[140px] truncate text-[10px] text-t-text-dim"
                    >
                        {{ latestEvent.location_text }}
                    </span>
                </div>
                <span class="shrink-0 font-mono text-[9px] text-t-text-faint">
                    {{ timeElapsed(latestEvent.created_at) }}
                </span>
            </div>
        </div>

        <!-- Clock -->
        <span class="mr-4 shrink-0 font-mono text-xs text-t-text-faint">
            {{ clock }}
        </span>

        <!-- User chip -->
        <UserChip :user="user" />
    </header>
</template>
