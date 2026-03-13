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
import ChannelMonitor from '@/components/incidents/ChannelMonitor.vue';
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
const channelCounts = computed(
    () => (page.props.channelCounts as Record<string, number>) ?? {},
);

const showChannelMonitor = computed(
    () =>
        userRole.value === 'dispatcher' ||
        userRole.value === 'supervisor' ||
        userRole.value === 'admin',
);

const roleLabels: Record<UserRole, string> = {
    admin: 'Administrator',
    dispatcher: 'Dispatcher',
    operator: 'Operator',
    responder: 'Responder',
    supervisor: 'Supervisor',
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    Welcome, {{ userName }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ roleLabels[userRole] }} Dashboard
                </p>
            </div>

            <!-- Channel Monitor (dispatcher/supervisor/admin) -->
            <ChannelMonitor
                v-if="showChannelMonitor && channelCounts"
                :channel-counts="channelCounts"
                realtime
            />

            <!-- Admin Dashboard -->
            <div
                v-if="userRole === 'admin'"
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-4"
            >
                <Link
                    href="/admin/users"
                    class="group rounded-[var(--radius)] border border-border bg-card p-5 shadow-[var(--shadow-1)] transition-colors hover:bg-accent"
                >
                    <Users
                        class="mb-3 h-6 w-6 text-t-text-faint group-hover:text-t-text-mid"
                    />
                    <h3 class="font-medium text-foreground">User Management</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Manage users and roles
                    </p>
                </Link>
                <Link
                    href="/dispatch"
                    class="group rounded-[var(--radius)] border border-border bg-card p-5 shadow-[var(--shadow-1)] transition-colors hover:bg-accent"
                >
                    <Map
                        class="mb-3 h-6 w-6 text-t-text-faint group-hover:text-t-text-mid"
                    />
                    <h3 class="font-medium text-foreground">
                        Dispatch Console
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Real-time incident map
                    </p>
                </Link>
                <Link
                    href="/analytics"
                    class="group rounded-[var(--radius)] border border-border bg-card p-5 shadow-[var(--shadow-1)] transition-colors hover:bg-accent"
                >
                    <BarChart3
                        class="mb-3 h-6 w-6 text-t-text-faint group-hover:text-t-text-mid"
                    />
                    <h3 class="font-medium text-foreground">Analytics</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        KPI dashboard and reports
                    </p>
                </Link>
                <Link
                    href="/admin/users"
                    class="group rounded-[var(--radius)] border border-border bg-card p-5 shadow-[var(--shadow-1)] transition-colors hover:bg-accent"
                >
                    <Shield
                        class="mb-3 h-6 w-6 text-t-text-faint group-hover:text-t-text-mid"
                    />
                    <h3 class="font-medium text-foreground">Admin Panel</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
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
                    class="rounded-[var(--radius)] border border-border bg-card p-5 shadow-[var(--shadow-1)]"
                >
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-muted-foreground">
                            Active Incidents
                        </h3>
                        <AlertTriangle class="h-5 w-5 text-t-text-faint" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-foreground">--</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Real-time data coming in Phase 3
                    </p>
                </div>
                <div
                    class="rounded-[var(--radius)] border border-border bg-card p-5 shadow-[var(--shadow-1)]"
                >
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-muted-foreground">
                            Queue Size
                        </h3>
                        <Map class="h-5 w-5 text-t-text-faint" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-foreground">--</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Real-time data coming in Phase 3
                    </p>
                </div>
                <div
                    class="rounded-[var(--radius)] border border-border bg-card p-5 shadow-[var(--shadow-1)]"
                >
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-muted-foreground">
                            Units Available
                        </h3>
                        <Truck class="h-5 w-5 text-t-text-faint" />
                    </div>
                    <p class="mt-2 text-3xl font-bold text-foreground">--</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Data available after Phase 4
                    </p>
                </div>
            </div>

            <!-- Responder Dashboard -->
            <div v-else-if="userRole === 'responder'" class="grid gap-4">
                <div
                    class="rounded-[var(--radius)] border border-border bg-card p-6 text-center shadow-[var(--shadow-1)]"
                >
                    <RadioTower
                        class="mx-auto mb-3 h-10 w-10 text-t-text-faint"
                    />
                    <h3 class="text-lg font-medium text-foreground">
                        No Active Assignment
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        You will be notified when a new assignment is pushed to
                        you.
                    </p>
                    <p class="mt-3 text-xs text-t-text-faint">
                        Assignment functionality coming in Phase 5
                    </p>
                </div>
                <div
                    class="rounded-[var(--radius)] border border-border bg-card p-5 shadow-[var(--shadow-1)]"
                >
                    <div class="flex items-center gap-3">
                        <ClipboardList class="h-5 w-5 text-t-text-faint" />
                        <div>
                            <h3 class="font-medium text-foreground">
                                Recent Incidents
                            </h3>
                            <p class="text-sm text-muted-foreground">
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
                    class="rounded-[var(--radius)] border border-border bg-card p-5 shadow-[var(--shadow-1)]"
                >
                    <h3 class="text-sm font-medium text-muted-foreground">
                        Avg Response Time
                    </h3>
                    <p class="mt-2 text-3xl font-bold text-foreground">--</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        KPI data coming in Phase 7
                    </p>
                </div>
                <div
                    class="rounded-[var(--radius)] border border-border bg-card p-5 shadow-[var(--shadow-1)]"
                >
                    <h3 class="text-sm font-medium text-muted-foreground">
                        Unit Utilization
                    </h3>
                    <p class="mt-2 text-3xl font-bold text-foreground">--</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        KPI data coming in Phase 7
                    </p>
                </div>
                <div
                    class="rounded-[var(--radius)] border border-border bg-card p-5 shadow-[var(--shadow-1)]"
                >
                    <h3 class="text-sm font-medium text-muted-foreground">
                        Resolution Rate
                    </h3>
                    <p class="mt-2 text-3xl font-bold text-foreground">--</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        KPI data coming in Phase 7
                    </p>
                </div>
                <div
                    class="rounded-[var(--radius)] border border-border bg-card p-5 shadow-[var(--shadow-1)]"
                >
                    <h3 class="text-sm font-medium text-muted-foreground">
                        Active Incidents
                    </h3>
                    <p class="mt-2 text-3xl font-bold text-foreground">--</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Real-time data coming in Phase 4
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
