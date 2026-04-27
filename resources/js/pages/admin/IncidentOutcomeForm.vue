<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/AdminIncidentOutcomeController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as outcomesIndex } from '@/routes/admin/incident-outcomes';
import type { BreadcrumbItem } from '@/types';

type OutcomeInput = {
    id: number;
    code: string;
    label: string;
    description: string | null;
    applicable_categories: string[] | null;
    is_universal: boolean;
    requires_vitals: boolean;
    requires_hospital: boolean;
    sort_order: number;
    is_active: boolean;
};

type Props = {
    outcome?: OutcomeInput;
    categories: string[];
};

const props = defineProps<Props>();

const isEditing = computed(() => !!props.outcome);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: outcomesIndex.url() },
    { title: 'Incident Outcomes', href: outcomesIndex.url() },
    { title: isEditing.value ? 'Edit' : 'Create', href: '#' },
];

const form = useForm({
    code: props.outcome?.code ?? '',
    label: props.outcome?.label ?? '',
    description: props.outcome?.description ?? '',
    applicable_categories: props.outcome?.applicable_categories ?? [],
    is_universal: props.outcome?.is_universal ?? false,
    requires_vitals: props.outcome?.requires_vitals ?? false,
    requires_hospital: props.outcome?.requires_hospital ?? false,
    sort_order: props.outcome?.sort_order ?? 0,
    is_active: props.outcome?.is_active ?? true,
});

function codify(value: string): string {
    return value
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}

watch(
    () => form.label,
    (label) => {
        if (!isEditing.value && !form.code) {
            form.code = codify(label);
        }
    },
);

watch(
    () => form.is_universal,
    (isUniversal) => {
        if (isUniversal) {
            form.applicable_categories = [];
        }
    },
);

function toggleCategory(category: string): void {
    const idx = form.applicable_categories.indexOf(category);

    if (idx === -1) {
        form.applicable_categories = [...form.applicable_categories, category];
    } else {
        form.applicable_categories = form.applicable_categories.filter(
            (c) => c !== category,
        );
    }
}

function submit(): void {
    if (isEditing.value && props.outcome) {
        form.put(update(props.outcome.id).url, { preserveScroll: true });
    } else {
        form.post(store().url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head
        :title="
            (isEditing ? 'Edit' : 'New') + ' Incident Outcome - Admin'
        "
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl space-y-6 p-4 sm:p-6 lg:p-8">
            <Heading
                :title="isEditing ? 'Edit Incident Outcome' : 'New Incident Outcome'"
                description="Outcomes appear in the responder's resolve sheet, filtered by the active incident's category."
            />

            <form
                class="space-y-5 rounded-[7px] border border-border bg-card p-6 shadow-[var(--shadow-1)]"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="label">Label</Label>
                        <Input
                            id="label"
                            v-model="form.label"
                            placeholder="Subject Detained / Handed to PNP"
                            maxlength="100"
                            required
                        />
                        <InputError :message="form.errors.label" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="code">Code</Label>
                        <Input
                            id="code"
                            v-model="form.code"
                            placeholder="SUBJECT_DETAINED"
                            maxlength="50"
                            class="font-mono"
                            required
                        />
                        <p class="text-xs text-t-text-faint">
                            UPPER_SNAKE_CASE. Stored on
                            <code>incidents.outcome</code>.
                        </p>
                        <InputError :message="form.errors.code" />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="description">Description (optional)</Label>
                    <Input
                        id="description"
                        v-model="form.description"
                        placeholder="One-line context shown in the admin list."
                        maxlength="255"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="space-y-3 rounded-md border border-border p-4">
                    <div class="flex items-start gap-2">
                        <Checkbox
                            id="is_universal"
                            :model-value="form.is_universal"
                            @update:model-value="
                                form.is_universal = $event === true
                            "
                        />
                        <div class="-mt-0.5">
                            <Label for="is_universal" class="cursor-pointer">
                                Universal — applies to all categories
                            </Label>
                            <p class="text-xs text-t-text-faint">
                                Universal outcomes (e.g. False Alarm) ignore
                                the category list and appear in every
                                responder's resolve sheet.
                            </p>
                        </div>
                    </div>

                    <div v-if="!form.is_universal">
                        <Label class="mb-2 block">Applicable categories</Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="cat in categories"
                                :key="cat"
                                type="button"
                                class="rounded-full border px-3 py-1 text-xs transition-colors"
                                :class="
                                    form.applicable_categories.includes(cat)
                                        ? 'border-t-accent bg-t-accent/15 text-t-accent'
                                        : 'border-border bg-transparent text-t-text-dim hover:border-t-text-dim/40'
                                "
                                @click="toggleCategory(cat)"
                            >
                                {{ cat }}
                            </button>
                        </div>
                        <InputError
                            :message="form.errors.applicable_categories"
                        />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="flex items-start gap-2">
                        <Checkbox
                            id="requires_vitals"
                            :model-value="form.requires_vitals"
                            @update:model-value="
                                form.requires_vitals = $event === true
                            "
                        />
                        <div class="-mt-0.5">
                            <Label
                                for="requires_vitals"
                                class="cursor-pointer"
                            >
                                Requires vitals
                            </Label>
                            <p class="text-xs text-t-text-faint">
                                Block resolve until vitals recorded.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <Checkbox
                            id="requires_hospital"
                            :model-value="form.requires_hospital"
                            @update:model-value="
                                form.requires_hospital = $event === true
                            "
                        />
                        <div class="-mt-0.5">
                            <Label
                                for="requires_hospital"
                                class="cursor-pointer"
                            >
                                Requires hospital
                            </Label>
                            <p class="text-xs text-t-text-faint">
                                Show hospital picker on resolve.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <Checkbox
                            id="is_active"
                            :model-value="form.is_active"
                            @update:model-value="
                                form.is_active = $event === true
                            "
                        />
                        <div class="-mt-0.5">
                            <Label for="is_active" class="cursor-pointer">
                                Active
                            </Label>
                            <p class="text-xs text-t-text-faint">
                                Disabled outcomes don't appear on resolve.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="sort_order">Sort order</Label>
                    <Input
                        id="sort_order"
                        v-model.number="form.sort_order"
                        type="number"
                        min="0"
                        max="9999"
                        class="w-32"
                    />
                    <p class="text-xs text-t-text-faint">
                        Lower values appear first within their bucket. Universal
                        outcomes always render last.
                    </p>
                    <InputError :message="form.errors.sort_order" />
                </div>

                <div
                    class="flex items-center justify-end gap-2 border-t border-border pt-4"
                >
                    <Link :href="outcomesIndex.url()">
                        <Button variant="ghost" type="button">Cancel</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing">
                        {{ isEditing ? 'Save Changes' : 'Create Outcome' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
