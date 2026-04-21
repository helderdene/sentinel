<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminCameraController, {
    destroy,
    edit,
    recommission,
} from '@/actions/App/Http/Controllers/Admin/AdminCameraController';
import CameraStatusBadge from '@/components/fras/CameraStatusBadge.vue';
import Heading from '@/components/Heading.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { index as camerasIndex } from '@/routes/admin/cameras';
import type { BreadcrumbItem } from '@/types';

type CameraStatusValue = 'online' | 'degraded' | 'offline';

type CameraRow = {
    id: string;
    camera_id_display: string | null;
    name: string;
    device_id: string;
    status: CameraStatusValue;
    location_label: string | null;
    barangay: { id: string; name: string } | null;
    total_enrollments: number;
    decommissioned_at: string | null;
};

type StatusOption = { name: string; value: CameraStatusValue };

type Props = {
    cameras: CameraRow[];
    statuses: StatusOption[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: camerasIndex.url() },
    { title: 'Cameras', href: camerasIndex.url() },
];

const search = ref('');
const statusFilter = ref<'all' | CameraStatusValue | 'decommissioned'>('all');
const hideDecommissioned = ref(true);

const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return props.cameras.filter((c) => {
        if (hideDecommissioned.value && c.decommissioned_at) {
            return false;
        }

        if (statusFilter.value !== 'all') {
            if (statusFilter.value === 'decommissioned') {
                if (!c.decommissioned_at) {
                    return false;
                }
            } else if (c.decommissioned_at || c.status !== statusFilter.value) {
                return false;
            }
        }

        if (needle.length > 0) {
            const haystack = `${c.name} ${c.device_id}`.toLowerCase();

            if (!haystack.includes(needle)) {
                return false;
            }
        }

        return true;
    });
});

function decommissionCamera(camera: CameraRow): void {
    router.delete(destroy(camera.id).url, { preserveScroll: true });
}

function recommissionCamera(camera: CameraRow): void {
    router.post(recommission(camera.id).url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Cameras - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
            <div class="flex items-center justify-between">
                <Heading
                    title="Cameras"
                    description="Manage the camera fleet, placement, and decommissioning"
                />
                <Link :href="AdminCameraController.create().url">
                    <Button>Create Camera</Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Input
                    v-model="search"
                    placeholder="Search by name or device ID…"
                    class="max-w-sm"
                />
                <Select v-model="statusFilter">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="All statuses" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All</SelectItem>
                        <SelectItem value="online">Online</SelectItem>
                        <SelectItem value="degraded">Degraded</SelectItem>
                        <SelectItem value="offline">Offline</SelectItem>
                        <SelectItem value="decommissioned">
                            Decommissioned
                        </SelectItem>
                    </SelectContent>
                </Select>
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
                                ID
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Name
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Device ID
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Location
                            </th>
                            <th
                                class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Enrollments
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
                            v-for="camera in filtered"
                            :key="camera.id"
                            class="border-b border-border transition-colors hover:bg-accent"
                            :class="{
                                'opacity-50': camera.decommissioned_at,
                            }"
                        >
                            <td
                                class="px-4 py-3 font-mono text-[10px] text-t-text-faint"
                            >
                                {{ camera.camera_id_display ?? '—' }}
                            </td>
                            <td class="px-4 py-3 font-medium text-foreground">
                                {{ camera.name }}
                            </td>
                            <td class="px-4 py-3">
                                <CameraStatusBadge
                                    :status="
                                        camera.decommissioned_at
                                            ? 'decommissioned'
                                            : camera.status
                                    "
                                />
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-[10px] text-muted-foreground"
                            >
                                {{ camera.device_id }}
                            </td>
                            <td
                                class="max-w-[240px] truncate px-4 py-3 text-muted-foreground"
                                :title="camera.location_label ?? ''"
                            >
                                {{ camera.location_label ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ camera.total_enrollments }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link :href="edit(camera.id).url">
                                        <Button variant="ghost" size="sm">
                                            Edit Camera
                                        </Button>
                                    </Link>

                                    <Button
                                        v-if="camera.decommissioned_at"
                                        variant="ghost"
                                        size="sm"
                                        class="text-t-online hover:text-t-online"
                                        @click="recommissionCamera(camera)"
                                    >
                                        Recommission
                                    </Button>

                                    <Dialog v-else>
                                        <DialogTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive hover:text-destructive"
                                            >
                                                Decommission
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Decommission Camera
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Are you sure you want to
                                                    decommission
                                                    {{ camera.name }}
                                                    <template
                                                        v-if="
                                                            camera.camera_id_display
                                                        "
                                                    >
                                                        ({{
                                                            camera.camera_id_display
                                                        }})</template
                                                    >? The camera will stop
                                                    appearing on the dispatch
                                                    map and will no longer
                                                    receive enrollment updates.
                                                    Existing recognition history
                                                    is preserved.
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
                                                        decommissionCamera(
                                                            camera,
                                                        )
                                                    "
                                                >
                                                    Decommission
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
                    <template v-if="props.cameras.length === 0">
                        <p class="font-semibold text-foreground">
                            No cameras yet.
                        </p>
                        <p>
                            Register the first camera to start receiving
                            recognition events on the dispatch map.
                        </p>
                    </template>
                    <template v-else>
                        <p>
                            No cameras match your filter. Clear the filter to
                            see all cameras.
                        </p>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
