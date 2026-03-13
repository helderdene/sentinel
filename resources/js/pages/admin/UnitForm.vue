<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/AdminUnitController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
} from '@/components/ui/combobox';
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
import { index as unitsIndex } from '@/routes/admin/units';
import type { BreadcrumbItem } from '@/types';

type UnitData = {
    id: string;
    callsign: string;
    type: string;
    agency: string;
    crew_capacity: number;
    status: string;
    shift: string | null;
    notes: string | null;
    users: Array<{ id: number; name: string }>;
};

type Props = {
    unit?: UnitData;
    types?: string[];
    statuses: string[];
    responders: Array<{ id: number; name: string; unit_id: string | null }>;
};

const props = defineProps<Props>();

const isEditing = computed(() => !!props.unit);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: unitsIndex.url() },
    { title: 'Units', href: unitsIndex.url() },
    { title: isEditing.value ? 'Edit' : 'Create', href: '#' },
];

const presetAgencies = ['CDRRMO', 'BFP', 'PNP'];

const form = useForm({
    type: props.unit?.type ?? '',
    callsign: props.unit?.callsign ?? '',
    agency: props.unit?.agency ?? 'CDRRMO',
    crew_capacity: props.unit?.crew_capacity ?? 4,
    status: props.unit?.status ?? 'AVAILABLE',
    shift: props.unit?.shift || 'none',
    notes: props.unit?.notes ?? '',
    crew_ids: props.unit?.users?.map((u) => u.id) ?? [],
});

const initialIsCustom =
    !!props.unit?.agency && !presetAgencies.includes(props.unit.agency);

const showCustomAgency = ref(initialIsCustom);

const isCustomAgency = computed(() => showCustomAgency.value);

const customAgencyInput = ref(
    initialIsCustom ? (props.unit?.agency ?? '') : '',
);

const crewSearch = ref('');

const availableResponders = computed(() =>
    props.responders.filter((r) => {
        // Already selected for this unit — keep visible
        if (form.crew_ids.includes(r.id)) {
            return true;
        }

        // Unassigned — available
        if (!r.unit_id) {
            return true;
        }

        // Assigned to this unit on server (editing) — available
        if (isEditing.value && r.unit_id === props.unit?.id) {
            return true;
        }

        // Assigned elsewhere — hide
        return false;
    }),
);

const filteredResponders = computed(() => {
    const search = crewSearch.value.toLowerCase();

    if (!search) {
        return availableResponders.value;
    }

    return availableResponders.value.filter((r) =>
        r.name.toLowerCase().includes(search),
    );
});

const isOverCapacity = computed(
    () => form.crew_ids.length > form.crew_capacity,
);

function handleAgencySelect(value: string): void {
    if (value === '__other__') {
        showCustomAgency.value = true;
        form.agency = customAgencyInput.value;
    } else {
        showCustomAgency.value = false;
        form.agency = value;
        customAgencyInput.value = '';
    }
}

watch(customAgencyInput, (val) => {
    if (showCustomAgency.value) {
        form.agency = val;
    }
});

function getResponderUnit(responder: {
    id: number;
    unit_id: string | null;
}): string {
    if (!responder.unit_id) {
        return '';
    }

    if (isEditing.value && responder.unit_id === props.unit?.id) {
        return '';
    }

    return responder.unit_id;
}

