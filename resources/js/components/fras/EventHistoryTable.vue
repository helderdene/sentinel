<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { UserRound } from 'lucide-vue-next';
import { computed } from 'vue';
import FrasSeverityBadge from '@/components/fras/FrasSeverityBadge.vue';
import ReplayBadge from '@/components/fras/ReplayBadge.vue';
import type { FrasSeverity, PersonnelCategoryValue } from '@/types/fras';

export interface RecognitionEventRow {
    id: string;
    severity: FrasSeverity;
    personnel: {
        id: string;
        name: string;
        category: PersonnelCategoryValue | null;
    } | null;
    camera: { id: string; camera_id_display: string; name: string };
    captured_at: string;
    face_image_url: string | null;
    incident_id: string | null;
    acknowledged_at: string | null;
    acknowledged_by: { id: number; name: string } | null;
    dismissed_at: string | null;
    dismissed_by: { id: number; name: string } | null;
    dismiss_reason: string | null;
    dismiss_reason_note: string | null;
    can_promote: boolean;
}

interface PaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginatorLink[];
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    events: Paginator<RecognitionEventRow>;
    replayCounts: Record<string, number>;
}>();

const emit = defineEmits<{
    'open-detail': [row: RecognitionEventRow];
    'open-promote': [row: RecognitionEventRow];
}>();

const CATEGORY_LABELS: Record<PersonnelCategoryValue, string> = {
    block: 'Block-list',
    missing: 'Missing person',
    lost_child: 'Lost child',
    allow: 'Allow',
};

const DISMISS_LABELS: Record<string, string> = {
    false_match: 'False match',
    test_event: 'Test event',
    duplicate: 'Duplicate',
    other: 'Other',
};

function categoryLabel(cat: PersonnelCategoryValue | null): string {
    return cat ? CATEGORY_LABELS[cat] : 'Unknown';
}

function replayKey(row: RecognitionEventRow): string {
    return `${row.camera.id}:${row.personnel?.id ?? ''}`;
}

function replayCount(row: RecognitionEventRow): number {
    if (!row.personnel) {
        return 0;
    }

    return props.replayCounts[replayKey(row)] ?? 0;
}

function relativeTime(iso: string): string {
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);

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
}

const visibleLinks = computed(() =>
    props.events.links.filter(
        (l) => l.label !== '&laquo; Previous' && l.label !== 'Next &raquo;',
    ),
);

