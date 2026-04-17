<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/AdminIncidentTypeController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { index as typesIndex } from '@/routes/admin/incident-types';
import type { BreadcrumbItem } from '@/types';

type CategoryInfo = {
    id: number;
    name: string;
    icon: string;
};

type IncidentTypeItem = {
    id: number;
    incident_category_id: number | null;
    category: string;
    name: string;
    code: string;
    default_priority: string;
    description: string | null;
    is_active: boolean;
    show_in_public_app: boolean;
    sort_order: number | null;
};

type Props = {
    type?: IncidentTypeItem;
    priorities: Array<{ value: string }>;
    categories: CategoryInfo[];
};

const props = defineProps<Props>();

const isEditing = computed(() => !!props.type);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: typesIndex.url() },
    { title: 'Incident Types', href: typesIndex.url() },
    { title: isEditing.value ? 'Edit' : 'Create', href: '#' },
];

const form = useForm({
    incident_category_id: props.type?.incident_category_id
        ? String(props.type.incident_category_id)
        : '',
    name: props.type?.name ?? '',
    code: props.type?.code ?? '',
    default_priority: props.type?.default_priority ?? '',
    description: props.type?.description ?? '',
    is_active: props.type?.is_active ?? true,
    show_in_public_app: props.type?.show_in_public_app ?? false,
    sort_order: props.type?.sort_order ?? undefined,
});

function submit(): void {
    form.transform((data) => ({
        ...data,
        incident_category_id: data.incident_category_id
            ? Number(data.incident_category_id)
            : null,
    }));

    if (isEditing.value && props.type) {
        form.submit(update(props.type.id));
    } else {
        form.submit(store());
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Incident Type' : 'Create Incident Type'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-6 p-4 sm:p-6 lg:p-8">
            <Heading
                :title="
                    isEditing ? 'Edit Incident Type' : 'Create Incident Type'
                "
                :description="
                    isEditing
                        ? 'Update incident type details'
                        : 'Add a new incident type to the taxonomy'
                "
            />

            <form
                class="space-y-6 rounded-[var(--radius)] border border-border bg-card p-6 shadow-[var(--shadow-1)]"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="incident_category_id">Category</Label>
                    <Select v-model="form.incident_category_id">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select a category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="cat in categories"
                                :key="cat.id"
                                :value="String(cat.id)"
                            >
                                {{ cat.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.incident_category_id" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            required
                            placeholder="e.g. Cardiac Arrest"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="code">Code</Label>
                        <Input
                            id="code"
                            v-model="form.code"
                            required
                            placeholder="e.g. MED-CA"
                            class="font-mono uppercase"
                        />
                        <InputError :message="form.errors.code" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="default_priority">Default Priority</Label>
                    <Select v-model="form.default_priority">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select priority" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="p in priorities"
                                :key="p.value"
                                :value="p.value"
                            >
                                {{ p.value }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.default_priority" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Optional description"
                        rows="3"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="sort_order">Sort Order</Label>
                        <Input
                            id="sort_order"
                            v-model.number="form.sort_order"
                            type="number"
                            placeholder="0"
                        />
                        <InputError :message="form.errors.sort_order" />
                    </div>

                    <div class="flex items-center gap-2 pt-6">
                        <Checkbox
                            id="is_active"
                            :checked="form.is_active"
                            @update:checked="
                                (val: boolean | 'indeterminate') =>
                                    (form.is_active = val === true)
                            "
                        />
                        <Label for="is_active" class="cursor-pointer">
                            Active
                        </Label>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="show_in_public_app"
                            :checked="form.show_in_public_app"
                            @update:checked="
                                (val: boolean | 'indeterminate') =>
                                    (form.show_in_public_app = val === true)
                            "
                        />
                        <Label for="show_in_public_app" class="cursor-pointer">
                            Show in Citizen App
                        </Label>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        When enabled, this incident type appears in the public
                        citizen reporting app
                    </p>
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">
                        {{ isEditing ? 'Update Type' : 'Create Type' }}
                    </Button>
                    <Link :href="typesIndex.url()">
                        <Button variant="outline" type="button">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
