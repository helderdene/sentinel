<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    storeAndTriage,
    triage,
} from '@/actions/App/Http/Controllers/IntakeStationController';
import ChBadge from '@/components/intake/ChBadge.vue';
import { channelDisplayMap } from '@/components/intake/ChBadge.vue';
import IntakeIconPin from '@/components/intake/icons/IntakeIconPin.vue';
import IntakePriorityPicker from '@/components/intake/IntakePriorityPicker.vue';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxLabel,
} from '@/components/ui/combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useGeocodingSearch } from '@/composables/useGeocodingSearch';
import { usePrioritySuggestion } from '@/composables/usePrioritySuggestion';
import type {
    GeocodingResult,
    Incident,
    IncidentChannel,
    IncidentPriority,
    IncidentType,
} from '@/types/incident';

type Props = {
    activeIncident?: Incident | null;
    isManualEntry: boolean;
    incidentTypes: Record<string, IncidentType[]>;
    channels: IncidentChannel[];
    priorities: IncidentPriority[];
    priorityConfig?: Record<string, unknown>;
};

const props = withDefaults(defineProps<Props>(), {
    activeIncident: null,
    priorityConfig: undefined,
});

const channelLabels: Record<IncidentChannel, string> = {
    phone: 'Voice',
    sms: 'SMS',
    app: 'App',
    iot: 'IoT',
    radio: 'Walk-in',
};

const form = useForm({
    incident_type_id: null as number | null,
    priority: 'P3' as IncidentPriority,
    location_text: '',
    caller_name: '',
    caller_contact: '',
    notes: '',
    latitude: null as number | null,
    longitude: null as number | null,
    barangay_id: null as number | null,
    channel: '' as IncidentChannel | '',
});

const selectionTimestamp = ref<number | null>(null);

const incidentTypeId = ref<number | null>(null);
const notesRef = computed(() => form.notes);

const { suggestion, isLoading: suggestionLoading } = usePrioritySuggestion(
    incidentTypeId,
    notesRef,
);

const locationQuery = ref('');
const { results: geocodingResults, isLoading: geocodingLoading } =
    useGeocodingSearch(locationQuery);
const showGeocodingDropdown = ref(false);

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
    if (val) {
        form.priority = val.priority;
    }
});

function prefillFromIncident(incident: Incident): void {
    form.incident_type_id = incident.incident_type_id || null;
    incidentTypeId.value = incident.incident_type_id || null;
    form.priority = incident.priority || 'P3';
    form.location_text = incident.location_text || '';
    form.caller_name = incident.caller_name || '';
    form.caller_contact = incident.caller_contact || '';
    form.notes = incident.notes || incident.raw_message || '';
    form.latitude = incident.coordinates?.lat ?? null;
    form.longitude = incident.coordinates?.lng ?? null;
    form.barangay_id = incident.barangay_id;
    form.channel = incident.channel || '';
    locationQuery.value = incident.location_text || '';
    selectionTimestamp.value = Date.now();
}

function resetForm(): void {
    form.reset();
    form.clearErrors();
    incidentTypeId.value = null;
    locationQuery.value = '';
    typeSearchQuery.value = '';
    showGeocodingDropdown.value = false;
    selectionTimestamp.value = null;
}

watch(
    () => props.activeIncident,
    (incident) => {
        if (incident) {
            prefillFromIncident(incident);
        } else if (!props.isManualEntry) {
            resetForm();
        }
    },
    { immediate: true },
);

watch(
    () => props.isManualEntry,
    (manual) => {
        if (manual) {
            resetForm();
            selectionTimestamp.value = Date.now();
        }
    },
);

function selectGeocodingResult(result: GeocodingResult): void {
    form.location_text = result.display_name;
    form.latitude = result.lat;
    form.longitude = result.lng;
    locationQuery.value = result.display_name;
    showGeocodingDropdown.value = false;
}

function onLocationInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    locationQuery.value = target.value;
    form.location_text = target.value;
    form.latitude = null;
    form.longitude = null;
    form.barangay_id = null;
    showGeocodingDropdown.value = true;
}

function hideGeocodingDropdown(): void {
    setTimeout(() => {
        showGeocodingDropdown.value = false;
    }, 200);
}

const priorityColors: Record<IncidentPriority, string> = {
    P1: 'var(--t-p1)',
    P2: 'var(--t-p2)',
    P3: 'var(--t-p3)',
    P4: 'var(--t-p4)',
};

