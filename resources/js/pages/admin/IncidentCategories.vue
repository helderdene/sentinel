<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Anchor,
    Biohazard,
    Car,
    CloudLightning,
    Flame,
    Heart,
    Megaphone,
    Shield,
    Siren,
    Waves,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import AdminIncidentCategoryController, {
    edit,
} from '@/actions/App/Http/Controllers/Admin/AdminIncidentCategoryController';
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
import { index as categoriesIndex } from '@/routes/admin/incident-categories';
import type { BreadcrumbItem } from '@/types';

type CategoryItem = {
    id: number;
    name: string;
    icon: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    incident_types_count: number;
};

type Props = {
    categories: CategoryItem[];
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: categoriesIndex.url() },
    { title: 'Incident Categories', href: categoriesIndex.url() },
];

const iconMap: Record<string, Component> = {
    Heart,
    Flame,
    CloudLightning,
    Car,
    Shield,
    Biohazard,
    Waves,
    Megaphone,
    AlertTriangle,
    Siren,
    Anchor,
};

function disableCategory(category: CategoryItem): void {
    router.delete(AdminIncidentCategoryController.destroy(category.id).url, {
        preserveScroll: true,
    });
}

function enableCategory(category: CategoryItem): void {
    router.put(
        AdminIncidentCategoryController.update(category.id).url,
        {
            name: category.name,
            icon: category.icon,
            description: category.description,
            is_active: true,
            sort_order: category.sort_order,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Incident Categories - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
            <div class="flex items-center justify-between">
                <Heading
                    title="Incident Categories"
                    description="Manage incident category taxonomy and icons"
                />
                <Link :href="AdminIncidentCategoryController.create().url">
                    <Button>Add Category</Button>
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
                                Icon
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Name
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Types
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Sort
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
                            v-for="category in categories"
                            :key="category.id"
                            class="border-b border-border transition-colors hover:bg-accent"
                            :class="{ 'opacity-50': !category.is_active }"
                        >
                            <td class="px-4 py-3">
                                <div
                                    class="flex size-8 items-center justify-center rounded-md bg-accent"
                                >
                                    <component
                                        :is="
                                            iconMap[category.icon] ??
                                            iconMap.AlertTriangle
                                        "
                                        class="size-4 text-foreground"
                                    />
                                </div>
                            </td>
                            <td class="px-4 py-3 font-medium text-foreground">
                                <div>{{ category.name }}</div>
                                <div
                                    v-if="category.description"
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    {{ category.description }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Badge variant="secondary">
                                    {{ category.incident_types_count }}
                                    {{
                                        category.incident_types_count === 1
                                            ? 'type'
                                            : 'types'
                                    }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        category.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        category.is_active
                                            ? 'Active'
                                            : 'Disabled'
                                    }}
                                </Badge>
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-[10px] text-t-text-faint"
                            >
                                {{ category.sort_order }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link :href="edit(category.id).url">
                                        <Button variant="ghost" size="sm">
                                            Edit
                                        </Button>
                                    </Link>

                                    <Button
                                        v-if="!category.is_active"
                                        variant="ghost"
                                        size="sm"
                                        class="text-t-online hover:text-t-online"
                                        @click="enableCategory(category)"
                                    >
                                        Enable
                                    </Button>

                                    <Dialog v-else>
                                        <DialogTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive hover:text-destructive"
                                            >
                                                Disable
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Disable Category
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Are you sure you want to
                                                    disable "{{
                                                        category.name
                                                    }}"? This will not affect
                                                    existing incident types.
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
                                                        disableCategory(
                                                            category,
                                                        )
                                                    "
                                                >
                                                    Disable
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
                    v-if="categories.length === 0"
                    class="p-8 text-center text-t-text-faint"
                >
                    No incident categories found. Add one to get started.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
