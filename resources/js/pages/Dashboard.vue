<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    ClipboardList,
    Map,
    RadioTower,
    Shield,
    Truck,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { UserRole } from '@/types/auth';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

const page = usePage();
const userName = computed(
    () => (page.props.auth as { user?: { name?: string } })?.user?.name,
);
const userRole = computed(
    () =>
        (page.props.auth as { user?: { role?: UserRole } })?.user?.role ??
        'dispatcher',
);

const roleLabels: Record<UserRole, string> = {
    admin: 'Administrator',
    dispatcher: 'Dispatcher',
    responder: 'Responder',
    supervisor: 'Supervisor',
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <div>
                <h1
                    class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100"
                >
                    Welcome, {{ userName }}
                </h1>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ roleLabels[userRole] }} Dashboard
                </p>
            </div>

            <!-- Admin Dashboard -->
            <div
                v-if="userRole === 'admin'"
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-4"
            >
                <Link
                    href="/admin/users"
                    class="group rounded-xl border border-sidebar-border/70 p-5 transition-colors hover:bg-neutral-50 dark:border-sidebar-border dark:hover:bg-neutral-800/50"
                >
                    <Users
                        class="mb-3 h-6 w-6 text-neutral-400 group-hover:text-neutral-600 dark:group-hover:text-neutral-300"
                    />
                    <h3
                        class="font-medium text-neutral-900 dark:text-neutral-100"
                    >
                        User Management
                    </h3>
                    <p
                        class="mt-1 text-sm text-neutral-500 dark:text-neutral-400"
                    >
                        Manage users and roles
                    </p>
                </Link>
                <Link
                    href="/dispatch"
                    class="group rounded-xl border border-sidebar-border/70 p-5 transition-colors hover:bg-neutral-50 dark:border-sidebar-border dark:hover:bg-neutral-800/50"
                >
                    <Map
                        class="mb-3 h-6 w-6 text-neutral-400 group-hover:text-neutral-600 dark:group-hover:text-neutral-300"
                    />
                    <h3
                        class="font-medium text-neutral-900 dark:text-neutral-100"
                    >
                        Dispatch Console
                    </h3>
                    <p
                        class="mt-1 text-sm text-neutral-500 dark:text-neutral-400"
                    >
                        Real-time incident map
                    </p>
                </Link>
                <Link
                    href="/analytics"
                    class="group rounded-xl border border-sidebar-border/70 p-5 transition-colors hover:bg-neutral-50 dark:border-sidebar-border dark:hover:bg-neutral-800/50"
                >
                    <BarChart3
                        class="mb-3 h-6 w-6 text-neutral-400 group-hover:text-neutral-600 dark:group-hover:text-neutral-300"
                    />
                    <h3
                        class="font-medium text-neutral-900 dark:text-neutral-100"
                    >
                        Analytics
                    </h3>
                    <p
                        class="mt-1 text-sm text-neutral-500 dark:text-neutral-400"
                    >
                        KPI dashboard and reports
                    </p>
                </Link>
                <Link
                    href="/admin/users"
                    class="group rounded-xl border border-sidebar-border/70 p-5 transition-colors hover:bg-neutral-50 dark:border-sidebar-border dark:hover:bg-neutral-800/50"
                >
                    <Shield
                        class="mb-3 h-6 w-6 text-neutral-400 group-hover:text-neutral-600 dark:group-hover:text-neutral-300"
                    />
                    <h3
                        class="font-medium text-neutral-900 dark:text-neutral-100"
                    >
                        Admin Panel
                    </h3>
                    <p
                        class="mt-1 text-sm text-neutral-500 dark:text-neutral-400"
                    >
                        System configuration
                    </p>
                </Link>
            </div>

            <!-- Dispatcher Dashboard -->
            <div
                v-else-if="userRole === 'dispatcher'"
                class="grid gap-4 md:grid-cols-3"
            >
                <div
                    class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <div class="flex items-center justify-between">
                        <h3
                            class="text-sm font-medium text-neutral-500 dark:text-neutral-400"
                        >
                            Active Incidents
                        </h3>
                        <AlertTriangle
                            class="h-5 w-5 text-neutral-400 dark:text-neutral-500"
                        />
                    </div>
                    <p
                        class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100"
                    >
                        --
                    </p>
                    <p
                        class="mt-1 text-xs text-neutral-500 dark:text-neutral-400"
                    >
                        Data available after Phase 2
                    </p>
                </div>
                <div
                    class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <div class="flex items-center justify-between">
                        <h3
                            class="text-sm font-medium text-neutral-500 dark:text-neutral-400"
                        >
                            Queue Size
                        </h3>
                        <Map
                            class="h-5 w-5 text-neutral-400 dark:text-neutral-500"
                        />
                    </div>
                    <p
                        class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100"
                    >
                        --
                    </p>
                    <p
                        class="mt-1 text-xs text-neutral-500 dark:text-neutral-400"
                    >
                        Data available after Phase 2
                    </p>
                </div>
                <div
                    class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <div class="flex items-center justify-between">
                        <h3
                            class="text-sm font-medium text-neutral-500 dark:text-neutral-400"
                        >
                            Units Available
                        </h3>
                        <Truck
                            class="h-5 w-5 text-neutral-400 dark:text-neutral-500"
                        />
                    </div>
                    <p
                        class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100"
                    >
                        --
                    </p>
                    <p
                        class="mt-1 text-xs text-neutral-500 dark:text-neutral-400"
                    >
                        Data available after Phase 4
                    </p>
                </div>
            </div>

            <!-- Responder Dashboard -->
            <div v-else-if="userRole === 'responder'" class="grid gap-4">
                <div
                    class="rounded-xl border border-sidebar-border/70 p-6 text-center dark:border-sidebar-border"
                >
                    <RadioTower
                        class="mx-auto mb-3 h-10 w-10 text-neutral-300 dark:text-neutral-600"
                    />
                    <h3
                        class="text-lg font-medium text-neutral-900 dark:text-neutral-100"
                    >
                        No Active Assignment
                    </h3>
                    <p
                        class="mt-1 text-sm text-neutral-500 dark:text-neutral-400"
                    >
                        You will be notified when a new assignment is pushed to
                        you.
                    </p>
                    <p
                        class="mt-3 text-xs text-neutral-400 dark:text-neutral-500"
                    >
                        Assignment functionality coming in Phase 5
                    </p>
                </div>
                <div
                    class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <div class="flex items-center gap-3">
                        <ClipboardList
                            class="h-5 w-5 text-neutral-400 dark:text-neutral-500"
                        />
                        <div>
                            <h3
                                class="font-medium text-neutral-900 dark:text-neutral-100"
                            >
                                Recent Incidents
                            </h3>
                            <p
                                class="text-sm text-neutral-500 dark:text-neutral-400"
                            >
                                No incident history yet
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Supervisor Dashboard -->
            <div
                v-else-if="userRole === 'supervisor'"
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-4"
            >
                <div
                    class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <h3
                        class="text-sm font-medium text-neutral-500 dark:text-neutral-400"
                    >
                        Avg Response Time
                    </h3>
                    <p
                        class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100"
                    >
                        --
                    </p>
                    <p
                        class="mt-1 text-xs text-neutral-500 dark:text-neutral-400"
                    >
                        KPI data coming in Phase 7
                    </p>
                </div>
                <div
                    class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <h3
                        class="text-sm font-medium text-neutral-500 dark:text-neutral-400"
                    >
                        Unit Utilization
                    </h3>
                    <p
                        class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100"
                    >
                        --
                    </p>
                    <p
                        class="mt-1 text-xs text-neutral-500 dark:text-neutral-400"
                    >
                        KPI data coming in Phase 7
                    </p>
                </div>
                <div
                    class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <h3
                        class="text-sm font-medium text-neutral-500 dark:text-neutral-400"
                    >
                        Resolution Rate
                    </h3>
                    <p
                        class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100"
                    >
                        --
                    </p>
                    <p
                        class="mt-1 text-xs text-neutral-500 dark:text-neutral-400"
                    >
                        KPI data coming in Phase 7
                    </p>
                </div>
                <div
                    class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <h3
                        class="text-sm font-medium text-neutral-500 dark:text-neutral-400"
                    >
                        Active Incidents
                    </h3>
                    <p
                        class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100"
                    >
                        --
                    </p>
                    <p
                        class="mt-1 text-xs text-neutral-500 dark:text-neutral-400"
                    >
                        Real-time data coming in Phase 4
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
