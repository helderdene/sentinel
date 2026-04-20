<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, ListChecks } from 'lucide-vue-next';
import AdminChecklistTemplateController from '@/actions/App/Http/Controllers/Admin/AdminChecklistTemplateController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as templatesIndex } from '@/routes/admin/checklist-templates';
import type { BreadcrumbItem } from '@/types';

type ChecklistItem = { key: string; label: string };

type TemplateRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    items: ChecklistItem[];
    is_default: boolean;
    is_active: boolean;
    incident_types_count: number;
};

type Props = {
    templates: TemplateRow[];
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: templatesIndex.url() },
    { title: 'Checklist Templates', href: templatesIndex.url() },
];

function disableTemplate(template: TemplateRow): void {
    if (template.is_default) {
        return;
    }

    router.delete(AdminChecklistTemplateController.destroy(template.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Checklist Templates - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
            <div class="flex items-center justify-between">
                <Heading
                    title="Checklist Templates"
                    description="Manage responder protocol checklists by incident type"
                />
                <Link :href="AdminChecklistTemplateController.create().url">
                    <Button>Add Template</Button>
                </Link>
            </div>

            <div
                class="overflow-hidden rounded-[7px] border border-border bg-card shadow-[var(--shadow-1)]"
            >
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-card">
                        <tr>
                            <th
                                class="px-4 py-2 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Name
                            </th>
                            <th
                                class="px-4 py-2 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Slug
                            </th>
                            <th
                                class="px-4 py-2 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Items
                            </th>
                            <th
                                class="px-4 py-2 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Assigned Types
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
                            v-for="template in templates"
                            :key="template.id"
                            class="border-b border-border transition-colors last:border-b-0 hover:bg-accent"
                            :class="{ 'opacity-60': !template.is_active }"
                        >
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <ListChecks
                                        class="size-4 text-t-text-faint"
                                    />
                                    <span class="font-medium text-foreground">
                                        {{ template.name }}
                                    </span>
                                    <Badge
                                        v-if="template.is_default"
                                        variant="secondary"
                                        class="gap-1"
                                    >
                                        <CheckCircle2 class="size-3" />
                                        Default
                                    </Badge>
                                </div>
                                <p
                                    v-if="template.description"
                                    class="mt-1 text-xs text-t-text-faint"
                                >
                                    {{ template.description }}
                                </p>
                            </td>
                            <td
                                class="px-4 py-2 font-mono text-[10px] text-t-text-faint"
                            >
                                {{ template.slug }}
                            </td>
                            <td class="px-4 py-2 text-foreground">
                                {{ template.items.length }}
                            </td>
                            <td class="px-4 py-2 text-foreground">
                                {{ template.incident_types_count }}
                            </td>
                            <td class="px-4 py-2">
                                <Badge
                                    :variant="
                                        template.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        template.is_active
                                            ? 'Active'
                                            : 'Disabled'
                                    }}
                                </Badge>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="
                                            AdminChecklistTemplateController.edit(
                                                template.id,
                                            ).url
                                        "
                                    >
                                        <Button variant="ghost" size="sm">
                                            Edit
                                        </Button>
                                    </Link>
                                    <Button
                                        v-if="
                                            template.is_active &&
                                            !template.is_default
                                        "
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        @click="disableTemplate(template)"
                                    >
                                        Disable
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="templates.length === 0">
                            <td
                                colspan="6"
                                class="p-8 text-center text-t-text-faint"
                            >
                                No checklist templates yet. Add one to get
                                started.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
