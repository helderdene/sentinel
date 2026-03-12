<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    type?: Record<string, unknown>;
    priorities: Array<Record<string, string>>;
    categories: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Incident Types', href: '#' },
    { title: 'Form', href: '#' },
];
</script>

<template>
    <Head title="Incident Type Form" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <h1>Incident Type Form</h1>
        </div>
    </AppLayout>
</template>
