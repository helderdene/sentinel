<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminIncidentTypeController from '@/actions/App/Http/Controllers/Admin/AdminIncidentTypeController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as typesIndex } from '@/routes/admin/incident-types';
import type { BreadcrumbItem } from '@/types';

type IncidentTypeItem = {
    id: number;
    category: string;
    name: string;
    code: string;
    default_priority: string;
    description: string | null;
    is_active: boolean;
    sort_order: number | null;
};

type Props = {
    types: IncidentTypeItem[];
    categories: string[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: typesIndex.url() },
    { title: 'Incident Types', href: typesIndex.url() },
];

const openCategories = ref<Set<string>>(new Set(props.categories));

const groupedTypes = computed(() => {
    const groups: Record<string, IncidentTypeItem[]> = {};

    for (const type of props.types) {
        if (!groups[type.category]) {
            groups[type.category] = [];
        }

        groups[type.category].push(type);
    }

    return groups;
});

const priorityColors: Record<string, string> = {
    P1: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    P2: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    P3: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    P4: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
};

function toggleCategory(category: string): void {
    if (openCategories.value.has(category)) {
        openCategories.value.delete(category);
    } else {
        openCategories.value.add(category);
    }
}

function disableType(type: IncidentTypeItem): void {
    router.put(
        AdminIncidentTypeController.update(type.id).url,
        {
            category: type.category,
            name: type.name,
            code: type.code,
            default_priority: type.default_priority,
            is_active: false,
        },
        { preserveScroll: true },
    );
}

function enableType(type: IncidentTypeItem): void {
    router.put(
        AdminIncidentTypeController.update(type.id).url,
        {
            category: type.category,
            name: type.name,
            code: type.code,
            default_priority: type.default_priority,
            is_active: true,
        },
        { preserveScroll: true },
    );
}

function categoryStats(category: string): { active: number; total: number } {
    const types = groupedTypes.value[category] ?? [];

    return {
        active: types.filter((t) => t.is_active).length,
        total: types.length,
    };
}
</script>

<template>
    <Head title="Incident Types - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
            <div class="flex items-center justify-between">
                <Heading
                    title="Incident Types"
                    description="Manage incident type taxonomy organized by category"
                />
                <Link :href="AdminIncidentTypeController.create().url">
                    <Button>Add Type</Button>
                </Link>
            </div>

            <div class="space-y-4">
                <Collapsible
                    v-for="category in categories"
                    :key="category"
                    :open="openCategories.has(category)"
                    @update:open="toggleCategory(category)"
                >
                    <div class="rounded-lg border">
                        <CollapsibleTrigger as-child>
                            <button
                                class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-muted/30"
                            >
                                <div class="flex items-center gap-3">
                                    <h3 class="text-sm font-semibold">
                                        {{ category }}
                                    </h3>
                                    <Badge variant="secondary">
                                        {{ categoryStats(category).active }}/{{
                                            categoryStats(category).total
                                        }}
                                        active
                                    </Badge>
                                </div>
                                <svg
                                    class="size-4 shrink-0 transition-transform"
                                    :class="{
                                        'rotate-180':
                                            openCategories.has(category),
                                    }"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <table class="w-full text-left text-sm">
                                <thead class="border-t bg-muted/30">
                                    <tr>
                                        <th class="px-4 py-2 font-medium">
                                            Code
                                        </th>
                                        <th class="px-4 py-2 font-medium">
                                            Name
                                        </th>
                                        <th class="px-4 py-2 font-medium">
                                            Priority
                                        </th>
                                        <th class="px-4 py-2 font-medium">
                                            Status
                                        </th>
                                        <th class="px-4 py-2 font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr
                                        v-for="type in groupedTypes[category]"
                                        :key="type.id"
                                        class="hover:bg-muted/20"
                                        :class="{
                                            'opacity-50': !type.is_active,
                                        }"
                                    >
                                        <td class="px-4 py-2 font-mono text-xs">
                                            {{ type.code }}
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ type.name }}
                                        </td>
                                        <td class="px-4 py-2">
                                            <Badge
                                                variant="secondary"
                                                :class="
                                                    priorityColors[
                                                        type.default_priority
                                                    ] ?? ''
                                                "
                                            >
                                                {{ type.default_priority }}
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-2">
                                            <Badge
                                                :variant="
                                                    type.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                "
                                            >
                                                {{
                                                    type.is_active
                                                        ? 'Active'
                                                        : 'Disabled'
                                                }}
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-2">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <Link
                                                    :href="
                                                        AdminIncidentTypeController.edit(
                                                            type.id,
                                                        ).url
                                                    "
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        Edit
                                                    </Button>
                                                </Link>
                                                <Button
                                                    v-if="type.is_active"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-destructive hover:text-destructive"
                                                    @click="disableType(type)"
                                                >
                                                    Disable
                                                </Button>
                                                <Button
                                                    v-else
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-green-600 hover:text-green-700"
                                                    @click="enableType(type)"
                                                >
                                                    Enable
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </CollapsibleContent>
                    </div>
                </Collapsible>

                <div
                    v-if="categories.length === 0"
                    class="rounded-lg border p-8 text-center text-muted-foreground"
                >
                    No incident types found. Add one to get started.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
