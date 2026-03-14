<script setup lang="ts">
import { computed, ref } from 'vue';
import { resolve } from '@/actions/App/Http/Controllers/ResponderController';
import HospitalSelect from '@/components/responder/HospitalSelect.vue';
import type { Hospital, IncidentOutcome } from '@/types/responder';

const props = defineProps<{
    incidentId: string | number;
    isOpen: boolean;
    hospitals: Hospital[];
}>();

const emit = defineEmits<{
    close: [];
    resolved: [];
}>();

interface OutcomeOption {
    type: IncidentOutcome;
    label: string;
    color: string;
}

const OUTCOME_OPTIONS: OutcomeOption[] = [
    {
        type: 'TREATED_ON_SCENE',
        label: 'Treated On Scene',
        color: '#1D9E75',
    },
    {
        type: 'TRANSPORTED_TO_HOSPITAL',
        label: 'Transported to Hospital',
        color: '#378ADD',
    },
    {
        type: 'REFUSED_TREATMENT',
        label: 'Patient Refused Treatment',
        color: '#EF9F27',
    },
    {
        type: 'DECLARED_DOA',
        label: 'Declared DOA',
        color: '#64748b',
    },
    {
        type: 'FALSE_ALARM',
        label: 'False Alarm / Stand Down',
        color: '#E24B4A',
    },
];

const selectedOutcome = ref<IncidentOutcome | null>(null);
const selectedHospital = ref<string | null>(null);
const closureNotes = ref('');
const isSubmitting = ref(false);
const errorMessage = ref('');

const showHospitalPicker = computed(
    () => selectedOutcome.value === 'TRANSPORTED_TO_HOSPITAL',
);

const canConfirm = computed(() => {
    if (!selectedOutcome.value) {
        return false;
    }

    if (
        selectedOutcome.value === 'TRANSPORTED_TO_HOSPITAL' &&
        !selectedHospital.value
    ) {
        return false;
    }

    return true;
});

function selectOutcome(type: IncidentOutcome): void {
    selectedOutcome.value = type;

    if (type !== 'TRANSPORTED_TO_HOSPITAL') {
        selectedHospital.value = null;
    }
}

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

async function handleConfirm(): Promise<void> {
    if (!canConfirm.value || isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        const route = resolve({
            incident: String(props.incidentId),
        });

        const response = await fetch(route.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({
                outcome: selectedOutcome.value,
                hospital: selectedHospital.value,
                closure_notes: closureNotes.value.trim() || null,
            }),
        });

        if (!response.ok) {
            if (response.status === 422) {
                const data = await response.json();

                errorMessage.value =
                    data.message ??
                    'Validation failed. Vitals may be required for this outcome.';

                return;
            }

            throw new Error('Request failed');
        }

        resetForm();
        emit('resolved');
    } catch {
        errorMessage.value = 'Failed to resolve incident. Please try again.';
    } finally {
        isSubmitting.value = false;
    }
}

function resetForm(): void {
    selectedOutcome.value = null;
    selectedHospital.value = null;
    closureNotes.value = '';
    errorMessage.value = '';
}

