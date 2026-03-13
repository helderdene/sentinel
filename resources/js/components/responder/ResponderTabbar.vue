<script setup lang="ts">
import { computed } from 'vue';
import type { ResponderTab } from '@/types/responder';

const props = defineProps<{
    activeTab: ResponderTab;
    middleTab: 'nav' | 'scene';
    unreadCount: number;
}>();

const emit = defineEmits<{
    'update:activeTab': [tab: ResponderTab];
}>();

const middleLabel = computed(() =>
    props.middleTab === 'nav' ? 'Nav' : 'Scene',
);

const tabs = computed(() => [
    {
        key: 'assignment' as ResponderTab,
        label: 'Assignment',
        icon: 'clipboard',
        badge: 0,
    },
    {
        key: props.middleTab as ResponderTab,
        label: middleLabel.value,
        icon: props.middleTab === 'nav' ? 'navigation' : 'document',
        badge: 0,
    },
    {
        key: 'chat' as ResponderTab,
        label: 'Chat',
        icon: 'message',
        badge: props.unreadCount,
    },
]);

function isActive(tabKey: ResponderTab): boolean {
    if (tabKey === 'nav' || tabKey === 'scene') {
        return props.activeTab === 'nav' || props.activeTab === 'scene';
    }

    return props.activeTab === tabKey;
}

function tabColor(tabKey: ResponderTab): string {
    return isActive(tabKey) ? 'var(--t-accent)' : 'var(--t-text-faint)';
}
</script>

<template>
    <nav
        class="flex h-20 shrink-0 items-start border-t border-t-border bg-t-surface pt-2.5"
    >
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="flex flex-1 cursor-pointer flex-col items-center gap-0.5 border-none bg-transparent px-0 py-1"
            @click="emit('update:activeTab', tab.key)"
        >
            <div class="relative">
                <!-- Clipboard / Assignment icon -->
                <svg
                    v-if="tab.icon === 'clipboard'"
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    :style="{ color: tabColor(tab.key) }"
                >
                    <rect
                        x="5"
                        y="3"
                        width="14"
                        height="18"
                        rx="2"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />
                    <path
                        d="M9 3V5H15V3"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <line
                        x1="9"
                        y1="10"
                        x2="15"
                        y2="10"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />
                    <line
                        x1="9"
                        y1="14"
                        x2="13"
                        y2="14"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />
                </svg>

                <!-- Navigation arrow icon -->
                <svg
                    v-if="tab.icon === 'navigation'"
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    :style="{ color: tabColor(tab.key) }"
                >
                    <path
                        d="M3 11L22 2L13 21L11 13L3 11Z"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>

                <!-- Document / Scene icon -->
                <svg
                    v-if="tab.icon === 'document'"
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    :style="{ color: tabColor(tab.key) }"
                >
                    <path
                        d="M14 2H6C4.9 2 4 2.9 4 4V20C4 21.1 4.9 22 6 22H18C19.1 22 20 21.1 20 20V8L14 2Z"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <polyline
                        points="14 2 14 8 20 8"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <line
                        x1="8"
                        y1="13"
                        x2="16"
                        y2="13"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />
                    <line
                        x1="8"
                        y1="17"
                        x2="12"
                        y2="17"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />
                </svg>

                <!-- Message / Chat icon -->
                <svg
                    v-if="tab.icon === 'message'"
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    :style="{ color: tabColor(tab.key) }"
                >
                    <path
                        d="M21 15C21 15.53 20.79 16.04 20.41 16.41C20.04 16.79 19.53 17 19 17H7L3 21V5C3 4.47 3.21 3.96 3.59 3.59C3.96 3.21 4.47 3 5 3H19C19.53 3 20.04 3.21 20.41 3.59C20.79 3.96 21 4.47 21 5V15Z"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>

                <span
                    v-if="tab.badge > 0"
                    class="absolute -top-1.5 -right-2.5 flex size-4 items-center justify-center rounded-full bg-t-p1 font-mono text-[9px] font-bold text-white"
                >
                    {{ tab.badge > 99 ? '99+' : tab.badge }}
                </span>
            </div>
            <span
                class="text-[10px] font-medium tracking-wide"
                :style="{ color: tabColor(tab.key) }"
            >
                {{ tab.label }}
            </span>
        </button>
    </nav>
</template>
