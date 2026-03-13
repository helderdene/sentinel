<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();

const tabs = [
    { name: 'Home', path: '/', icon: 'home' },
    { name: 'My Reports', path: '/reports', icon: 'list' },
    { name: 'About', path: '/about', icon: 'info' },
] as const;

const activeTab = computed(() => {
    const path = route.path;
    if (path === '/') return '/';
    if (path.startsWith('/reports') || path.startsWith('/track')) return '/reports';
    if (path.startsWith('/about')) return '/about';
    return '/';
});

function navigate(path: string): void {
    router.push(path);
}
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-50 flex h-20 shrink-0 items-start border-t border-t-border bg-t-surface pt-2.5">
        <button
            v-for="tab in tabs"
            :key="tab.path"
            class="flex flex-1 cursor-pointer flex-col items-center gap-0.5 border-none bg-transparent px-0 py-1"
            @click="navigate(tab.path)"
        >
            <!-- Home icon -->
            <svg
                v-if="tab.icon === 'home'"
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                :style="{ color: activeTab === tab.path ? 'var(--t-accent)' : 'var(--t-text-faint)' }"
            >
                <path
                    d="M3 12L12 3L21 12"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <path
                    d="M5 10V20H10V14H14V20H19V10"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>

            <!-- List icon -->
            <svg
                v-if="tab.icon === 'list'"
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                :style="{ color: activeTab === tab.path ? 'var(--t-accent)' : 'var(--t-text-faint)' }"
            >
                <rect x="3" y="5" width="18" height="3" rx="1.5" stroke="currentColor" stroke-width="1.8" />
                <rect x="3" y="11" width="18" height="3" rx="1.5" stroke="currentColor" stroke-width="1.8" />
                <rect x="3" y="17" width="12" height="3" rx="1.5" stroke="currentColor" stroke-width="1.8" />
            </svg>

            <!-- Info icon -->
            <svg
                v-if="tab.icon === 'info'"
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                :style="{ color: activeTab === tab.path ? 'var(--t-accent)' : 'var(--t-text-faint)' }"
            >
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" />
                <line x1="12" y1="11" x2="12" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <circle cx="12" cy="7.5" r="1" fill="currentColor" />
            </svg>

            <span
                class="text-[10px] font-medium tracking-wide"
                :style="{ color: activeTab === tab.path ? 'var(--t-accent)' : 'var(--t-text-faint)' }"
            >
                {{ tab.name }}
            </span>
        </button>
    </nav>
</template>
