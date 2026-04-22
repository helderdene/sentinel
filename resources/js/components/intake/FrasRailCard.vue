<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { show as incidentShow } from '@/actions/App/Http/Controllers/IncidentController';
import FrasSeverityBadge from '@/components/fras/FrasSeverityBadge.vue';
import IntakeIconFras from '@/components/intake/icons/IntakeIconFras.vue';
import type { FrasRailEvent, PersonnelCategoryValue } from '@/types/fras';

const props = defineProps<{ event: FrasRailEvent }>();
const emit = defineEmits<{ 'open-modal': [event: FrasRailEvent] }>();

const CATEGORY_LABELS: Record<PersonnelCategoryValue, string> = {
    block: 'Block-list',
    missing: 'Missing person',
    lost_child: 'Lost child',
    allow: 'Allow',
};

const categoryLabel = computed(() =>
    props.event.personnel_category
        ? CATEGORY_LABELS[props.event.personnel_category]
        : 'Unknown',
);

// Relative time ticker (updates every 30s to keep "Xm ago" fresh).
const now = ref(Date.now());
let interval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    interval = setInterval(() => {
        now.value = Date.now();
    }, 30_000);
});

onBeforeUnmount(() => {
    if (interval !== null) {
        clearInterval(interval);
    }
});

const relativeTime = computed(() => {
    const diff = Math.floor(
        (now.value - new Date(props.event.captured_at).getTime()) / 1000,
    );

    if (diff < 10) {
        return 'Just now';
    }

    if (diff < 60) {
        return `${diff}s ago`;
    }

    if (diff < 3600) {
        return `${Math.floor(diff / 60)}m ago`;
    }

    if (diff < 86_400) {
        return `${Math.floor(diff / 3600)}h ago`;
    }

    return `${Math.floor(diff / 86_400)}d ago`;
});

const ariaLabel = computed(() => {
    const name = props.event.personnel_name ?? 'Unknown person';
    const camera = props.event.camera_label ?? 'unknown camera';

    return `FRAS recognition: ${name} — ${categoryLabel.value} — ${props.event.severity} — ${camera} — ${relativeTime.value}`;
});

function handleClick(): void {
    if (props.event.incident_id !== null) {
        router.visit(incidentShow({ incident: props.event.incident_id }).url);
    } else {
        emit('open-modal', props.event);
    }
}

function handleKeydown(e: KeyboardEvent): void {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        handleClick();
    }
}
</script>

<template>
    <article
        role="button"
        tabindex="0"
        class="flex h-16 cursor-pointer items-stretch gap-3 rounded-[var(--radius)] border border-t-border bg-t-surface p-3 shadow-sm transition-colors hover:bg-t-surface-alt"
        :aria-label="ariaLabel"
        @click="handleClick"
        @keydown="handleKeydown"
    >
        <!-- Accent stripe -->
        <div
            class="w-0.5 self-stretch rounded-full"
            style="background-color: var(--t-ch-fras)"
        />

        <!-- Face thumbnail -->
        <div
            class="size-10 flex-shrink-0 overflow-hidden rounded-[var(--radius)] bg-t-bg"
        >
            <img
                v-if="event.face_image_url"
                :src="event.face_image_url"
                :alt="`Face capture of ${event.personnel_name ?? 'unknown person'}`"
                class="size-full object-cover"
            />
            <div
                v-else
                class="flex size-full items-center justify-center"
                style="color: var(--t-ch-fras)"
                aria-hidden="true"
            >
                <IntakeIconFras :size="20" />
            </div>
        </div>

        <!-- Content column -->
        <div class="flex min-w-0 flex-1 flex-col justify-between gap-1">
            <div class="flex items-center justify-between gap-2">
                <span
                    class="truncate text-sm font-semibold text-t-text"
                    :title="event.personnel_name ?? 'Unknown person'"
                >
                    {{ event.personnel_name ?? 'Unknown person' }}
                </span>
                <FrasSeverityBadge :severity="event.severity" />
            </div>
            <div class="flex items-center justify-between gap-2">
                <span class="truncate font-mono text-[10px] text-t-text-faint">
                    {{ categoryLabel }} · {{ event.camera_label ?? '—' }}
                </span>
                <div class="flex flex-shrink-0 items-center gap-2">
                    <span
                        v-if="event.incident_id"
                        class="inline-flex items-center rounded-full border px-2 py-[1px] font-mono text-[9px] font-bold tracking-[1.5px] uppercase"
                        :style="{
                            backgroundColor:
                                'color-mix(in srgb, var(--t-online) 15%, transparent)',
                            borderColor:
                                'color-mix(in srgb, var(--t-online) 40%, transparent)',
                            color: 'var(--t-online)',
                        }"
                    >
                        CREATED INCIDENT
                    </span>
                    <span class="font-mono text-[10px] text-t-text-faint">
                        {{ relativeTime }}
                    </span>
                </div>
            </div>
        </div>
    </article>
</template>
