<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import EventHistoryFilters from '@/components/fras/EventHistoryFilters.vue';
import type {
    AvailableCamera,
    FilterState,
} from '@/components/fras/EventHistoryFilters.vue';
import EventHistoryTable from '@/components/fras/EventHistoryTable.vue';
import type {
    Paginator,
    RecognitionEventRow,
} from '@/components/fras/EventHistoryTable.vue';
import FrasEventDetailModal from '@/components/fras/FrasEventDetailModal.vue';
import type { FrasDetailEvent } from '@/components/fras/FrasEventDetailModal.vue';
import PromoteIncidentModal from '@/components/fras/PromoteIncidentModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    events: Paginator<RecognitionEventRow>;
    filters: FilterState;
    availableCameras: AvailableCamera[];
    replayCounts: Record<string, number>;
}>();

const searching = ref<boolean>(false);
const detailEvent = ref<FrasDetailEvent | null>(null);
const promoteEvent = ref<RecognitionEventRow | null>(null);

function applyFilters(
    nextFilters: FilterState,
    opts: { fromSearch: boolean },
): void {
    // Debounced free-text search uses `replace: true` so individual keystrokes
    // don't pile up in browser history (threat T-22-07-05 — info disclosure via
    // retained search terms). All other filter changes preserve history so the
    // back button honors deliberate operator intent (CONTEXT D-08).
    router.get('/fras/events', serializeForUrl(nextFilters), {
        preserveState: true,
        preserveScroll: true,
        replace: opts.fromSearch,
        onStart: () => {
            searching.value = true;
        },
        onFinish: () => {
            searching.value = false;
        },
    });
}

type SerializedFilters = {
    severity?: string[];
    camera_id?: string;
    q?: string;
    from?: string;
    to?: string;
};

/**
 * Translate the FilterState to a flat record Inertia router.get can
 * serialize. `null` values are dropped so the URL stays clean.
 */
function serializeForUrl(f: FilterState): SerializedFilters {
    const out: SerializedFilters = {};

    if (f.severity.length > 0) {
        out.severity = f.severity;
    }

    if (f.camera_id) {
        out.camera_id = f.camera_id;
    }

    if (f.q && f.q.length > 0) {
        out.q = f.q;
    }

    if (f.from) {
        out.from = f.from;
    }

    if (f.to) {
        out.to = f.to;
    }

    return out;
}

function openDetail(row: RecognitionEventRow): void {
    detailEvent.value = row as FrasDetailEvent;
}

function openPromote(row: RecognitionEventRow): void {
    promoteEvent.value = row;
}

function handleDetailPromote(): void {
    if (detailEvent.value) {
        promoteEvent.value = detailEvent.value;
    }

    detailEvent.value = null;
}
</script>

<template>
    <Head title="FRAS Events — IRMS" />
    <div class="space-y-6 p-6 lg:p-8">
        <header class="space-y-1">
            <h1 class="text-2xl font-semibold text-t-text">FRAS Events</h1>
            <p class="text-sm text-t-text-dim">
                Search recognition history. Filter by camera, severity, and
                date.
            </p>
        </header>

        <EventHistoryFilters
            :model-value="props.filters"
            :available-cameras="props.availableCameras"
            :searching="searching"
            @update:model-value="
                (next, opts) => applyFilters(next, opts)
            "
        />

        <EventHistoryTable
            :events="props.events"
            :replay-counts="props.replayCounts"
            @open-detail="openDetail"
            @open-promote="openPromote"
        />

        <FrasEventDetailModal
            :event="detailEvent"
            @close="detailEvent = null"
            @promote="handleDetailPromote"
        />

        <PromoteIncidentModal
            :event="promoteEvent"
            @close="promoteEvent = null"
        />
    </div>
</template>
