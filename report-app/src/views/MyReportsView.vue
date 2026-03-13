<script setup lang="ts">
import type { CitizenReport } from '@/types';
import {
    CITIZEN_STATUS_MAP,
    PRIORITY_BG,
    PRIORITY_COLORS,
} from '@/types';
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import StatusBadge from '@/components/StatusBadge.vue';
import { useApi } from '@/composables/useApi';
import { useReportStorage } from '@/composables/useReportStorage';

const router = useRouter();
const { getReports, updateReportStatus } = useReportStorage();
const api = useApi();

const reports = ref(getReports());
const trackToken = ref('');
const refreshing = ref(false);

function citizenStatus(status: string): string {
    return CITIZEN_STATUS_MAP[status] ?? status;
}

function priorityColor(priority: number): string {
    return PRIORITY_COLORS[priority] ?? '#64748b';
}

function priorityBg(priority: number): string {
    return PRIORITY_BG[priority] ?? 'rgba(100,116,139,.08)';
}

function elapsed(ts: string): string {
    const m = Math.floor(
        (Date.now() - new Date(ts).getTime()) / 60000
    );

    if (m < 1) {
        return 'Just now';
    }

    if (m < 60) {
        return `${m}m ago`;
    }

    return `${Math.floor(m / 60)}h ${m % 60}m ago`;
}

function handleTrack(): void {
    const token = trackToken.value.trim();

    if (token) {
        router.push(`/track/${token}`);
    }
}

async function refreshStatuses(): Promise<void> {
    if (reports.value.length === 0) {
        return;
    }

    refreshing.value = true;

    for (const report of reports.value) {
        try {
            const res = await api.get<{
                data: CitizenReport;
            }>(`/api/v1/citizen/reports/${report.token}`);
            updateReportStatus(report.token, res.data.status);
        } catch {
            // Individual report refresh failure is non-critical
        }
    }

    reports.value = getReports();
    refreshing.value = false;
}

onMounted(() => {
    reports.value = getReports();
    refreshStatuses();
});
</script>

