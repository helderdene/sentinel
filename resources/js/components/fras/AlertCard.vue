<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Check, UserRound, X } from 'lucide-vue-next';
import { computed } from 'vue';
import FrasSeverityBadge from '@/components/fras/FrasSeverityBadge.vue';
import { Button } from '@/components/ui/button';
import type { FrasAlertItem } from '@/types/fras';

const props = defineProps<{ alert: FrasAlertItem }>();

const emit = defineEmits<{
    dismiss: [eventId: string];
    'view-details': [eventId: string];
}>();

/**
 * ACK form — scalar no-payload POST. Server updates `acknowledged_at` +
 * `acknowledged_by`, fires FrasAlertAcknowledged broadcast which removes
 * this card from all operators' feeds via useFrasFeed.
 *
 * Backend route `fras.alerts.ack` lands in Plan 22-05; until Wayfinder
 * regenerates, post by path.
 */
const ackForm = useForm({});

const CATEGORY_LABELS: Record<
    'block' | 'missing' | 'lost_child',
    { label: string; tone: 'red' | 'amber' }
> = {
    block: { label: 'Block-list', tone: 'red' },
    missing: { label: 'Missing person', tone: 'amber' },
    lost_child: { label: 'Lost child', tone: 'red' },
};

const categoryChip = computed(() => CATEGORY_LABELS[props.alert.personnel.category]);

const categoryTokenVar = computed(() =>
    categoryChip.value.tone === 'red' ? 'var(--t-p1)' : 'var(--t-unit-onscene)',
);

const cameraLabel = computed(() => {
    const cam = props.alert.camera;

    if (cam.camera_id_display && cam.name && cam.camera_id_display !== cam.name) {
        return `${cam.camera_id_display} — ${cam.name}`;
    }

    return cam.camera_id_display || cam.name;
});

const capturedRelative = computed(() => {
    const then = new Date(props.alert.captured_at).getTime();
    const diffSec = Math.max(0, Math.round((Date.now() - then) / 1000));

    if (diffSec < 30) {
        return 'Just now';
    }

    if (diffSec < 60) {
        return `${diffSec}s ago`;
    }

    if (diffSec < 3600) {
        return `${Math.floor(diffSec / 60)}m ago`;
    }

    if (diffSec < 86400) {
        return `${Math.floor(diffSec / 3600)}h ago`;
    }

    return `${Math.floor(diffSec / 86400)}d ago`;
});

const ariaLabel = computed(
    () =>
        `FRAS alert: ${props.alert.severity} — ${props.alert.personnel.name} (${categoryChip.value.label}) at ${props.alert.camera.camera_id_display} — ${capturedRelative.value}. Actions: acknowledge or dismiss.`,
);

function acknowledge(): void {
    ackForm.post(`/fras/alerts/${props.alert.event_id}/ack`, {
        preserveScroll: true,
        onError: (errors) => {
            // 409 — another operator beat us. Silent: broadcast will clear the card.
            if ((errors as { status?: number }).status === 409) {
                return;
            }
        },
    });
}

function openDismiss(): void {
    emit('dismiss', props.alert.event_id);
}

function viewDetails(): void {
    emit('view-details', props.alert.event_id);
}

// Keep `router` referenced so tree-shaking doesn't drop the import in
// case future extension needs programmatic navigation.
void router;
</script>

<template>
    <article
        class="flex max-w-2xl gap-4 rounded-[var(--radius)] border border-t-border bg-card p-4 shadow-sm"
        :aria-label="ariaLabel"
    >
        <div
            class="flex size-12 flex-shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius)] bg-t-bg"
        >
            <img
                v-if="alert.face_image_url"
                :src="alert.face_image_url"
                :alt="`Face capture of ${alert.personnel.name}`"
                class="size-full object-cover"
            />
            <UserRound v-else class="size-6 text-t-text-faint" />
        </div>

        <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <FrasSeverityBadge :severity="alert.severity" />
                <span
                    class="truncate text-sm font-semibold text-foreground"
                    :title="alert.personnel.name"
                >
                    {{ alert.personnel.name }}
                </span>
                <span
                    class="inline-flex items-center rounded-full border px-2 py-[2px] font-mono text-[10px] font-bold tracking-[1px] uppercase"
                    :style="{
                        backgroundColor: `color-mix(in srgb, ${categoryTokenVar} 15%, transparent)`,
                        borderColor: `color-mix(in srgb, ${categoryTokenVar} 40%, transparent)`,
                        color: categoryTokenVar,
                    }"
                >
                    {{ categoryChip.label }}
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <span class="font-mono text-xs text-t-text-faint">{{ cameraLabel }}</span>
                <span class="font-mono text-[10px] text-t-text-faint">{{
                    capturedRelative
                }}</span>
            </div>

            <button
                type="button"
                class="text-xs text-t-accent hover:underline"
                @click="viewDetails"
            >
                View details
            </button>
        </div>

        <div class="flex flex-shrink-0 flex-col gap-2">
            <Button
                class="min-w-[96px] bg-t-online text-white hover:bg-t-online/90 focus-visible:ring-t-online/50"
                :disabled="ackForm.processing"
                @click="acknowledge"
            >
                <Check class="mr-1 size-4" />
                <span>{{
                    ackForm.processing ? 'Acknowledging…' : 'Acknowledge'
                }}</span>
            </Button>

            <Button
                variant="destructive"
                class="min-w-[96px]"
                @click="openDismiss"
            >
                <X class="mr-1 size-4" />
                <span>Dismiss</span>
            </Button>
        </div>
    </article>
</template>
