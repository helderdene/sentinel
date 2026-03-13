<script setup lang="ts">
import { useIntervalFn } from '@vueuse/core';
import type { ComputedRef, Ref } from 'vue';
import { computed, inject, ref, watch } from 'vue';
import ChatTab from '@/components/responder/ChatTab.vue';
import MessageBanner from '@/components/responder/MessageBanner.vue';
import SceneTab from '@/components/responder/SceneTab.vue';
import StandbyScreen from '@/components/responder/StandbyScreen.vue';
import { useGpsTracking } from '@/composables/useGpsTracking';
import { useResponderSession } from '@/composables/useResponderSession';
import ResponderLayout from '@/layouts/ResponderLayout.vue';
import type {
    Hospital,
    IncidentMessageItem,
    IncidentStatus,
    ResponderIncident,
    ResponderTab,
    ResponderUnit,
} from '@/types/responder';

defineOptions({
    layout: ResponderLayout,
});

const props = defineProps<{
    incident: ResponderIncident | null;
    unit: ResponderUnit;
    messages: IncidentMessageItem[];
    hospitals: Hospital[];
    userId: number;
}>();

const session = useResponderSession(
    props.incident,
    props.unit,
    props.messages,
    props.userId,
);

const gps = useGpsTracking(props.unit.id, session.currentStatus);

watch(
    session.hasActiveIncident,
    (hasIncident) => {
        if (hasIncident) {
            gps.start();
        } else {
            gps.stop();
        }
    },
    { immediate: true },
);

const ACK_TIMEOUT_SECONDS = 90;
const ackRemainingSeconds = ref(0);
const ackStartedAt = ref<string | null>(null);

const isDispatchedStatus = computed(
    () => session.currentStatus.value === 'DISPATCHED',
);

const { pause: pauseAckTimer, resume: resumeAckTimer } = useIntervalFn(
    () => {
        if (!ackStartedAt.value || !isDispatchedStatus.value) {
            ackRemainingSeconds.value = 0;
            pauseAckTimer();

            return;
        }

        const elapsed = Math.floor(
            (Date.now() - new Date(ackStartedAt.value).getTime()) / 1000,
        );

        ackRemainingSeconds.value = Math.max(0, ACK_TIMEOUT_SECONDS - elapsed);
    },
    1000,
    { immediate: false },
);

watch(session.showAssignmentNotification, (showing) => {
    if (showing) {
        ackStartedAt.value = new Date().toISOString();
        ackRemainingSeconds.value = ACK_TIMEOUT_SECONDS;
        resumeAckTimer();
    } else {
        ackRemainingSeconds.value = 0;
        pauseAckTimer();
    }
});

const layoutUnit = inject<Ref<ResponderUnit>>('unit');
const layoutIncident = inject<Ref<ResponderIncident | null>>('incident');
const layoutActiveTab = inject<Ref<ResponderTab>>('activeTab');
const layoutMiddleTab = inject<Ref<'nav' | 'scene'>>('middleTab');
const layoutUnreadCount = inject<Ref<number>>('unreadCount');
const layoutCurrentStatus = inject<Ref<IncidentStatus | null>>('currentStatus');
const layoutAckTimerRemaining = inject<Ref<number>>('ackTimerRemaining');
const injectedConnectionStatus =
    inject<ComputedRef<string>>('connectionStatus');
const connectionStatusValue = computed(
    () => injectedConnectionStatus?.value ?? 'online',
);
const layoutOnAdvance = inject<Ref<(() => void) | null>>('onAdvance');
const layoutOnShowOutcomeSheet =
    inject<Ref<(() => void) | null>>('onShowOutcomeSheet');

if (layoutUnit) {
    layoutUnit.value = props.unit;
}

watch(
    session.unit,
    (u) => {
        if (layoutUnit) {
            layoutUnit.value = u;
        }
    },
    { deep: true },
);

watch(
    session.activeIncident,
    (inc) => {
        if (layoutIncident) {
            layoutIncident.value = inc;
        }
    },
    { immediate: true },
);

watch(
    session.activeTab,
    (tab) => {
        if (layoutActiveTab) {
            layoutActiveTab.value = tab;
        }
    },
    { immediate: true },
);

watch(
    session.middleTab,
    (tab) => {
        if (layoutMiddleTab) {
            layoutMiddleTab.value = tab;
        }
    },
    { immediate: true },
);

watch(
    session.unreadCount,
    (count) => {
        if (layoutUnreadCount) {
            layoutUnreadCount.value = count;
        }
    },
    { immediate: true },
);

