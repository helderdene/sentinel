<script setup lang="ts">
import type { ResponderUnit } from '@/types/responder';

defineProps<{
    unit: ResponderUnit;
    connectionStatus: string;
}>();
</script>

<template>
    <div class="flex flex-1 flex-col items-center justify-center gap-6 px-6">
        <!-- Callsign -->
        <div class="text-center">
            <span class="font-mono text-[26px] font-extrabold text-t-text">
                {{ unit.callsign }}
            </span>
        </div>

        <!-- Waiting animation -->
        <div class="relative flex items-center justify-center">
            <span class="standby-ring standby-ring--1" />
            <span class="standby-ring standby-ring--2" />
            <span class="standby-ring standby-ring--3" />
            <span
                class="relative z-10 size-3 rounded-full"
                :style="{
                    backgroundColor:
                        connectionStatus === 'online'
                            ? 'var(--t-online)'
                            : connectionStatus === 'reconnecting'
                              ? 'var(--t-p3)'
                              : 'var(--t-p1)',
                }"
            />
        </div>

        <!-- Status text -->
        <div class="text-center">
            <p
                class="font-mono text-[11px] font-semibold tracking-[1.5px] text-t-text-faint uppercase"
            >
                Standing By
            </p>
            <p
                v-if="connectionStatus === 'online'"
                class="mt-1 text-[11px] text-t-text-faint"
            >
                Connected to dispatch
            </p>
            <p
                v-else-if="connectionStatus === 'reconnecting'"
                class="mt-1 text-[11px]"
                :style="{ color: 'var(--t-p3)' }"
            >
                Reconnecting...
            </p>
            <p
                v-else
                class="mt-1 text-[11px]"
                :style="{ color: 'var(--t-p1)' }"
            >
                Disconnected
            </p>
        </div>
    </div>
</template>

<style scoped>
.standby-ring {
    position: absolute;
    border-radius: 50%;
    border: 1px solid var(--t-online);
    opacity: 0;
    animation: standby-pulse 3s ease-out infinite;
}

.standby-ring--1 {
    width: 40px;
    height: 40px;
    animation-delay: 0s;
}

.standby-ring--2 {
    width: 60px;
    height: 60px;
    animation-delay: 1s;
}

.standby-ring--3 {
    width: 80px;
    height: 80px;
    animation-delay: 2s;
}

@keyframes standby-pulse {
    0% {
        transform: scale(0.5);
        opacity: 0.5;
    }
    100% {
        transform: scale(1);
        opacity: 0;
    }
}
</style>
