<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { Loader2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
} from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';

export interface FilterState {
    severity: string[];
    camera_id: string | null;
    q: string | null;
    from: string | null;
    to: string | null;
}

export interface AvailableCamera {
    id: string;
    camera_id_display: string;
    name: string;
}

const props = defineProps<{
    modelValue: FilterState;
    availableCameras: AvailableCamera[];
    searching: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: FilterState, opts: { fromSearch: boolean }];
}>();

const SEVERITY_OPTIONS: Array<{ value: string; label: string }> = [
    { value: 'critical', label: 'Critical' },
    { value: 'warning', label: 'Warning' },
    { value: 'info', label: 'Info' },
];

const localSearch = ref<string>(props.modelValue.q ?? '');

watch(
    () => props.modelValue.q,
    (next) => {
        const nextValue = next ?? '';
        if (nextValue !== localSearch.value) {
            localSearch.value = nextValue;
        }
    },
);

const cameraSearch = ref<string>('');

const filteredCameras = computed(() => {
    const term = cameraSearch.value.trim().toLowerCase();

    if (term.length === 0) {
        return props.availableCameras;
    }

    return props.availableCameras.filter((c) => {
        const display = c.camera_id_display.toLowerCase();
        const name = c.name.toLowerCase();

        return display.includes(term) || name.includes(term);
    });
});

const selectedCamera = computed(() =>
    props.availableCameras.find((c) => c.id === props.modelValue.camera_id) ??
    null,
);

const hasAnyFilter = computed(() => {
    const m = props.modelValue;

    return (
        m.severity.length > 0 ||
        m.camera_id !== null ||
        (m.q !== null && m.q.trim().length > 0) ||
        m.from !== null ||
        m.to !== null
    );
});

function emitNext(patch: Partial<FilterState>, fromSearch = false): void {
    emit(
        'update:modelValue',
        { ...props.modelValue, ...patch },
        { fromSearch },
    );
}

function toggleSeverity(value: string): void {
    const current = props.modelValue.severity;
    const next = current.includes(value)
        ? current.filter((v) => v !== value)
        : [...current, value];

    emitNext({ severity: next });
}

function selectCamera(id: string | null): void {
    emitNext({ camera_id: id });
}

function updateFrom(e: Event): void {
    const value = (e.target as HTMLInputElement).value;

    emitNext({ from: value.length > 0 ? value : null });
}

function updateTo(e: Event): void {
    const value = (e.target as HTMLInputElement).value;

    emitNext({ to: value.length > 0 ? value : null });
}

const debouncedSearchUpdate = useDebounceFn((q: string) => {
    emitNext({ q }, true);
}, 300);

function onSearchInput(e: Event): void {
    const value = (e.target as HTMLInputElement).value;

    localSearch.value = value;
    debouncedSearchUpdate(value);
}

function clearFilters(): void {
    localSearch.value = '';
    cameraSearch.value = '';
    emit(
        'update:modelValue',
        {
            severity: [],
            camera_id: null,
            q: '',
            from: null,
            to: null,
        },
        { fromSearch: false },
    );
}
</script>

<template>
    <div
        class="space-y-4 rounded-[var(--radius)] border border-t-border bg-t-surface-alt/30 p-4"
    >
        <!-- Severity pills -->
        <div class="space-y-2">
            <label
                class="font-mono text-[9px] tracking-[2px] text-t-text-faint uppercase"
            >
                Severity
            </label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="opt in SEVERITY_OPTIONS"
                    :key="opt.value"
                    type="button"
                    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                    :class="
                        modelValue.severity.includes(opt.value)
                            ? 'border-t-accent bg-t-accent text-white'
                            : 'border-t-border bg-transparent text-t-text hover:border-t-accent/40'
                    "
                    :aria-pressed="modelValue.severity.includes(opt.value)"
                    @click="toggleSeverity(opt.value)"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <!-- Camera filter -->
            <div class="space-y-2">
                <label
                    class="font-mono text-[9px] tracking-[2px] text-t-text-faint uppercase"
                >
                    Camera
                </label>
                <Combobox
                    :model-value="modelValue.camera_id"
                    @update:model-value="
                        (v) =>
                            selectCamera(
                                typeof v === 'string' && v.length > 0 ? v : null,
                            )
                    "
                >
                    <ComboboxInput
                        v-model="cameraSearch"
                        :placeholder="
                            selectedCamera
                                ? `${selectedCamera.camera_id_display} — ${selectedCamera.name}`
                                : 'All cameras'
                        "
                        @keydown.enter.prevent
                    />
                    <ComboboxContent
                        class="max-h-[220px] w-[--reka-combobox-trigger-width] overflow-y-auto"
                    >
                        <ComboboxEmpty>No cameras found.</ComboboxEmpty>
                        <ComboboxItem
                            v-if="modelValue.camera_id !== null"
                            value="__clear__"
                            @select.prevent="selectCamera(null)"
                        >
                            <span class="text-xs text-t-text-faint">
                                Clear camera selection
                            </span>
                        </ComboboxItem>
                        <ComboboxItem
                            v-for="camera in filteredCameras"
                            :key="camera.id"
                            :value="camera.id"
                            @select.prevent="selectCamera(camera.id)"
                        >
                            <span class="flex items-center gap-2">
                                <span class="font-mono text-xs">
                                    {{ camera.camera_id_display }}
                                </span>
                                <span class="text-xs text-t-text-dim">
                                    — {{ camera.name }}
                                </span>
                            </span>
                        </ComboboxItem>
                    </ComboboxContent>
                </Combobox>
            </div>

            <!-- Date range -->
            <div class="space-y-2">
                <label
                    class="font-mono text-[9px] tracking-[2px] text-t-text-faint uppercase"
                >
                    Date range
                </label>
                <div class="flex items-center gap-2">
                    <Input
                        type="date"
                        :value="modelValue.from ?? ''"
                        placeholder="From"
                        aria-label="Date range from"
                        class="flex-1"
                        @change="updateFrom"
                    />
                    <span
                        class="text-xs text-t-text-faint"
                        aria-hidden="true"
                    >
                        —
                    </span>
                    <Input
                        type="date"
                        :value="modelValue.to ?? ''"
                        placeholder="To"
                        aria-label="Date range to"
                        class="flex-1"
                        @change="updateTo"
                    />
                </div>
            </div>

            <!-- Search -->
            <div class="space-y-2 md:col-span-2">
                <label
                    class="font-mono text-[9px] tracking-[2px] text-t-text-faint uppercase"
                >
                    Search
                </label>
                <div class="relative">
                    <Input
                        :value="localSearch"
                        type="search"
                        placeholder="Search by personnel or camera…"
                        class="pr-9"
                        @input="onSearchInput"
                    />
                    <div
                        class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3"
                    >
                        <Loader2
                            v-if="searching"
                            class="size-4 animate-spin text-t-text-faint"
                            aria-label="Searching"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Clear filters button -->
        <div v-if="hasAnyFilter" class="flex justify-end">
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="gap-1"
                @click="clearFilters"
            >
                <X class="size-3" aria-hidden="true" />
                Clear filters
            </Button>
        </div>
    </div>
</template>
