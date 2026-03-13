<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

defineOptions({ layout: AppLayout });

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/users' },
    { title: 'Units', href: '/admin/units' },
    { title: 'Form', href: '#' },
];
</script>

<template>
    <Head title="Unit Form" />
    <div class="space-y-6">
        <Heading
            title="Unit Form"
            description="Create or edit a unit."
            :breadcrumbs="breadcrumbs"
        />
        <p class="text-muted-foreground">
            Unit form - coming in Plan 11-02.
        </p>
    </div>
</template>
