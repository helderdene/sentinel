<script setup lang="ts">
import { computed } from 'vue';
import type { DispatchUnit } from '@/types/dispatch';

const props = defineProps<{
    units: DispatchUnit[];
}>();

const emit = defineEmits<{
    'select-unit': [id: string];
}>();

interface UnitGroup {
    agency: string;
    units: DispatchUnit[];
}

const groupedUnits = computed<UnitGroup[]>(() => {
    const groups = new Map<string, DispatchUnit[]>();

    for (const unit of props.units) {
        const existing = groups.get(unit.agency);

        if (existing) {
            existing.push(unit);
        } else {
            groups.set(unit.agency, [unit]);
        }
    }

    return Array.from(groups.entries())
        .map(([agency, units]) => ({ agency, units }))
        .sort((a, b) => a.agency.localeCompare(b.agency));
});

const statusDotColors: Record<string, string> = {
    AVAILABLE: 'var(--t-unit-available)',
    DISPATCHED: 'var(--t-unit-dispatched)',
    EN_ROUTE: 'var(--t-unit-enroute)',
    ON_SCENE: 'var(--t-unit-onscene)',
    OFFLINE: 'var(--t-unit-offline)',
};

function dotColor(status: string): string {
    return statusDotColors[status] ?? 'var(--t-text-faint)';
}

function statusText(status: string): string {
    return status.replace(/_/g, ' ');
}

const totalUnits = computed(() => props.units.length);
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Header -->
        <div
            class="flex items-center gap-2 border-b border-t-border px-3 py-2.5"
        >
            <span
                class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
            >
                UNIT STATUS
            </span>
            <span
                class="flex size-5 items-center justify-center rounded-full bg-t-online/15 font-mono text-[10px] font-bold text-t-online"
            >
                {{ totalUnits }}
            </span>
        </div>

        <!-- Unit groups -->
        <div class="flex-1 overflow-y-auto">
            <div v-for="group in groupedUnits" :key="group.agency">
                <div
                    class="sticky top-0 border-b border-t-border bg-t-surface-alt/80 px-3 py-1.5 backdrop-blur-sm"
                >
                    <span
                        class="font-mono text-[9px] font-bold tracking-[1px] text-t-text-faint uppercase"
                    >
                        {{ group.agency }}
                    </span>
                </div>
                <button
                    v-for="unit in group.units"
                    :key="unit.id"
                    class="flex w-full items-center gap-2 border-b border-t-border/50 px-3 py-2 text-left transition-colors hover:bg-t-surface-alt/60"
                    @click="emit('select-unit', unit.id)"
                >
                    <span
                        class="size-2 shrink-0 rounded-full"
                        :style="{ backgroundColor: dotColor(unit.status) }"
                    />
                    <span class="font-mono text-[11px] font-bold text-t-text">
                        {{ unit.callsign }}
                    </span>
                    <span
                        class="font-mono text-[9px]"
                        :style="{ color: dotColor(unit.status) }"
                    >
                        {{ statusText(unit.status) }}
                    </span>
                    <span
                        class="ml-auto font-mono text-[9px] text-t-text-faint"
                    >
                        {{ unit.crew_capacity }}
                    </span>
                </button>
            </div>

            <div
                v-if="units.length === 0"
                class="px-3 py-6 text-center text-xs text-t-text-faint"
            >
                No units available
            </div>
        </div>
    </div>
</template>
