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

type CategoryInfo = {
    id: number;
    name: string;
    icon: string;
};

type IncidentTypeItem = {
    id: number;
    incident_category_id: number | null;
    category: string;
    name: string;
    code: string;
    default_priority: string;
    description: string | null;
    is_active: boolean;
    sort_order: number | null;
    incident_category: CategoryInfo | null;
};

type Props = {
    types: IncidentTypeItem[];
    categories: CategoryInfo[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: typesIndex.url() },
    { title: 'Incident Types', href: typesIndex.url() },
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

const categoryNames = computed(() => props.categories.map((c) => c.name));

const openCategories = ref<Set<string>>(new Set(categoryNames.value));

const groupedTypes = computed(() => {
    const groups: Record<string, IncidentTypeItem[]> = {};

    for (const type of props.types) {
        const catName = type.incident_category?.name ?? type.category;

        if (!groups[catName]) {
            groups[catName] = [];
        }

        groups[catName].push(type);
    }

    return groups;
});

const priorityColors: Record<string, string> = {
    P1: 'bg-[color-mix(in_srgb,var(--t-p1)_12%,transparent)] text-t-p1',
    P2: 'bg-[color-mix(in_srgb,var(--t-p2)_12%,transparent)] text-t-p2',
    P3: 'bg-[color-mix(in_srgb,var(--t-p3)_12%,transparent)] text-t-p3',
    P4: 'bg-[color-mix(in_srgb,var(--t-p4)_12%,transparent)] text-t-p4',
};

function getCategoryIcon(categoryName: string): Component {
    const cat = props.categories.find((c) => c.name === categoryName);

    return iconMap[cat?.icon ?? ''] ?? AlertTriangle;
}

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
            incident_category_id: type.incident_category_id,
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
            incident_category_id: type.incident_category_id,
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
                    :key="category.id"
                    :open="openCategories.has(category.name)"
                    @update:open="toggleCategory(category.name)"
                >
                    <div
                        class="overflow-hidden rounded-[7px] border border-border bg-card shadow-[var(--shadow-1)]"
                    >
                        <CollapsibleTrigger as-child>
                            <button
                                class="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-accent"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-6 items-center justify-center rounded bg-accent"
                                    >
                                        <component
                                            :is="getCategoryIcon(category.name)"
                                            class="size-3.5 text-foreground"
                                        />
                                    </div>
                                    <h3 class="text-sm font-semibold">
                                        {{ category.name }}
                                    </h3>
                                    <Badge variant="secondary">
                                        {{
                                            categoryStats(category.name).active
                                        }}/{{
                                            categoryStats(category.name).total
                                        }}
                                        active
                                    </Badge>
                                </div>
                                <svg
                                    class="size-4 shrink-0 transition-transform"
                                    :class="{
                                        'rotate-180': openCategories.has(
                                            category.name,
                                        ),
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
                                <thead class="border-t border-border bg-card">
                                    <tr>
                                        <th
                                            class="px-4 py-2 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                                        >
                                            Code
                                        </th>
                                        <th
                                            class="px-4 py-2 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                                        >
                                            Name
                                        </th>
                                        <th
                                            class="px-4 py-2 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                                        >
                                            Priority
                                        </th>
                                        <th
                                            class="px-4 py-2 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                                        >
                                            Status
                                        </th>
                                        <th
                                            class="px-4 py-2 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="type in groupedTypes[
                                            category.name
                                        ]"
                                        :key="type.id"
                                        class="border-b border-border transition-colors hover:bg-accent"
                                        :class="{
                                            'opacity-50': !type.is_active,
                                        }"
                                    >
                                        <td
                                            class="px-4 py-2 font-mono text-[10px] text-t-text-faint"
                                        >
                                            {{ type.code }}
                                        </td>
                                        <td
                                            class="px-4 py-2 font-medium text-foreground"
                                        >
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
                                                    class="text-t-online hover:text-t-online"
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
                    class="rounded-[7px] border border-border bg-card p-8 text-center text-t-text-faint shadow-[var(--shadow-3)]"
                >
                    No incident types found. Add one to get started.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
