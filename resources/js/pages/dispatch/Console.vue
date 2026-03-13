<script setup lang="ts">
import type { Ref } from 'vue';
import { computed, inject, ref, watch } from 'vue';
import DispatchQueuePanel from '@/components/dispatch/DispatchQueuePanel.vue';
import MapLegend from '@/components/dispatch/MapLegend.vue';
import { useDispatchMap } from '@/composables/useDispatchMap';
import { useDispatchSession } from '@/composables/useDispatchSession';
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

const localIncidents = ref<DispatchIncident[]>([...props.incidents]);
const localUnits = ref<DispatchUnit[]>([...props.units]);

watch(
    () => props.incidents,
    (val) => {
        localIncidents.value = [...val];
    },
);

watch(
    () => props.units,
    (val) => {
        localUnits.value = [...val];
    },
);

const { metrics: sessionMetrics } = useDispatchSession(
    localIncidents,
    localUnits,
    props.metrics.averageHandleTime,
);

const dispatchStats = inject<{
    activeIncidents: Ref<number>;
    criticalIncidents: Ref<number>;
    totalIncidents: Ref<number>;
    averageHandleTime: Ref<number | null>;
    unitsAvailable: Ref<number>;
    unitsTotal: Ref<number>;
}>('dispatchStats');

watch(
    sessionMetrics,
    (m) => {
        if (dispatchStats) {
            dispatchStats.activeIncidents.value = m.activeIncidents;
            dispatchStats.criticalIncidents.value = m.criticalIncidents;
            dispatchStats.totalIncidents.value = m.totalIncidents;
            dispatchStats.averageHandleTime.value = m.averageHandleTime;
            dispatchStats.unitsAvailable.value = m.unitsAvailable;
            dispatchStats.unitsTotal.value = m.unitsTotal;
        }
    },
    { immediate: true },
);

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
        ? (localIncidents.value.find(
              (i) => i.id === selectedIncidentId.value,
          ) ?? null)
        : null,
);

const selectedUnit = computed(() =>
    selectedUnitId.value
        ? (localUnits.value.find((u) => u.id === selectedUnitId.value) ?? null)
        : null,
);

function buildConnectionLines(): void {
    const assignments: Array<{
        incident: DispatchIncident;
        unit: DispatchUnit;
    }> = [];

    for (const incident of localIncidents.value) {
        if (!incident.assigned_units) {
            continue;
        }

        for (const au of incident.assigned_units) {
            const unit = localUnits.value.find((u) => u.id === au.unit_id);

            if (unit) {
                assignments.push({ incident, unit });
            }
        }
    }

    updateConnectionLines(assignments);
}

watch(isLoaded, (loaded) => {
    if (loaded) {
        setIncidentData(localIncidents.value);
        setUnitData(localUnits.value);
        buildConnectionLines();
    }
});

function handleIncidentSelect(id: string): void {
    selectedIncidentId.value = id;
    selectedUnitId.value = null;

    const incident = localIncidents.value.find((i) => i.id === id);

    if (incident) {
        flyToIncident(incident);
    }
}

onIncidentClick((id: string) => {
    handleIncidentSelect(id);
});

onUnitClick((id: string) => {
    selectedUnitId.value = id;
    selectedIncidentId.value = null;

    const unit = localUnits.value.find((u) => u.id === id);

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
        <!-- Left panel: Incident Queue -->
        <div
            class="z-10 w-80 shrink-0 border-r border-t-border bg-t-bg/95 backdrop-blur-sm dark:border-t-border dark:bg-[#0f172a]/95"
        >
            <DispatchQueuePanel
                :incidents="localIncidents"
                :selected-incident-id="selectedIncidentId"
                @select-incident="handleIncidentSelect"
            />
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
                </div>
                <div
                    v-else-if="selectedUnit"
                    class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                >
                    UNIT DETAIL
                    <p class="mt-2 font-sans text-xs text-t-text normal-case">
                        {{ selectedUnit.callsign }} --
                        {{ selectedUnit.status }}
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
                        Right panels will be wired in Task 2.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
