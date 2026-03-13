<script setup lang="ts">
import { CITIZEN_STATUS_MAP, PRIORITY_BG, PRIORITY_COLORS } from '@/types';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import StatusBadge from '@/components/StatusBadge.vue';
import { useReportStorage } from '@/composables/useReportStorage';

const router = useRouter();
const { getReports } = useReportStorage();

const recentReports = ref(getReports().slice(0, 3));

onMounted(() => {
    recentReports.value = getReports().slice(0, 3);
});

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

function citizenStatus(status: string): string {
    return CITIZEN_STATUS_MAP[status] ?? status;
}

function priorityColor(priority: number): string {
    return PRIORITY_COLORS[priority] ?? '#64748b';
}

function priorityBg(priority: number): string {
    return PRIORITY_BG[priority] ?? 'rgba(100,116,139,.08)';
}

const hasReports = computed(() => recentReports.value.length > 0);
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Hero section -->
        <div
            class="relative shrink-0 overflow-hidden px-[22px] pb-7 pt-[22px]"
            style="background-color: var(--t-brand)"
        >
            <!-- Decorative rings -->
            <div
                class="absolute -right-10 -top-10 h-[180px] w-[180px] rounded-full"
                style="border: 1px solid rgba(255, 255, 255, 0.06)"
            />
            <div
                class="absolute -right-5 -top-5 h-[120px] w-[120px] rounded-full"
                style="border: 1px solid rgba(255, 255, 255, 0.08)"
            />
            <div
                class="absolute right-2.5 top-2.5 h-[60px] w-[60px] rounded-full"
                style="border: 1px solid rgba(255, 255, 255, 0.12)"
            />

            <div class="mb-[18px] flex items-center gap-2.5">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-[10px]"
                    style="background: rgba(255, 255, 255, 0.12)"
                >
                    <!-- Shield icon -->
                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 28 28"
                        fill="none"
                    >
                        <path
                            d="M14 2L24 7V14C24 20 19.6 25 14 26C8.4 25 4 20 4 14V7L14 2Z"
                            stroke="#ffffff"
                            stroke-width="1.8"
                            stroke-linejoin="round"
                        />
                        <path
                            d="M9 14L12.5 17.5L19 11"
                            stroke="#ffffff"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>
                <div>
                    <div
                        class="text-[15px] font-bold tracking-wide text-white"
                    >
                        CDRRMO Butuan City
                    </div>
                    <div
                        class="font-mono text-[11px] tracking-widest"
                        style="color: rgba(255, 255, 255, 0.55)"
                    >
                        EMERGENCY REPORTING
                    </div>
                </div>
            </div>

            <div
                class="-tracking-[0.3px] mb-1.5 text-[26px] font-extrabold leading-[1.15] text-white"
            >
                Report an<br />Emergency
            </div>
            <div
                class="text-[13px] leading-relaxed"
                style="color: rgba(255, 255, 255, 0.6)"
            >
                Fast, direct reporting to<br />CDRRMO response teams.
            </div>
        </div>

        <!-- Report CTA button -->
        <div class="relative z-[5] -mt-[18px] shrink-0 px-5">
            <button
                class="flex w-full cursor-pointer items-center justify-center gap-2.5 rounded-xl border-none py-[17px] text-[17px] font-bold tracking-wide text-white"
                style="
                    background-color: var(--t-p1);
                    box-shadow: 0 8px 24px rgba(220, 38, 38, 0.31);
                "
                @click="router.push('/report/type')"
            >
                <!-- Alert triangle icon -->
                <svg
                    width="18"
                    height="18"
                    viewBox="0 0 16 16"
                    fill="none"
                >
                    <path
                        d="M8 1.5L15 14.5H1L8 1.5Z"
                        stroke="#fff"
                        stroke-width="1.4"
                        stroke-linejoin="round"
                    />
                    <line
                        x1="8"
                        y1="7"
                        x2="8"
                        y2="10.5"
                        stroke="#fff"
                        stroke-width="1.4"
                        stroke-linecap="round"
                    />
                    <circle cx="8" cy="12.2" r=".8" fill="#fff" />
                </svg>
                Report Emergency Now
            </button>
        </div>

        <!-- Quick Tips -->
        <div class="shrink-0 px-5 pb-1.5 pt-4">
            <div
                class="mb-2.5 font-mono text-[10px] uppercase tracking-[1.5px] text-t-text-faint"
            >
                QUICK TIPS
            </div>
            <div class="flex gap-2">
                <div
                    v-for="tip in [
                        {
                            t: 'Stay calm',
                            d: 'Take a breath before reporting',
                        },
                        {
                            t: 'Be specific',
                            d: 'Include location & details',
                        },
                        {
                            t: 'Stay safe',
                            d: 'Move away from danger first',
                        },
                    ]"
                    :key="tip.t"
                    class="flex-1 rounded-[10px] border border-t-border bg-t-surface p-2.5 shadow-[0_1px_3px_rgba(0,0,0,.04)]"
                >
                    <div
                        class="mb-0.5 text-[11px] font-bold text-t-text"
                    >
                        {{ tip.t }}
                    </div>
                    <div
                        class="text-[10px] leading-snug text-t-text-dim"
                    >
                        {{ tip.d }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Reports + Hotline (scrollable) -->
        <div class="hide-scrollbar flex-1 overflow-y-auto px-5 pb-4 pt-2">
            <div
                class="mb-2.5 font-mono text-[10px] uppercase tracking-[1.5px] text-t-text-faint"
            >
                RECENT REPORTS
            </div>

            <!-- Report cards -->
            <div
                v-if="hasReports"
                class="flex flex-col gap-2"
            >
                <div
                    v-for="report in recentReports"
                    :key="report.token"
                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-t-border bg-t-surface p-3 shadow-[0_1px_4px_rgba(0,0,0,.04)]"
                    @click="router.push(`/track/${report.token}`)"
                >
                    <!-- Priority-colored icon area -->
                    <div
                        class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-[10px]"
                        :style="{
                            backgroundColor: priorityBg(report.priority),
                            border: `1px solid ${priorityColor(report.priority)}20`,
                        }"
                    >
                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 28 28"
                            fill="none"
                            :style="{ color: priorityColor(report.priority) }"
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
                    <div class="min-w-0 flex-1">
                        <div
                            class="mb-0.5 text-[13px] font-semibold text-t-text"
                        >
                            {{ report.type }}
                        </div>
                        <div
                            class="flex items-center gap-1 text-[11px] text-t-text-dim"
                        >
                            <!-- Pin icon -->
                            <svg
                                width="12"
                                height="12"
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
                                report.barangay || 'Unknown'
                            }}
                            &middot;
                            {{ elapsed(report.submittedAt) }}
                        </div>
                    </div>
                    <StatusBadge
                        :status="citizenStatus(report.status)"
                    />
                </div>
            </div>

            <div
                v-else
                class="py-6 text-center text-[13px] text-t-text-faint"
            >
                No recent reports. Your submitted reports will appear here.
            </div>

            <!-- Emergency hotline card -->
            <div
                class="mt-2 flex items-center gap-3 rounded-xl border p-3"
                style="
                    background: rgba(220, 38, 38, 0.03);
                    border-color: rgba(220, 38, 38, 0.12);
                "
            >
                <div
                    class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-[10px]"
                    style="background: rgba(220, 38, 38, 0.14)"
                >
                    <!-- Phone icon -->
                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 16 16"
                        fill="none"
                        style="color: var(--t-p1)"
                    >
                        <path
                            d="M4 2h3l1 3-2 1a8 8 0 004 4l1-2 3 1v3a2 2 0 01-2 2C6 14 2 10 2 4a2 2 0 012-2z"
                            stroke="currentColor"
                            stroke-width="1.3"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>
                <div>
                    <div
                        class="mb-0.5 text-[12px] font-bold"
                        style="color: var(--t-p1)"
                    >
                        Emergency Hotline
                    </div>
                    <div
                        class="font-mono text-[18px] font-extrabold tracking-wide text-t-text"
                    >
                        0917-123-4567
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
