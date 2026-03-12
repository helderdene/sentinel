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
    low: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    moderate:
        'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    high: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    very_high: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
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

            <div class="overflow-hidden rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">District</th>
                            <th class="px-4 py-3 font-medium">Population</th>
                            <th class="px-4 py-3 font-medium">Risk Level</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="barangay in filteredBarangays"
                            :key="barangay.id"
                            class="hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">
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
                    class="p-8 text-center text-muted-foreground"
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
