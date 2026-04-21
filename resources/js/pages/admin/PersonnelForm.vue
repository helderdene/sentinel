<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronDown, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/AdminPersonnelController';
import EnrollmentProgressPanel from '@/components/fras/EnrollmentProgressPanel.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EnrollmentRow } from '@/composables/useEnrollmentProgress';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as personnelIndex } from '@/routes/admin/personnel';
import type { BreadcrumbItem } from '@/types';

type Category = 'block' | 'missing' | 'lost_child' | 'allow';

type PersonnelProp = {
    id: string;
    name: string;
    category: Category;
    expires_at: string | null;
    consent_basis: string | null;
    gender: number | null;
    birthday: string | null;
    id_card: string | null;
    phone: string | null;
    address: string | null;
};

type Props = {
    personnel?: PersonnelProp;
    categories: Array<{ name: string; value: Category }>;
    photo_signed_url?: string | null;
    enrollment_rows?: EnrollmentRow[];
};

const props = defineProps<Props>();

const isEditing = computed(() => !!props.personnel);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Admin', href: personnelIndex.url() },
    { title: 'Personnel', href: personnelIndex.url() },
    { title: isEditing.value ? 'Edit' : 'Create', href: '#' },
]);

function normalizeDateInput(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    // Accept full ISO strings or plain Y-m-d; date input needs Y-m-d.
    return iso.slice(0, 10);
}

function normalizeGender(val: number | null | undefined): string {
    if (val === null || val === undefined) {
        return 'unspecified';
    }

    return String(val);
}

const form = useForm<{
    photo: File | null;
    name: string;
    category: Category | '';
    expires_at: string;
    gender: string;
    birthday: string;
    id_card: string;
    phone: string;
    address: string;
    consent_basis: string;
}>({
    photo: null,
    name: props.personnel?.name ?? '',
    category: props.personnel?.category ?? '',
    expires_at: normalizeDateInput(props.personnel?.expires_at),
    gender: normalizeGender(props.personnel?.gender),
    birthday: normalizeDateInput(props.personnel?.birthday),
    id_card: props.personnel?.id_card ?? '',
    phone: props.personnel?.phone ?? '',
    address: props.personnel?.address ?? '',
    consent_basis: props.personnel?.consent_basis ?? '',
});

const photoPreview = ref<string | null>(null);
const photoError = ref<string | null>(null);
const isDraggingOver = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const MAX_PHOTO_BYTES = 1_048_576; // 1 MiB

function acceptFile(file: File): void {
    photoError.value = null;

    const mime = file.type.toLowerCase();

    if (mime !== 'image/jpeg' && mime !== 'image/jpg') {
        photoError.value = `Photo must be a JPEG under 1 MB. Got ${file.type || 'unknown type'}.`;

        return;
    }

    if (file.size > MAX_PHOTO_BYTES) {
        const sizeMb = (file.size / 1_048_576).toFixed(2);
        photoError.value = `Photo must be a JPEG under 1 MB. Got ${sizeMb} MB.`;

        return;
    }

    form.photo = file;

    if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
    }

    photoPreview.value = URL.createObjectURL(file);
}

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        acceptFile(file);
    }
}

function onDrop(event: DragEvent): void {
    event.preventDefault();
    isDraggingOver.value = false;
    const file = event.dataTransfer?.files?.[0];

    if (file) {
        acceptFile(file);
    }
}

function onDropzoneClick(): void {
    fileInput.value?.click();
}

function onDropzoneKey(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        onDropzoneClick();
    }
}

const hasDetails = computed(
    () =>
        !!(
            props.personnel?.gender !== null ||
            props.personnel?.birthday ||
            props.personnel?.id_card
        ),
);

const hasContact = computed(
    () => !!(props.personnel?.phone || props.personnel?.address),
);

const detailsOpen = ref(isEditing.value && hasDetails.value);
const contactOpen = ref(isEditing.value && hasContact.value);

