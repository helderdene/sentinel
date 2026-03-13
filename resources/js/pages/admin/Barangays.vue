<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminBarangayController from '@/actions/App/Http/Controllers/Admin/AdminBarangayController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as barangaysIndex } from '@/routes/admin/barangays';
import type { BreadcrumbItem } from '@/types';

type BarangayItem = {
    id: number;
    name: string;
    district: string | null;
    city: string;
    population: number | null;
    risk_level: string | null;
};

type Props = {
    barangays: BarangayItem[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: barangaysIndex.url() },
    { title: 'Barangays', href: barangaysIndex.url() },
];

const search = ref('');

const filteredBarangays = computed(() => {
    if (!search.value) {
        return props.barangays;
    }

    const q = search.value.toLowerCase();

    return props.barangays.filter(
        (b) =>
            b.name.toLowerCase().includes(q) ||
            (b.district?.toLowerCase().includes(q) ?? false),
    );
});

const riskColors: Record<string, string> = {
    low: 'bg-[color-mix(in_srgb,var(--t-p4)_12%,transparent)] text-t-p4',
    moderate: 'bg-[color-mix(in_srgb,var(--t-p3)_12%,transparent)] text-t-p3',
    high: 'bg-[color-mix(in_srgb,var(--t-p2)_12%,transparent)] text-t-p2',
    very_high: 'bg-[color-mix(in_srgb,var(--t-p1)_12%,transparent)] text-t-p1',
};

function formatPopulation(pop: number | null): string {
    if (pop === null) {
        return '-';
    }

    return pop.toLocaleString('en-PH');
}

function formatRiskLevel(risk: string | null): string {
    if (!risk) {
        return '-';
    }

    return risk.replace('_', ' ');
}
</script>

<template>
    <Head title="Barangays - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
            <div class="flex items-center justify-between">
                <Heading
                    title="Barangays"
                    description="View and edit barangay metadata (risk level, population, district)"
                />
            </div>

            <div class="flex items-center gap-4">
                <Input
                    v-model="search"
                    placeholder="Search by name or district..."
                    class="max-w-sm"
                />
                <span class="text-sm text-muted-foreground">
                    {{ filteredBarangays.length }} of
                    {{ barangays.length }} barangays
                </span>
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
                                District
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Population
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Risk Level
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
                            v-for="barangay in filteredBarangays"
                            :key="barangay.id"
                            class="border-b border-border transition-colors hover:bg-accent"
                        >
                            <td class="px-4 py-3 font-medium text-foreground">
                                {{ barangay.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ barangay.district ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatPopulation(barangay.population) }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    v-if="barangay.risk_level"
                                    variant="secondary"
                                    :class="
                                        riskColors[barangay.risk_level] ?? ''
                                    "
                                >
                                    {{ formatRiskLevel(barangay.risk_level) }}
                                </Badge>
                                <span v-else class="text-muted-foreground">
                                    -
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="
                                        AdminBarangayController.edit(
                                            barangay.id,
                                        ).url
                                    "
                                >
                                    <Button variant="ghost" size="sm">
                                        Edit
                                    </Button>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div
                    v-if="filteredBarangays.length === 0"
                    class="p-8 text-center text-t-text-faint"
                >
                    {{
                        search
                            ? 'No barangays match your search.'
                            : 'No barangays found.'
                    }}
                </div>
            </div>
        </div>
    </AppLayout>
</template>
