import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import type { AnalyticsFilters } from '@/types/analytics';

interface PageFilters {
    start_date?: string;
    end_date?: string;
    incident_type_id?: number;
    priority?: string;
    barangay_id?: number;
}

export function useAnalyticsFilters() {
    const page = usePage<{ filters: PageFilters }>();

    const preset = ref<string | null>('30d');
    const startDate = ref<string | null>(null);
    const endDate = ref<string | null>(null);
    const incidentTypeId = ref<number | null>(null);
    const priority = ref<string | null>(null);
    const barangayId = ref<number | null>(null);

    // Initialize from URL query params
    const url = new URL(window.location.href);
    const urlPreset = url.searchParams.get('preset');
    const urlStartDate = url.searchParams.get('start_date');
    const urlEndDate = url.searchParams.get('end_date');
    const urlTypeId = url.searchParams.get('incident_type_id');
    const urlPriority = url.searchParams.get('priority');
    const urlBarangayId = url.searchParams.get('barangay_id');

    if (urlPreset) {
        preset.value = urlPreset;
    } else if (urlStartDate && urlEndDate) {
        preset.value = null;
        startDate.value = urlStartDate;
        endDate.value = urlEndDate;
    }

    if (urlTypeId) {
        incidentTypeId.value = parseInt(urlTypeId, 10);
    }

    if (urlPriority) {
        priority.value = urlPriority;
    }

    if (urlBarangayId) {
        barangayId.value = parseInt(urlBarangayId, 10);
    }

    // Sync filters from Inertia page props on navigation
    if (page.props.filters) {
        const f = page.props.filters;

        if (f.incident_type_id && !urlTypeId) {
            incidentTypeId.value = f.incident_type_id;
        }

        if (f.priority && !urlPriority) {
            priority.value = f.priority;
        }

        if (f.barangay_id && !urlBarangayId) {
            barangayId.value = f.barangay_id;
        }
    }

    const queryObject = computed<Partial<AnalyticsFilters>>(() => {
        const q: Record<string, string | number> = {};

        if (preset.value) {
            q.preset = preset.value;
        } else {
            if (startDate.value) {
                q.start_date = startDate.value;
            }

            if (endDate.value) {
                q.end_date = endDate.value;
            }
        }

        if (incidentTypeId.value) {
            q.incident_type_id = incidentTypeId.value;
        }

        if (priority.value) {
            q.priority = priority.value;
        }

        if (barangayId.value) {
            q.barangay_id = barangayId.value;
        }

        return q;
    });

    function navigate(): void {
        const currentPath = window.location.pathname;

        router.get(
            currentPath,
            queryObject.value as Record<string, string | number>,
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function applyPreset(newPreset: string): void {
        preset.value = newPreset;
        startDate.value = null;
        endDate.value = null;
        navigate();
    }

    function applyCustomDates(start: string, end: string): void {
        preset.value = null;
        startDate.value = start;
        endDate.value = end;
        navigate();
    }

    function setIncidentType(id: number | null): void {
        incidentTypeId.value = id;
        navigate();
    }

    function setPriority(p: string | null): void {
        priority.value = p;
        navigate();
    }

    function setBarangay(id: number | null): void {
        barangayId.value = id;
        navigate();
    }

    function clearFilters(): void {
        preset.value = '30d';
        startDate.value = null;
        endDate.value = null;
        incidentTypeId.value = null;
        priority.value = null;
        barangayId.value = null;
        navigate();
    }

    return {
        preset,
        startDate,
        endDate,
        incidentTypeId,
        priority,
        barangayId,
        queryObject,
        navigate,
        applyPreset,
        applyCustomDates,
        setIncidentType,
        setPriority,
        setBarangay,
        clearFilters,
    };
}
