<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    Building2,
    Camera,
    ClipboardList,
    FolderTree,
    History,
    IdCard,
    LayoutGrid,
    Landmark,
    ListChecks,
    Map,
    RadioTower,
    Shield,
    Siren,
    Tags,
    Truck,
    Users,
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
import { index as incidentsIndex } from '@/routes/incidents';
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
                title: 'Incidents',
                href: incidentsIndex(),
                icon: AlertTriangle,
            },
            {
                title: 'Intake Station',
                href: '/intake',
                icon: ClipboardList,
            },
            {
                title: 'FRAS Alerts',
                href: '/fras/alerts',
                icon: Siren,
            },
            {
                title: 'FRAS Events',
                href: '/fras/events',
                icon: History,
            },
            {
                title: 'Analytics',
                href: '/analytics',
                icon: BarChart3,
            },
            {
                title: 'Admin',
                href: '/admin/users',
                icon: Shield,
                children: [
                    {
                        title: 'Users',
                        href: '/admin/users',
                        icon: Users,
                    },
                    {
                        title: 'Barangays',
                        href: '/admin/barangays',
                        icon: Landmark,
                    },
                    {
                        title: 'Incident Categories',
                        href: '/admin/incident-categories',
                        icon: FolderTree,
                    },
                    {
                        title: 'Incident Types',
                        href: '/admin/incident-types',
                        icon: Tags,
                    },
                    {
                        title: 'Checklist Templates',
                        href: '/admin/checklist-templates',
                        icon: ListChecks,
                    },
                    {
                        title: 'Units',
                        href: '/admin/units',
                        icon: Truck,
                    },
                    {
                        title: 'Cameras',
                        href: '/admin/cameras',
                        icon: Camera,
                    },
                    {
                        title: 'Personnel',
                        href: '/admin/personnel',
                        icon: IdCard,
                    },
                    {
                        title: 'City',
                        href: '/admin/city',
                        icon: Building2,
                    },
                ],
            },
        ],
        operator: [
            {
                title: 'Intake Station',
                href: '/intake',
                icon: ClipboardList,
            },
            {
                title: 'FRAS Alerts',
                href: '/fras/alerts',
                icon: Siren,
            },
            {
                title: 'FRAS Events',
                href: '/fras/events',
                icon: History,
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
                title: 'Incidents',
                href: incidentsIndex(),
                icon: AlertTriangle,
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
        ],
        supervisor: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Intake Station',
                href: '/intake',
                icon: ClipboardList,
            },
            {
                title: 'FRAS Alerts',
                href: '/fras/alerts',
                icon: Siren,
            },
            {
                title: 'FRAS Events',
                href: '/fras/events',
                icon: History,
            },
            {
                title: 'Dispatch Console',
                href: '/dispatch',
                icon: Map,
            },
            {
                title: 'All Incidents',
                href: incidentsIndex(),
                icon: AlertTriangle,
            },
            {
                title: 'Units',
                href: '/admin/units',
                icon: Truck,
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
