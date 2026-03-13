<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminUserController from '@/actions/App/Http/Controllers/Admin/AdminUserController';
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
import { index as usersIndex } from '@/routes/admin/users';
import type { BreadcrumbItem, UserRole } from '@/types';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    unit_id: string | null;
    badge_number: string | null;
    phone: string | null;
    created_at: string;
    unit: { id: string; callsign: string } | null;
};

type RoleOption = { value: string };

type Props = {
    users: AdminUser[];
    roles: RoleOption[];
    units: Array<Record<string, unknown>>;
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: usersIndex.url() },
    { title: 'Users', href: usersIndex.url() },
];

const roleColors: Record<string, string> = {
    admin: 'bg-[color-mix(in_srgb,var(--t-role-admin)_12%,transparent)] text-t-role-admin',
    dispatcher:
        'bg-[color-mix(in_srgb,var(--t-accent)_12%,transparent)] text-t-accent',
    operator:
        'bg-[color-mix(in_srgb,var(--t-role-operator)_12%,transparent)] text-t-role-operator',
    responder:
        'bg-[color-mix(in_srgb,var(--t-online)_12%,transparent)] text-t-online',
    supervisor:
        'bg-[color-mix(in_srgb,var(--t-role-supervisor)_12%,transparent)] text-t-role-supervisor',
};

function deleteUser(user: AdminUser): void {
    router.delete(AdminUserController.destroy(user.id).url, {
        preserveScroll: true,
    });
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head title="Users - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
            <div class="flex items-center justify-between">
                <Heading
                    title="Users"
                    description="Manage user accounts and role assignments"
                />
                <Link :href="AdminUserController.create().url">
                    <Button>Create User</Button>
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
                                Name
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Email
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Role
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Unit
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Created
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
                            v-for="user in users"
                            :key="user.id"
                            class="border-b border-border transition-colors hover:bg-accent"
                        >
                            <td class="px-4 py-3 font-medium text-foreground">
                                {{ user.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ user.email }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    variant="secondary"
                                    :class="roleColors[user.role] ?? ''"
                                >
                                    {{ user.role }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{
                                    user.unit
                                        ? user.unit.callsign
                                        : user.role === 'responder'
                                          ? 'Unassigned'
                                          : '-'
                                }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatDate(user.created_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="
                                            AdminUserController.edit(user.id)
                                                .url
                                        "
                                    >
                                        <Button variant="ghost" size="sm">
                                            Edit
                                        </Button>
                                    </Link>

                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive hover:text-destructive"
                                            >
                                                Delete
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Delete User
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Are you sure you want to
                                                    delete
                                                    {{ user.name }}? This action
                                                    cannot be undone.
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
                                                    @click="deleteUser(user)"
                                                >
                                                    Delete
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
                    v-if="users.length === 0"
                    class="p-8 text-center text-t-text-faint"
                >
                    No users found. Create one to get started.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
