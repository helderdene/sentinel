<script setup lang="ts">
import type { IncidentType } from '@/types';
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import StepIndicator from '@/components/StepIndicator.vue';
import TypeCard from '@/components/TypeCard.vue';
import { useApi } from '@/composables/useApi';
import { useReportDraft } from '@/composables/useReportDraft';

const router = useRouter();
const { get, loading } = useApi();
const { setType } = useReportDraft();

const types = ref<IncidentType[]>([]);
const selectedKey = ref<number | null>(null);

onMounted(async () => {
    try {
        const res = await get<{ data: IncidentType[] }>(
            '/api/v1/citizen/incident-types'
        );
        types.value = res.data;
    } catch {
        // Error handled by useApi
    }
});

function handleSelect(type: IncidentType): void {
    selectedKey.value = type.id;
    setType(type);
    setTimeout(() => {
        router.push('/report/details');
    }, 200);
}
</script>

<template>
    <div class="flex h-full flex-col bg-t-bg">
        <!-- Header -->
        <div
            class="flex shrink-0 items-center gap-2.5 border-b border-t-border bg-t-surface px-4 py-3.5 shadow-[0_1px_4px_rgba(0,0,0,.04)]"
        >
            <button
                class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-[9px] border border-t-border bg-transparent"
                @click="router.push('/')"
            >
                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 20 20"
                    fill="none"
                    class="text-t-text-dim"
                >
                    <path
                        d="M13 4L7 10L13 16"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </button>
            <div>
                <div class="text-[16px] font-bold text-t-text">
                    What happened?
                </div>
                <div class="text-[11px] text-t-text-dim">
                    Select the type of emergency
                </div>
            </div>
        </div>

        <!-- Step indicator -->
        <div class="shrink-0 px-4 pt-2.5">
            <StepIndicator :current-step="1" />
        </div>

        <!-- Type grid (scrollable) -->
        <div class="hide-scrollbar flex-1 overflow-y-auto px-4 pb-4 pt-3.5">
            <!-- Loading skeleton -->
            <div
                v-if="loading"
                class="grid grid-cols-2 gap-2.5"
            >
                <div
                    v-for="n in 8"
                    :key="n"
                    class="h-36 animate-pulse rounded-xl border border-t-border bg-t-surface"
                />
            </div>

            <!-- Type cards -->
            <div
                v-else
                class="grid grid-cols-2 gap-2.5"
            >
                <TypeCard
                    v-for="type in types"
                    :key="type.id"
                    :type="type"
                    :selected="selectedKey === type.id"
                    @select="handleSelect(type)"
                />
            </div>
        </div>
    </div>
</template>
