<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    destroy,
    edit,
    recommission,
} from '@/actions/App/Http/Controllers/Admin/AdminUnitController';
import AdminUnitController from '@/actions/App/Http/Controllers/Admin/AdminUnitController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as unitsIndex } from '@/routes/admin/units';
import type { BreadcrumbItem } from '@/types';

type AdminUnit = {
    id: string;
    callsign: string;
    type: string;
    agency: string;
    crew_capacity: number;
    status: string;
    shift: string | null;
    decommissioned_at: string | null;
    users_count: number;
    users: Array<{ id: number; name: string }>;
};

type Props = {
    units: AdminUnit[];
    types: Array<{ value: string }>;
    statuses: Array<{ value: string }>;
    responders: Array<{ id: number; name: string; unit_id: string | null }>;
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: unitsIndex.url() },
    { title: 'Units', href: unitsIndex.url() },
];

const statusColors: Record<string, string> = {
    AVAILABLE:
        'bg-[color-mix(in_srgb,var(--t-unit-available)_12%,transparent)] text-t-unit-available',
    DISPATCHED:
        'bg-[color-mix(in_srgb,var(--t-unit-dispatched)_12%,transparent)] text-t-unit-dispatched',
    EN_ROUTE:
        'bg-[color-mix(in_srgb,var(--t-unit-enroute)_12%,transparent)] text-t-unit-enroute',
    ON_SCENE:
        'bg-[color-mix(in_srgb,var(--t-unit-onscene)_12%,transparent)] text-t-unit-onscene',
    OFFLINE:
        'bg-[color-mix(in_srgb,var(--t-unit-offline)_12%,transparent)] text-t-unit-offline',
};

const typeColors: Record<string, string> = {
    ambulance: 'bg-[color-mix(in_srgb,var(--t-p1)_12%,transparent)] text-t-p1',
    fire: 'bg-[color-mix(in_srgb,var(--t-p2)_12%,transparent)] text-t-p2',
    rescue: 'bg-[color-mix(in_srgb,var(--t-accent)_12%,transparent)] text-t-accent',
    police: 'bg-[color-mix(in_srgb,var(--t-role-supervisor)_12%,transparent)] text-t-role-supervisor',
    boat: 'bg-[color-mix(in_srgb,var(--t-ch-sms)_12%,transparent)] text-t-ch-sms',
};

function decommissionUnit(unit: AdminUnit): void {
    router.delete(destroy(unit.id).url, {
        preserveScroll: true,
    });
}

function recommissionUnit(unit: AdminUnit): void {
    router.post(recommission(unit.id).url, {}, { preserveScroll: true });
}

function formatStatus(status: string): string {
    return status.replace(/_/g, ' ');
}
</script>

<template>
    <Head title="Units - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
            <div class="flex items-center justify-between">
                <Heading
                    title="Units"
                    description="Manage response units, crew assignments, and decommissioning"
                />
                <Link :href="AdminUnitController.create().url">
                    <Button>Create Unit</Button>
                </Link>
            </div>

            <div
                class="overflow-hidden rounded-[7px] border border-border bg-card shadow-[var(--shadow-1)]"
            >
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-card">
                        <tr>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                ID
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Callsign
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Type
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Crew
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Agency
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="unit in units"
                            :key="unit.id"
                            class="border-b border-border transition-colors hover:bg-accent"
                            :class="{
                                'opacity-50': unit.decommissioned_at,
                            }"
                        >
                            <td
                                class="px-4 py-3 font-mono text-[10px] text-t-text-faint"
                            >
                                {{ unit.id }}
                            </td>
                            <td class="px-4 py-3 font-medium text-foreground">
                                {{ unit.callsign }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    variant="secondary"
                                    :class="typeColors[unit.type] ?? ''"
                                >
                                    {{ unit.type }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    v-if="unit.decommissioned_at"
                                    variant="secondary"
                                    class="bg-[color-mix(in_srgb,var(--t-unit-offline)_12%,transparent)] text-t-unit-offline"
                                >
                                    Decommissioned
                                </Badge>
                                <Badge
                                    v-else
                                    variant="secondary"
                                    :class="statusColors[unit.status] ?? ''"
                                >
                                    {{ formatStatus(unit.status) }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="{
                                        'text-t-p2':
                                            unit.users_count >
                                            unit.crew_capacity,
                                    }"
                                >
                                    {{ unit.users_count }}/{{
                                        unit.crew_capacity
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ unit.agency }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link :href="edit(unit.id).url">
                                        <Button variant="ghost" size="sm">
                                            Edit
                                        </Button>
                                    </Link>

                                    <Button
                                        v-if="unit.decommissioned_at"
                                        variant="ghost"
                                        size="sm"
                                        class="text-t-online hover:text-t-online"
                                        @click="recommissionUnit(unit)"
                                    >
                                        Recommission
                                    </Button>

                                    <Dialog v-else>
                                        <DialogTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive hover:text-destructive"
                                            >
                                                Decommission
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Decommission Unit
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Are you sure you want to
                                                    decommission
                                                    {{ unit.callsign }} ({{
                                                        unit.id
                                                    }})? Crew members will be
                                                    unassigned.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter>
                                                <DialogClose as-child>
                                                    <Button variant="outline">
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    variant="destructive"
                                                    @click="
                                                        decommissionUnit(unit)
                                                    "
                                                >
                                                    Decommission
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div
                    v-if="units.length === 0"
                    class="p-8 text-center text-t-text-faint"
                >
                    No units found. Create one to get started.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
