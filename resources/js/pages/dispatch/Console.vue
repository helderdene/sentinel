<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { computed, inject, ref, watch } from 'vue';
import { unassignUnit } from '@/actions/App/Http/Controllers/DispatchConsoleController';
import DispatchQueuePanel from '@/components/dispatch/DispatchQueuePanel.vue';
import IncidentDetailPanel from '@/components/dispatch/IncidentDetailPanel.vue';
import MapLegend from '@/components/dispatch/MapLegend.vue';
import UnitDetailPanel from '@/components/dispatch/UnitDetailPanel.vue';
import UnitStatusPanel from '@/components/dispatch/UnitStatusPanel.vue';
import { useAlertSystem } from '@/composables/useAlertSystem';
import { useDispatchFeed } from '@/composables/useDispatchFeed';
import { useDispatchMap } from '@/composables/useDispatchMap';
import { useDispatchSession } from '@/composables/useDispatchSession';
import DispatchLayout from '@/layouts/DispatchLayout.vue';
import type {
    DispatchAgency,
    DispatchIncident,
    DispatchMetrics,
    DispatchUnit,
} from '@/types/dispatch';
import type { TickerEvent } from '@/types/incident';

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

const alertSystem = useAlertSystem();

const dispatchStats = inject<{
    activeIncidents: Ref<number>;
    criticalIncidents: Ref<number>;
    totalIncidents: Ref<number>;
    averageHandleTime: Ref<number | null>;
    unitsAvailable: Ref<number>;
    unitsTotal: Ref<number>;
}>('dispatchStats');

const tickerEventsInjected = inject<Ref<TickerEvent[]>>('tickerEvents');

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

type RightPanelMode = 'unit-status' | 'incident-detail' | 'unit-detail';

const rightPanelMode = computed<RightPanelMode>(() => {
    if (selectedIncidentId.value) {
        return 'incident-detail';
    }

    if (selectedUnitId.value) {
        return 'unit-detail';
    }

    return 'unit-status';
});

const mapComposable = useDispatchMap('dispatch-map');

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
} = mapComposable;

const { tickerEvents } = useDispatchFeed(
    localIncidents,
    localUnits,
    mapComposable,
    alertSystem,
);

watch(
    tickerEvents,
    (events) => {
        if (tickerEventsInjected) {
            tickerEventsInjected.value = events;
        }
    },
    { deep: true, immediate: true },
);

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

function handleIncidentClose(): void {
    selectedIncidentId.value = null;
}

function handleUnitSelect(id: string): void {
    selectedUnitId.value = id;
    selectedIncidentId.value = null;

    const unit = localUnits.value.find((u) => u.id === id);

    if (unit) {
        flyToUnit(unit);
    }
}

function handleUnitBack(): void {
    selectedUnitId.value = null;
}

function handleAckExpired(): void {
    alertSystem.playAckExpiredTone();
}

async function handleUnassign(unitId: string): Promise<void> {
    if (!selectedIncidentId.value) {
        return;
    }

    const xsrfToken = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

    const response = await fetch(
        unassignUnit.url(selectedIncidentId.value),
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken,
            },
            body: JSON.stringify({ unit_id: unitId }),
        },
    );

    if (response.ok) {
        router.reload({ only: ['incidents', 'units'] });
    }
}

onIncidentClick((id: string) => {
    handleIncidentSelect(id);
});

onUnitClick((id: string) => {
    handleUnitSelect(id);
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
            class="z-10 w-[360px] shrink-0 border-l border-t-border bg-t-bg/95 backdrop-blur-sm dark:border-t-border dark:bg-[#0f172a]/95"
        >
            <IncidentDetailPanel
                v-if="rightPanelMode === 'incident-detail' && selectedIncident"
                :incident="selectedIncident"
                :agencies="props.agencies"
                @close="handleIncidentClose"
                @ack-expired="handleAckExpired"
                @unassign="handleUnassign"
            />
            <UnitDetailPanel
                v-else-if="rightPanelMode === 'unit-detail' && selectedUnit"
                :unit="selectedUnit"
                :incidents="localIncidents"
                @back="handleUnitBack"
            />
            <UnitStatusPanel
                v-else
                :units="localUnits"
                @select-unit="handleUnitSelect"
            />
        </div>
    </div>
</template>
