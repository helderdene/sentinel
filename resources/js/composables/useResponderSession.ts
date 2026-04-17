import { echo, useEcho } from '@laravel/echo-vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useAlertSystem } from '@/composables/useAlertSystem';
import type {
    AssignmentPayload,
    IncidentMessageItem,
    IncidentStatus,
    MessagePayload,
    ResponderIncident,
    ResponderTab,
    ResponderUnit,
} from '@/types/responder';

interface IncidentStatusChangedPayload {
    id: number;
    incident_no: string;
    old_status: IncidentStatus;
    new_status: IncidentStatus;
    priority: string;
    acknowledged_at: string | null;
    en_route_at: string | null;
    on_scene_at: string | null;
    resolving_at: string | null;
    resolved_at: string | null;
}

export function useResponderSession(
    initialIncident: ResponderIncident | null,
    initialUnit: ResponderUnit,
    initialMessages: IncidentMessageItem[],
    userId: number,
) {
    const activeIncident = ref<ResponderIncident | null>(initialIncident);
    const unit = ref<ResponderUnit>({ ...initialUnit });
    const messages = ref<IncidentMessageItem[]>([...initialMessages]);
    const unreadCount = ref(0);
    const activeTab = ref<ResponderTab>('assignment');
    const showAssignmentNotification = ref(false);
    const showOutcomeSheet = ref(false);
    const showClosureSummary = ref(false);

    const currentStatus = computed<IncidentStatus | null>(() =>
        activeIncident.value ? activeIncident.value.status : null,
    );

    const isOnScene = computed(
        () =>
            currentStatus.value === 'ON_SCENE' ||
            currentStatus.value === 'RESOLVING',
    );

    const middleTab = computed<'nav' | 'scene'>(() =>
        isOnScene.value ? 'scene' : 'nav',
    );

    const hasActiveIncident = computed(() => activeIncident.value !== null);

    const alertSystem = useAlertSystem();

    useEcho<AssignmentPayload>(`user.${userId}`, 'AssignmentPushed', (p) => {
        activeIncident.value = {
            id: p.id,
            incident_no: p.incident_no,
            priority: p.priority,
            status: p.status,
            incident_type: {
                id: 0,
                name: p.incident_type ?? 'Unknown',
                code: '',
                category: '',
            },
            location_text: p.location_text,
            barangay: p.barangay ? { id: 0, name: p.barangay } : null,
            coordinates: p.coordinates,
            notes: p.notes,
            caller_name: null,
            caller_contact: null,
            assigned_units: [],
            vitals: null,
            assessment_tags: [],
            checklist_data: null,
            checklist_pct: 0,
            outcome: null,
            hospital: null,
            timeline: [],
            acknowledged_at: null,
            en_route_at: null,
            on_scene_at: null,
            resolving_at: null,
            resolved_at: null,
        };

        showAssignmentNotification.value = true;
        messages.value = [];
        unreadCount.value = 0;
        alertSystem.playPriorityTone(p.priority);
    });

    let currentMessageChannelName: string | null = null;
    let currentIncidentChannelName: string | null = null;

    function applyStatusPayload(payload: IncidentStatusChangedPayload): void {
        if (!activeIncident.value || activeIncident.value.id !== payload.id) {
            return;
        }

        activeIncident.value = {
            ...activeIncident.value,
            status: payload.new_status,
            acknowledged_at:
                payload.acknowledged_at ??
                activeIncident.value.acknowledged_at,
            en_route_at:
                payload.en_route_at ?? activeIncident.value.en_route_at,
            on_scene_at:
                payload.on_scene_at ?? activeIncident.value.on_scene_at,
            resolving_at:
                payload.resolving_at ?? activeIncident.value.resolving_at,
            resolved_at:
                payload.resolved_at ?? activeIncident.value.resolved_at,
        };

        if (payload.new_status === 'RESOLVED') {
            showClosureSummary.value = true;
        }
    }

    function subscribeToIncidentChannels(incidentId: number): void {
        const messagesChannel = `incident.${incidentId}.messages`;
        currentMessageChannelName = messagesChannel;

        echo()
            .private(messagesChannel)
            .listen('MessageSent', (m: MessagePayload) => {
                messages.value.push({
                    id: m.id,
                    body: m.body,
                    is_quick_reply: m.is_quick_reply,
                    sender: {
                        id: m.sender_id,
                        name: m.sender_name,
                        role: m.sender_role,
                        unit_callsign: m.sender_unit_callsign,
                    },
                    sender_type: 'user',
                    sender_id: m.sender_id,
                    created_at: m.sent_at,
                });

                if (m.sender_id !== userId && activeTab.value !== 'chat') {
                    unreadCount.value++;
                }
            });

        const incidentChannel = `incident.${incidentId}`;
        currentIncidentChannelName = incidentChannel;

        echo()
            .private(incidentChannel)
            .listen(
                'IncidentStatusChanged',
                (payload: IncidentStatusChangedPayload) => {
                    applyStatusPayload(payload);
                },
            );
    }

    function unsubscribeFromIncidentChannels(): void {
        if (currentMessageChannelName) {
            echo().leaveChannel(`private-${currentMessageChannelName}`);
            currentMessageChannelName = null;
        }

        if (currentIncidentChannelName) {
            echo().leaveChannel(`private-${currentIncidentChannelName}`);
            currentIncidentChannelName = null;
        }
    }

    if (activeIncident.value) {
        subscribeToIncidentChannels(activeIncident.value.id);
    }

    watch(
        () => activeIncident.value?.id,
        (newId, oldId) => {
            if (oldId !== undefined && oldId !== null) {
                unsubscribeFromIncidentChannels();
            }

            if (newId !== undefined && newId !== null) {
                subscribeToIncidentChannels(newId);
            }
        },
    );

    onUnmounted(() => {
        unsubscribeFromIncidentChannels();
    });

    function acknowledgeAssignment(): void {
        showAssignmentNotification.value = false;

        if (activeIncident.value) {
            activeIncident.value = {
                ...activeIncident.value,
                status: 'ACKNOWLEDGED',
                acknowledged_at: new Date().toISOString(),
            };
        }

        activeTab.value = 'nav';
    }

    function advanceStatus(nextStatus: IncidentStatus): void {
        if (!activeIncident.value) {
            return;
        }

        const now = new Date().toISOString();
        const updates: Partial<ResponderIncident> = { status: nextStatus };

        switch (nextStatus) {
            case 'ACKNOWLEDGED':
                updates.acknowledged_at = now;
                break;
            case 'EN_ROUTE':
                updates.en_route_at = now;
                break;
            case 'ON_SCENE':
                updates.on_scene_at = now;
                break;
            case 'RESOLVING':
                updates.resolving_at = now;
                break;
            case 'RESOLVED':
                updates.resolved_at = now;
                break;
        }

        activeIncident.value = { ...activeIncident.value, ...updates };
    }

    function setActiveTab(tab: ResponderTab): void {
        activeTab.value = tab;

        if (tab === 'chat') {
            unreadCount.value = 0;
        }
    }

    function markMessagesRead(): void {
        unreadCount.value = 0;
    }

    function resetAfterClosure(): void {
        activeIncident.value = null;
        messages.value = [];
        unreadCount.value = 0;
        activeTab.value = 'assignment';
        showAssignmentNotification.value = false;
        showOutcomeSheet.value = false;
        showClosureSummary.value = false;
    }

    return {
        activeIncident,
        unit,
        messages,
        unreadCount,
        activeTab,
        showAssignmentNotification,
        showOutcomeSheet,
        showClosureSummary,
        currentStatus,
        isOnScene,
        middleTab,
        hasActiveIncident,
        acknowledgeAssignment,
        advanceStatus,
        setActiveTab,
        markMessagesRead,
        resetAfterClosure,
    };
}