watch(
    session.currentStatus,
    (status) => {
        if (layoutCurrentStatus) {
            layoutCurrentStatus.value = status;
        }
    },
    { immediate: true },
);

watch(
    ackRemainingSeconds,
    (seconds) => {
        if (layoutAckTimerRemaining) {
            layoutAckTimerRemaining.value = seconds;
        }
    },
    { immediate: true },
);

watch(layoutActiveTab!, (tab) => {
    session.setActiveTab(tab);
});

function handleAdvance(): void {
    if (!session.currentStatus.value) {
        return;
    }

    const transitions: Record<string, IncidentStatus> = {
        DISPATCHED: 'ACKNOWLEDGED',
        ACKNOWLEDGED: 'EN_ROUTE',
        EN_ROUTE: 'ON_SCENE',
        ON_SCENE: 'RESOLVING',
    };

    const next = transitions[session.currentStatus.value];

    if (next) {
        if (next === 'ACKNOWLEDGED') {
            session.acknowledgeAssignment();
        } else {
            session.advanceStatus(next);
        }
    }
}

function handleShowOutcomeSheet(): void {
    session.showOutcomeSheet.value = true;
}

if (layoutOnAdvance) {
    layoutOnAdvance.value = handleAdvance;
}

if (layoutOnShowOutcomeSheet) {
    layoutOnShowOutcomeSheet.value = handleShowOutcomeSheet;
}

// MessageBanner state
const bannerMessage = ref<IncidentMessageItem | null>(null);
const bannerVisible = ref(false);

watch(
    () => session.messages.value.length,
    (newLen, oldLen) => {
        if (
            newLen > oldLen &&
            session.activeTab.value !== 'chat' &&
            session.messages.value.length > 0
        ) {
            bannerMessage.value =
                session.messages.value[session.messages.value.length - 1];
            bannerVisible.value = true;
        }
    },
);

function dismissBanner(): void {
    bannerVisible.value = false;
}

function goToChat(): void {
    bannerVisible.value = false;
    session.setActiveTab('chat');

    if (layoutActiveTab) {
        layoutActiveTab.value = 'chat';
    }
}

function handleMessagesRead(): void {
    session.markMessagesRead();
}
</script>

<template>
    <div class="flex flex-1 flex-col overflow-y-auto">
        <!-- Standby screen: no active incident and no assignment notification -->
        <StandbyScreen
            v-if="
                !session.hasActiveIncident.value &&
                !session.showAssignmentNotification.value
            "
            :unit="session.unit.value"
            :connection-status="connectionStatusValue"
        />

        <!-- Assignment notification placeholder -->
        <div
            v-else-if="session.showAssignmentNotification.value"
            class="flex flex-1 items-center justify-center p-6"
        >
            <div
                class="rounded-xl border border-t-border bg-t-surface p-6 text-center shadow-lg"
            >
                <p class="font-mono text-sm font-semibold text-t-text-dim">
                    Assignment notification coming in Plan 04
                </p>
            </div>
        </div>

        <!-- Active incident: tab content -->
        <template v-else>
            <!-- Message banner for incoming messages when not on chat tab -->
            <MessageBanner
                :message="bannerMessage"
                :is-visible="bannerVisible"
                @dismiss="dismissBanner"
                @go-to-chat="goToChat"
            />

            <div
                v-if="session.activeTab.value === 'assignment'"
                class="flex flex-1 items-center justify-center p-6"
            >
                <div
                    class="rounded-xl border border-t-border bg-t-surface p-6 text-center"
                >
                    <p class="font-mono text-sm font-semibold text-t-text-dim">
                        Assignment tab content (Plan 04)
                    </p>
                </div>
            </div>

            <div
                v-else-if="session.activeTab.value === 'nav'"
                class="flex flex-1 items-center justify-center p-6"
            >
                <div
                    class="rounded-xl border border-t-border bg-t-surface p-6 text-center"
                >
                    <p class="font-mono text-sm font-semibold text-t-text-dim">
                        Navigation tab (Plan 04)
                    </p>
                </div>
            </div>

            <SceneTab
                v-else-if="
                    session.activeTab.value === 'scene' &&
                    session.activeIncident.value
                "
                :incident="session.activeIncident.value"
            />

            <ChatTab
                v-else-if="
                    session.activeTab.value === 'chat' &&
                    session.activeIncident.value
                "
                :messages="session.messages.value"
                :incident-id="session.activeIncident.value.id"
                :current-user-id="props.userId"
                @messages-read="handleMessagesRead"
            />
        </template>
    </div>
</template>