function submit(): void {
    form
        .transform((data) => {
            const payload: Record<string, unknown> = { ...data };

            if (payload.gender === 'unspecified') {
                payload.gender = null;
            } else {
                payload.gender = Number(payload.gender);
            }

            if (!payload.expires_at) {
                payload.expires_at = null;
            }

            if (!payload.birthday) {
                payload.birthday = null;
            }

            if (payload.photo === null) {
                delete payload.photo;
            }

            return payload;
        });

    if (isEditing.value && props.personnel) {
        form.submit(update(props.personnel.id));
    } else {
        form.submit(store());
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Personnel' : 'Create Personnel'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-6 p-4 sm:p-6 lg:p-8">
            <Heading
                :title="isEditing ? 'Edit Personnel' : 'Create Personnel'"
                :description="
                    isEditing
                        ? 'Update details, photo, or category. Photo or category changes re-enroll to all cameras.'
                        : 'Add a person to the watch-list and trigger enrollment across all cameras'
                "
            />

            <form
                class="space-y-6 rounded-[var(--radius)] border border-border bg-card p-6 shadow-[var(--shadow-1)]"
                enctype="multipart/form-data"
                @submit.prevent="submit"
            >
                <!-- Section 1: Photo -->
                <div class="space-y-4">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Photo
                    </h3>

                    <div class="flex gap-4">
                        <div
                            v-if="
                                photoPreview ||
                                (isEditing && photo_signed_url)
                            "
                            class="size-24 shrink-0 overflow-hidden rounded-[var(--radius)] border border-border"
                        >
                            <img
                                :src="photoPreview ?? photo_signed_url ?? ''"
                                :alt="
                                    'Current enrollment photo of ' +
                                    (personnel?.name ?? 'personnel')
                                "
                                class="h-full w-full object-cover"
                            />
                        </div>

                        <div
                            role="button"
                            tabindex="0"
                            aria-describedby="photo-help"
                            class="flex h-48 flex-1 cursor-pointer flex-col items-center justify-center gap-2 rounded-[var(--radius)] border-2 border-dashed bg-t-surface-alt transition-colors"
                            :class="{
                                'border-t-accent bg-[color-mix(in_srgb,var(--t-accent)_8%,transparent)]':
                                    isDraggingOver,
                                'border-border hover:border-t-accent':
                                    !isDraggingOver,
                            }"
                            @click="onDropzoneClick"
                            @keydown="onDropzoneKey"
                            @dragover.prevent="isDraggingOver = true"
                            @dragleave.prevent="isDraggingOver = false"
                            @drop="onDrop"
                        >
                            <Upload class="size-6 text-t-text-faint" />
                            <p class="text-sm text-foreground">
                                <span v-if="form.photo">
                                    {{ form.photo.name }}
                                </span>
                                <span v-else> Drop photo here or click to browse </span>
                            </p>
                            <p class="text-[10px] text-t-text-faint">
                                JPEG only. Max 1 MB. Auto-resized to 1080p at
                                quality 85.
                            </p>
                            <input
                                ref="fileInput"
                                id="photo"
                                type="file"
                                accept="image/jpeg"
                                class="hidden"
                                @change="onFileChange"
                            />
                        </div>
                    </div>

                    <p id="photo-help" class="sr-only">
                        Upload a JPEG photo under 1 MB. The image will be auto
                        resized.
                    </p>

                    <InputError :message="photoError ?? form.errors.photo" />
                </div>

                <!-- Section 2: Identity -->
                <div class="space-y-4">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Identity
                    </h3>

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Full name"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="category">Category</Label>
                            <Select v-model="form.category">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="block">Block</SelectItem>
                                    <SelectItem value="missing">
                                        Missing
                                    </SelectItem>
                                    <SelectItem value="lost_child">
                                        Lost child
                                    </SelectItem>
                                    <SelectItem value="allow">Allow</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.category" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="expires_at">Expires</Label>
                            <Input
                                id="expires_at"
                                v-model="form.expires_at"
                                type="date"
                            />
                            <p class="text-[10px] text-t-text-faint">
                                Leave blank for no expiry. Expired entries are
                                auto-unenrolled within the hour.
                            </p>
                            <InputError :message="form.errors.expires_at" />
                        </div>
                    </div>
                </div>

                <!-- Section 3: Details (collapsible) -->
                <div class="space-y-4">
                    <Collapsible v-model:open="detailsOpen">
                        <CollapsibleTrigger
                            class="flex w-full items-center justify-between"
                        >
                            <h3
                                class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Details (optional)
                            </h3>
                            <ChevronDown
                                class="size-4 text-t-text-faint transition-transform"
                                :class="{ 'rotate-180': detailsOpen }"
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent class="space-y-4 pt-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="gender">Gender</Label>
                                    <Select v-model="form.gender">
                                        <SelectTrigger class="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="unspecified">
                                                Unspecified
                                            </SelectItem>
                                            <SelectItem value="0">
                                                Male
                                            </SelectItem>
                                            <SelectItem value="1">
                                                Female
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="form.errors.gender" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="birthday">Birthday</Label>
                                    <Input
                                        id="birthday"
                                        v-model="form.birthday"
                                        type="date"
                                    />
                                    <InputError
                                        :message="form.errors.birthday"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="id_card">ID card</Label>
                                <Input
                                    id="id_card"
                                    v-model="form.id_card"
                                    placeholder="ID card number"
                                />
                                <InputError :message="form.errors.id_card" />
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </div>

                <!-- Section 4: Contact (collapsible) -->
                <div class="space-y-4">
                    <Collapsible v-model:open="contactOpen">
                        <CollapsibleTrigger
                            class="flex w-full items-center justify-between"
                        >
                            <h3
                                class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                            >
                                Contact (optional)
                            </h3>
                            <ChevronDown
                                class="size-4 text-t-text-faint transition-transform"
                                :class="{ 'rotate-180': contactOpen }"
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent class="space-y-4 pt-4">
                            <div class="grid gap-2">
                                <Label for="phone">Phone</Label>
                                <Input
                                    id="phone"
                                    v-model="form.phone"
                                    placeholder="+63 …"
                                />
                                <InputError :message="form.errors.phone" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="address">Address</Label>
                                <Input
                                    id="address"
                                    v-model="form.address"
                                />
                                <InputError :message="form.errors.address" />
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </div>

                <!-- Section 5: Consent -->
                <div class="space-y-4">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Consent
                    </h3>

                    <div class="grid gap-2">
                        <Label for="consent_basis">Consent basis</Label>
                        <textarea
                            id="consent_basis"
                            v-model="form.consent_basis"
                            class="h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            placeholder='e.g. "Butuan City Police blotter #2026-041 dated 2026-04-19 authorizing watch-list inclusion under RA 10173 §12(e) (response to public health, safety, or order)."'
                        />
                        <p class="text-[10px] text-t-text-faint">
                            Record the legal basis or authorization for
                            watch-list inclusion per RA 10173. Required for
                            audit.
                        </p>
                        <InputError :message="form.errors.consent_basis" />
                    </div>
                </div>

                <!-- Section 6: Enrollment Status (edit-mode only) -->
                <div
                    v-if="isEditing && personnel"
                    class="space-y-4"
                >
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Enrollment Status
                    </h3>

                    <EnrollmentProgressPanel
                        :personnel-id="personnel.id"
                        :initial-rows="enrollment_rows ?? []"
                    />
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">
                        {{
                            isEditing ? 'Update Personnel' : 'Create Personnel'
                        }}
                    </Button>
                    <Link :href="personnelIndex.url()">
                        <Button variant="outline" type="button">Cancel</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
