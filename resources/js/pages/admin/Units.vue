<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

defineOptions({ layout: AppLayout });

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/users' },
    { title: 'Units', href: '/admin/units' },
];
</script>

<template>
    <Head title="Units" />
    <div class="space-y-6">
        <Heading
            title="Units"
            description="Manage response units."
            :breadcrumbs="breadcrumbs"
        />
        <p class="text-muted-foreground">
            Units management page - coming in Plan 11-02.
        </p>
    </div>
</template>
