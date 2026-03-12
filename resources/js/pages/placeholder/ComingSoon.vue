<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Construction } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    title: string;
    description?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: props.title,
        href: '#',
    },
];
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 items-center justify-center p-4">
            <div
                class="w-full max-w-md rounded-xl border border-sidebar-border/70 bg-white p-8 text-center dark:border-sidebar-border dark:bg-neutral-900"
            >
                <div
                    class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-neutral-100 dark:bg-neutral-800"
                >
                    <Construction
                        class="h-7 w-7 text-neutral-500 dark:text-neutral-400"
                    />
                </div>

                <h2
                    class="mb-2 text-xl font-semibold text-neutral-900 dark:text-neutral-100"
                >
                    {{ title }}
                </h2>

                <p
                    v-if="description"
                    class="mb-6 text-sm text-neutral-500 dark:text-neutral-400"
                >
                    {{ description }}
                </p>

                <p
                    v-else
                    class="mb-6 text-sm text-neutral-500 dark:text-neutral-400"
                >
                    This feature will be available in a future update.
                </p>

                <Link
                    :href="dashboard()"
                    class="inline-flex items-center gap-2 text-sm font-medium text-neutral-600 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Dashboard
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
