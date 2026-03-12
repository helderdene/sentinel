<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    user?: Record<string, unknown>;
    roles: Array<Record<string, string>>;
    units: Array<Record<string, unknown>>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Users', href: '#' },
    { title: 'Form', href: '#' },
];
</script>

<template>
    <Head title="User Form" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <h1>User Form</h1>
        </div>
    </AppLayout>
</template>
