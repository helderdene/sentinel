<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import type { IncidentMessageItem } from '@/types/responder';

const props = defineProps<{
    message: IncidentMessageItem | null;
    isVisible: boolean;
}>();

const emit = defineEmits<{
    dismiss: [];
    'go-to-chat': [];
}>();

const dismissTimer = ref<ReturnType<typeof setTimeout> | null>(null);

watch(
    () => props.isVisible,
    (visible) => {
        if (visible) {
            if (dismissTimer.value) {
                clearTimeout(dismissTimer.value);
            }

            dismissTimer.value = setTimeout(() => {
                emit('dismiss');
            }, 4000);
        }
    },
);

onUnmounted(() => {
    if (dismissTimer.value) {
        clearTimeout(dismissTimer.value);
    }
});

function handleTap(): void {
    if (dismissTimer.value) {
        clearTimeout(dismissTimer.value);
    }

    emit('go-to-chat');
}
</script>

<template>
    <Transition name="banner-slide">
        <div
            v-if="isVisible && message"
            class="fixed inset-x-0 top-[44px] z-50 px-3 pt-2"
            @click="handleTap"
        >
            <div
                class="flex cursor-pointer items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-50 px-4 py-3 shadow-lg dark:bg-emerald-950/80"
            >
                <!-- Chat icon -->
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500/20"
                >
                    <svg
                        class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M3.43 2.524A41.29 41.29 0 0110 2c2.236 0 4.43.18 6.57.524 1.437.231 2.43 1.49 2.43 2.902v5.148c0 1.413-.993 2.67-2.43 2.902a41.202 41.202 0 01-5.183.501.78.78 0 00-.528.224l-3.579 3.58A.75.75 0 016 17.25v-3.443a41.033 41.033 0 01-2.57-.33C2.993 13.244 2 11.986 2 10.574V5.426c0-1.413.993-2.67 2.43-2.902z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>

                <!-- Message content -->
                <div class="min-w-0 flex-1">
                    <p
                        class="text-xs font-semibold text-emerald-700 dark:text-emerald-300"
                    >
                        {{ message.sender?.name ?? 'Dispatch' }}
                    </p>

                    <p
                        class="truncate text-sm text-emerald-800 dark:text-emerald-200"
                    >
                        {{ message.body }}
                    </p>
                </div>

                <!-- Tap hint -->
                <span
                    class="shrink-0 text-[10px] font-medium text-emerald-600/60 dark:text-emerald-400/60"
                >
                    Tap to view
                </span>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.banner-slide-enter-active,
.banner-slide-leave-active {
    transition:
        transform 0.3s ease-out,
        opacity 0.3s ease-out;
}

.banner-slide-enter-from {
    transform: translateY(-100%);
    opacity: 0;
}

.banner-slide-leave-to {
    transform: translateY(-100%);
    opacity: 0;
}
</style>
