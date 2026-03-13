<script setup lang="ts">
import type { Ref } from 'vue';
import { computed, inject, ref, watch } from 'vue';
import MapLegend from '@/components/dispatch/MapLegend.vue';
import { useDispatchMap } from '@/composables/useDispatchMap';
import DispatchLayout from '@/layouts/DispatchLayout.vue';
import type {
    DispatchAgency,
    DispatchIncident,
    DispatchMetrics,
    DispatchUnit,
} from '@/types/dispatch';

defineOptions({
    layout: DispatchLayout,
});

const props = defineProps<{
    incidents: DispatchIncident[];
    units: DispatchUnit[];
    agencies: DispatchAgency[];
    metrics: DispatchMetrics;
}>();

const dispatchStats = inject<{
    activeIncidents: Ref<number>;
    criticalIncidents: Ref<number>;
    totalIncidents: Ref<number>;
    averageHandleTime: Ref<number | null>;
    unitsAvailable: Ref<number>;
    unitsTotal: Ref<number>;
}>('dispatchStats');

if (dispatchStats) {
    dispatchStats.activeIncidents.value = props.metrics.activeIncidents;
    dispatchStats.criticalIncidents.value = props.metrics.criticalIncidents;
    dispatchStats.totalIncidents.value = props.metrics.totalIncidents;
    dispatchStats.averageHandleTime.value = props.metrics.averageHandleTime;
    dispatchStats.unitsAvailable.value = props.metrics.unitsAvailable;
    dispatchStats.unitsTotal.value = props.metrics.unitsTotal;
}

const selectedIncidentId = ref<string | null>(null);
const selectedUnitId = ref<string | null>(null);

const {
    isLoaded,
    setIncidentData,
    setUnitData,
    updateConnectionLines,
    flyToIncident,
    flyToUnit,
    onIncidentClick,
    onUnitClick,
    onDeselect,
} = useDispatchMap('dispatch-map');

const selectedIncident = computed(() =>
    selectedIncidentId.value
        ? (props.incidents.find((i) => i.id === selectedIncidentId.value) ??
          null)
        : null,
);

const selectedUnit = computed(() =>
    selectedUnitId.value
        ? (props.units.find((u) => u.id === selectedUnitId.value) ?? null)
        : null,
);

function buildConnectionLines(): void {
    const assignments: Array<{
        incident: DispatchIncident;
        unit: DispatchUnit;
    }> = [];

    for (const incident of props.incidents) {
        if (!incident.assigned_units) {
            continue;
        }

        for (const au of incident.assigned_units) {
            const unit = props.units.find((u) => u.id === au.unit_id);

            if (unit) {
                assignments.push({ incident, unit });
            }
        }
    }

    updateConnectionLines(assignments);
}

watch(isLoaded, (loaded) => {
    if (loaded) {
        setIncidentData(props.incidents);
        setUnitData(props.units);
        buildConnectionLines();
    }
});

onIncidentClick((id: string) => {
    selectedIncidentId.value = id;
    selectedUnitId.value = null;

    const incident = props.incidents.find((i) => i.id === id);

    if (incident) {
        flyToIncident(incident);
    }
});

onUnitClick((id: string) => {
    selectedUnitId.value = id;
    selectedIncidentId.value = null;

    const unit = props.units.find((u) => u.id === id);

    if (unit) {
        flyToUnit(unit);
    }
});

onDeselect(() => {
    selectedIncidentId.value = null;
    selectedUnitId.value = null;
});
</script>

<template>
    <div class="flex h-full w-full">
        <!-- Left panel -->
        <div
            class="z-10 w-80 shrink-0 overflow-y-auto border-r border-t-border bg-t-bg/95 backdrop-blur-sm dark:border-t-border dark:bg-[#0f172a]/95"
        >
            <div class="p-3">
                <div
                    class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                >
                    QUEUE PANEL
                </div>
                <p class="mt-2 text-xs text-t-text-dim">
                    Incident queue will be implemented in Plan 03.
                </p>
            </div>
        </div>

        <!-- Center map area -->
        <div class="relative flex-1">
            <div id="dispatch-map" class="h-full w-full" />
            <MapLegend />
        </div>

        <!-- Right panel -->
        <div
            class="z-10 w-[360px] shrink-0 overflow-y-auto border-l border-t-border bg-t-bg/95 backdrop-blur-sm dark:border-t-border dark:bg-[#0f172a]/95"
        >
            <div class="p-3">
                <div
                    v-if="selectedIncident"
                    class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                >
                    INCIDENT DETAIL
                    <p class="mt-2 font-sans text-xs text-t-text normal-case">
                        {{ selectedIncident.incident_no }} --
                        {{ selectedIncident.priority }}
                    </p>
                    <p
                        class="mt-1 font-sans text-xs text-t-text-dim normal-case"
                    >
                        Detail panel will be implemented in Plan 03.
                    </p>
                </div>
                <div
                    v-else-if="selectedUnit"
                    class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                >
                    UNIT STATUS
                    <p class="mt-2 font-sans text-xs text-t-text normal-case">
                        {{ selectedUnit.callsign }} --
                        {{ selectedUnit.status }}
                    </p>
                    <p
                        class="mt-1 font-sans text-xs text-t-text-dim normal-case"
                    >
                        Unit detail panel will be implemented in Plan 03.
                    </p>
                </div>
                <div
                    v-else
                    class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                >
                    UNIT STATUS
                    <p
                        class="mt-2 font-sans text-xs text-t-text-dim normal-case"
                    >
                        Unit roster panel will be implemented in Plan 03.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
