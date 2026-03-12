import { useEcho } from '@laravel/echo-vue';
import type { Ref } from 'vue';
import { computed, ref } from 'vue';
import { channelDisplayMap } from '@/components/intake/ChBadge.vue';
import type { ChannelKey } from '@/components/intake/ChBadge.vue';
import type {
    Incident,
    IncidentCreatedPayload,
    IncidentStatusChangedPayload,
} from '@/types/incident';

export type FeedFilter = 'all' | 'pending' | 'triaged';

const MAX_FEED_SIZE = 100;

export function useIntakeFeed(
    initialPending: Incident[],
    initialTriaged: Incident[],
) {
    const pendingIncidents = ref<Incident[]>([...initialPending]);
    const triagedIncidents = ref<Incident[]>([...initialTriaged]);
    const activeFilter = ref<FeedFilter>('all');
    const activeIncident = ref<Incident | null>(null);

    const pendingCount = computed(() => pendingIncidents.value.length);
    const triagedCount = computed(() => triagedIncidents.value.length);

    const feedIncidents = computed(() => {
        switch (activeFilter.value) {
            case 'pending':
                return pendingIncidents.value;

            case 'triaged':
                return triagedIncidents.value;

            default:
                return [...pendingIncidents.value, ...triagedIncidents.value];
        }
    });

    const channelCounts = computed(() => {
        const counts: Record<ChannelKey, number> = {
            SMS: 0,
            APP: 0,
            VOICE: 0,
            IOT: 0,
            WALKIN: 0,
        };

        for (const incident of pendingIncidents.value) {
            const key = channelDisplayMap[incident.channel];

            if (key) {
                counts[key]++;
            }
        }

        return counts;
    });

    function setFilter(filter: FeedFilter): void {
        activeFilter.value = filter;
    }

    function selectIncident(incident: Incident | null): void {
        activeIncident.value = incident;
    }

    function isTriaged(incident: Incident): boolean {
        return incident.status === 'TRIAGED';
    }

    useEcho<IncidentCreatedPayload>(
        'dispatch.incidents',
        'IncidentCreated',
        (e) => {
            if (e.status !== 'PENDING') {
                return;
            }

            if (pendingIncidents.value.some((inc) => inc.id === e.id)) {
                return;
            }

            const newIncident: Incident = {
                id: e.id,
                incident_no: e.incident_no,
                incident_type_id: 0,
                incident_type: {
                    id: 0,
                    category: '',
                    name: e.incident_type ?? '',
                    code: '',
                    default_priority: e.priority,
                    is_active: true,
                },
                priority: e.priority,
                status: e.status,
                channel: e.channel,
                location_text: e.location_text,
                coordinates: null,
                barangay_id: null,
                barangay: e.barangay ? { id: 0, name: e.barangay } : null,
                caller_name: null,
                caller_contact: null,
                raw_message: null,
                notes: null,
                assigned_unit: null,
                dispatched_at: null,
                acknowledged_at: null,
                en_route_at: null,
                on_scene_at: null,
                resolved_at: null,
                outcome: null,
                hospital: null,
                scene_time_sec: null,
                checklist_pct: null,
                vitals: null,
                assessment_tags: null,
                closure_notes: null,
                report_pdf_url: null,
                created_by: null,
                created_at: e.created_at,
                updated_at: e.created_at,
            };

            pendingIncidents.value.unshift(newIncident);

            if (pendingIncidents.value.length > MAX_FEED_SIZE) {
                pendingIncidents.value.pop();
            }
        },
    );

    useEcho<IncidentStatusChangedPayload>(
        'dispatch.incidents',
        'IncidentStatusChanged',
        (e) => {
            if (e.new_status === 'TRIAGED') {
                const index = pendingIncidents.value.findIndex(
                    (inc) => inc.id === e.id,
                );

                if (index !== -1) {
                    const [moved] = pendingIncidents.value.splice(index, 1);
                    moved.status = 'TRIAGED';
                    triagedIncidents.value.unshift(moved);

                    if (
                        activeIncident.value &&
                        activeIncident.value.id === e.id
                    ) {
                        activeIncident.value = null;
                    }
                }
            }
        },
    );

    return {
        feedIncidents,
        pendingIncidents: pendingIncidents as Ref<Incident[]>,
        triagedIncidents: triagedIncidents as Ref<Incident[]>,
        pendingCount,
        triagedCount,
        activeFilter,
        activeIncident,
        channelCounts,
        setFilter,
        selectIncident,
        isTriaged,
    };
}
