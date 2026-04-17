<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Admin/AdminCityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type CityData = {
    id: number;
    name: string;
    province: string | null;
    country: string;
    center_latitude: number | string;
    center_longitude: number | string;
    default_zoom: number;
    timezone: string;
    contact_number: string | null;
    emergency_hotline: string | null;
};

type Props = {
    city: CityData;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Admin', href: '#' },
    { title: 'City', href: '#' },
];

const form = useForm({
    name: props.city.name,
    province: props.city.province ?? '',
    country: props.city.country,
    center_latitude: Number(props.city.center_latitude),
    center_longitude: Number(props.city.center_longitude),
    default_zoom: props.city.default_zoom,
    timezone: props.city.timezone,
    contact_number: props.city.contact_number ?? '',
    emergency_hotline: props.city.emergency_hotline ?? '',
});

function submit(): void {
    form.submit(update());
}
</script>

<template>
    <Head title="City Settings - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl space-y-6 p-4 sm:p-6 lg:p-8">
            <Heading
                title="City Settings"
                description="The city's details and map center. The dispatch map uses these coordinates as its initial position."
            />

            <form
                class="space-y-6 rounded-[var(--radius)] border border-border bg-card p-6 shadow-[var(--shadow-1)]"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">City Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="province">Province</Label>
                        <Input
                            id="province"
                            v-model="form.province"
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.province" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="country">Country</Label>
                        <Input
                            id="country"
                            v-model="form.country"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.country" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="timezone">Timezone</Label>
                        <Input
                            id="timezone"
                            v-model="form.timezone"
                            placeholder="Asia/Manila"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.timezone" />
                    </div>
                </div>

                <div class="border-t border-border pt-6">
                    <h3 class="mb-4 text-sm font-semibold text-foreground">
                        Map Center
                    </h3>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="center_latitude">Latitude</Label>
                            <Input
                                id="center_latitude"
                                v-model.number="form.center_latitude"
                                type="number"
                                step="0.0000001"
                                min="-90"
                                max="90"
                                required
                            />
                            <InputError
                                :message="form.errors.center_latitude"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="center_longitude">Longitude</Label>
                            <Input
                                id="center_longitude"
                                v-model.number="form.center_longitude"
                                type="number"
                                step="0.0000001"
                                min="-180"
                                max="180"
                                required
                            />
                            <InputError
                                :message="form.errors.center_longitude"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="default_zoom">Default Zoom</Label>
                            <Input
                                id="default_zoom"
                                v-model.number="form.default_zoom"
                                type="number"
                                min="1"
                                max="22"
                                required
                            />
                            <InputError :message="form.errors.default_zoom" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Tip: look up exact coordinates at
                        <a
                            class="underline"
                            href="https://www.openstreetmap.org"
                            target="_blank"
                            rel="noopener"
                            >openstreetmap.org</a
                        >. Zoom 13 covers a city; 15 covers a barangay.
                    </p>
                </div>

                <div class="border-t border-border pt-6">
                    <h3 class="mb-4 text-sm font-semibold text-foreground">
                        Contact
                    </h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="contact_number">Contact Number</Label>
                            <Input
                                id="contact_number"
                                v-model="form.contact_number"
                                autocomplete="off"
                            />
                            <InputError
                                :message="form.errors.contact_number"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="emergency_hotline"
                                >Emergency Hotline</Label
                            >
                            <Input
                                id="emergency_hotline"
                                v-model="form.emergency_hotline"
                                autocomplete="off"
                            />
                            <InputError
                                :message="form.errors.emergency_hotline"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Button :disabled="form.processing">
                        Save City Settings
                    </Button>
                    <span
                        v-if="form.recentlySuccessful"
                        class="text-sm text-emerald-500"
                    >
                        Saved.
                    </span>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