const submitColor = computed(
    () => priorityColors[form.priority] ?? 'var(--t-accent)',
);

const canSubmit = computed(() => {
    if (!form.incident_type_id || !form.priority || !form.location_text) {
        return false;
    }

    if (props.isManualEntry && !form.channel) {
        return false;
    }

    return !form.processing;
});

function submit(): void {
    const url = props.isManualEntry
        ? storeAndTriage.url()
        : props.activeIncident
          ? triage.url(props.activeIncident.id)
          : null;

    if (!url) {
        return;
    }

    form.post(url, {
        preserveScroll: true,
    });
}

const sourceChannel = computed(() => {
    if (!props.activeIncident || props.isManualEntry) {
        return null;
    }

    return props.activeIncident.channel;
});

const sourceChannelKey = computed(() => {
    if (!sourceChannel.value) {
        return null;
    }

    return channelDisplayMap[sourceChannel.value] ?? null;
});
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent="submit">
        <!-- Source indicator (pre-filled from existing incident) -->
        <div
            v-if="sourceChannelKey && !isManualEntry"
            class="flex items-center gap-2 rounded-lg border border-t-border bg-t-surface-alt px-3 py-2"
        >
            <ChBadge :ch="sourceChannelKey" small />
            <span class="text-[11px] text-t-text-dim">
                via {{ channelLabels[sourceChannel!] ?? sourceChannel }}
            </span>
        </div>

        <!-- Original message block -->
        <div
            v-if="
                activeIncident &&
                !isManualEntry &&
                (activeIncident.raw_message || activeIncident.notes)
            "
            class="rounded-lg border border-t-border bg-t-surface-alt px-3 py-2"
            style="
                border-left-width: 3px;
                border-left-color: var(--t-border-med);
            "
        >
            <span
                class="mb-1 block font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
            >
                ORIGINAL MESSAGE
            </span>
            <p class="text-[12px] leading-relaxed text-t-text-mid">
                {{ activeIncident.raw_message || activeIncident.notes || '' }}
            </p>
        </div>

        <!-- Channel selector (manual entry only) -->
        <div v-if="isManualEntry" class="flex flex-col gap-1.5">
            <label
                class="text-[11px] font-semibold tracking-wide text-t-text-mid uppercase"
            >
                Channel
            </label>
            <Select v-model="form.channel as string">
                <SelectTrigger
                    class="w-full border-t-border bg-t-surface text-t-text"
                >
                    <SelectValue placeholder="Select reporting channel" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="ch in channels" :key="ch" :value="ch">
                        {{ channelLabels[ch] }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p v-if="form.errors.channel" class="text-[11px] text-t-p1">
                {{ form.errors.channel }}
            </p>
        </div>

        <!-- Incident Type -->
        <div class="flex flex-col gap-1.5">
            <label
                class="text-[11px] font-semibold tracking-wide text-t-text-mid uppercase"
            >
                Incident Type
            </label>
            <Combobox
                v-model="selectedTypeValue"
                v-model:search-term="typeSearchQuery"
                :filter-function="(list: string[]) => list"
            >
                <ComboboxInput
                    class="border-t-border bg-t-surface text-t-text"
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
                    <ComboboxEmpty> No incident types found. </ComboboxEmpty>
                    <template
                        v-for="(types, category) in filteredTypeGroups"
                        :key="category"
                    >
                        <ComboboxGroup>
                            <ComboboxLabel>{{ category }}</ComboboxLabel>
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
            <p
                v-if="form.errors.incident_type_id"
                class="text-[11px] text-t-p1"
            >
                {{ form.errors.incident_type_id }}
            </p>
        </div>

        <!-- Priority Picker -->
        <div class="flex flex-col gap-1.5">
            <label
                class="flex items-center gap-2 text-[11px] font-semibold tracking-wide text-t-text-mid uppercase"
            >
                Priority
                <span
                    v-if="suggestionLoading"
                    class="font-normal text-t-text-faint normal-case"
                >
                    Analyzing...
                </span>
            </label>
            <IntakePriorityPicker
                v-model="form.priority"
                :suggestion="suggestion"
            />
            <p v-if="form.errors.priority" class="text-[11px] text-t-p1">
                {{ form.errors.priority }}
            </p>
        </div>

        <!-- Location -->
        <div class="relative flex flex-col gap-1.5">
            <label
                class="text-[11px] font-semibold tracking-wide text-t-text-mid uppercase"
            >
                Location
            </label>
            <div class="relative">
                <div
                    class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2"
                >
                    <IntakeIconPin :size="13" color="var(--t-text-faint)" />
                </div>
                <input
                    type="text"
                    :value="form.location_text"
                    placeholder="Type an address to search..."
                    autocomplete="off"
                    class="w-full rounded-lg border border-t-border bg-t-surface py-2 pr-3 pl-8 text-[13px] text-t-text transition-colors outline-none placeholder:text-t-text-faint focus:border-t-border-foc"
                    @input="onLocationInput"
                    @focus="showGeocodingDropdown = geocodingResults.length > 0"
                    @blur="hideGeocodingDropdown"
                />
            </div>
            <div
                v-if="showGeocodingDropdown && geocodingResults.length > 0"
                class="absolute top-full z-50 mt-1 w-full rounded-lg border border-t-border bg-t-surface shadow-lg"
            >
                <ul class="max-h-[160px] overflow-y-auto p-1">
                    <li
                        v-for="(result, i) in geocodingResults"
                        :key="i"
                        class="cursor-pointer rounded-md px-2.5 py-1.5 text-[12px] text-t-text-mid transition-colors hover:bg-t-surface-alt"
                        @mousedown.prevent="selectGeocodingResult(result)"
                    >
                        {{ result.display_name }}
                    </li>
                </ul>
            </div>
            <p v-if="geocodingLoading" class="text-[10px] text-t-text-faint">
                Searching locations...
            </p>
            <p v-if="form.errors.location_text" class="text-[11px] text-t-p1">
                {{ form.errors.location_text }}
            </p>
        </div>

        <!-- Caller Name + Contact -->
        <div class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1.5">
                <label
                    class="text-[11px] font-semibold tracking-wide text-t-text-mid uppercase"
                >
                    Caller Name
                </label>
                <input
                    v-model="form.caller_name"
                    type="text"
                    placeholder="e.g. Juan dela Cruz"
                    class="w-full rounded-lg border border-t-border bg-t-surface px-3 py-2 text-[13px] text-t-text transition-colors outline-none placeholder:text-t-text-faint focus:border-t-border-foc"
                />
                <p v-if="form.errors.caller_name" class="text-[11px] text-t-p1">
                    {{ form.errors.caller_name }}
                </p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label
                    class="text-[11px] font-semibold tracking-wide text-t-text-mid uppercase"
                >
                    Caller Contact
                </label>
                <input
                    v-model="form.caller_contact"
                    type="text"
                    placeholder="e.g. 09171234567"
                    class="w-full rounded-lg border border-t-border bg-t-surface px-3 py-2 text-[13px] text-t-text transition-colors outline-none placeholder:text-t-text-faint focus:border-t-border-foc"
                />
                <p
                    v-if="form.errors.caller_contact"
                    class="text-[11px] text-t-p1"
                >
                    {{ form.errors.caller_contact }}
                </p>
            </div>
        </div>

        <!-- Notes -->
        <div class="flex flex-col gap-1.5">
            <label
                class="text-[11px] font-semibold tracking-wide text-t-text-mid uppercase"
            >
                Notes
            </label>
            <textarea
                v-model="form.notes"
                rows="3"
                placeholder="Additional details or observations..."
                class="w-full resize-none rounded-lg border border-t-border bg-t-surface px-3 py-2 text-[13px] leading-relaxed text-t-text transition-colors outline-none placeholder:text-t-text-faint focus:border-t-border-foc"
            />
            <p v-if="form.errors.notes" class="text-[11px] text-t-p1">
                {{ form.errors.notes }}
            </p>
        </div>

        <!-- Submit Button -->
        <button
            type="submit"
            :disabled="!canSubmit"
            class="mt-1 w-full rounded-[7px] px-4 py-2.5 text-[13px] font-semibold text-white shadow-sm transition-all disabled:cursor-not-allowed disabled:border disabled:border-t-border disabled:bg-t-surface-alt disabled:text-t-text-faint disabled:shadow-none"
            :style="
                canSubmit
                    ? {
                          backgroundColor: submitColor,
                          boxShadow: `0 2px 6px color-mix(in srgb, ${submitColor} 30%, transparent)`,
                      }
                    : {}
            "
        >
            {{
                form.processing
                    ? 'Submitting...'
                    : isManualEntry
                      ? 'Create & Triage'
                      : 'Submit Triage'
            }}
        </button>
    </form>
</template>
