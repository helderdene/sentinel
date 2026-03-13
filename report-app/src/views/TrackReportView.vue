<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PriorityBadge from '@/components/PriorityBadge.vue';
import StatusPipeline from '@/components/StatusPipeline.vue';
import { useApi } from '@/composables/useApi';
import type { CitizenReport } from '@/types';
import { CITIZEN_STATUS_MAP } from '@/types';

const route = useRoute();
const router = useRouter();
const { get, loading } = useApi();

const report = ref<CitizenReport | null>(null);
const notFound = ref(false);

function citizenStatus(status: string): string {
    return CITIZEN_STATUS_MAP[status] ?? status;
}

function fmtDate(ts: string): string {
    return new Date(ts).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

async function fetchReport(): Promise<void> {
    const token = route.params.token as string;
    notFound.value = false;

    try {
        const res = await get<{ data: CitizenReport }>(
            `/api/v1/citizen/reports/${token}`
        );
        report.value = res.data;
    } catch {
        notFound.value = true;
    }
}

onMounted(() => {
    fetchReport();
});
</script>

<template>
    <div class="flex h-full flex-col bg-t-bg">
        <!-- Header -->
        <div
            class="flex shrink-0 items-center gap-2.5 border-b border-t-border bg-t-surface px-4 py-3.5 shadow-[0_1px_4px_rgba(0,0,0,.04)]"
        >
            <button
                class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-[9px] border border-t-border bg-transparent"
                @click="router.push('/reports')"
            >
                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 20 20"
                    fill="none"
                    class="text-t-text-dim"
                >
                    <path
                        d="M13 4L7 10L13 16"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </button>
            <div>
                <div class="text-[16px] font-bold text-t-text">
                    Track Report
                </div>
            </div>
        </div>

        <!-- Loading state -->
        <div
            v-if="loading"
            class="flex flex-1 flex-col items-center justify-center"
        >
            <div
                class="mb-3 h-8 w-8 animate-spin rounded-full border-[3px] border-t-border"
                style="border-top-color: var(--t-accent)"
            />
            <div class="text-[13px] text-t-text-faint">
                Loading report...
            </div>
        </div>

        <!-- Not found -->
        <div
            v-else-if="notFound"
            class="flex flex-1 flex-col items-center justify-center px-6 text-center"
        >
            <svg
                width="48"
                height="48"
                viewBox="0 0 24 24"
                fill="none"
                class="mb-4 text-t-text-faint opacity-40"
            >
                <circle
                    cx="12"
                    cy="12"
                    r="9"
                    stroke="currentColor"
                    stroke-width="1.8"
                />
                <line
                    x1="8"
                    y1="8"
                    x2="16"
                    y2="16"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />
                <line
                    x1="16"
                    y1="8"
                    x2="8"
                    y2="16"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />
            </svg>
            <div
                class="mb-1.5 text-[15px] font-semibold text-t-text-dim"
            >
                Report not found
            </div>
            <div class="text-[13px] text-t-text-faint">
                Please check your tracking ID and try again.
            </div>
        </div>

        <!-- Report found -->
        <div
            v-else-if="report"
            class="hide-scrollbar flex-1 overflow-y-auto px-4 pb-5 pt-4"
        >
            <!-- Tracking token -->
            <div class="mb-4 text-center">
                <div
                    class="mb-1.5 font-mono text-[10px] uppercase tracking-[1.5px] text-t-text-faint"
                >
                    TRACKING ID
                </div>
                <div
                    class="font-mono text-[20px] font-bold tracking-wider"
                    style="color: var(--t-accent)"
                >
                    {{ report.tracking_token }}
                </div>
            </div>

            <!-- Type + Priority -->
            <div
                class="mb-3.5 flex items-center justify-between"
            >
                <div>
                    <div
                        class="mb-1 text-[16px] font-bold text-t-text"
                    >
                        {{ report.type }}
                    </div>
                    <PriorityBadge :priority="report.priority" />
                </div>
            </div>

            <!-- Status Pipeline -->
            <div
                class="mb-4 rounded-[14px] border border-t-border bg-t-surface px-4 py-4 shadow-[0_2px_8px_rgba(0,0,0,.04)]"
            >
                <div
                    class="mb-3.5 font-mono text-[10px] uppercase tracking-[1.5px] text-t-text-faint"
                >
                    STATUS
                </div>
                <StatusPipeline
                    :current-status="
                        citizenStatus(report.status)
                    "
                />
            </div>

            <!-- Detail card -->
            <div
                class="mb-4 rounded-[14px] border border-t-border bg-t-surface px-4 py-3.5 shadow-[0_1px_4px_rgba(0,0,0,.04)]"
            >
                <div
                    v-for="[label, value] in [
                        [
                            'Barangay',
                            report.barangay
                                ? `Brgy. ${report.barangay}`
                                : '--',
                        ],
                        [
                            'Location',
                            report.location_text || '--',
                        ],
                        ['Submitted', fmtDate(report.submitted_at)],
                    ]"
                    :key="label"
                    class="mb-2.5 flex items-center justify-between gap-3 text-[13px]"
                >
                    <span
                        class="shrink-0 font-mono text-[10px] uppercase text-t-text-faint"
                        >{{ label }}</span
                    >
                    <span
                        class="text-right font-medium text-t-text-mid"
                        >{{ value }}</span
                    >
                </div>
                <div
                    class="mt-0.5 border-t border-t-border pt-2.5"
                >
                    <div
                        class="mb-1 font-mono text-[10px] uppercase text-t-text-faint"
                    >
                        DESCRIPTION
                    </div>
                    <div
                        class="text-[13px] leading-relaxed text-t-text-mid"
                    >
                        {{ report.description }}
                    </div>
                </div>
            </div>

            <!-- Refresh button -->
            <button
                class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border-[1.5px] border-t-border bg-t-surface py-3.5 text-[14px] font-semibold text-t-text-mid"
                :disabled="loading"
                @click="fetchReport"
            >
                <svg
                    width="16"
                    height="16"
                    viewBox="0 0 16 16"
                    fill="none"
                    class="text-t-text-dim"
                    :class="{ 'animate-spin': loading }"
                >
                    <path
                        d="M14 8A6 6 0 114 3.6"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                    />
                    <path
                        d="M14 2V6H10"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                {{ loading ? 'Refreshing...' : 'Refresh Status' }}
            </button>
        </div>
    </div>
</template>
