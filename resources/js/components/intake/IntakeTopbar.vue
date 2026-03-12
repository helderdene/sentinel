<script setup lang="ts">
import type { Ref } from 'vue';
import { inject, onMounted, onUnmounted, ref } from 'vue';
import IntakeIconIntake from '@/components/intake/icons/IntakeIconIntake.vue';
import UserChip from '@/components/intake/UserChip.vue';
import type { User } from '@/types/auth';

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

const tickerEvents = inject<Ref<string[]>>(
    'tickerEvents',
    ref<string[]>([]),
);

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
                <span class="text-t-brand">IRMS</span>
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
            <span class="truncate text-xs text-t-text-faint">
                {{
                    tickerEvents.length > 0
                        ? tickerEvents[0]
                        : 'Awaiting events...'
                }}
            </span>
        </div>

        <!-- Clock -->
        <span class="mr-4 shrink-0 font-mono text-xs text-t-text-faint">
            {{ clock }}
        </span>

        <!-- User chip -->
        <UserChip :user="user" />
    </header>
</template>
