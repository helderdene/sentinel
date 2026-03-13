<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';

export interface SelectOption {
    value: number;
    label: string;
}

const props = defineProps<{
    options: SelectOption[];
    modelValue: number | null;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const open = ref(false);
const query = ref('');
const inputRef = ref<HTMLInputElement | null>(null);
const listRef = ref<HTMLDivElement | null>(null);
const highlightedIndex = ref(-1);

const selectedLabel = computed(() => {
    if (props.modelValue === null) {
return '';
}

    return (
        props.options.find((o) => o.value === props.modelValue)?.label ??
        ''
    );
});

const filtered = computed(() => {
    if (!query.value.trim()) {
return props.options;
}

    const q = query.value.toLowerCase();

    return props.options.filter((o) => o.label.toLowerCase().includes(q));
});

function openDropdown(): void {
    open.value = true;
    query.value = '';
    highlightedIndex.value = -1;
    nextTick(() => inputRef.value?.focus());
}

function closeDropdown(): void {
    // Small delay so click on option registers
    setTimeout(() => {
        open.value = false;
        query.value = '';
    }, 150);
}

function selectOption(opt: SelectOption): void {
    emit('update:modelValue', opt.value);
    open.value = false;
    query.value = '';
}

function clearSelection(): void {
    emit('update:modelValue', null);
    query.value = '';
    nextTick(() => inputRef.value?.focus());
}

function handleKeydown(e: KeyboardEvent): void {
    if (!open.value) {
        if (e.key === 'ArrowDown' || e.key === 'Enter') {
            openDropdown();
            e.preventDefault();
        }

        return;
    }

    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault();
            highlightedIndex.value = Math.min(
                highlightedIndex.value + 1,
                filtered.value.length - 1
            );
            scrollToHighlighted();
            break;
        case 'ArrowUp':
            e.preventDefault();
            highlightedIndex.value = Math.max(
                highlightedIndex.value - 1,
                0
            );
            scrollToHighlighted();
            break;
        case 'Enter':
            e.preventDefault();

            if (
                highlightedIndex.value >= 0 &&
                highlightedIndex.value < filtered.value.length
            ) {
                selectOption(filtered.value[highlightedIndex.value]);
            }

            break;
        case 'Escape':
            open.value = false;
            query.value = '';
            break;
    }
}

function scrollToHighlighted(): void {
    nextTick(() => {
        const el = listRef.value?.children[
            highlightedIndex.value
        ] as HTMLElement | undefined;
        el?.scrollIntoView({ block: 'nearest' });
    });
}

watch(query, () => {
    highlightedIndex.value = 0;
});
</script>

<template>
    <div class="relative">
        <!-- Closed state: shows selected or placeholder -->
        <button
            v-if="!open"
            type="button"
            class="flex w-full cursor-pointer items-center rounded-[10px] border-[1.5px] border-t-border bg-t-surface px-3.5 py-[11px] text-left text-[14px] outline-none transition-colors focus:border-t-accent"
            :class="
                modelValue !== null ? 'text-t-text' : 'text-t-text-faint'
            "
            @click="openDropdown"
        >
            <span class="flex-1 truncate">
                {{ selectedLabel || placeholder || 'Select...' }}
            </span>
            <!-- Clear button -->
            <svg
                v-if="modelValue !== null"
                width="16"
                height="16"
                viewBox="0 0 16 16"
                fill="none"
                class="mr-1 shrink-0 text-t-text-dim hover:text-t-text"
                @click.stop="clearSelection"
            >
                <path
                    d="M5 5L11 11M11 5L5 11"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                />
            </svg>
            <!-- Chevron -->
            <svg
                width="16"
                height="16"
                viewBox="0 0 16 16"
                fill="none"
                class="shrink-0 text-t-text-dim"
            >
                <path
                    d="M4 6L8 10L12 6"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </button>

        <!-- Open state: search input -->
        <div
            v-if="open"
            class="rounded-[10px] border-[1.5px] border-t-accent bg-t-surface shadow-lg"
        >
            <div class="flex items-center gap-2 px-3.5 py-[11px]">
                <svg
                    width="16"
                    height="16"
                    viewBox="0 0 16 16"
                    fill="none"
                    class="shrink-0 text-t-text-dim"
                >
                    <circle
                        cx="7"
                        cy="7"
                        r="4.5"
                        stroke="currentColor"
                        stroke-width="1.4"
                    />
                    <path
                        d="M10.5 10.5L13.5 13.5"
                        stroke="currentColor"
                        stroke-width="1.4"
                        stroke-linecap="round"
                    />
                </svg>
                <input
                    ref="inputRef"
                    v-model="query"
                    type="text"
                    placeholder="Search barangay..."
                    class="flex-1 bg-transparent text-[14px] text-t-text outline-none"
                    @blur="closeDropdown"
                    @keydown="handleKeydown"
                />
            </div>

            <!-- Options list -->
            <div
                ref="listRef"
                class="max-h-48 overflow-y-auto border-t border-t-border"
            >
                <div
                    v-if="filtered.length === 0"
                    class="px-3.5 py-3 text-center text-[13px] text-t-text-faint"
                >
                    No barangays found
                </div>
                <button
                    v-for="(opt, i) in filtered"
                    :key="opt.value"
                    type="button"
                    class="flex w-full cursor-pointer items-center gap-2 border-none bg-transparent px-3.5 py-2.5 text-left text-[14px] text-t-text transition-colors"
                    :class="[
                        i === highlightedIndex
                            ? 'bg-t-accent/8'
                            : 'hover:bg-t-border/30',
                        opt.value === modelValue
                            ? 'font-semibold'
                            : '',
                    ]"
                    @mousedown.prevent="selectOption(opt)"
                    @mouseenter="highlightedIndex = i"
                >
                    <svg
                        v-if="opt.value === modelValue"
                        width="14"
                        height="14"
                        viewBox="0 0 16 16"
                        fill="none"
                        class="shrink-0 text-t-accent"
                    >
                        <path
                            d="M3.5 8L6.5 11L12.5 5"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <span
                        :class="
                            opt.value === modelValue ? '' : 'pl-[22px]'
                        "
                    >
                        {{ opt.label }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
