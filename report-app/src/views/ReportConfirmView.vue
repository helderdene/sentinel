<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import PriorityBadge from '@/components/PriorityBadge.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import StepIndicator from '@/components/StepIndicator.vue';
import { useReportDraft } from '@/composables/useReportDraft';
import { useReportStorage } from '@/composables/useReportStorage';
import type { IncidentType } from '@/types';
import {
    CITIZEN_STATUS_MAP,
    PRIORITY_BG,
    PRIORITY_COLORS,
} from '@/types';

const router = useRouter();
const draft = useReportDraft();
const { reports } = useReportStorage();

// Read the latest report from localStorage (just submitted)
const latestReport = ref(reports.value[0] ?? null);
const selectedType = ref<IncidentType | null>(
    draft.selectedType.value
);

onMounted(() => {
    if (!selectedType.value && !latestReport.value) {
        router.replace('/');

        return;
    }
});

// Reset draft when leaving
onUnmounted(() => {
    draft.reset();
});

function citizenStatus(status: string): string {
    return CITIZEN_STATUS_MAP[status] ?? status;
}

function priorityColor(priority: number): string {
    return PRIORITY_COLORS[priority] ?? '#64748b';
}

function priorityBg(priority: number): string {
    return PRIORITY_BG[priority] ?? 'rgba(100,116,139,.08)';
}

