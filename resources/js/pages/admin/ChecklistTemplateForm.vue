<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { GripVertical, Plus, Trash2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/AdminChecklistTemplateController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as templatesIndex } from '@/routes/admin/checklist-templates';
import type { BreadcrumbItem } from '@/types';

type ChecklistItem = { key: string; label: string };

type TemplateInput = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    items: ChecklistItem[];
    is_default: boolean;
    is_active: boolean;
};

type Props = {
    template?: TemplateInput;
};

const props = defineProps<Props>();

const isEditing = computed(() => !!props.template);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: templatesIndex.url() },
    { title: 'Checklist Templates', href: templatesIndex.url() },
    { title: isEditing.value ? 'Edit' : 'Create', href: '#' },
];

const form = useForm({
    name: props.template?.name ?? '',
    slug: props.template?.slug ?? '',
    description: props.template?.description ?? '',
    items:
        props.template?.items && props.template.items.length > 0
            ? [...props.template.items]
            : [{ key: '', label: '' }],
    is_default: props.template?.is_default ?? false,
    is_active: props.template?.is_active ?? true,
});

function slugify(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}

watch(
    () => form.name,
    (name) => {
        if (!isEditing.value && !form.slug) {
            form.slug = slugify(name);
        }
    },
);

function addItem(): void {
    form.items.push({ key: '', label: '' });
}

function removeItem(index: number): void {
    if (form.items.length <= 1) {
        return;
    }

    form.items.splice(index, 1);
}

function moveItem(from: number, to: number): void {
    if (to < 0 || to >= form.items.length) {
        return;
    }

    const item = form.items.splice(from, 1)[0];
    form.items.splice(to, 0, item);
}

function autoFillKey(index: number): void {
    const item = form.items[index];

    if (!item.key && item.label) {
        item.key = slugify(item.label);
    }
}

function submit(): void {
    if (isEditing.value && props.template) {
        form.submit(update(props.template.id));
    } else {
        form.submit(store());
    }
}

function itemError(index: number, field: 'key' | 'label'): string | undefined {
    return (form.errors as Record<string, string>)[`items.${index}.${field}`];
}
</script>

<template>
    <Head
        :title="
            isEditing ? 'Edit Checklist Template' : 'Create Checklist Template'
        "
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl space-y-6 p-4 sm:p-6 lg:p-8">
            <Heading
                :title="
                    isEditing
                        ? 'Edit Checklist Template'
                        : 'Create Checklist Template'
                "
                :description="
                    isEditing
                        ? 'Update the protocol checklist items'
                        : 'Define a new protocol checklist that can be assigned to incident types'
                "
            />

            <form
                class="space-y-6 rounded-[var(--radius)] border border-border bg-card p-6 shadow-[var(--shadow-1)]"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            required
                            placeholder="e.g. Structure Fire"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input
                            id="slug"
                            v-model="form.slug"
                            required
                            placeholder="e.g. structure_fire"
                            class="font-mono"
                        />
                        <InputError :message="form.errors.slug" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        class="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Optional summary of when this checklist applies"
                        rows="2"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="is_default"
                            :model-value="form.is_default"
                            @update:model-value="
                                (val: boolean | 'indeterminate') =>
                                    (form.is_default = val === true)
                            "
                        />
                        <Label for="is_default" class="cursor-pointer">
                            Default (fallback) template
                        </Label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="is_active"
                            :model-value="form.is_active"
                            @update:model-value="
                                (val: boolean | 'indeterminate') =>
                                    (form.is_active = val === true)
                            "
                        />
                        <Label for="is_active" class="cursor-pointer">
                            Active
                        </Label>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label>Checklist Items</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addItem"
                        >
                            <Plus class="mr-1 size-3.5" />
                            Add item
                        </Button>
                    </div>

                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="space-y-1 rounded-md border border-border p-3"
                    >
                        <div class="flex items-start gap-2">
                            <div class="flex flex-col gap-1 pt-1">
                                <button
                                    type="button"
                                    class="cursor-pointer text-t-text-faint hover:text-foreground disabled:opacity-30"
                                    :disabled="index === 0"
                                    @click="moveItem(index, index - 1)"
                                    aria-label="Move up"
                                >
                                    <svg
                                        class="size-3"
                                        viewBox="0 0 12 12"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <polyline points="3 7 6 4 9 7" />
                                    </svg>
                                </button>
                                <GripVertical
                                    class="size-3 text-t-text-faint"
                                />
                                <button
                                    type="button"
                                    class="cursor-pointer text-t-text-faint hover:text-foreground disabled:opacity-30"
                                    :disabled="index === form.items.length - 1"
                                    @click="moveItem(index, index + 1)"
                                    aria-label="Move down"
                                >
                                    <svg
                                        class="size-3"
                                        viewBox="0 0 12 12"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <polyline points="3 5 6 8 9 5" />
                                    </svg>
                                </button>
                            </div>

                            <div class="flex-1 grid gap-2 sm:grid-cols-5">
                                <div class="sm:col-span-3">
                                    <Input
                                        v-model="item.label"
                                        placeholder="Item label (e.g. Scene secured)"
                                        required
                                        @blur="autoFillKey(index)"
                                    />
                                    <InputError
                                        :message="itemError(index, 'label')"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <Input
                                        v-model="item.key"
                                        placeholder="key (e.g. scene_secured)"
                                        required
                                        class="font-mono text-xs"
                                    />
                                    <InputError
                                        :message="itemError(index, 'key')"
                                    />
                                </div>
                            </div>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive"
                                :disabled="form.items.length <= 1"
                                @click="removeItem(index)"
                                aria-label="Remove item"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                    <InputError :message="form.errors.items" />
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">
                        {{ isEditing ? 'Update Template' : 'Create Template' }}
                    </Button>
                    <Link :href="templatesIndex.url()">
                        <Button variant="outline" type="button">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
