<script setup lang="ts">
import { Loader2, Wifi, WifiOff } from 'lucide-vue-next';
import type { BannerLevel } from '@/composables/useWebSocket';

defineProps<{
    bannerLevel: BannerLevel;
    isSyncing: boolean;
}>();

const bannerClasses: Record<Exclude<BannerLevel, 'none'>, string> = {
    amber: 'bg-amber-500 text-white',
    red: 'bg-red-600 text-white',
    green: 'bg-green-500 text-white',
};
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="max-h-0 opacity-0"
        enter-to-class="max-h-12 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="max-h-12 opacity-100"
        leave-to-class="max-h-0 opacity-0"
    >
        <div
            v-if="bannerLevel !== 'none'"
            :class="[
                'flex items-center justify-center gap-2 overflow-hidden px-4 py-2 text-sm font-medium',
                bannerClasses[bannerLevel],
            ]"
        >
            <template v-if="bannerLevel === 'amber'">
                <Loader2 class="size-4 animate-spin" />
                <span>Reconnecting...</span>
            </template>
            <template v-else-if="bannerLevel === 'red'">
                <WifiOff class="size-4" />
                <span>Connection lost -- data may be outdated</span>
            </template>
            <template v-else-if="bannerLevel === 'green'">
                <Wifi class="size-4" />
                <span v-if="isSyncing">Connected -- syncing...</span>
                <span v-else>Connected</span>
            </template>
        </div>
    </Transition>
</template>
