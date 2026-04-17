<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Anchor,
    Axe,
    Biohazard,
    Bomb,
    Building,
    Car,
    CircleAlert,
    CloudLightning,
    Construction,
    Flame,
    Footprints,
    Handshake,
    Heart,
    HelpCircle,
    Megaphone,
    Mountain,
    Phone,
    Search,
    Shield,
    ShieldAlert,
    Siren,
    Skull,
    Snowflake,
    Sun,
    TreePine,
    Truck,
    Umbrella,
    Waves,
    Wrench,
    Zap,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/AdminIncidentCategoryController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as categoriesIndex } from '@/routes/admin/incident-categories';
import type { BreadcrumbItem } from '@/types';

type CategoryData = {
    id: number;
    name: string;
    icon: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
};

type Props = {
    category?: CategoryData;
};

const props = defineProps<Props>();

const isEditing = computed(() => !!props.category);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: categoriesIndex.url() },
    { title: 'Incident Categories', href: categoriesIndex.url() },
    { title: isEditing.value ? 'Edit' : 'Create', href: '#' },
];

const form = useForm({
    name: props.category?.name ?? '',
    icon: props.category?.icon ?? 'AlertTriangle',
    description: props.category?.description ?? '',
    is_active: props.category?.is_active ?? true,
    sort_order: props.category?.sort_order ?? undefined,
});

type IconOption = {
    name: string;
    component: Component;
    label: string;
};

const allIcons: IconOption[] = [
    { name: 'Heart', component: Heart, label: 'Heart / Medical' },
    { name: 'Flame', component: Flame, label: 'Flame / Fire' },
    { name: 'CloudLightning', component: CloudLightning, label: 'Storm' },
    { name: 'Car', component: Car, label: 'Vehicle' },
    { name: 'Shield', component: Shield, label: 'Shield / Security' },
    { name: 'ShieldAlert', component: ShieldAlert, label: 'Shield Alert' },
    { name: 'Biohazard', component: Biohazard, label: 'Biohazard' },
    { name: 'Waves', component: Waves, label: 'Waves / Water' },
    { name: 'Megaphone', component: Megaphone, label: 'Megaphone' },
    { name: 'AlertTriangle', component: AlertTriangle, label: 'Warning' },
    { name: 'Siren', component: Siren, label: 'Siren / Emergency' },
    { name: 'Anchor', component: Anchor, label: 'Anchor / Marine' },
    { name: 'Zap', component: Zap, label: 'Electricity' },
    { name: 'Skull', component: Skull, label: 'Danger' },
    { name: 'Bomb', component: Bomb, label: 'Explosive' },
    { name: 'Truck', component: Truck, label: 'Truck' },
    { name: 'Building', component: Building, label: 'Building' },
    { name: 'Mountain', component: Mountain, label: 'Mountain / Landslide' },
    { name: 'TreePine', component: TreePine, label: 'Tree / Forest' },
    { name: 'Sun', component: Sun, label: 'Sun / Heat' },
    { name: 'Snowflake', component: Snowflake, label: 'Cold' },
    { name: 'Umbrella', component: Umbrella, label: 'Umbrella / Flood' },
    { name: 'Construction', component: Construction, label: 'Construction' },
    { name: 'Wrench', component: Wrench, label: 'Wrench / Utility' },
    { name: 'Axe', component: Axe, label: 'Axe / Rescue' },
    { name: 'Footprints', component: Footprints, label: 'Search & Rescue' },
    { name: 'Phone', component: Phone, label: 'Phone / Comms' },
    { name: 'Handshake', component: Handshake, label: 'Coordination' },
    { name: 'Search', component: Search, label: 'Search' },
    { name: 'CircleAlert', component: CircleAlert, label: 'Alert' },
    { name: 'HelpCircle', component: HelpCircle, label: 'Help / Other' },
];

const iconSearch = ref('');

const filteredIcons = computed(() => {
    const search = iconSearch.value.toLowerCase();

    if (!search) {
        return allIcons;
    }

    return allIcons.filter(
        (icon) =>
            icon.label.toLowerCase().includes(search) ||
            icon.name.toLowerCase().includes(search),
    );
});

function selectIcon(name: string): void {
    form.icon = name;
}

function getIconComponent(name: string): Component {
    return allIcons.find((i) => i.name === name)?.component ?? AlertTriangle;
}

function submit(): void {
    if (isEditing.value && props.category) {
        form.submit(update(props.category.id));
    } else {
        form.submit(store());
    }
}
</script>

<template>
    <Head
        :title="
            isEditing ? 'Edit Incident Category' : 'Create Incident Category'
        "
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-6 p-4 sm:p-6 lg:p-8">
            <Heading
                :title="
                    isEditing
                        ? 'Edit Incident Category'
                        : 'Create Incident Category'
                "
                :description="
                    isEditing
                        ? 'Update category details and icon'
                        : 'Add a new incident category with an icon'
                "
            />

            <form
                class="space-y-6 rounded-[var(--radius)] border border-border bg-card p-6 shadow-[var(--shadow-1)]"
                @submit.prevent="submit"
            >
                <!-- Name -->
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        required
                        placeholder="e.g. Medical"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Icon Picker -->
                <div class="grid gap-2">
                    <Label>Icon</Label>

                    <!-- Selected icon preview -->
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-md border border-border bg-accent"
                        >
                            <component
                                :is="getIconComponent(form.icon)"
                                class="size-5 text-foreground"
                            />
                        </div>
                        <span class="text-sm text-muted-foreground">
                            {{ form.icon }}
                        </span>
                    </div>

                    <!-- Search -->
                    <Input
                        v-model="iconSearch"
                        placeholder="Search icons..."
                        class="mt-2"
                    />

                    <!-- Icon grid -->
                    <div
                        class="mt-2 grid max-h-[280px] grid-cols-6 gap-2 overflow-y-auto rounded-md border border-border p-3 sm:grid-cols-8"
                    >
                        <button
                            v-for="icon in filteredIcons"
                            :key="icon.name"
                            type="button"
                            class="flex flex-col items-center gap-1 rounded-md p-2 transition-colors hover:bg-accent"
                            :class="{
                                'bg-accent ring-2 ring-ring':
                                    form.icon === icon.name,
                            }"
                            :title="icon.label"
                            @click="selectIcon(icon.name)"
                        >
                            <component
                                :is="icon.component"
                                class="size-5"
                                :class="
                                    form.icon === icon.name
                                        ? 'text-foreground'
                                        : 'text-muted-foreground'
                                "
                            />
                            <span
                                class="truncate text-center text-[9px] leading-tight text-muted-foreground"
                            >
                                {{ icon.name }}
                            </span>
                        </button>
                    </div>
                    <InputError :message="form.errors.icon" />
                </div>

                <!-- Description -->
                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Optional description for this category"
                        rows="3"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <!-- Sort Order + Active -->
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

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">
                        {{ isEditing ? 'Update Category' : 'Create Category' }}
                    </Button>
                    <Link :href="categoriesIndex.url()">
                        <Button variant="outline" type="button">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
