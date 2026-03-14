<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { requestMutualAid } from '@/actions/App/Http/Controllers/DispatchConsoleController';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { DispatchAgency, DispatchIncident } from '@/types/dispatch';

const props = defineProps<{
    incident: DispatchIncident;
    agencies: DispatchAgency[];
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const selectedAgencyId = ref<number | null>(null);
const notes = ref('');
const isSubmitting = ref(false);
const showSuccess = ref(false);

const suggestedAgencyIds = computed(() => {
    const incidentTypeId = props.incident.incident_type_id;

    return props.agencies
        .filter((agency) =>
            agency.incident_types.some((it) => it.id === incidentTypeId),
        )
        .map((a) => a.id);
});

const sortedAgencies = computed(() => {
    const suggested = props.agencies.filter((a) =>
        suggestedAgencyIds.value.includes(a.id),
    );
    const others = props.agencies.filter(
        (a) => !suggestedAgencyIds.value.includes(a.id),
    );

    return [...suggested, ...others];
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            selectedAgencyId.value = null;
            notes.value = '';
            showSuccess.value = false;
        }
    },
);

async function handleSubmit(): Promise<void> {
    if (!selectedAgencyId.value || isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;

    try {
        const url = requestMutualAid.url(props.incident.id);
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((row) => row.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] ?? '',
                ),
            },
            body: JSON.stringify({
                agency_id: selectedAgencyId.value,
                notes: notes.value || null,
            }),
        });

        if (response.ok) {
            showSuccess.value = true;

            setTimeout(() => {
                emit('update:open', false);
            }, 1200);
        }
    } finally {
        isSubmitting.value = false;
    }
}

function handleClose(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="border-t-border bg-t-bg sm:max-w-lg dark:bg-[#05101E]"
            :show-close-button="false"
        >
            <DialogHeader>
                <DialogTitle
                    class="font-mono text-sm font-bold tracking-wider text-t-text"
                >
                    REQUEST MUTUAL AID
                </DialogTitle>
                <DialogDescription class="text-xs text-t-text-dim">
                    Incident {{ incident.incident_no }} --
                    {{ incident.incident_type?.name ?? 'Unclassified' }}
                </DialogDescription>
            </DialogHeader>

            <!-- Success state -->
            <div
                v-if="showSuccess"
                class="flex flex-col items-center gap-2 py-6"
            >
                <svg
                    class="size-10 text-t-online"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <span class="font-mono text-sm font-bold text-t-online">
                    Mutual aid request sent
                </span>
            </div>

            <!-- Agency cards -->
            <div v-else class="space-y-3">
                <div class="max-h-64 space-y-2 overflow-y-auto pr-1">
                    <button
                        v-for="agency in sortedAgencies"
                        :key="agency.id"
                        class="w-full rounded-lg border p-3 text-left transition-all"
                        :class="[
                            selectedAgencyId === agency.id
                                ? 'border-t-accent bg-t-surface shadow-sm'
                                : 'border-t-border bg-t-bg hover:border-t-accent/40 hover:bg-t-surface/60',
                        ]"
                        @click="selectedAgencyId = agency.id"
                    >
                        <div class="flex items-center gap-2">
                            <!-- Suggestion star -->
                            <svg
                                v-if="suggestedAgencyIds.includes(agency.id)"
                                class="size-3.5 shrink-0 text-t-accent"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                />
                            </svg>
                            <span
                                class="font-mono text-xs font-bold text-t-text"
                            >
                                {{ agency.name }}
                            </span>
                            <span
                                class="rounded px-1.5 py-[1px] font-mono text-[9px] font-bold"
                                :style="{
                                    backgroundColor:
                                        'color-mix(in srgb, var(--t-accent) 10%, transparent)',
                                    color: 'var(--t-accent)',
                                }"
                            >
                                {{ agency.code }}
                            </span>
                        </div>
                        <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1">
                            <a
                                v-if="agency.contact_phone"
                                :href="'tel:' + agency.contact_phone"
                                class="flex items-center gap-1 text-[10px] text-t-text-dim hover:text-t-accent"
                                @click.stop
                            >
                                <svg
                                    class="size-2.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"
                                    />
                                </svg>
                                {{ agency.contact_phone }}
                            </a>
                            <span
                                v-if="agency.contact_email"
                                class="flex items-center gap-1 text-[10px] text-t-text-dim"
                            >
                                <svg
                                    class="size-2.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"
                                    />
                                </svg>
                                {{ agency.contact_email }}
                            </span>
                            <span
                                v-if="agency.radio_channel"
                                class="flex items-center gap-1 text-[10px] text-t-text-dim"
                            >
                                <svg
                                    class="size-2.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9.348 14.652a3.75 3.75 0 010-5.304m5.304 0a3.75 3.75 0 010 5.304m-7.425 2.121a6.75 6.75 0 010-9.546m9.546 0a6.75 6.75 0 010 9.546M5.106 18.894c-3.808-3.808-3.808-9.98 0-13.788m13.788 0c3.808 3.808 3.808 9.98 0 13.788M12 12h.008v.008H12V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"
                                    />
                                </svg>
                                {{ agency.radio_channel }}
                            </span>
                        </div>
                    </button>
                </div>

                <!-- Notes textarea -->
                <div>
                    <label
                        class="mb-1 block font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
                    >
                        NOTES
                    </label>
                    <textarea
                        v-model="notes"
                        rows="2"
                        class="w-full resize-none rounded border border-t-border bg-t-surface px-2.5 py-1.5 font-mono text-xs text-t-text placeholder:text-t-text-faint focus:border-t-accent focus:outline-none"
                        placeholder="Specific requests or details..."
                    />
                </div>
            </div>

            <DialogFooter v-if="!showSuccess" class="gap-2">
                <button
                    class="rounded border border-t-border px-4 py-1.5 font-mono text-[10px] font-bold tracking-wider text-t-text-dim transition-colors hover:bg-t-surface-alt"
                    @click="handleClose"
                >
                    CANCEL
                </button>
                <button
                    class="rounded border border-t-accent/40 px-4 py-1.5 font-mono text-[10px] font-bold tracking-wider transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                    :class="
                        selectedAgencyId
                            ? 'bg-t-accent/10 text-t-accent hover:bg-t-accent/20'
                            : 'text-t-text-faint'
                    "
                    :disabled="!selectedAgencyId || isSubmitting"
                    @click="handleSubmit"
                >
                    {{ isSubmitting ? 'REQUESTING...' : 'REQUEST AID' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
