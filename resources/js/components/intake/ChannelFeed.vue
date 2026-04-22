<script setup lang="ts">
import { computed } from 'vue';
import type { Component } from 'vue';
import type { ChannelKey } from '@/components/intake/ChBadge.vue';
import FeedCard from '@/components/intake/FeedCard.vue';
import IntakeIconApp from '@/components/intake/icons/IntakeIconApp.vue';
import IntakeIconFras from '@/components/intake/icons/IntakeIconFras.vue';
import IntakeIconIot from '@/components/intake/icons/IntakeIconIot.vue';
import IntakeIconSms from '@/components/intake/icons/IntakeIconSms.vue';
import IntakeIconVoice from '@/components/intake/icons/IntakeIconVoice.vue';
import IntakeIconWalkin from '@/components/intake/icons/IntakeIconWalkin.vue';
import type { FeedFilter } from '@/composables/useIntakeFeed';
import type { Incident } from '@/types/incident';

type Props = {
    feedIncidents: Incident[];
    activeIncident: Incident | null;
    channelCounts: Record<ChannelKey, number>;
    activeFilter: FeedFilter;
    pendingCount: number;
    triagedCount: number;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'select-incident': [incident: Incident];
    'manual-entry': [];
    'set-filter': [filter: FeedFilter];
}>();

interface ChannelRow {
    key: ChannelKey;
    label: string;
    icon: Component;
    color: string;
}

const channelRows: ChannelRow[] = [
    {
        key: 'VOICE',
        label: 'Voice',
        icon: IntakeIconVoice,
        color: 'var(--t-ch-voice)',
    },
    {
        key: 'SMS',
        label: 'SMS',
        icon: IntakeIconSms,
        color: 'var(--t-ch-sms)',
    },
    {
        key: 'APP',
        label: 'App',
        icon: IntakeIconApp,
        color: 'var(--t-ch-app)',
    },
    {
        key: 'IOT',
        label: 'IoT',
        icon: IntakeIconIot,
        color: 'var(--t-ch-iot)',
    },
    {
        key: 'WALKIN',
        label: 'Walk-in',
        icon: IntakeIconWalkin,
        color: 'var(--t-ch-walkin)',
    },
    {
        key: 'FRAS',
        label: 'FRAS',
        icon: IntakeIconFras,
        color: 'var(--t-ch-fras)',
    },
];

const totalChannelCount = computed(() => {
    let sum = 0;

    for (const row of channelRows) {
        sum += props.channelCounts[row.key] ?? 0;
    }

    return sum || 1;
});

function barWidth(key: ChannelKey): string {
    const count = props.channelCounts[key] ?? 0;

    return `${Math.max((count / totalChannelCount.value) * 100, 2)}%`;
}

type FilterTab = {
    key: FeedFilter;
    label: string;
    count: number;
};

const filterTabs = computed<FilterTab[]>(() => [
    {
        key: 'all',
        label: 'All',
        count: props.pendingCount + props.triagedCount,
    },
    { key: 'pending', label: 'Pending', count: props.pendingCount },
    { key: 'triaged', label: 'Triaged', count: props.triagedCount },
]);

function isTriaged(incident: Incident): boolean {
    return incident.status === 'TRIAGED';
}
</script>

<template>
    <div
        class="flex w-[296px] shrink-0 flex-col border-r border-t-border bg-t-surface"
    >
        <!-- Channel Activity -->
        <div class="border-b border-t-border px-3 py-3">
            <span
                class="mb-2 block font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
            >
                CHANNEL ACTIVITY
            </span>
            <div class="flex flex-col gap-1.5">
                <div
                    v-for="row in channelRows"
                    :key="row.key"
                    class="flex items-center gap-2"
                >
                    <div
                        class="flex size-[26px] shrink-0 items-center justify-center rounded"
                        :style="{
                            backgroundColor: `color-mix(in srgb, ${row.color} 10%, transparent)`,
                        }"
                    >
                        <component
                            :is="row.icon"
                            :size="13"
                            :color="row.color"
                        />
                    </div>
                    <span class="w-10 text-[11px] text-t-text-dim">
                        {{ row.label }}
                    </span>
                    <div
                        class="h-[6px] flex-1 overflow-hidden rounded-full bg-t-bg"
                    >
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            :style="{
                                width: barWidth(row.key),
                                backgroundColor: row.color,
                                opacity: 0.7,
                            }"
                        />
                    </div>
                    <span
                        class="w-6 text-right font-mono text-[10px] text-t-text-faint"
                    >
                        {{ channelCounts[row.key] ?? 0 }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="flex gap-1 border-b border-t-border px-3 py-2">
            <button
                v-for="tab in filterTabs"
                :key="tab.key"
                type="button"
                class="rounded-[5px] border px-2.5 py-1 text-[11px] transition-all"
                :class="
                    activeFilter === tab.key
                        ? 'border-t-accent font-semibold text-t-accent'
                        : 'border-transparent font-normal text-t-text-dim'
                "
                :style="
                    activeFilter === tab.key
                        ? {
                              backgroundColor:
                                  'color-mix(in srgb, var(--t-accent) 8%, transparent)',
                          }
                        : { backgroundColor: 'transparent' }
                "
                @click="emit('set-filter', tab.key)"
            >
                {{ tab.label }} ({{ tab.count }})
            </button>
        </div>

        <!-- Feed Cards (scrollable) -->
        <div class="flex-1 overflow-y-auto px-2 py-2">
            <TransitionGroup name="feed" tag="div" class="flex flex-col gap-2">
                <FeedCard
                    v-for="incident in feedIncidents"
                    :key="incident.id"
                    :incident="incident"
                    :active="
                        activeIncident !== null &&
                        activeIncident.id === incident.id
                    "
                    :triaged="isTriaged(incident)"
                    @select="emit('select-incident', $event)"
                />
            </TransitionGroup>

            <div
                v-if="feedIncidents.length === 0"
                class="flex flex-col items-center py-8 text-center"
            >
                <p class="text-xs text-t-text-faint">No incidents to show</p>
            </div>
        </div>

        <!-- Manual Entry Button -->
        <div class="border-t border-t-border px-3 py-2">
            <button
                type="button"
                class="w-full rounded-[7px] border border-t-border bg-t-surface px-3 py-2 text-[12px] font-medium text-t-text-mid transition-colors hover:bg-t-surface-alt"
                @click="emit('manual-entry')"
            >
                + Manual Entry
            </button>
        </div>
    </div>
</template>

<style scoped>
.feed-enter-active {
    animation: slideIn 0.3s ease-out;
}

.feed-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}

.feed-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

.feed-move {
    transition: transform 0.3s ease;
}
</style>
