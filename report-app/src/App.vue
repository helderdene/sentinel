<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const isReportFlow = computed(() => {
    return route.path.startsWith('/report/');
});
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-t-bg text-t-text">
        <main class="flex-1 overflow-y-auto">
            <router-view />
        </main>
        <!-- BottomNav placeholder - wired in Task 2 -->
        <div v-if="!isReportFlow" class="h-20 shrink-0 border-t border-t-border bg-t-surface" />
    </div>
</template>
