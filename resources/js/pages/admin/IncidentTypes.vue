<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    types: Array<Record<string, unknown>>;
    categories: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Incident Types', href: '#' },
];
</script>

<template>
    <Head title="Incident Types" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <h1>Incident Types</h1>
        </div>
    </AppLayout>
</template>