function toggleCrew(responderId: number): void {
    const index = form.crew_ids.indexOf(responderId);

    if (index === -1) {
        form.crew_ids.push(responderId);
    } else {
        form.crew_ids.splice(index, 1);
    }
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        shift: data.shift === 'none' ? '' : data.shift,
    }));

    if (isEditing.value && props.unit) {
        form.submit(update(props.unit.id));
    } else {
        form.submit(store());
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Unit' : 'Create Unit'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-6 p-4 sm:p-6 lg:p-8">
            <Heading
                :title="isEditing ? 'Edit Unit' : 'Create Unit'"
                :description="
                    isEditing
                        ? 'Update unit details and crew assignments'
                        : 'Create a new response unit with crew assignment'
                "
            />

            <form
                class="space-y-6 rounded-[var(--radius)] border border-border bg-card p-6 shadow-[var(--shadow-1)]"
                @submit.prevent="submit"
            >
                <!-- Section 1: Unit Identity -->
                <div class="space-y-4">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Unit Identity
                    </h3>

                    <div v-if="!isEditing" class="grid gap-2">
                        <Label for="type">Type</Label>
                        <Select v-model="form.type">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select unit type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="t in types"
                                    :key="t"
                                    :value="t"
                                >
                                    {{ t.charAt(0).toUpperCase() + t.slice(1) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div v-else class="grid gap-2">
                        <Label>Type</Label>
                        <Input
                            :model-value="
                                (unit?.type ?? '').charAt(0).toUpperCase() +
                                (unit?.type ?? '').slice(1)
                            "
                            disabled
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="callsign">Callsign</Label>
                        <Input
                            id="callsign"
                            v-model="form.callsign"
                            placeholder="e.g. Ambulance 3 (auto-generated if blank)"
                        />
                        <InputError :message="form.errors.callsign" />
                    </div>
                </div>

                <!-- Section 2: Organization -->
                <div class="space-y-4">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Organization
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="agency">Agency</Label>
                            <Select
                                :model-value="
                                    isCustomAgency ? '__other__' : form.agency
                                "
                                @update:model-value="handleAgencySelect"
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select agency" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="agency in presetAgencies"
                                        :key="agency"
                                        :value="agency"
                                    >
                                        {{ agency }}
                                    </SelectItem>
                                    <SelectItem value="__other__">
                                        Other
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Input
                                v-if="isCustomAgency"
                                v-model="customAgencyInput"
                                placeholder="Enter agency name"
                            />
                            <InputError :message="form.errors.agency" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="shift">Shift</Label>
                            <Select v-model="form.shift">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Unassigned" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Unassigned
                                    </SelectItem>
                                    <SelectItem value="day"> Day </SelectItem>
                                    <SelectItem value="night">
                                        Night
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.shift" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="crew_capacity">Crew Capacity</Label>
                        <Input
                            id="crew_capacity"
                            v-model.number="form.crew_capacity"
                            type="number"
                            min="1"
                            max="20"
                        />
                        <InputError :message="form.errors.crew_capacity" />
                    </div>
                </div>

                <!-- Section 3: Status -->
                <div class="space-y-4">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Status
                    </h3>

                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <Select v-model="form.status">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="s in statuses"
                                    :key="s"
                                    :value="s"
                                >
                                    {{
                                        s
                                            .replace(/_/g, ' ')
                                            .charAt(0)
                                            .toUpperCase() +
                                        s
                                            .replace(/_/g, ' ')
                                            .slice(1)
                                            .toLowerCase()
                                    }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>
                </div>

                <!-- Section 4: Crew Assignment -->
                <div class="space-y-4">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Crew Assignment
                    </h3>

                    <div v-if="isOverCapacity" class="mb-2">
                        <Badge
                            variant="secondary"
                            class="bg-[color-mix(in_srgb,var(--t-p2)_12%,transparent)] text-t-p2"
                        >
                            Crew exceeds capacity ({{ form.crew_ids.length }}/{{
                                form.crew_capacity
                            }})
                        </Badge>
                    </div>

                    <div class="grid gap-2">
                        <Label>Assign Responders</Label>
                        <Combobox :model-value="form.crew_ids" multiple>
                            <ComboboxInput
                                v-model="crewSearch"
                                placeholder="Search responders..."
                                @keydown.enter.prevent
                            />
                            <ComboboxContent
                                class="max-h-[200px] w-[--reka-combobox-trigger-width] overflow-y-auto"
                            >
                                <ComboboxEmpty>
                                    {{
                                        availableResponders.length === 0
                                            ? 'No available responders'
                                            : 'No responders found.'
                                    }}
                                </ComboboxEmpty>
                                <ComboboxItem
                                    v-for="responder in filteredResponders"
                                    :key="responder.id"
                                    :value="responder.id"
                                    @select.prevent="toggleCrew(responder.id)"
                                >
                                    <span class="flex items-center gap-2">
                                        <span>{{ responder.name }}</span>
                                        <span
                                            v-if="getResponderUnit(responder)"
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            ({{ getResponderUnit(responder) }})
                                        </span>
                                    </span>
                                </ComboboxItem>
                            </ComboboxContent>
                        </Combobox>
                        <InputError :message="form.errors.crew_ids" />
                    </div>

                    <div
                        v-if="form.crew_ids.length > 0"
                        class="flex flex-wrap gap-2"
                    >
                        <Badge
                            v-for="id in form.crew_ids"
                            :key="id"
                            variant="secondary"
                            class="cursor-pointer"
                            @click="toggleCrew(id)"
                        >
                            {{
                                responders.find((r) => r.id === id)?.name ??
                                `#${id}`
                            }}
                            <span class="ml-1 text-muted-foreground"> x </span>
                        </Badge>
                    </div>
                </div>

                <!-- Section 5: Notes -->
                <div class="space-y-4">
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
                            placeholder="Optional notes about this unit"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing">
                        {{ isEditing ? 'Update Unit' : 'Create Unit' }}
                    </Button>
                    <Link :href="unitsIndex.url()">
                        <Button variant="outline" type="button">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
