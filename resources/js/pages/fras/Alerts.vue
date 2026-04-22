<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AlertCard from '@/components/fras/AlertCard.vue';
import AudioMuteToggle from '@/components/fras/AudioMuteToggle.vue';
import DismissReasonModal from '@/components/fras/DismissReasonModal.vue';
import { useFrasFeed } from '@/composables/useFrasFeed';
import AppLayout from '@/layouts/AppLayout.vue';
import type { FrasAlertItem } from '@/types/fras';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    initialAlerts: FrasAlertItem[];
    audioMuted: boolean;
    frasConfig: { audioEnabled: boolean };
}>();

const { alerts } = useFrasFeed(props.initialAlerts);

const dismissTargetEventId = ref<string | null>(null);
const dismissModalOpen = ref(false);

function openDismiss(eventId: string): void {
    dismissTargetEventId.value = eventId;
    dismissModalOpen.value = true;
}

function closeDismiss(): void {
    dismissTargetEventId.value = null;
    dismissModalOpen.value = false;
}

const page = usePage();
const connectionLive = computed(() => {
    // useEcho handles silent reconnect; page.props.auth presence is a
    // sufficient initial-render hint. More granular status can come
    // from a future useConnectionStatus composable.
    return page.props.auth !== undefined;
});
</script>

<template>
    <Head title="FRAS Alerts — IRMS" />

    <main
        role="main"
        aria-label="FRAS live alerts"
        class="space-y-6 p-6 lg:p-8"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-semibold">
                    FRAS Alerts
                    <span
                        v-if="connectionLive"
                        class="font-mono text-xs text-t-online"
                    >
                        ● LIVE
                    </span>
                    <span
                        v-else
                        class="font-mono text-xs text-t-unit-onscene"
                    >
                        ● RECONNECTING…
                    </span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    Live recognition alerts. Acknowledge when you're
                    handling; dismiss false positives.
                </p>
            </div>
            <AudioMuteToggle :muted="props.audioMuted" />
        </div>

        <div
            class="mx-auto max-h-[calc(100vh-14rem)] max-w-2xl space-y-3 overflow-y-auto"
        >
            <AlertCard
                v-for="alert in alerts"
                :key="alert.event_id"
                :alert="alert"
                @dismiss="openDismiss"
            />

            <div
                v-if="alerts.length === 0"
                class="flex flex-col items-center gap-3 py-12 text-center text-t-text-faint"
            >
                <ShieldCheck class="size-12" />
                <h2 class="text-lg font-semibold text-foreground">
                    No active alerts.
                </h2>
                <p class="text-sm">
                    Critical and warning FRAS events will appear here as
                    cameras match watchlist personnel.
                </p>
            </div>
        </div>

        <DismissReasonModal
            :open="dismissModalOpen"
            :event-id="dismissTargetEventId"
            @close="closeDismiss"
            @update:open="(v) => (dismissModalOpen = v)"
        />
    </main>
</template>
