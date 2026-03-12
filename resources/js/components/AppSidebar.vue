<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    ClipboardList,
    LayoutGrid,
    ListOrdered,
    Map,
    MessageSquare,
    Plus,
    RadioTower,
    Shield,
    Truck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import {
    create as incidentsCreate,
    index as incidentsIndex,
    queue as incidentsQueue,
} from '@/routes/incidents';
import type { NavItem } from '@/types';
import type { UserRole } from '@/types/auth';

const page = usePage();
const userRole = computed(
    () => (page.props.auth as { user?: { role?: UserRole } })?.user?.role,
);

const mainNavItems = computed<NavItem[]>(() => {
    const role = userRole.value;

    if (!role) {
        return [];
    }

    const items: Record<UserRole, NavItem[]> = {
        admin: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Dispatch Console',
                href: '/dispatch',
                icon: Map,
            },
            {
                title: 'Incident Queue',
                href: incidentsQueue(),
                icon: ListOrdered,
            },
            {
                title: 'Incidents',
                href: incidentsIndex(),
                icon: AlertTriangle,
            },
            {
                title: '+ New Incident',
                href: incidentsCreate(),
                icon: Plus,
            },
            {
                title: 'Units',
                href: '/units',
                icon: Truck,
            },
            {
                title: 'Messages',
                href: '/messages',
                icon: MessageSquare,
            },
            {
                title: 'Intake Station',
                href: '/intake',
                icon: ClipboardList,
            },
            {
                title: 'Analytics',
                href: '/analytics',
                icon: BarChart3,
            },
            {
                title: 'Admin Panel',
                href: '/admin/users',
                icon: Shield,
            },
        ],
        operator: [
            {
                title: 'Intake Station',
                href: '/intake',
                icon: ClipboardList,
            },
            {
                title: 'Incident Queue',
                href: incidentsQueue(),
                icon: ListOrdered,
            },
            {
                title: 'Messages',
                href: '/messages',
                icon: MessageSquare,
            },
        ],
        dispatcher: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Dispatch Console',
                href: '/dispatch',
                icon: Map,
            },
            {
                title: 'Incident Queue',
                href: incidentsQueue(),
                icon: ListOrdered,
            },
            {
                title: 'Incidents',
                href: incidentsIndex(),
                icon: AlertTriangle,
            },
            {
                title: '+ New Incident',
                href: incidentsCreate(),
                icon: Plus,
            },
            {
                title: 'Messages',
                href: '/messages',
                icon: MessageSquare,
            },
        ],
        responder: [
            {
                title: 'Active Assignment',
                href: '/assignment',
                icon: RadioTower,
            },
            {
                title: 'My Incidents',
                href: '/my-incidents',
                icon: ClipboardList,
            },
            {
                title: 'Messages',
                href: '/messages',
                icon: MessageSquare,
            },
        ],
        supervisor: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Dispatch Console',
                href: '/dispatch',
                icon: Map,
            },
            {
                title: 'Incident Queue',
                href: incidentsQueue(),
                icon: ListOrdered,
            },
            {
                title: 'All Incidents',
                href: incidentsIndex(),
                icon: AlertTriangle,
            },
            {
                title: '+ New Incident',
                href: incidentsCreate(),
                icon: Plus,
            },
            {
                title: 'Units',
                href: '/units',
                icon: Truck,
            },
            {
                title: 'Messages',
                href: '/messages',
                icon: MessageSquare,
            },
            {
                title: 'Analytics',
                href: '/analytics',
                icon: BarChart3,
            },
        ],
    };

    return items[role] ?? [];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
