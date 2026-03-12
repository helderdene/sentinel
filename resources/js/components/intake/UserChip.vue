<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import IntakeIconLogout from '@/components/intake/icons/IntakeIconLogout.vue';
import RoleBadge from '@/components/intake/RoleBadge.vue';
import { logout } from '@/routes';
import type { User } from '@/types/auth';

type Props = {
    user: User;
};

const props = defineProps<Props>();
const open = ref(false);

const initials = computed(() => {
    const parts = props.user.name.split(/[\s,]+/).filter(Boolean);

    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }

    return props.user.name.slice(0, 2).toUpperCase();
});

const roleKey = computed(() => {
    const role = props.user.role;

    if (role === 'admin' || role === 'supervisor') {
        return role;
    }

    return 'operator';
});

const permissionLabels: Record<string, string> = {
    create_incidents: 'Triage',
    dispatch_units: 'Submit Dispatch',
    manage_users: 'Manage Users',
    view_analytics: 'View Analytics',
    view_all_incidents: 'View All Incidents',
    manage_system: 'Manage System',
};

const permissions = computed(() =>
    Object.entries(props.user.can)
        .filter(([key]) => key in permissionLabels)
        .map(([key, value]) => ({
            label: permissionLabels[key],
            allowed: value,
        })),
);

function toggle() {
    open.value = !open.value;
}

function closeDropdown() {
    open.value = false;
}
</script>

<template>
    <div class="relative">
        <button
            class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition-colors hover:bg-t-bg dark:hover:bg-white/5"
            @click="toggle"
        >
            <span
                class="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-t-brand/10 font-mono text-[10px] font-bold text-t-brand"
            >
                {{ initials }}
            </span>
            <span class="text-xs text-t-text dark:text-t-text">
                {{ user.name }}
            </span>
            <RoleBadge :role-key="roleKey" small />
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="open"
                class="absolute top-full right-0 z-50 mt-1 w-64 rounded-lg border border-t-border bg-t-surface shadow-[0_8px_24px_rgba(0,0,0,.12)] dark:border-t-border dark:bg-t-surface"
                @mouseleave="closeDropdown"
            >
                <div
                    class="flex items-center gap-3 border-b border-t-border p-3"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-t-brand/10 font-mono text-sm font-bold text-t-brand"
                    >
                        {{ initials }}
                    </span>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium text-t-text">
                            {{ user.name }}
                        </div>
                        <RoleBadge :role-key="roleKey" small class="mt-0.5" />
                    </div>
                </div>

                <div class="border-b border-t-border px-3 py-2">
                    <div
                        class="mb-1.5 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        PERMISSIONS
                    </div>
                    <div class="space-y-1">
                        <div
                            v-for="perm in permissions"
                            :key="perm.label"
                            class="flex items-center justify-between text-[11px]"
                        >
                            <span class="text-t-text-dim">
                                {{ perm.label }}
                            </span>
                            <span
                                class="font-mono text-[9px] font-bold"
                                :class="
                                    perm.allowed
                                        ? 'text-t-online'
                                        : 'text-t-text-faint'
                                "
                            >
                                {{ perm.allowed ? 'YES' : 'NO' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="p-2">
                    <Link
                        :href="logout()"
                        method="post"
                        as="button"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs text-t-p1 transition-colors hover:bg-t-p1/5"
                        @click="closeDropdown"
                    >
                        <IntakeIconLogout :size="14" color="var(--t-p1)" />
                        Sign Out
                    </Link>
                </div>
            </div>
        </Transition>
    </div>
</template>
