<script setup lang="ts">
import {
    ClipboardList,
    FileText,
    MessageSquare,
    Navigation2,
} from 'lucide-vue-next';
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

const middleIcon = computed(() =>
    props.middleTab === 'nav' ? Navigation2 : FileText,
);

const middleLabel = computed(() =>
    props.middleTab === 'nav' ? 'Nav' : 'Scene',
);

const tabs = computed(() => [
    {
        key: 'assignment' as ResponderTab,
        label: 'Assignment',
        icon: ClipboardList,
        badge: 0,
    },
    {
        key: props.middleTab as ResponderTab,
        label: middleLabel.value,
        icon: middleIcon.value,
        badge: 0,
    },
    {
        key: 'chat' as ResponderTab,
        label: 'Chat',
        icon: MessageSquare,
        badge: props.unreadCount,
    },
]);

function isActive(tabKey: ResponderTab): boolean {
    if (tabKey === 'nav' || tabKey === 'scene') {
        return props.activeTab === 'nav' || props.activeTab === 'scene';
    }

    return props.activeTab === tabKey;
}
</script>

<template>
    <nav
        class="flex h-[56px] shrink-0 items-stretch border-t border-t-border bg-t-surface"
    >
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="relative flex min-h-[44px] flex-1 flex-col items-center justify-center gap-0.5 transition-colors"
            :class="
                isActive(tab.key)
                    ? 'border-t-2 border-t-accent text-t-accent'
                    : 'border-t-2 border-transparent text-t-text-dim'
            "
            @click="emit('update:activeTab', tab.key)"
        >
            <div class="relative">
                <component :is="tab.icon" :size="20" />
                <span
                    v-if="tab.badge > 0"
                    class="absolute -top-1.5 -right-2.5 flex size-4 items-center justify-center rounded-full bg-red-500 font-mono text-[9px] font-bold text-white"
                >
                    {{ tab.badge > 99 ? '99+' : tab.badge }}
                </span>
            </div>
            <span class="text-[10px] font-semibold">{{ tab.label }}</span>
        </button>
    </nav>
</template>
