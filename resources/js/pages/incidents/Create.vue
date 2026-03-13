<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { store } from '@/actions/App/Http/Controllers/IncidentController';
import Heading from '@/components/Heading.vue';
import PrioritySelector from '@/components/incidents/PrioritySelector.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxLabel,
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
import { useGeocodingSearch } from '@/composables/useGeocodingSearch';
import { usePrioritySuggestion } from '@/composables/usePrioritySuggestion';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { queue as incidentsQueue } from '@/routes/incidents';
import type { BreadcrumbItem } from '@/types';
import type {
    GeocodingResult,
    IncidentChannel,
    IncidentPriority,
    IncidentType,
} from '@/types/incident';

type Props = {
    incidentTypes: Record<string, IncidentType[]>;
    channels: IncidentChannel[];
    priorities: IncidentPriority[];
    priorityConfig: Record<string, unknown>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Incident Queue', href: incidentsQueue() },
    { title: 'New Incident', href: '#' },
];

const form = useForm({
    channel: '' as IncidentChannel | '',
    caller_name: '',
    caller_contact: '',
    incident_type_id: null as number | null,
    priority: 'P3' as IncidentPriority,
    location_text: '',
    latitude: null as number | null,
    longitude: null as number | null,
    barangay_id: null as number | null,
    notes: '',
});

const incidentTypeId = ref<number | null>(null);
const notesRef = computed(() => form.notes);

const { suggestion, isLoading: suggestionLoading } = usePrioritySuggestion(
    incidentTypeId,
    notesRef,
);

const locationQuery = ref('');
const { results: geocodingResults, isLoading: geocodingLoading } =
    useGeocodingSearch(locationQuery);

const selectedBarangayName = ref<string | null>(null);
const showGeocodingDropdown = ref(false);

const channelLabels: Record<IncidentChannel, string> = {
    phone: 'Phone',
    sms: 'SMS',
    app: 'App (Walk-in/Web)',
    iot: 'IoT Sensor',
    radio: 'Radio',
};

const allTypes = computed(() => {
    const items: IncidentType[] = [];

    for (const category of Object.keys(props.incidentTypes)) {
        items.push(...props.incidentTypes[category]);
    }

    return items;
});

const typeSearchQuery = ref('');

const filteredTypeGroups = computed(() => {
    const query = typeSearchQuery.value.toLowerCase().trim();
    const groups: Record<string, IncidentType[]> = {};

    for (const [category, types] of Object.entries(props.incidentTypes)) {
        const filtered = query
            ? types.filter(
                  (t) =>
                      t.name.toLowerCase().includes(query) ||
                      t.code.toLowerCase().includes(query) ||
                      category.toLowerCase().includes(query),
              )
            : types;

        if (filtered.length > 0) {
            groups[category] = filtered;
        }
    }

    return groups;
});

const selectedTypeValue = computed({
    get() {
        return form.incident_type_id ? String(form.incident_type_id) : '';
    },
    set(val: string) {
        const id = val ? Number(val) : null;
        form.incident_type_id = id;
        incidentTypeId.value = id;

        if (id) {
            const type = allTypes.value.find((t) => t.id === id);

            if (type) {
                form.priority = type.default_priority;
            }
        }
    },
});

watch(suggestion, (val) => {
    if (val && !form.isDirty) {
        form.priority = val.priority;
    }
});

function selectGeocodingResult(result: GeocodingResult): void {
    form.location_text = result.display_name;
    form.latitude = result.lat;
    form.longitude = result.lng;
    locationQuery.value = result.display_name;
    showGeocodingDropdown.value = false;
    selectedBarangayName.value = null;
}

function onLocationInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    locationQuery.value = target.value;
    form.location_text = target.value;
    form.latitude = null;
    form.longitude = null;
    form.barangay_id = null;
    selectedBarangayName.value = null;
    showGeocodingDropdown.value = true;
}

function hideGeocodingDropdown(): void {
    setTimeout(() => {
        showGeocodingDropdown.value = false;
    }, 200);
}

function submit(): void {
    form.submit(store());
}
</script>

