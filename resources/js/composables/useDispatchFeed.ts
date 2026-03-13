import { useEcho } from '@laravel/echo-vue';
import type { Ref } from 'vue';
import { computed, ref } from 'vue';
import type { useAlertSystem } from '@/composables/useAlertSystem';
import type { useDispatchMap } from '@/composables/useDispatchMap';
import { useWebSocket } from '@/composables/useWebSocket';
import type {
    DispatchIncident,
    DispatchMessageItem,
    DispatchMessagePayload,
    DispatchUnit,
    MutualAidPayload,
    UnitLocationPayload,
    UnitStatusChangedPayload,
} from '@/types/dispatch';
import type {
    IncidentCreatedPayload,
    IncidentStatusChangedPayload,
    TickerEvent,
} from '@/types/incident';

const PRIORITY_ORDER: Record<string, number> = {
    P1: 0,
    P2: 1,
    P3: 2,
    P4: 3,
};

const MAX_TICKER_EVENTS = 20;

export function useDispatchFeed(
    localIncidents: Ref<DispatchIncident[]>,
    localUnits: Ref<DispatchUnit[]>,
    mapRef: ReturnType<typeof useDispatchMap>,
    alertSystem: ReturnType<typeof useAlertSystem>,
    currentUserId: number,
    selectedIncidentId: Ref<string | null>,
) {
    const tickerEvents = ref<TickerEvent[]>([]);
    const unreadByIncident = ref(new Map<string, number>());
    const messagesByIncident = ref(new Map<string, DispatchMessageItem[]>());

    const totalUnreadMessages = computed(() => {
        let total = 0;

        for (const count of unreadByIncident.value.values()) {
            total += count;
        }

        return total;
    });

    const { onStateSync } = useWebSocket();

    function addTickerEvent(event: TickerEvent): void {
        tickerEvents.value.unshift(event);

        if (tickerEvents.value.length > MAX_TICKER_EVENTS) {
            tickerEvents.value.pop();
        }
    }

    function refreshMapIncidents(): void {
        mapRef.setIncidentData(localIncidents.value);
    }

    function refreshMapUnits(): void {
        mapRef.setUnitData(localUnits.value);
    }

    function rebuildConnectionLines(): void {
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

        mapRef.updateConnectionLines(assignments);
    }

    function clearUnread(incidentId: string): void {
        const updated = new Map(unreadByIncident.value);
        updated.delete(incidentId);
        unreadByIncident.value = updated;
    }

    function getMessages(incidentId: string): DispatchMessageItem[] {
        return messagesByIncident.value.get(incidentId) ?? [];
    }

    function addLocalMessage(
        incidentId: string,
        message: DispatchMessageItem,
    ): void {
        const updated = new Map(messagesByIncident.value);
        const existing = updated.get(incidentId) ?? [];
        updated.set(incidentId, [...existing, message]);
        messagesByIncident.value = updated;
    }

    // --- dispatch.incidents channel ---

    useEcho<IncidentCreatedPayload>(
        'dispatch.incidents',
        'IncidentCreated',
        (e) => {
            if (localIncidents.value.some((inc) => inc.id === e.id)) {
                return;
            }

            const newIncident: DispatchIncident = {
                id: e.id,
                incident_no: e.incident_no,
                incident_type_id: 0,
                incident_type: {
                    id: 0,
                    category: '',
                    name: e.incident_type ?? 'Unclassified',
                    code: '',
                    default_priority: e.priority,
                    is_active: true,
                },
                priority: e.priority,
                status: e.status,
                channel: e.channel,
                location_text: e.location_text,
                coordinates: e.coordinates ?? null,
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
                assigned_units: [],
            };

            const insertIndex = localIncidents.value.findIndex(
                (inc) =>
                    (PRIORITY_ORDER[inc.priority] ?? 4) >
                    (PRIORITY_ORDER[e.priority] ?? 4),
            );

            if (insertIndex === -1) {
                localIncidents.value.push(newIncident);
            } else {
                localIncidents.value.splice(insertIndex, 0, newIncident);
            }

            refreshMapIncidents();

            alertSystem.playPriorityTone(e.priority);

            if (e.priority === 'P1') {
                alertSystem.triggerP1Flash();
            }

            addTickerEvent({
                incident_no: e.incident_no,
                priority: e.priority,
                channel: e.channel,
                incident_type: e.incident_type,
                location_text: e.location_text,
                created_at: e.created_at,
            });
        },
    );

    useEcho<IncidentStatusChangedPayload>(
        'dispatch.incidents',
        'IncidentStatusChanged',
        (e) => {
            const index = localIncidents.value.findIndex(
                (inc) => inc.id === e.id,
            );

            if (index === -1) {
                return;
            }

            if (e.new_status === 'RESOLVED') {
                localIncidents.value.splice(index, 1);

                const updatedUnread = new Map(unreadByIncident.value);
                updatedUnread.delete(e.id);
                unreadByIncident.value = updatedUnread;

                const updatedMessages = new Map(messagesByIncident.value);
                updatedMessages.delete(e.id);
                messagesByIncident.value = updatedMessages;
            } else {
                localIncidents.value[index].status = e.new_status;
            }

            refreshMapIncidents();
            rebuildConnectionLines();

            addTickerEvent({
                incident_no: e.incident_no,
                priority: e.priority,
                channel: 'radio',
                incident_type: null,
                location_text: `Status: ${e.old_status} -> ${e.new_status}`,
                created_at: new Date().toISOString(),
            });
        },
    );

    useEcho<MutualAidPayload>(
        'dispatch.incidents',
        'MutualAidRequested',
        (e) => {
            addTickerEvent({
                incident_no: e.incident_no,
                priority: 'P1',
                channel: 'radio',
                incident_type: `Mutual Aid: ${e.agency.name}`,
                location_text: e.notes ?? '',
                created_at: e.timestamp,
            });
        },
    );

    useEcho<DispatchMessagePayload>(
        'dispatch.incidents',
        'MessageSent',
        (m) => {
            if (m.sender_id === currentUserId) {
                return;
            }

            const messageItem: DispatchMessageItem = {
                id: m.id,
                body: m.body,
                is_quick_reply: m.is_quick_reply,
                sender_id: m.sender_id,
                sender_name: m.sender_name,
                sender_role: m.sender_role,
                sender_unit_callsign: m.sender_unit_callsign,
                sent_at: m.sent_at,
            };

            const updatedMessages = new Map(messagesByIncident.value);
            const existing = updatedMessages.get(m.incident_id) ?? [];
            updatedMessages.set(m.incident_id, [...existing, messageItem]);
            messagesByIncident.value = updatedMessages;

            if (m.incident_id !== selectedIncidentId.value) {
                const updatedUnread = new Map(unreadByIncident.value);
                const currentCount = updatedUnread.get(m.incident_id) ?? 0;
                updatedUnread.set(m.incident_id, currentCount + 1);
                unreadByIncident.value = updatedUnread;

                alertSystem.playMessageTone();
            }
        },
    );

    // --- dispatch.units channel ---

    useEcho<UnitLocationPayload>(
        'dispatch.units',
        'UnitLocationUpdated',
        (e) => {
            const unit = localUnits.value.find((u) => u.id === e.id);

            if (unit) {
                mapRef.animateUnitTo(e.id, e.longitude, e.latitude);
                unit.coordinates = {
                    lat: e.latitude,
                    lng: e.longitude,
                };
                rebuildConnectionLines();
            }
        },
    );

    useEcho<UnitStatusChangedPayload>(
        'dispatch.units',
        'UnitStatusChanged',
        (e) => {
            const unit = localUnits.value.find((u) => u.id === e.id);

            if (unit) {
                unit.status = e.new_status;
                refreshMapUnits();
            }
        },
    );

    // --- State sync on reconnect ---

    onStateSync((data) => {
        const freshIncidents = data.incidents.map(
            (inc): DispatchIncident => ({
                id: inc.id,
                incident_no: inc.incident_no,
                incident_type_id: inc.incident_type?.id ?? 0,
                incident_type: inc.incident_type ?? {
                    id: 0,
                    category: '',
                    name: 'Unclassified',
                    code: '',
                    default_priority: inc.priority,
                    is_active: true,
                },
                priority: inc.priority,
                status: inc.status,
                channel: inc.channel,
                location_text: inc.location_text,
                coordinates: null,
                barangay_id: inc.barangay?.id ?? null,
                barangay: inc.barangay ?? null,
                caller_name: inc.caller_name,
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
                created_at: inc.created_at,
                updated_at: inc.created_at,
                assigned_units: [],
            }),
        );

        localIncidents.value = freshIncidents;

        if (data.units) {
            const freshUnits = data.units.map(
                (u): DispatchUnit => ({
                    id: u.id,
                    callsign: u.callsign,
                    type: u.type,
                    agency: '',
                    crew_capacity: 0,
                    status: u.status as DispatchUnit['status'],
                    coordinates: u.coordinates,
                    active_incident_id: null,
                }),
            );

            localUnits.value = freshUnits;
        }

        unreadByIncident.value = new Map();
        messagesByIncident.value = new Map();

        refreshMapIncidents();
        refreshMapUnits();
        rebuildConnectionLines();
    });

    return {
        tickerEvents,
        unreadByIncident,
        totalUnreadMessages,
        messagesByIncident,
        clearUnread,
        getMessages,
        addLocalMessage,
    };
}
