<script setup lang="ts">
import { useEcho } from '@laravel/echo-vue';
import { Cpu, Globe, MessageSquare, Phone, Radio } from 'lucide-vue-next';
import type { Component } from 'vue';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { IncidentChannel, IncidentCreatedPayload } from '@/types/incident';

const props = withDefaults(
    defineProps<{
        channelCounts: Record<string, number>;
        realtime?: boolean;
    }>(),
    {
        realtime: false,
    },
);

const localCounts = ref<Record<string, number>>({
    ...props.channelCounts,
});

watch(
    () => props.channelCounts,
    (newCounts) => {
        localCounts.value = { ...newCounts };
    },
    { deep: true },
);

if (props.realtime) {
    useEcho<IncidentCreatedPayload>(
        'dispatch.incidents',
        'IncidentCreated',
        (e) => {
            localCounts.value[e.channel] =
                (localCounts.value[e.channel] ?? 0) + 1;
        },
    );
}

type ChannelConfig = {
    key: IncidentChannel;
    label: string;
    icon: Component;
};

const channels: ChannelConfig[] = [
    { key: 'phone', label: 'Phone', icon: Phone },
    { key: 'sms', label: 'SMS', icon: MessageSquare },
    { key: 'app', label: 'App', icon: Globe },
    { key: 'iot', label: 'IoT', icon: Cpu },
    { key: 'radio', label: 'Radio', icon: Radio },
];

function getCount(counts: Record<string, number>, key: string): number {
    return counts[key] ?? 0;
}
</script>

<template>
    <div>
        <h3
            class="mb-4 text-sm font-medium text-neutral-500 dark:text-neutral-400"
        >
            Channel Monitor
        </h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <Card
                v-for="ch in channels"
                :key="ch.key"
                :class="[
                    'flex flex-col items-center justify-center py-4',
                    getCount(localCounts, ch.key) === 0 ? 'opacity-50' : '',
                ]"
            >
                <CardHeader class="p-0 pb-2">
                    <component
                        :is="ch.icon"
                        class="size-6 text-neutral-400 dark:text-neutral-500"
                    />
                </CardHeader>
                <CardContent class="flex flex-col items-center gap-1 p-0">
                    <CardTitle class="text-xs font-medium">
                        {{ ch.label }}
                    </CardTitle>
                    <Badge
                        :variant="
                            getCount(localCounts, ch.key) > 0
                                ? 'default'
                                : 'secondary'
                        "
                    >
                        {{ getCount(localCounts, ch.key) }}
                    </Badge>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
