<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    barangay: Record<string, unknown>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Barangays', href: '#' },
    { title: 'Edit', href: '#' },
];
</script>

<template>
    <Head title="Edit Barangay" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <h1>Edit Barangay</h1>
        </div>
    </AppLayout>
</template>