<template>
    <Head title="New Incident" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-6 p-4 sm:p-6 lg:p-8">
            <Heading
                title="New Incident"
                description="Fill out the triage form to create a new incident"
            />

            <form
                class="space-y-8 rounded-[var(--radius)] border border-border bg-card p-6 shadow-[var(--shadow-1)]"
                @submit.prevent="submit"
            >
                <!-- Channel + Caller Info -->
                <fieldset class="space-y-4">
                    <legend
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Channel & Caller Info
                    </legend>

                    <div class="grid gap-2">
                        <Label for="channel">Channel</Label>
                        <Select v-model="form.channel">
                            <SelectTrigger class="w-full">
                                <SelectValue
                                    placeholder="Select reporting channel"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="ch in channels"
                                    :key="ch"
                                    :value="ch"
                                >
                                    {{ channelLabels[ch] }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.channel" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="caller_name">Caller Name</Label>
                            <Input
                                id="caller_name"
                                v-model="form.caller_name"
                                placeholder="e.g. Juan dela Cruz"
                            />
                            <InputError :message="form.errors.caller_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="caller_contact"> Caller Contact </Label>
                            <Input
                                id="caller_contact"
                                v-model="form.caller_contact"
                                placeholder="e.g. 09171234567"
                            />
                            <InputError :message="form.errors.caller_contact" />
                        </div>
                    </div>
                </fieldset>

                <!-- Incident Details -->
                <fieldset class="space-y-4">
                    <legend
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Incident Details
                    </legend>

                    <div class="grid gap-2">
                        <Label>Incident Type</Label>
                        <Combobox
                            v-model="selectedTypeValue"
                            v-model:search-term="typeSearchQuery"
                            :filter-function="
                                (list: string[]) => {
                                    return list;
                                }
                            "
                        >
                            <ComboboxInput
                                placeholder="Search incident types..."
                                :display-value="
                                    (val: string) => {
                                        const t = allTypes.find(
                                            (t) => String(t.id) === val,
                                        );
                                        return t ? `${t.code} ${t.name}` : '';
                                    }
                                "
                            />
                            <ComboboxContent>
                                <ComboboxEmpty>
                                    No incident types found.
                                </ComboboxEmpty>
                                <template
                                    v-for="(
                                        types, category
                                    ) in filteredTypeGroups"
                                    :key="category"
                                >
                                    <ComboboxGroup>
                                        <ComboboxLabel>
                                            {{ category }}
                                        </ComboboxLabel>
                                        <ComboboxItem
                                            v-for="t in types"
                                            :key="t.id"
                                            :value="String(t.id)"
                                        >
                                            <span class="font-mono text-xs">
                                                {{ t.code }}
                                            </span>
                                            {{ t.name }}
                                        </ComboboxItem>
                                    </ComboboxGroup>
                                </template>
                            </ComboboxContent>
                        </Combobox>
                        <InputError :message="form.errors.incident_type_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Priority</Label>
                        <div class="flex items-center gap-3">
                            <PrioritySelector
                                v-model="form.priority"
                                :suggestion="suggestion"
                            />
                            <span
                                v-if="suggestionLoading"
                                class="text-xs text-muted-foreground"
                            >
                                Analyzing...
                            </span>
                        </div>
                        <InputError :message="form.errors.priority" />
                    </div>
                </fieldset>

                <!-- Location -->
                <fieldset class="space-y-4">
                    <legend
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Location
                    </legend>

                    <div class="relative grid gap-2">
                        <Label for="location_text">Location Address</Label>
                        <Input
                            id="location_text"
                            :model-value="form.location_text"
                            placeholder="Type an address to search..."
                            autocomplete="off"
                            @input="onLocationInput"
                            @focus="
                                showGeocodingDropdown =
                                    geocodingResults.length > 0
                            "
                            @blur="hideGeocodingDropdown"
                        />
                        <div
                            v-if="
                                showGeocodingDropdown &&
                                geocodingResults.length > 0
                            "
                            class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-md"
                        >
                            <ul class="max-h-[200px] overflow-y-auto p-1">
                                <li
                                    v-for="(result, i) in geocodingResults"
                                    :key="i"
                                    class="cursor-pointer rounded-sm px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground"
                                    @mousedown.prevent="
                                        selectGeocodingResult(result)
                                    "
                                >
                                    {{ result.display_name }}
                                </li>
                            </ul>
                        </div>
                        <p
                            v-if="geocodingLoading"
                            class="text-xs text-muted-foreground"
                        >
                            Searching locations...
                        </p>
                        <InputError :message="form.errors.location_text" />
                    </div>

                    <div
                        v-if="form.latitude && form.longitude"
                        class="grid gap-4 sm:grid-cols-2"
                    >
                        <div class="grid gap-2">
                            <Label class="text-muted-foreground">
                                Coordinates
                            </Label>
                            <p class="text-sm">
                                {{ form.latitude?.toFixed(6) }},
                                {{ form.longitude?.toFixed(6) }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-muted-foreground">
                                Barangay
                            </Label>
                            <p class="text-sm">
                                {{
                                    selectedBarangayName ??
                                    'Will be assigned on submit'
                                }}
                            </p>
                        </div>
                    </div>
                </fieldset>

                <!-- Notes -->
                <fieldset class="space-y-4">
                    <legend
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Notes
                    </legend>

                    <div class="grid gap-2">
                        <Label for="notes">Incident Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            class="flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                            placeholder="Describe the incident, caller report, and any additional details..."
                            rows="4"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </fieldset>

                <!-- Submit -->
                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? 'Creating...'
                                : '+ Create Incident'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
