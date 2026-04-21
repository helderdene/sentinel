<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/AdminCameraController';
import CameraLocationPicker from '@/components/admin/CameraLocationPicker.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as camerasIndex } from '@/routes/admin/cameras';
import type { BreadcrumbItem } from '@/types';

type CameraProp = {
    id: string;
    name: string;
    device_id: string;
    camera_id_display: string | null;
    location: { lat: number; lng: number } | { latitude: number; longitude: number } | null;
    location_label: string | null;
    notes: string | null;
    barangay: { id: string; name: string } | null;
};

type Props = {
    camera?: CameraProp;
    statuses: Array<{ name: string; value: string }>;
};

const props = defineProps<Props>();

const isEditing = computed(() => !!props.camera);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Admin', href: camerasIndex.url() },
    { title: 'Cameras', href: camerasIndex.url() },
    { title: isEditing.value ? 'Edit' : 'Create', href: '#' },
]);

function extractLat(
    loc: CameraProp['location'],
): number | null {
    if (!loc) {
        return null;
    }

    if ('lat' in loc) {
        return loc.lat;
    }

    if ('latitude' in loc) {
        return loc.latitude;
    }

    return null;
}

function extractLng(
    loc: CameraProp['location'],
): number | null {
    if (!loc) {
        return null;
    }

    if ('lng' in loc) {
        return loc.lng;
    }

    if ('longitude' in loc) {
        return loc.longitude;
    }

    return null;
}

const form = useForm({
    name: props.camera?.name ?? '',
    device_id: props.camera?.device_id ?? '',
    latitude: extractLat(props.camera?.location ?? null) as number | null,
    longitude: extractLng(props.camera?.location ?? null) as number | null,
    location_label: props.camera?.location_label ?? '',
    notes: props.camera?.notes ?? '',
});

function onCoordinatesUpdate(lat: number, lng: number): void {
    form.latitude = lat;
    form.longitude = lng;
}

function onAddressUpdate(address: string): void {
    form.location_label = address;
}

function submit(): void {
    if (isEditing.value && props.camera) {
        form.submit(update(props.camera.id));
    } else {
        form.submit(store());
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Camera' : 'Create Camera'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-6 p-4 sm:p-6 lg:p-8">
            <Heading
                :title="isEditing ? 'Edit Camera' : 'Create Camera'"
                :description="
                    isEditing
                        ? 'Update camera details and placement'
                        : 'Register a new camera with location and device ID'
                "
            />

            <div
                v-if="isEditing && camera?.camera_id_display"
                class="rounded-[var(--radius)] bg-secondary px-4 py-2 font-mono text-[11px] text-t-text-mid"
            >
                ID: {{ camera.camera_id_display }}
            </div>

            <form
                class="space-y-6 rounded-[var(--radius)] border border-border bg-card p-6 shadow-[var(--shadow-1)]"
                @submit.prevent="submit"
            >
                <!-- Section 1: Camera Identity -->
                <div class="space-y-4">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Camera Identity
                    </h3>

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="e.g. Main Gate East"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="device_id">Device ID</Label>
                        <Input
                            id="device_id"
                            v-model="form.device_id"
                            placeholder="e.g. 1026700"
                        />
                        <p class="text-[10px] text-t-text-faint">
                            Must match the MQTT client ID the camera reports on
                            heartbeat.
                        </p>
                        <InputError :message="form.errors.device_id" />
                    </div>
                </div>

                <!-- Section 2: Placement -->
                <div class="space-y-4">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Placement
                    </h3>

                    <CameraLocationPicker
                        :latitude="form.latitude"
                        :longitude="form.longitude"
                        :address="form.location_label"
                        @update:coordinates="onCoordinatesUpdate"
                        @update:address="onAddressUpdate"
                    />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>Address (auto)</Label>
                            <div class="text-sm text-muted-foreground">
                                {{ form.location_label || '—' }}
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label>Barangay (auto)</Label>
                            <div class="text-sm text-muted-foreground">
                                {{
                                    camera?.barangay?.name ??
                                    '— (will be detected on save)'
                                }}
                            </div>
                        </div>
                    </div>

                    <InputError :message="form.errors.latitude" />
                    <InputError :message="form.errors.longitude" />
                    <InputError :message="form.errors.location_label" />
                </div>

                <!-- Section 3: Notes (edit mode only) -->
                <div v-if="isEditing" class="space-y-4">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Notes
                    </h3>

                    <div class="grid gap-2">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            class="h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            placeholder="Optional notes about this camera"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">
                        {{ isEditing ? 'Update Camera' : 'Create Camera' }}
                    </Button>
                    <Link :href="camerasIndex.url()">
                        <Button variant="outline" type="button">Cancel</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
