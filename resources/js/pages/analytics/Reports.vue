<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface PaginatedReports {
    data: Array<{
        id: number;
        type: string;
        title: string;
        period: string;
        file_path: string;
        csv_path: string | null;
        status: string;
        created_at: string;
    }>;
    links: object;
    meta: object;
}

defineProps<{
    reports: PaginatedReports;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Analytics', href: '/analytics' },
    { title: 'Reports', href: '/analytics/reports' },
];
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <h1
                class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100"
            >
                Reports Download Center
            </h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Generated compliance reports -- full implementation in Plan 02.
            </p>
        </div>
    </AppLayout>
</template>
