<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import type { Hospital } from '@/types/responder';

const props = defineProps<{
    hospitals: Hospital[];
    modelValue: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
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

    return props.hospitals.find((h) => h.id === props.modelValue)?.name ?? '';
});

const filtered = computed(() => {
    if (!query.value.trim()) {
        return props.hospitals;
    }

    const q = query.value.toLowerCase();

    return props.hospitals.filter((h) => h.name.toLowerCase().includes(q));
});

function openDropdown(): void {
    open.value = true;
    query.value = '';
    highlightedIndex.value = -1;
    nextTick(() => inputRef.value?.focus());
}

function closeDropdown(): void {
    setTimeout(() => {
        open.value = false;
        query.value = '';
    }, 150);
}

function selectOption(hospital: Hospital): void {
    emit('update:modelValue', hospital.id);
    open.value = false;
    query.value = '';
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
                filtered.value.length - 1,
            );
            scrollToHighlighted();
            break;
        case 'ArrowUp':
            e.preventDefault();
            highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0);
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
        const el = listRef.value?.children[highlightedIndex.value] as
            | HTMLElement
            | undefined;
        el?.scrollIntoView({ block: 'nearest' });
    });
}

watch(query, () => {
    highlightedIndex.value = 0;
});
</script>

<template>
    <div class="relative">
        <button
            v-if="!open"
            type="button"
            class="flex w-full cursor-pointer items-center rounded-[10px] border-[1.5px] border-t-border bg-t-bg px-3.5 py-2.5 text-left text-[14px] transition-colors outline-none focus:border-t-border-foc"
            :class="modelValue !== null ? 'text-t-text' : 'text-t-text-faint'"
            @click="openDropdown"
        >
            <span class="flex-1 truncate">
                {{ selectedLabel || 'Select hospital...' }}
            </span>
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

        <div
            v-if="open"
            class="relative z-20 rounded-[10px] border border-t-border-foc bg-t-surface shadow-lg"
        >
            <div class="flex items-center gap-2 px-3.5 py-2.5">
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
                    placeholder="Search hospital..."
                    class="flex-1 bg-transparent text-[14px] text-t-text outline-none"
                    @blur="closeDropdown"
                    @keydown="handleKeydown"
                />
            </div>

            <div
                ref="listRef"
                class="max-h-48 overflow-y-auto border-t border-t-border"
            >
                <div
                    v-if="filtered.length === 0"
                    class="px-3.5 py-3 text-center text-[11px] text-t-text-faint"
                >
                    No hospitals found
                </div>
                <button
                    v-for="(h, i) in filtered"
                    :key="h.id"
                    type="button"
                    class="flex w-full cursor-pointer items-center gap-2 border-none bg-transparent px-3.5 py-2.5 text-left text-[13px] text-t-text transition-colors"
                    :class="[
                        i === highlightedIndex
                            ? 'bg-t-accent/8'
                            : 'hover:bg-t-border/30',
                        h.id === modelValue ? 'font-semibold' : '',
                    ]"
                    @mousedown.prevent="selectOption(h)"
                    @mouseenter="highlightedIndex = i"
                >
                    <svg
                        v-if="h.id === modelValue"
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
                    <span :class="h.id === modelValue ? '' : 'pl-[22px]'">
                        {{ h.name }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