function cleanLinkLabel(label: string): string {
    // Laravel paginator emits HTML entities for prev/next; strip them.
    return label
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace(/&[#\w]+;/g, '')
        .trim();
}
</script>

<template>
    <div class="space-y-4">
        <div
            class="overflow-x-auto rounded-[var(--radius)] border border-t-border"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="bg-t-surface-alt/40 font-mono text-[9px] tracking-[2px] text-t-text-faint uppercase"
                >
                    <tr>
                        <th class="w-14 px-3 py-2"></th>
                        <th class="px-3 py-2">Severity</th>
                        <th class="px-3 py-2">Personnel</th>
                        <th class="px-3 py-2">Camera</th>
                        <th class="px-3 py-2">Captured</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody
                    class="divide-y divide-t-border"
                    data-slot="event-history-tbody"
                >
                    <tr
                        v-for="row in events.data"
                        :key="row.id"
                        class="bg-transparent transition-colors hover:bg-t-surface-alt/30"
                    >
                        <td class="px-3 py-2">
                            <button
                                type="button"
                                class="size-10 overflow-hidden rounded-[var(--radius)] border border-t-border bg-t-bg"
                                :aria-label="`Open event detail for ${row.personnel?.name ?? 'unknown person'}`"
                                @click="emit('open-detail', row)"
                            >
                                <img
                                    v-if="row.face_image_url"
                                    :src="row.face_image_url"
                                    :alt="`Face of ${row.personnel?.name ?? 'unknown person'}`"
                                    class="size-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex size-full items-center justify-center text-t-text-faint"
                                    aria-hidden="true"
                                >
                                    <UserRound class="size-5" />
                                </div>
                            </button>
                        </td>
                        <td class="px-3 py-2">
                            <FrasSeverityBadge :severity="row.severity" />
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="text-sm font-semibold text-t-text hover:underline"
                                    @click="emit('open-detail', row)"
                                >
                                    {{
                                        row.personnel?.name ?? 'Unknown person'
                                    }}
                                </button>
                                <span
                                    v-if="row.personnel"
                                    class="inline-flex items-center rounded-full border border-t-border px-2 py-0.5 text-[10px] text-t-text-dim"
                                >
                                    {{ categoryLabel(row.personnel.category) }}
                                </span>
                                <ReplayBadge
                                    v-if="replayCount(row) >= 2"
                                    :count="replayCount(row)"
                                />
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex flex-col">
                                <span class="font-mono text-xs text-t-text">
                                    {{ row.camera.camera_id_display }}
                                </span>
                                <span class="text-xs text-t-text-dim">
                                    {{ row.camera.name }}
                                </span>
                            </div>
                        </td>
                        <td class="px-3 py-2 font-mono text-xs text-t-text">
                            {{ relativeTime(row.captured_at) }}
                        </td>
                        <td class="px-3 py-2">
                            <template v-if="row.incident_id !== null">
                                <Link
                                    :href="`/incidents/${row.incident_id}`"
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 font-mono text-[10px] tracking-[1.5px] uppercase"
                                    :style="{
                                        backgroundColor:
                                            'color-mix(in srgb, var(--t-online) 15%, transparent)',
                                        borderColor:
                                            'color-mix(in srgb, var(--t-online) 40%, transparent)',
                                        color: 'var(--t-online)',
                                    }"
                                >
                                    <span aria-hidden="true">●</span>
                                    CREATED INCIDENT
                                </Link>
                            </template>
                            <template v-else-if="row.dismissed_at !== null">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border border-t-border px-2 py-0.5 text-[10px] text-t-text-dim uppercase"
                                >
                                    ✕ Dismissed
                                    <span
                                        v-if="row.dismiss_reason"
                                        class="text-t-text-faint"
                                    >
                                        —
                                        {{
                                            DISMISS_LABELS[
                                                row.dismiss_reason
                                            ] ?? row.dismiss_reason
                                        }}
                                    </span>
                                </span>
                            </template>
                            <template v-else-if="row.acknowledged_at !== null">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] uppercase"
                                    :style="{
                                        backgroundColor:
                                            'color-mix(in srgb, var(--t-online) 10%, transparent)',
                                        borderColor:
                                            'color-mix(in srgb, var(--t-online) 30%, transparent)',
                                        color: 'var(--t-online)',
                                    }"
                                >
                                    ✓ Acknowledged
                                </span>
                            </template>
                            <template v-else>
                                <span
                                    class="text-t-text-faint"
                                    aria-label="No status"
                                >
                                    —
                                </span>
                            </template>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div
                                class="flex items-center justify-end gap-3 text-xs"
                            >
                                <button
                                    type="button"
                                    class="text-t-accent hover:underline"
                                    @click="emit('open-detail', row)"
                                >
                                    View
                                </button>
                                <button
                                    v-if="
                                        row.incident_id === null &&
                                        row.severity !== 'critical' &&
                                        row.can_promote
                                    "
                                    type="button"
                                    class="text-t-accent hover:underline"
                                    @click="emit('open-promote', row)"
                                >
                                    Promote
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="events.data.length === 0">
                        <td
                            colspan="7"
                            class="px-3 py-8 text-center text-sm text-t-text-dim"
                        >
                            No events match your filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination footer -->
        <div
            v-if="events.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <div class="text-xs text-t-text-dim">
                Page {{ events.current_page }} of {{ events.last_page }}
            </div>
            <nav
                class="flex flex-wrap items-center gap-1"
                aria-label="Pagination"
            >
                <Link
                    v-for="(link, idx) in visibleLinks"
                    :key="`${link.label}-${idx}`"
                    :href="link.url ?? ''"
                    :aria-disabled="link.url === null"
                    :aria-current="link.active ? 'page' : undefined"
                    preserve-scroll
                    preserve-state
                    class="inline-flex min-w-8 items-center justify-center rounded-md border px-2 py-1 text-xs font-medium transition-colors"
                    :class="
                        link.active
                            ? 'border-t-accent bg-t-accent text-white'
                            : link.url === null
                              ? 'cursor-not-allowed border-t-border text-t-text-faint'
                              : 'border-t-border text-t-text hover:border-t-accent/40'
                    "
                >
                    <span v-html="cleanLinkLabel(link.label)" />
                </Link>
            </nav>
        </div>
    </div>
</template>
