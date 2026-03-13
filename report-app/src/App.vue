<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import BottomNav from '@/components/BottomNav.vue';

const route = useRoute();

const isReportFlow = computed(() => {
    return route.path.startsWith('/report/');
});
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-t-bg text-t-text">
        <main
            class="hide-scrollbar flex-1 overflow-y-auto"
            :class="{ 'pb-20': !isReportFlow }"
        >
            <router-view v-slot="{ Component }">
                <transition name="fade" mode="out-in">
                    <component :is="Component" />
                </transition>
            </router-view>
        </main>
        <BottomNav v-if="!isReportFlow" />
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
