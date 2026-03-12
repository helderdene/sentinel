<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/AdminUserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    unit: { id: string; callsign: string } | null;
};

type UnitOption = {
    id: string;
    callsign: string;
};

type Props = {
    user?: AdminUser;
    roles: Array<{ value: string }>;
    units: UnitOption[];
};

const props = defineProps<Props>();

const isEditing = computed(() => !!props.user);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: usersIndex.url() },
    { title: 'Users', href: usersIndex.url() },
    { title: isEditing.value ? 'Edit' : 'Create', href: '#' },
];

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    role: props.user?.role ?? '',
    unit_id: props.user?.unit_id ?? '',
    badge_number: props.user?.badge_number ?? '',
    phone: props.user?.phone ?? '',
});

const showUnitField = computed(() => form.role === 'responder');

watch(
    () => form.role,
    (newRole) => {
        if (newRole !== 'responder') {
            form.unit_id = '';
        }
    },
);

function submit(): void {
    if (isEditing.value && props.user) {
        form.submit(update(props.user.id));
    } else {
        form.submit(store());
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit User' : 'Create User'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-6 p-4 sm:p-6 lg:p-8">
            <Heading
                :title="isEditing ? 'Edit User' : 'Create User'"
                :description="
                    isEditing
                        ? 'Update user account details'
                        : 'Create a new user account with role assignment'
                "
            />

            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        required
                        autocomplete="name"
                        placeholder="Full name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="Email address"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="password">
                            Password
                            <span
                                v-if="isEditing"
                                class="text-muted-foreground"
                            >
                                (leave blank to keep current)
                            </span>
                        </Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            :required="!isEditing"
                            autocomplete="new-password"
                            placeholder="Password"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">
                            Confirm Password
                        </Label>
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            :required="!isEditing"
                            autocomplete="new-password"
                            placeholder="Confirm password"
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="role">Role</Label>
                    <Select v-model="form.role">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select a role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="role in roles"
                                :key="role.value"
                                :value="role.value"
                            >
                                {{
                                    role.value.charAt(0).toUpperCase() +
                                    role.value.slice(1)
                                }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.role" />
                </div>

                <div v-if="showUnitField" class="grid gap-2">
                    <Label for="unit_id">Unit</Label>
                    <Select v-model="form.unit_id">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select a unit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="unit in units"
                                :key="unit.id"
                                :value="unit.id"
                            >
                                {{ unit.id }} - {{ unit.callsign }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.unit_id" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="badge_number">Badge Number</Label>
                        <Input
                            id="badge_number"
                            v-model="form.badge_number"
                            placeholder="e.g. BD-1234"
                        />
                        <InputError :message="form.errors.badge_number" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Phone</Label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            placeholder="Phone number"
                        />
                        <InputError :message="form.errors.phone" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">
                        {{ isEditing ? 'Update User' : 'Create User' }}
                    </Button>
                    <Link :href="usersIndex.url()">
                        <Button variant="outline" type="button">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
