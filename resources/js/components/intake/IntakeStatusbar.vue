<script setup lang="ts">
import { computed } from 'vue';
import type { User } from '@/types/auth';

type ConnectionStatus = 'online' | 'reconnecting' | 'disconnected';

type Props = {
    connectionStatus?: ConnectionStatus;
    user: User;
};

const props = withDefaults(defineProps<Props>(), {
    connectionStatus: 'online',
});

const statusConfig = computed(() => {
    switch (props.connectionStatus) {
        case 'reconnecting':
            return {
                dotColor: 'bg-yellow-500',
                textColor: 'text-yellow-600 dark:text-yellow-400',
                label: 'RECONNECTING...',
                animate: true,
            };

        case 'disconnected':
            return {
                dotColor: 'bg-red-500',
                textColor: 'text-red-600 dark:text-red-400',
                label: 'CONNECTION LOST',
                animate: false,
            };

        default:
            return {
                dotColor: 'bg-t-online',
                textColor: 'text-t-online',
                label: 'INTAKE ONLINE',
                animate: true,
            };
    }
});

const roleLabel = computed(() => {
    const role = props.user.role;

    if (role === 'admin' || role === 'supervisor') {
        return role.toUpperCase();
    }

    return 'OPERATOR';
});
</script>

<template>
    <footer
        class="flex h-6 shrink-0 items-center justify-between border-t border-t-border bg-t-surface px-4 dark:border-t-border dark:bg-t-surface"
    >
        <!-- Left: connection status -->
        <div class="flex items-center gap-1.5">
            <span
                class="size-1.5 rounded-full"
                :class="[
                    statusConfig.dotColor,
                    statusConfig.animate
                        ? 'animate-[pulse_3s_ease-in-out_infinite]'
                        : '',
                ]"
            />
            <span
                class="font-mono text-[9.5px] font-bold tracking-[2px]"
                :class="statusConfig.textColor"
            >
                {{ statusConfig.label }}
            </span>
        </div>

        <!-- Right: metadata -->
        <div
            class="flex items-center gap-2 font-mono text-[9px] text-t-text-faint"
        >
            <span class="uppercase">{{ roleLabel }}</span>
            <span class="text-t-text-dim">{{ user.name }}</span>
            <span class="text-t-border-med">|</span>
            <span>CDRRMO - BUTUAN CITY</span>
            <span
                class="rounded bg-t-bg px-1.5 py-[1px] text-[8px] font-bold dark:bg-white/5"
            >
                v1.0
            </span>
        </div>
    </footer>
</template>