function handleClose(): void {
    resetForm();
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <Transition name="sheet-fade">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 flex flex-col justify-end"
            >
                <div
                    class="absolute inset-0 bg-black/50"
                    @click="handleClose"
                />

                <Transition name="sheet-slide">
                    <div
                        v-if="isOpen"
                        class="relative z-10 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-t-surface shadow-xl"
                    >
                        <div class="flex justify-center pt-3 pb-1">
                            <div
                                class="h-1 w-10 rounded-full bg-t-text-faint/40"
                            />
                        </div>

                        <div class="px-5 pb-6">
                            <h3
                                class="mb-4 text-center text-[16px] font-bold text-t-text"
                            >
                                Select Outcome
                            </h3>

                            <div
                                v-if="errorMessage"
                                class="mb-3 rounded-[10px] border border-t-p1/30 bg-t-p1/5 px-3 py-2 text-[13px] text-t-p1"
                            >
                                {{ errorMessage }}
                            </div>

                            <div class="space-y-2">
                                <button
                                    v-for="option in OUTCOME_OPTIONS"
                                    :key="option.type"
                                    type="button"
                                    class="flex min-h-[56px] w-full items-center gap-3 rounded-[10px] border px-4 text-left text-[13px] font-semibold transition-colors active:scale-[0.99]"
                                    :class="
                                        selectedOutcome === option.type
                                            ? 'border-2'
                                            : 'border-t-border'
                                    "
                                    :style="
                                        selectedOutcome === option.type
                                            ? {
                                                  borderColor: option.color,
                                                  backgroundColor: `color-mix(in srgb, ${option.color} 8%, transparent)`,
                                                  color: option.color,
                                              }
                                            : { color: 'var(--t-text)' }
                                    "
                                    @click="selectOutcome(option.type)"
                                >
                                    <span
                                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2"
                                        :style="{
                                            borderColor:
                                                selectedOutcome === option.type
                                                    ? option.color
                                                    : 'var(--t-border)',
                                        }"
                                    >
                                        <span
                                            v-if="
                                                selectedOutcome === option.type
                                            "
                                            class="h-2.5 w-2.5 rounded-full"
                                            :style="{
                                                backgroundColor: option.color,
                                            }"
                                        />
                                    </span>
                                    {{ option.label }}
                                </button>
                            </div>

                            <Transition name="expand">
                                <div v-if="showHospitalPicker" class="mt-3">
                                    <label
                                        class="mb-1 block text-[11px] font-semibold text-t-text-dim"
                                    >
                                        Hospital
                                    </label>
                                    <HospitalSelect
                                        v-model="selectedHospital"
                                        :hospitals="hospitals"
                                    />
                                </div>
                            </Transition>

                            <div class="mt-3">
                                <label
                                    class="mb-1 block text-[11px] font-semibold text-t-text-dim"
                                >
                                    Closure Notes (optional)
                                </label>
                                <textarea
                                    v-model="closureNotes"
                                    placeholder="Additional notes..."
                                    rows="2"
                                    maxlength="2000"
                                    class="w-full resize-none rounded-[10px] border-[1.5px] border-t-border bg-t-surface px-3.5 py-[11px] text-[14px] text-t-text placeholder-t-text-faint transition-colors outline-none focus:border-t-accent"
                                />
                            </div>

                            <button
                                type="button"
                                class="mt-4 flex min-h-[52px] w-full items-center justify-center rounded-[13px] font-sans text-[16px] font-bold tracking-wide text-white shadow-lg transition-transform active:scale-[0.98] disabled:opacity-50"
                                :class="
                                    canConfirm
                                        ? 'bg-t-accent'
                                        : 'cursor-not-allowed bg-t-text-faint'
                                "
                                :disabled="!canConfirm || isSubmitting"
                                @click="handleConfirm"
                            >
                                {{
                                    isSubmitting
                                        ? 'Resolving...'
                                        : 'Confirm & Close Incident'
                                }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.sheet-fade-enter-active,
.sheet-fade-leave-active {
    transition: opacity 0.2s ease;
}

.sheet-fade-enter-from,
.sheet-fade-leave-to {
    opacity: 0;
}

.sheet-slide-enter-active {
    transition: transform 0.3s ease-out;
}

.sheet-slide-leave-active {
    transition: transform 0.2s ease-in;
}

.sheet-slide-enter-from {
    transform: translateY(100%);
}

.sheet-slide-leave-to {
    transform: translateY(100%);
}

.expand-enter-active,
.expand-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
    max-height: 0;
    opacity: 0;
}

.expand-enter-to,
.expand-leave-from {
    max-height: 200px;
    opacity: 1;
}
</style>
