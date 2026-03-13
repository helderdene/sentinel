<script setup lang="ts">
import { Download, FileText, Loader2 } from 'lucide-vue-next';

import { downloadReport } from '@/actions/App/Http/Controllers/AnalyticsController';
import type { GeneratedReport } from '@/types/analytics';

defineProps<{
    report: GeneratedReport;
}>();

const TYPE_BADGES: Record<string, { label: string; class: string }> = {
    quarterly: {
        label: 'Quarterly',
        class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    },
    annual: {
        label: 'Annual',
        class: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    },
    dilg_monthly: {
        label: 'DILG',
        class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    },
    ndrrmc_sitrep: {
        label: 'NDRRMC',
        class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    },
};

const STATUS_BADGES: Record<string, { label: string; class: string }> = {
    generating: {
        label: 'Generating',
        class: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    },
    ready: {
        label: 'Ready',
        class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    },
    failed: {
        label: 'Failed',
        class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    },
};

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div
        class="flex items-center justify-between rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-zinc-900"
    >
        <div class="flex items-center gap-3">
            <div
                class="flex h-9 w-9 items-center justify-center rounded-lg bg-neutral-100 dark:bg-neutral-800"
            >
                <FileText
                    class="h-4 w-4 text-neutral-500 dark:text-neutral-400"
                />
            </div>

            <div>
                <div class="flex items-center gap-2">
                    <h4
                        class="text-sm font-medium text-neutral-900 dark:text-neutral-100"
                    >
                        {{ report.title }}
                    </h4>
                    <span
                        class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium"
                        :class="
                            TYPE_BADGES[report.type]?.class ??
                            'bg-neutral-100 text-neutral-600'
                        "
                    >
                        {{ TYPE_BADGES[report.type]?.label ?? report.type }}
                    </span>
                </div>
                <div
                    class="mt-0.5 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400"
                >
                    <span>{{ report.period }}</span>
                    <span>{{ formatDate(report.created_at) }}</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <!-- Status Badge -->
            <span
                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium"
                :class="
                    STATUS_BADGES[report.status]?.class ??
                    'bg-neutral-100 text-neutral-600'
                "
            >
                <Loader2
                    v-if="report.status === 'generating'"
                    class="h-3 w-3 animate-spin"
                />
                {{ STATUS_BADGES[report.status]?.label ?? report.status }}
            </span>

            <!-- Download Buttons -->
            <template v-if="report.status === 'ready'">
                <a
                    :href="downloadReport.url(report.id)"
                    class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-700"
                >
                    <Download class="h-3 w-3" />
                    PDF
                </a>
                <a
                    v-if="report.csv_path"
                    :href="downloadReport.url(report.id) + '?format=csv'"
                    class="inline-flex items-center gap-1 rounded-lg bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-700 transition-colors hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700"
                >
                    <Download class="h-3 w-3" />
                    CSV
                </a>
            </template>

            <template v-if="report.status === 'generating'">
                <span class="text-xs text-neutral-400 italic">
                    Processing...
                </span>
            </template>
        </div>
    </div>
</template>
