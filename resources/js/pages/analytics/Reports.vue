<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { BarChart3, FileDown, Loader2, Map, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import {
    dashboard,
    generateReport,
    heatmap,
    reports as reportsRoute,
} from '@/actions/App/Http/Controllers/AnalyticsController';
import ReportRow from '@/components/analytics/ReportRow.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { GeneratedReport } from '@/types/analytics';

interface PaginatedReports {
    data: GeneratedReport[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    meta?: {
        current_page: number;
        last_page: number;
    };
}

const props = defineProps<{
    reports: PaginatedReports;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Analytics', href: '/analytics' },
    { title: 'Reports', href: '/analytics/reports' },
];

// Generate report forms
const showQuarterlyForm = ref(false);
const showAnnualForm = ref(false);

const currentYear = new Date().getFullYear();
const currentQuarter = Math.ceil((new Date().getMonth() + 1) / 3);

const quarterlyForm = useForm({
    type: 'quarterly',
    period: `Q${currentQuarter}-${currentYear}`,
});

const annualForm = useForm({
    type: 'annual',
    period: String(currentYear),
});

function submitQuarterly(): void {
    quarterlyForm.post(generateReport.url(), {
        preserveScroll: true,
        onSuccess: () => {
            showQuarterlyForm.value = false;
        },
    });
}

function submitAnnual(): void {
    annualForm.post(generateReport.url(), {
        preserveScroll: true,
        onSuccess: () => {
            showAnnualForm.value = false;
        },
    });
}

// Auto-polling for generating reports
const hasGeneratingReports = computed(() =>
    props.reports.data.some((r) => r.status === 'generating'),
);

const { pause, resume } = useIntervalFn(
    () => {
        router.reload({ only: ['reports'] });
    },
    5000,
    { immediate: false },
);

watch(
    hasGeneratingReports,
    (isGenerating) => {
        if (isGenerating) {
            resume();
        } else {
            pause();
        }
    },
    { immediate: true },
);

// Quarter/year options
const quarters = computed(() => {
    const opts: string[] = [];

    for (let y = currentYear; y >= currentYear - 2; y--) {
        for (let q = 4; q >= 1; q--) {
            if (y === currentYear && q > currentQuarter) {
                continue;
            }

            opts.push(`Q${q}-${y}`);
        }
    }

    return opts;
});

const years = computed(() => {
    const opts: number[] = [];

    for (let y = currentYear; y >= currentYear - 5; y--) {
        opts.push(y);
    }

    return opts;
});
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col">
            <!-- Tab Navigation -->
            <div
                class="flex items-center gap-1 border-b border-neutral-200 px-4 dark:border-neutral-800"
            >
                <Link
                    :href="dashboard.url()"
                    class="flex items-center gap-1.5 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                >
                    <BarChart3 class="h-4 w-4" />
                    Dashboard
                </Link>
                <Link
                    :href="heatmap.url()"
                    class="flex items-center gap-1.5 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                >
                    <Map class="h-4 w-4" />
                    Heatmap
                </Link>
                <Link
                    :href="reportsRoute.url()"
                    class="flex items-center gap-1.5 border-b-2 border-blue-600 px-3 py-2.5 text-sm font-medium text-blue-600 dark:text-blue-400"
                >
                    <FileDown class="h-4 w-4" />
                    Reports
                </Link>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-4">
                <!-- Generate Report Section -->
                <div
                    class="mb-6 rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-zinc-900"
                >
                    <h3
                        class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100"
                    >
                        Generate Report
                    </h3>

                    <div class="flex flex-wrap gap-3">
                        <!-- Quarterly -->
                        <div>
                            <button
                                v-if="!showQuarterlyForm"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-blue-700"
                                @click="showQuarterlyForm = true"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                Quarterly Report
                            </button>
                            <form
                                v-else
                                class="flex items-end gap-2"
                                @submit.prevent="submitQuarterly"
                            >
                                <div>
                                    <label
                                        class="mb-1 block text-xs text-neutral-500 dark:text-neutral-400"
                                    >
                                        Period
                                    </label>
                                    <select
                                        v-model="quarterlyForm.period"
                                        class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-zinc-800 dark:text-neutral-200"
                                    >
                                        <option
                                            v-for="q in quarters"
                                            :key="q"
                                            :value="q"
                                        >
                                            {{ q }}
                                        </option>
                                    </select>
                                </div>
                                <button
                                    type="submit"
                                    :disabled="quarterlyForm.processing"
                                    class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                                >
                                    <Loader2
                                        v-if="quarterlyForm.processing"
                                        class="h-3 w-3 animate-spin"
                                    />
                                    Generate
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-2 py-1.5 text-xs text-neutral-500 hover:text-neutral-700 dark:text-neutral-400"
                                    @click="showQuarterlyForm = false"
                                >
                                    Cancel
                                </button>
                            </form>
                        </div>

                        <!-- Annual -->
                        <div>
                            <button
                                v-if="!showAnnualForm"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-purple-600 px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-purple-700"
                                @click="showAnnualForm = true"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                Annual Report
                            </button>
                            <form
                                v-else
                                class="flex items-end gap-2"
                                @submit.prevent="submitAnnual"
                            >
                                <div>
                                    <label
                                        class="mb-1 block text-xs text-neutral-500 dark:text-neutral-400"
                                    >
                                        Year
                                    </label>
                                    <select
                                        v-model="annualForm.period"
                                        class="rounded border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-zinc-800 dark:text-neutral-200"
                                    >
                                        <option
                                            v-for="y in years"
                                            :key="y"
                                            :value="String(y)"
                                        >
                                            {{ y }}
                                        </option>
                                    </select>
                                </div>
                                <button
                                    type="submit"
                                    :disabled="annualForm.processing"
                                    class="inline-flex items-center gap-1 rounded-lg bg-purple-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-purple-700 disabled:opacity-50"
                                >
                                    <Loader2
                                        v-if="annualForm.processing"
                                        class="h-3 w-3 animate-spin"
                                    />
                                    Generate
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-2 py-1.5 text-xs text-neutral-500 hover:text-neutral-700 dark:text-neutral-400"
                                    @click="showAnnualForm = false"
                                >
                                    Cancel
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Polling Indicator -->
                <div
                    v-if="hasGeneratingReports"
                    class="mb-4 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400"
                >
                    <Loader2 class="h-3 w-3 animate-spin" />
                    Checking for updates...
                </div>

                <!-- Report List -->
                <div v-if="reports.data.length > 0" class="space-y-3">
                    <ReportRow
                        v-for="report in reports.data"
                        :key="report.id"
                        :report="report"
                    />
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="flex flex-col items-center justify-center py-16 text-neutral-400"
                >
                    <FileDown class="mb-3 h-10 w-10" />
                    <p class="text-sm">No reports generated yet</p>
                    <p class="mt-1 text-xs">
                        Use the buttons above to generate quarterly or annual
                        reports.
                    </p>
                </div>

                <!-- Pagination -->
                <div
                    v-if="reports.links && reports.links.length > 3"
                    class="mt-6 flex justify-center gap-1"
                >
                    <template v-for="link in reports.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                            :class="
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-400 dark:hover:bg-neutral-700'
                            "
                            preserve-scroll
                        >
                            <span v-html="link.label" />
                        </Link>
                        <span
                            v-else
                            class="rounded-lg px-3 py-1.5 text-xs text-neutral-300 dark:text-neutral-600"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
