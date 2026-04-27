<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Stethoscope, Tag } from 'lucide-vue-next';
import AdminIncidentOutcomeController from '@/actions/App/Http/Controllers/Admin/AdminIncidentOutcomeController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as outcomesIndex } from '@/routes/admin/incident-outcomes';
import type { BreadcrumbItem } from '@/types';

type OutcomeRow = {
    id: number;
    code: string;
    label: string;
    description: string | null;
    applicable_categories: string[] | null;
    is_universal: boolean;
    requires_vitals: boolean;
    requires_hospital: boolean;
    sort_order: number;
    is_active: boolean;
};

type Props = {
    outcomes: OutcomeRow[];
    categories: string[];
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: outcomesIndex.url() },
    { title: 'Incident Outcomes', href: outcomesIndex.url() },
];

function disableOutcome(outcome: OutcomeRow): void {
    router.delete(AdminIncidentOutcomeController.destroy(outcome.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Incident Outcomes - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
            <div class="flex items-center justify-between">
                <Heading
                    title="Incident Outcomes"
                    description="Manage the outcomes responders can choose when resolving an incident"
                />
                <Link :href="AdminIncidentOutcomeController.create().url">
                    <Button>Add Outcome</Button>
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
                                Label
                            </th>
                            <th
                                class="px-4 py-2 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Code
                            </th>
                            <th
                                class="px-4 py-2 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Categories
                            </th>
                            <th
                                class="px-4 py-2 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Flags
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
                            v-for="outcome in outcomes"
                            :key="outcome.id"
                            class="border-b border-border transition-colors last:border-b-0 hover:bg-accent"
                            :class="{ 'opacity-60': !outcome.is_active }"
                        >
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <Tag class="size-4 text-t-text-faint" />
                                    <span class="font-medium text-foreground">
                                        {{ outcome.label }}
                                    </span>
                                    <Badge
                                        v-if="outcome.is_universal"
                                        variant="secondary"
                                        class="gap-1"
                                    >
                                        <CheckCircle2 class="size-3" />
                                        Universal
                                    </Badge>
                                </div>
                                <p
                                    v-if="outcome.description"
                                    class="mt-1 text-xs text-t-text-faint"
                                >
                                    {{ outcome.description }}
                                </p>
                            </td>
                            <td
                                class="px-4 py-2 font-mono text-[10px] text-t-text-faint"
                            >
                                {{ outcome.code }}
                            </td>
                            <td class="px-4 py-2">
                                <span
                                    v-if="outcome.is_universal"
                                    class="text-xs text-t-text-faint italic"
                                >
                                    All categories
                                </span>
                                <div
                                    v-else
                                    class="flex flex-wrap gap-1"
                                >
                                    <Badge
                                        v-for="cat in outcome.applicable_categories ??
                                        []"
                                        :key="cat"
                                        variant="outline"
                                        class="text-[10px]"
                                    >
                                        {{ cat }}
                                    </Badge>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-1">
                                    <Badge
                                        v-if="outcome.requires_vitals"
                                        variant="outline"
                                        class="gap-1"
                                    >
                                        <Stethoscope class="size-3" />
                                        Vitals
                                    </Badge>
                                    <Badge
                                        v-if="outcome.requires_hospital"
                                        variant="outline"
                                    >
                                        Hospital
                                    </Badge>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <Badge
                                    :variant="
                                        outcome.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        outcome.is_active
                                            ? 'Active'
                                            : 'Disabled'
                                    }}
                                </Badge>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="
                                            AdminIncidentOutcomeController.edit(
                                                outcome.id,
                                            ).url
                                        "
                                    >
                                        <Button variant="ghost" size="sm">
                                            Edit
                                        </Button>
                                    </Link>
                                    <Button
                                        v-if="outcome.is_active"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        @click="disableOutcome(outcome)"
                                    >
                                        Disable
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="outcomes.length === 0">
                            <td
                                colspan="6"
                                class="p-8 text-center text-t-text-faint"
                            >
                                No incident outcomes yet. Add one to get
                                started.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