function fmtTime(ts: string): string {
    return new Date(ts).toLocaleTimeString('en-PH', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function goTrack(): void {
    if (latestReport.value) {
        router.push(`/track/${latestReport.value.token}`);
    }
}

function goReportAnother(): void {
    draft.reset();
    router.push('/report/type');
}

function goHome(): void {
    draft.reset();
    router.push('/');
}
</script>

<template>
    <div class="flex h-full flex-col bg-t-bg">
        <!-- Step bar -->
        <div
            class="shrink-0 border-b border-t-border bg-t-surface px-4 pb-3.5 pt-3.5"
        >
            <StepIndicator :current-step="3" />
        </div>

        <div
            v-if="latestReport"
            class="hide-scrollbar flex-1 overflow-y-auto"
        >
            <!-- Success animation -->
            <div
                class="flex flex-col items-center px-6 pb-6 pt-9 text-center"
            >
                <div class="mb-4" style="color: var(--t-p4)">
                    <svg
                        width="72"
                        height="72"
                        viewBox="0 0 64 64"
                        fill="none"
                    >
                        <circle
                            cx="32"
                            cy="32"
                            r="29"
                            stroke="currentColor"
                            stroke-width="3"
                            stroke-dasharray="200"
                            class="animate-circle"
                        />
                        <path
                            d="M18 33L27 42L46 22"
                            stroke="currentColor"
                            stroke-width="3.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-dasharray="60"
                            stroke-dashoffset="60"
                            class="animate-check"
                        />
                    </svg>
                </div>
                <div
                    class="-tracking-[0.3px] mb-1.5 text-[24px] font-extrabold text-t-text"
                >
                    Report Submitted
                </div>
                <div
                    class="max-w-[260px] text-[14px] leading-relaxed text-t-text-dim"
                >
                    Your report has been received by CDRRMO
                    Butuan City and is being reviewed.
                </div>
            </div>

            <!-- Tracking token -->
            <div class="mx-4 mb-3.5 text-center">
                <div
                    class="mb-2 font-mono text-[10px] uppercase tracking-[1.5px] text-t-text-faint"
                >
                    YOUR TRACKING ID
                </div>
                <div
                    class="font-mono text-[22px] font-bold tracking-wider"
                    style="color: var(--t-accent)"
                >
                    {{ latestReport.token }}
                </div>
            </div>

            <!-- Report summary card -->
            <div
                class="mx-4 mb-3.5 overflow-hidden rounded-[14px] border border-t-border bg-t-surface shadow-[0_2px_8px_rgba(0,0,0,.05)]"
            >
                <!-- Card header -->
                <div
                    class="border-b px-4 py-3.5"
                    :style="{
                        backgroundColor: priorityBg(
                            latestReport.priority
                        ),
                        borderBottomColor: `${priorityColor(latestReport.priority)}20`,
                    }"
                >
                    <div
                        class="flex items-start justify-between"
                    >
                        <div>
                            <div
                                class="mb-1 text-[15px] font-bold text-t-text"
                            >
                                {{ latestReport.type }}
                            </div>
                            <PriorityBadge
                                :priority="
                                    latestReport.priority
                                "
                            />
                        </div>
                        <StatusBadge
                            :status="
                                citizenStatus(
                                    latestReport.status
                                )
                            "
                        />
                    </div>
                </div>
                <!-- Card body -->
                <div class="px-4 py-3.5">
                    <div
                        v-for="[label, value] in [
                            [
                                'Report ID',
                                latestReport.token,
                            ],
                            [
                                'Location',
                                latestReport.barangay
                                    ? `Brgy. ${latestReport.barangay}`
                                    : 'GPS Detected',
                            ],
                            [
                                'Submitted',
                                fmtTime(
                                    latestReport.submittedAt
                                ),
                            ],
                        ]"
                        :key="label"
                        class="mb-2.5 flex items-center justify-between gap-3 text-[13px]"
                    >
                        <span
                            class="shrink-0 font-mono text-[10px] text-t-text-faint"
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
                            class="mb-1 font-mono text-[10px] text-t-text-faint"
                        >
                            DESCRIPTION
                        </div>
                        <div
                            class="text-[13px] leading-relaxed text-t-text-mid"
                        >
                            {{ latestReport.description }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status pipeline (vertical) -->
            <div
                class="mx-4 mb-3.5 rounded-[14px] border border-t-border bg-t-surface px-4 py-3.5 shadow-[0_2px_8px_rgba(0,0,0,.04)]"
            >
                <div
                    class="mb-3.5 font-mono text-[10px] uppercase tracking-[1.5px] text-t-text-faint"
                >
                    RESPONSE PIPELINE
                </div>
                <div class="relative">
                    <!-- Connecting line -->
                    <div
                        class="absolute bottom-3.5 left-[15px] top-3.5 w-[1.5px] bg-t-border"
                    />
                    <div
                        v-for="(stage, i) in [
                            'Received',
                            'Verified',
                            'Dispatched',
                            'Resolved',
                        ]"
                        :key="stage"
                        class="relative z-[1] flex items-start gap-3"
                        :class="{ 'mb-[18px]': i < 3 }"
                    >
                        <div
                            class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-full border-2"
                            :style="{
                                backgroundColor:
                                    i === 0
                                        ? 'var(--t-accent)'
                                        : '#fff',
                                borderColor:
                                    i === 0
                                        ? 'var(--t-accent)'
                                        : 'var(--t-border)',
                                boxShadow:
                                    i === 0
                                        ? '0 0 0 4px rgba(37,99,235,.1)'
                                        : 'none',
                            }"
                        >
                            <svg
                                v-if="i === 0"
                                width="12"
                                height="12"
                                viewBox="0 0 12 12"
                                fill="none"
                            >
                                <path
                                    d="M2 6L5 9L10 3"
                                    stroke="#fff"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            <div
                                v-else
                                class="h-2 w-2 rounded-full bg-t-border"
                            />
                        </div>
                        <div class="pt-1">
                            <div
                                class="text-[13px]"
                                :class="
                                    i === 0
                                        ? 'font-bold text-t-text'
                                        : 'font-medium text-t-text-faint'
                                "
                            >
                                {{ stage }}
                            </div>
                            <div
                                v-if="i === 0"
                                class="mt-0.5 text-[11px] text-t-text-dim"
                            >
                                {{
                                    fmtTime(
                                        latestReport.submittedAt
                                    )
                                }}
                                -- System
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- What happens next -->
            <div
                class="mx-4 mb-3.5 rounded-[14px] border border-t-border bg-t-surface px-4 py-3.5"
            >
                <div class="mb-3 text-[14px] font-bold text-t-text">
                    What happens next?
                </div>
                <div
                    v-for="(step, i) in [
                        {
                            n: '1',
                            t: 'Report is reviewed by CDRRMO operators',
                        },
                        {
                            n: '2',
                            t: 'Response team is dispatched to your location',
                        },
                        {
                            n: '3',
                            t: 'Track your report status in My Reports',
                        },
                    ]"
                    :key="i"
                    class="mb-2.5 flex items-start gap-3"
                >
                    <div
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-mono text-[11px] font-bold text-white"
                        style="background-color: var(--t-accent)"
                    >
                        {{ step.n }}
                    </div>
                    <div
                        class="pt-0.5 text-[13px] leading-snug text-t-text-mid"
                    >
                        {{ step.t }}
                    </div>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex gap-2.5 px-4 pb-8">
                <button
                    class="flex-1 cursor-pointer rounded-xl border-[1.5px] border-t-border bg-t-surface py-3.5 text-[14px] font-semibold text-t-text-mid"
                    @click="goHome"
                >
                    Done
                </button>
                <button
                    class="flex-1 cursor-pointer rounded-xl border-none py-3.5 text-[14px] font-bold text-white"
                    style="
                        background-color: var(--t-accent);
                        box-shadow: 0 4px 16px
                            rgba(37, 99, 235, 0.25);
                    "
                    @click="goTrack"
                >
                    Track Report
                </button>
            </div>

            <!-- Report another link -->
            <div class="pb-8 text-center">
                <button
                    class="cursor-pointer border-none bg-transparent text-[13px] font-semibold underline"
                    style="color: var(--t-accent)"
                    @click="goReportAnother"
                >
                    Report Another Emergency
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes circleDraw {
    from {
        stroke-dashoffset: 200;
    }
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes checkDraw {
    from {
        stroke-dashoffset: 60;
    }
    to {
        stroke-dashoffset: 0;
    }
}

.animate-circle {
    animation: circleDraw 0.6s ease forwards;
}

.animate-check {
    animation: checkDraw 0.5s 0.5s ease forwards;
}
</style>