<template>
    <div class="flex h-full flex-col bg-t-bg">
        <!-- Header -->
        <div
            class="shrink-0 border-b border-t-border bg-t-surface px-[18px] py-4 shadow-[0_1px_4px_rgba(0,0,0,.04)]"
        >
            <div class="text-[18px] font-bold text-t-text">
                My Reports
            </div>
            <div class="mt-0.5 text-[12px] text-t-text-dim">
                {{ reports.length }}
                report{{ reports.length !== 1 ? 's' : '' }}
                submitted
            </div>
        </div>

        <div
            class="hide-scrollbar flex-1 overflow-y-auto px-4 pb-5 pt-3.5"
        >
            <!-- Track by ID -->
            <div
                class="mb-3.5 flex gap-2 rounded-xl border border-t-border bg-t-surface p-3"
            >
                <input
                    v-model="trackToken"
                    type="text"
                    placeholder="Enter tracking ID..."
                    class="min-w-0 flex-1 rounded-[10px] border-[1.5px] border-t-border bg-t-bg px-3 py-2.5 text-[14px] text-t-text outline-none transition-colors focus:border-t-accent"
                    @keyup.enter="handleTrack"
                />
                <button
                    class="shrink-0 cursor-pointer rounded-[10px] border-none px-4 py-2.5 text-[14px] font-semibold text-white"
                    style="background-color: var(--t-accent)"
                    @click="handleTrack"
                >
                    Track
                </button>
            </div>

            <!-- Loading indicator -->
            <div
                v-if="refreshing"
                class="mb-3 flex items-center justify-center gap-2 text-[12px] text-t-text-faint"
            >
                <div
                    class="h-3 w-3 animate-spin rounded-full border-2 border-t-accent"
                    style="border-top-color: transparent"
                />
                Refreshing statuses...
            </div>

            <!-- Empty state -->
            <div
                v-if="reports.length === 0 && !refreshing"
                class="pt-[60px] text-center"
            >
                <div
                    class="mb-3 flex justify-center opacity-40"
                >
                    <svg
                        width="48"
                        height="48"
                        viewBox="0 0 24 24"
                        fill="none"
                        class="text-t-text-faint"
                    >
                        <rect
                            x="3"
                            y="5"
                            width="18"
                            height="3"
                            rx="1.5"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />
                        <rect
                            x="3"
                            y="11"
                            width="18"
                            height="3"
                            rx="1.5"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />
                        <rect
                            x="3"
                            y="17"
                            width="12"
                            height="3"
                            rx="1.5"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />
                    </svg>
                </div>
                <div
                    class="mb-1.5 text-[15px] font-semibold text-t-text-dim"
                >
                    No reports yet
                </div>
                <div class="mb-5 text-[13px] text-t-text-faint">
                    Your submitted reports will appear here.
                </div>
                <button
                    class="cursor-pointer rounded-xl border-none px-6 py-3 text-[14px] font-semibold text-white"
                    style="
                        background-color: var(--t-accent);
                        box-shadow: 0 4px 16px
                            rgba(37, 99, 235, 0.25);
                    "
                    @click="router.push('/report/type')"
                >
                    Report an Emergency
                </button>
            </div>

            <!-- Report cards -->
            <div
                v-for="report in reports"
                :key="report.token"
                class="mb-2.5 cursor-pointer overflow-hidden rounded-[13px] border border-t-border bg-t-surface shadow-[0_1px_4px_rgba(0,0,0,.04)]"
                @click="router.push(`/track/${report.token}`)"
            >
                <!-- Priority stripe -->
                <div
                    class="h-1 rounded-t-[13px]"
                    :style="{
                        backgroundColor: priorityColor(
                            report.priority
                        ),
                    }"
                />
                <div class="p-3.5">
                    <div
                        class="mb-2 flex items-start justify-between"
                    >
                        <div
                            class="flex items-center gap-2.5"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px]"
                                :style="{
                                    backgroundColor:
                                        priorityBg(
                                            report.priority
                                        ),
                                }"
                            >
                                <svg
                                    width="20"
                                    height="20"
                                    viewBox="0 0 28 28"
                                    fill="none"
                                    :style="{
                                        color: priorityColor(
                                            report.priority
                                        ),
                                    }"
                                >
                                    <path
                                        d="M14 3L26 25H2L14 3Z"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linejoin="round"
                                    />
                                    <line
                                        x1="14"
                                        y1="11"
                                        x2="14"
                                        y2="18"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                    />
                                    <circle
                                        cx="14"
                                        cy="21.5"
                                        r="1.3"
                                        fill="currentColor"
                                    />
                                </svg>
                            </div>
                            <div>
                                <div
                                    class="text-[14px] font-bold text-t-text"
                                >
                                    {{ report.type }}
                                </div>
                                <div
                                    class="mt-0.5 flex items-center gap-1 text-[11px] text-t-text-dim"
                                >
                                    <!-- Pin icon -->
                                    <svg
                                        width="11"
                                        height="11"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        class="text-t-text-faint"
                                    >
                                        <path
                                            d="M8 1.5C5.51 1.5 3.5 3.51 3.5 6C3.5 9.5 8 14.5 8 14.5C8 14.5 12.5 9.5 12.5 6C12.5 3.51 10.49 1.5 8 1.5Z"
                                            stroke="currentColor"
                                            stroke-width="1.4"
                                        />
                                        <circle
                                            cx="8"
                                            cy="6"
                                            r="1.8"
                                            fill="currentColor"
                                        />
                                    </svg>
                                    {{
                                        report.barangay ||
                                        'Unknown'
                                    }}
                                </div>
                            </div>
                        </div>
                        <StatusBadge
                            :status="
                                citizenStatus(report.status)
                            "
                        />
                    </div>
                    <div
                        class="mb-2 border-t border-t-border pt-2 text-[12px] leading-snug text-t-text-dim"
                    >
                        {{
                            report.description.length > 80
                                ? report.description.slice(
                                      0,
                                      80
                                  ) + '...'
                                : report.description
                        }}
                    </div>
                    <div
                        class="flex items-center justify-between"
                    >
                        <span
                            class="font-mono text-[10px] text-t-text-faint"
                            >{{ report.token }}</span
                        >
                        <span
                            class="text-[11px] text-t-text-faint"
                            >{{
                                elapsed(report.submittedAt)
                            }}</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
