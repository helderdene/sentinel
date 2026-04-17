<script setup lang="ts">
import { ref } from 'vue';
import { requestResource } from '@/actions/App/Http/Controllers/ResponderController';
import type { ResourceType } from '@/types/responder';

const props = defineProps<{
    incidentId: string | number;
    isOpen: boolean;
}>();

const emit = defineEmits<{
    close: [];
    requested: [];
}>();

const notes = ref('');
const isSubmitting = ref(false);
const successMessage = ref('');

interface ResourceOption {
    type: ResourceType;
    label: string;
}

const RESOURCE_OPTIONS: ResourceOption[] = [
    { type: 'ADDITIONAL_AMBULANCE', label: 'Additional Ambulance' },
    { type: 'FIRE_UNIT', label: 'Fire Unit' },
    { type: 'POLICE_BACKUP', label: 'Police Backup' },
    { type: 'RESCUE_BOAT', label: 'Rescue Boat' },
    { type: 'MEDICAL_OFFICER', label: 'Medical Officer' },
    { type: 'MEDEVAC', label: 'Medevac' },
];

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

async function handleRequest(type: ResourceType): Promise<void> {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;

    try {
        const route = requestResource({
            incident: String(props.incidentId),
        });

        await fetch(route.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({
                type,
                notes: notes.value.trim() || null,
            }),
        });

        successMessage.value = 'Resource requested';
        notes.value = '';

        setTimeout(() => {
            successMessage.value = '';
            emit('requested');
            emit('close');
        }, 1200);
    } catch {
        // Silent fail
    } finally {
        isSubmitting.value = false;
    }
}

function handleClose(): void {
    notes.value = '';
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
            >
                <div
                    class="absolute inset-0 bg-black/50"
                    @click="handleClose"
                />

                <div
                    class="relative z-10 w-full max-w-md rounded-t-[14px] bg-t-surface p-5 shadow-xl sm:rounded-[14px]"
                >
                    <div
                        v-if="successMessage"
                        class="flex flex-col items-center py-8"
                    >
                        <svg
                            width="48"
                            height="48"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="text-t-p4"
                        >
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                        <p class="mt-3 text-[14px] font-semibold text-t-text">
                            {{ successMessage }}
                        </p>
                    </div>

                    <template v-else>
                        <h3
                            class="mb-4 text-center text-[16px] font-bold text-t-text"
                        >
                            Request Additional Resource
                        </h3>

                        <div class="grid grid-cols-2 gap-2.5">
                            <button
                                v-for="option in RESOURCE_OPTIONS"
                                :key="option.type"
                                type="button"
                                class="flex min-h-[56px] items-center justify-center rounded-[10px] border border-t-border bg-t-bg px-3 text-center text-[13px] font-semibold text-t-text shadow-[0_1px_3px_rgba(0,0,0,.04)] transition-colors active:bg-t-accent/10"
                                :disabled="isSubmitting"
                                @click="handleRequest(option.type)"
                            >
                                {{ option.label }}
                            </button>
                        </div>

                        <textarea
                            v-model="notes"
                            placeholder="Optional notes..."
                            rows="2"
                            class="mt-3 w-full resize-none rounded-[10px] border-[1.5px] border-t-border bg-t-surface px-3.5 py-[11px] text-[14px] text-t-text placeholder-t-text-faint transition-colors outline-none focus:border-t-accent"
                        />

                        <button
                            type="button"
                            class="mt-3 flex min-h-[44px] w-full items-center justify-center rounded-[10px] border border-t-border text-[13px] font-medium text-t-text-dim transition-colors active:bg-t-border/30"
                            @click="handleClose"
                        >
                            Cancel
                        </button>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}
</style>
