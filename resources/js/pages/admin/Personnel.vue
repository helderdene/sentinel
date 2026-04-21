<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileCheck2, FileX2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AdminPersonnelController, {
    destroy,
    edit,
    recommission,
} from '@/actions/App/Http/Controllers/Admin/AdminPersonnelController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as personnelIndex } from '@/routes/admin/personnel';
import type { BreadcrumbItem } from '@/types';

type Category = 'block' | 'missing' | 'lost_child' | 'allow';

type PersonnelRow = {
    id: string;
    name: string;
    category: Category;
    expires_at: string | null;
    consent_basis: string | null;
    total_enrollments: number;
    done_enrollments: number;
    failed_enrollments: number;
    decommissioned_at: string | null;
};

type Props = {
    personnel: PersonnelRow[];
    categories: Array<{ name: string; value: Category }>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: personnelIndex.url() },
    { title: 'Personnel', href: personnelIndex.url() },
];

const search = ref('');
const categoryFilter = ref<'all' | Category>('all');
const hideExpired = ref(true);
const hideDecommissioned = ref(true);

const now = new Date();

function isExpired(expiresAt: string | null): boolean {
    if (!expiresAt) {
        return false;
    }

    return new Date(expiresAt).getTime() < now.getTime();
}

const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return props.personnel.filter((p) => {
        if (hideDecommissioned.value && p.decommissioned_at) {
            return false;
        }

        if (hideExpired.value && isExpired(p.expires_at)) {
            return false;
        }

        if (
            categoryFilter.value !== 'all' &&
            p.category !== categoryFilter.value
        ) {
            return false;
        }

        if (needle.length > 0 && !p.name.toLowerCase().includes(needle)) {
            return false;
        }

        return true;
    });
});

const categoryColors: Record<Category, string> = {
    block: 'bg-[color-mix(in_srgb,var(--t-p1)_12%,transparent)] text-t-p1',
    missing: 'bg-[color-mix(in_srgb,var(--t-p2)_12%,transparent)] text-t-p2',
    lost_child:
        'bg-[color-mix(in_srgb,var(--t-ch-iot)_12%,transparent)] text-t-ch-iot',
    allow: 'bg-[color-mix(in_srgb,var(--t-online)_12%,transparent)] text-t-online',
};

const categoryLabels: Record<Category, string> = {
    block: 'Block',
    missing: 'Missing',
    lost_child: 'Lost child',
    allow: 'Allow',
};

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    return d.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function enrollmentColor(row: PersonnelRow): string {
    if (row.failed_enrollments > 0) {
        return 'text-t-p1';
    }

    if (
        row.total_enrollments > 0 &&
        row.done_enrollments === row.total_enrollments
    ) {
        return 'text-t-online';
    }

    if (row.total_enrollments > row.done_enrollments) {
        return 'text-t-unit-onscene';
    }

    return 'text-muted-foreground';
}

function removePersonnel(p: PersonnelRow): void {
    router.delete(destroy(p.id).url, { preserveScroll: true });
}

function restorePersonnel(p: PersonnelRow): void {
    router.post(recommission(p.id).url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Personnel - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
            <div class="flex items-center justify-between">
                <Heading
                    title="Personnel"
                    description="Manage the watch-list, categories, and camera enrollment"
                />
                <Link :href="AdminPersonnelController.create().url">
                    <Button>Create Personnel</Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Input
                    v-model="search"
                    placeholder="Search by name…"
                    class="max-w-sm"
                />
                <Select v-model="categoryFilter">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="All categories" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All</SelectItem>
                        <SelectItem value="block">Block</SelectItem>
                        <SelectItem value="missing">Missing</SelectItem>
                        <SelectItem value="lost_child">Lost child</SelectItem>
                        <SelectItem value="allow">Allow</SelectItem>
                    </SelectContent>
                </Select>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model="hideExpired" />
                    Hide expired
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model="hideDecommissioned" />
                    Hide decommissioned
                </label>
            </div>

            <div
                class="overflow-hidden rounded-[var(--radius)] border border-border bg-card shadow-[var(--shadow-1)]"
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
                                Category
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Expires
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Enrollments
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Consent
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
                            v-for="person in filtered"
                            :key="person.id"
                            class="border-b border-border transition-colors hover:bg-accent"
                            :class="{
                                'opacity-50': person.decommissioned_at,
                            }"
                        >
                            <td class="px-4 py-3 font-medium text-foreground">
                                {{ person.name }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    variant="secondary"
                                    :class="categoryColors[person.category]"
                                >
                                    {{ categoryLabels[person.category] }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span
                                        :class="
                                            person.expires_at
                                                ? 'text-muted-foreground'
                                                : 'text-t-text-faint'
                                        "
                                    >
                                        {{ formatDate(person.expires_at) }}
                                    </span>
                                    <Badge
                                        v-if="isExpired(person.expires_at)"
                                        variant="secondary"
                                        class="bg-[color-mix(in_srgb,var(--t-unit-offline)_12%,transparent)] font-semibold text-t-unit-offline"
                                    >
                                        Expired
                                    </Badge>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="enrollmentColor(person)">
                                    {{ person.done_enrollments }}/{{
                                        person.total_enrollments
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <span class="inline-flex">
                                                <FileCheck2
                                                    v-if="person.consent_basis"
                                                    class="size-4 text-t-online"
                                                />
                                                <FileX2
                                                    v-else
                                                    class="size-4 text-t-p2"
                                                />
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p v-if="person.consent_basis">
                                                Consent basis recorded
                                            </p>
                                            <p v-else>
                                                Consent basis missing — edit to
                                                add
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link :href="edit(person.id).url">
                                        <Button variant="ghost" size="sm">
                                            Edit Personnel
                                        </Button>
                                    </Link>

                                    <Button
                                        v-if="person.decommissioned_at"
                                        variant="ghost"
                                        size="sm"
                                        class="text-t-online hover:text-t-online"
                                        @click="restorePersonnel(person)"
                                    >
                                        Restore to Watch-list
                                    </Button>

                                    <Dialog v-else>
                                        <DialogTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive hover:text-destructive"
                                            >
                                                Remove from Watch-list
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Remove from Watch-list
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Are you sure you want to
                                                    remove {{ person.name }}
                                                    from the watch-list? The
                                                    person will be unenrolled
                                                    from all cameras
                                                    immediately. History is
                                                    preserved for audit; the
                                                    record is not deleted.
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
                                                        removePersonnel(person)
                                                    "
                                                >
                                                    Remove from Watch-list
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
                    v-if="filtered.length === 0"
                    class="space-y-1 p-8 text-center text-t-text-faint"
                >
                    <template v-if="props.personnel.length === 0">
                        <p class="font-semibold text-foreground">
                            No personnel on the watch-list yet.
                        </p>
                        <p>
                            Add the first person to begin enrollment across all
                            active cameras.
                        </p>
                    </template>
                    <template v-else>
                        <p>
                            No personnel match your filter. Clear the filter to
                            see all personnel.
                        </p>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
