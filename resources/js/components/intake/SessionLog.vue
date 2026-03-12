<script setup lang="ts">
import { ref } from 'vue';

import PriBadge from '@/components/intake/PriBadge.vue';
import type { IncidentPriority } from '@/types/incident';

function priorityNum(p: IncidentPriority): 1 | 2 | 3 | 4 {
    return parseInt(p.replace('P', '')) as 1 | 2 | 3 | 4;
}

export type SessionLogEntry = {
    timestamp: string;
    action: string;
    priority?: IncidentPriority;
};

const MAX_ENTRIES = 50;

const entries = ref<SessionLogEntry[]>([]);

function addEntry(entry: Omit<SessionLogEntry, 'timestamp'>): void {
    const now = new Date();
    const timestamp = now.toLocaleTimeString('en-US', {
        hour12: false,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });

    entries.value.unshift({
        ...entry,
        timestamp,
    });

    if (entries.value.length > MAX_ENTRIES) {
        entries.value.pop();
    }
}

defineExpose({ addEntry });
</script>

<template>
    <div class="border-t border-t-border px-3 py-2">
        <p
            class="mb-2 font-mono text-[9px] font-medium tracking-[2px] text-t-text-faint uppercase"
        >
            Session Log
        </p>

        <div class="max-h-[140px] space-y-1.5 overflow-y-auto">
            <div
                v-for="(entry, index) in entries"
                :key="index"
                class="flex items-start gap-2"
            >
                <span class="shrink-0 font-mono text-[9.5px] text-t-text-faint">
                    {{ entry.timestamp }}
                </span>
                <span class="text-[11px] text-t-text-mid">
                    {{ entry.action }}
                </span>
                <PriBadge
                    v-if="entry.priority"
                    :p="priorityNum(entry.priority)"
                    size="sm"
                    class="ml-auto shrink-0"
                />
            </div>

            <p
                v-if="entries.length === 0"
                class="py-2 text-center text-[10px] text-t-text-faint"
            >
                No activity yet
            </p>
        </div>
    </div>
</template>
