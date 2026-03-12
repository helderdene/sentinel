<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ConnectionBanner from '@/components/ConnectionBanner.vue';
import { useWebSocket } from '@/composables/useWebSocket';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { bannerLevel, isSyncing } = useWebSocket();
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <ConnectionBanner
                :banner-level="bannerLevel"
                :is-syncing="isSyncing"
            />
            <slot />
        </AppContent>
    </AppShell>
</template>
