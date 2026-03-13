<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Admin/AdminBarangayController';
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
import { index as barangaysIndex } from '@/routes/admin/barangays';
import type { BreadcrumbItem } from '@/types';

type BarangayData = {
    id: number;
    name: string;
    district: string | null;
    city: string;
    population: number | null;
    risk_level: string | null;
};

type Props = {
    barangay: BarangayData;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: barangaysIndex.url() },
    { title: 'Barangays', href: barangaysIndex.url() },
    { title: props.barangay.name, href: '#' },
];

const form = useForm({
    district: props.barangay.district ?? '',
    population: props.barangay.population ?? undefined,
    risk_level: props.barangay.risk_level ?? '',
});

function submit(): void {
    form.submit(update(props.barangay.id));
}

const riskLevels = [
    { value: 'low', label: 'Low' },
    { value: 'moderate', label: 'Moderate' },
    { value: 'high', label: 'High' },
    { value: 'very_high', label: 'Very High' },
];
</script>

<template>
    <Head :title="`Edit ${barangay.name} - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-6 p-4 sm:p-6 lg:p-8">
            <Heading
                :title="`Edit ${barangay.name}`"
                description="Update barangay metadata. Boundary polygons are managed separately."
            />

            <div
                class="rounded-[var(--radius)] border border-border bg-card p-4 shadow-[var(--shadow-1)]"
            >
                <dl class="grid gap-2 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">
                            Name
                        </dt>
                        <dd class="text-sm">{{ barangay.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">
                            City
                        </dt>
                        <dd class="text-sm">
                            {{ barangay.city ?? 'Butuan City' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <form
                class="space-y-6 rounded-[var(--radius)] border border-border bg-card p-6 shadow-[var(--shadow-1)]"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="district">District</Label>
                    <Input
                        id="district"
                        v-model="form.district"
                        placeholder="e.g. District 1"
                    />
                    <InputError :message="form.errors.district" />
                </div>

                <div class="grid gap-2">
                    <Label for="population">Population</Label>
                    <Input
                        id="population"
                        v-model.number="form.population"
                        type="number"
                        min="0"
                        placeholder="Population count"
                    />
                    <InputError :message="form.errors.population" />
                </div>

                <div class="grid gap-2">
                    <Label for="risk_level">Risk Level</Label>
                    <Select v-model="form.risk_level">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select risk level" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="level in riskLevels"
                                :key="level.value"
                                :value="level.value"
                            >
                                {{ level.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.risk_level" />
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">
                        Update Barangay
                    </Button>
                    <Link :href="barangaysIndex.url()">
                        <Button variant="outline" type="button">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
