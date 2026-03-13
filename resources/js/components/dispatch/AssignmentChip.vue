<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { assignUnit } from '@/actions/App/Http/Controllers/DispatchConsoleController';
import type { NearbyUnit } from '@/types/dispatch';

const props = defineProps<{
    unit: NearbyUnit;
    incidentId: string;
}>();

const isAssigning = ref(false);

async function handleAssign(): Promise<void> {
    if (isAssigning.value) {
        return;
    }

    isAssigning.value = true;

    try {
        const xsrfToken = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((row) => row.startsWith('XSRF-TOKEN='))
                ?.split('=')[1] ?? '',
        );

        const response = await fetch(assignUnit.url(props.incidentId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken,
            },
            body: JSON.stringify({ unit_id: props.unit.id }),
        });

        if (response.ok) {
            router.reload({ only: ['incidents', 'units'] });
        }
    } finally {
        isAssigning.value = false;
    }
}

const typeIcons: Record<string, string> = {
    ambulance: 'AMB',
    fire_truck: 'FT',
    patrol_car: 'PC',
    rescue: 'RSC',
};

function unitTypeLabel(type: string): string {
    return typeIcons[type] ?? type.substring(0, 3).toUpperCase();
}
</script>

<template>
    <button
        class="flex w-full items-center gap-2 rounded border border-t-border bg-t-surface px-2.5 py-2 text-left transition-colors hover:border-t-accent/40 hover:bg-t-accent/5 disabled:opacity-50"
        :disabled="isAssigning"
        @click="handleAssign"
    >
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5">
                <span class="font-mono text-[11px] font-bold text-t-text">
                    {{ unit.callsign }}
                </span>
                <span
                    class="rounded bg-t-surface-alt px-1 py-[1px] font-mono text-[8px] font-bold text-t-text-dim"
                >
                    {{ unitTypeLabel(unit.type) }}
                </span>
            </div>
            <div class="mt-0.5 text-[9px] text-t-text-faint">
                {{ unit.agency }}
            </div>
        </div>
        <div class="flex shrink-0 flex-col items-end gap-0.5">
            <span class="font-mono text-[10px] font-bold text-t-accent">
                {{ unit.distance_km.toFixed(1) }} km
            </span>
            <span class="font-mono text-[9px] text-t-text-faint">
                ~{{ Math.round(unit.eta_minutes) }} min
            </span>
        </div>
        <div
            v-if="isAssigning"
            class="ml-1 size-3.5 animate-spin rounded-full border-2 border-t-accent border-r-transparent"
        />
    </button>
</template>
