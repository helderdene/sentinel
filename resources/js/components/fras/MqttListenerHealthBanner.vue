<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';
import type { MqttListenerHealthStatus } from '@/types/mqtt';

const props = defineProps<{
    status: MqttListenerHealthStatus;
    lastMessageReceivedAt: string | null;
    since: string | null;
}>();

const isSilent = computed(
    () => props.status === 'SILENT' || props.status === 'DISCONNECTED',
);

const label = computed(() => {
    if (props.status === 'DISCONNECTED') {
        return 'MQTT broker disconnected';
    }

    return 'MQTT listener silent -- camera events may be delayed';
});
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
            v-if="isSilent"
            class="flex items-center justify-center gap-2 overflow-hidden bg-red-600 px-4 py-2 text-sm font-medium text-white"
            role="alert"
        >
            <AlertTriangle class="size-4" />
            <span>{{ label }}</span>
            <span v-if="lastMessageReceivedAt" class="opacity-80">
                -- last message {{ lastMessageReceivedAt }}
            </span>
        </div>
    </Transition>
</template>
