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
    admin: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    dispatcher: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    responder:
        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    supervisor:
        'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
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

            <div class="overflow-hidden rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Role</th>
                            <th class="px-4 py-3 font-medium">Unit</th>
                            <th class="px-4 py-3 font-medium">Created</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">
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
                    class="p-8 text-center text-muted-foreground"
                >
                    No users found. Create one to get started.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
