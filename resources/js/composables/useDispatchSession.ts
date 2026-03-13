import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type {
    DispatchIncident,
    DispatchMetrics,
    DispatchUnit,
} from '@/types/dispatch';
import type { IncidentStatus } from '@/types/incident';

interface ResolvedEntry {
    createdAt: number;
    resolvedAt: number;
}

const ACTIVE_STATUSES: IncidentStatus[] = [
    'DISPATCHED',
    'EN_ROUTE',
    'ON_SCENE',
];

export function useDispatchSession(
    incidents: Ref<DispatchIncident[]>,
    units: Ref<DispatchUnit[]>,
    initialAverageHandleTime: number | null,
) {
    const resolvedEntries = ref<ResolvedEntry[]>([]);
    const serverHandleTime = ref<number | null>(initialAverageHandleTime);

    const previousStatuses = new Map<string, IncidentStatus>();

    watch(
        incidents,
        (current) => {
            for (const incident of current) {
                const prev = previousStatuses.get(incident.id);

                if (
                    prev &&
                    prev !== 'RESOLVED' &&
                    incident.status === 'RESOLVED'
                ) {
                    resolvedEntries.value.push({
                        createdAt: new Date(incident.created_at).getTime(),
                        resolvedAt: Date.now(),
                    });
                }

                previousStatuses.set(incident.id, incident.status);
            }
        },
        { deep: true },
    );

    watch(
        incidents,
        (current) => {
            for (const incident of current) {
                if (!previousStatuses.has(incident.id)) {
                    previousStatuses.set(incident.id, incident.status);
                }
            }
        },
        { immediate: true },
    );

    const activeIncidents: ComputedRef<number> = computed(
        () =>
            incidents.value.filter((i) => ACTIVE_STATUSES.includes(i.status))
                .length,
    );

    const criticalIncidents: ComputedRef<number> = computed(
        () =>
            incidents.value.filter(
                (i) =>
                    i.priority === 'P1' && ACTIVE_STATUSES.includes(i.status),
            ).length,
    );

    const totalIncidents: ComputedRef<number> = computed(
        () => incidents.value.filter((i) => i.status !== 'RESOLVED').length,
    );

    const unitsAvailable: ComputedRef<number> = computed(
        () => units.value.filter((u) => u.status === 'AVAILABLE').length,
    );

    const unitsTotal: ComputedRef<number> = computed(
        () => units.value.filter((u) => u.status !== 'OFFLINE').length,
    );

    const averageHandleTime: ComputedRef<number | null> = computed(() => {
        if (resolvedEntries.value.length > 0) {
            const totalMs = resolvedEntries.value.reduce(
                (sum, entry) => sum + (entry.resolvedAt - entry.createdAt),
                0,
            );

            return totalMs / resolvedEntries.value.length / 60000;
        }

        return serverHandleTime.value;
    });

    const metrics: ComputedRef<DispatchMetrics> = computed(() => ({
        activeIncidents: activeIncidents.value,
        criticalIncidents: criticalIncidents.value,
        totalIncidents: totalIncidents.value,
        unitsAvailable: unitsAvailable.value,
        unitsTotal: unitsTotal.value,
        averageHandleTime: averageHandleTime.value,
    }));

    return {
        metrics,
        activeIncidents,
        criticalIncidents,
        totalIncidents,
        unitsAvailable,
        unitsTotal,
        averageHandleTime,
    };
}
