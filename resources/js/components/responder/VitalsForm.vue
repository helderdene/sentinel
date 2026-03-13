<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { updateVitals } from '@/actions/App/Http/Controllers/ResponderController';
import type { ResponderIncident, VitalsData } from '@/types/responder';

const props = defineProps<{
    incident: ResponderIncident;
}>();

interface VitalsField {
    key: keyof VitalsData;
    label: string;
    unit: string;
    placeholder: string;
    min: number;
    max: number;
    helperText: string;
}

const VITALS_FIELDS: VitalsField[] = [
    {
        key: 'systolic_bp',
        label: 'Systolic BP',
        unit: 'mmHg',
        placeholder: '120',
        min: 50,
        max: 300,
        helperText: 'Normal: 90-120 mmHg',
    },
    {
        key: 'diastolic_bp',
        label: 'Diastolic BP',
        unit: 'mmHg',
        placeholder: '80',
        min: 20,
        max: 200,
        helperText: 'Normal: 60-80 mmHg',
    },
    {
        key: 'heart_rate',
        label: 'Heart Rate',
        unit: 'bpm',
        placeholder: '72',
        min: 20,
        max: 300,
        helperText: 'Normal: 60-100 bpm',
    },
    {
        key: 'spo2',
        label: 'SpO2',
        unit: '%',
        placeholder: '98',
        min: 0,
        max: 100,
        helperText: 'Normal: 95-100%',
    },
    {
        key: 'gcs',
        label: 'GCS',
        unit: '3-15',
        placeholder: '15',
        min: 3,
        max: 15,
        helperText: 'Glasgow Coma Scale (3=deep coma, 15=fully alert)',
    },
];

const form = reactive<Record<string, string>>({
    systolic_bp: props.incident.vitals?.systolic_bp?.toString() ?? '',
    diastolic_bp: props.incident.vitals?.diastolic_bp?.toString() ?? '',
    heart_rate: props.incident.vitals?.heart_rate?.toString() ?? '',
    spo2: props.incident.vitals?.spo2?.toString() ?? '',
    gcs: props.incident.vitals?.gcs?.toString() ?? '',
});

const errors = reactive<Record<string, string>>({});
const isSaving = ref(false);
const saveSuccess = ref(false);

function validate(): boolean {
    let valid = true;

    for (const field of VITALS_FIELDS) {
        errors[field.key] = '';
        const value = form[field.key];

        if (value === '' || value === undefined) {
            continue;
        }

        const num = Number(value);

        if (isNaN(num)) {
            errors[field.key] = 'Must be a number';
            valid = false;
        } else if (num < field.min || num > field.max) {
            errors[field.key] = `Must be between ${field.min} and ${field.max}`;
            valid = false;
        }
    }

    return valid;
}

const filledCount = computed(() => {
    let count = 0;
    const sys = form.systolic_bp;
    const dia = form.diastolic_bp;

    if ((sys && sys !== '') || (dia && dia !== '')) {
        count++;
    }

    if (form.heart_rate && form.heart_rate !== '') {
        count++;
    }

    if (form.spo2 && form.spo2 !== '') {
        count++;
    }

    if (form.gcs && form.gcs !== '') {
        count++;
    }

    return count;
});

defineExpose({ filledCount });

async function saveVitals(): Promise<void> {
    if (!validate()) {
        return;
    }

    isSaving.value = true;
    saveSuccess.value = false;

    const payload: Record<string, number | null> = {};

    for (const field of VITALS_FIELDS) {
        const value = form[field.key];
        payload[field.key] = value && value !== '' ? Number(value) : null;
    }

    const xsrfToken = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

    try {
        const response = await fetch(
            updateVitals.url({ incident: String(props.incident.id) }),
            {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken,
                },
                body: JSON.stringify(payload),
            },
        );

        if (response.ok) {
            saveSuccess.value = true;
            setTimeout(() => {
                saveSuccess.value = false;
            }, 2000);
        }
    } catch {
        // Network error -- silent fail
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Vitals fields -->
        <div
            v-for="field in VITALS_FIELDS"
            :key="field.key"
            class="flex flex-col gap-1"
        >
            <label
                :for="`vitals-${field.key}`"
                class="text-[12px] font-semibold text-t-text-dim"
            >
                {{ field.label }}
                <span class="text-[11px] text-t-text-faint"
                    >({{ field.unit }})</span
                >
            </label>

            <input
                :id="`vitals-${field.key}`"
                v-model="form[field.key]"
                type="number"
                inputmode="numeric"
                :placeholder="field.placeholder"
                :min="field.min"
                :max="field.max"
                class="min-h-[44px] rounded-[10px] border-[1.5px] bg-t-surface px-3.5 py-[11px] font-mono text-[14px] text-t-text transition-colors outline-none placeholder:text-t-text-dim/50"
                :class="
                    errors[field.key]
                        ? 'border-t-p1'
                        : 'border-t-border focus:border-t-accent'
                "
            />

            <p
                v-if="errors[field.key]"
                class="text-[11px] font-medium text-t-p1"
            >
                {{ errors[field.key] }}
            </p>

            <p v-else class="text-[11px] text-t-text-dim">
                {{ field.helperText }}
            </p>
        </div>

        <!-- Save button -->
        <button
            type="button"
            class="flex min-h-[44px] items-center justify-center rounded-[10px] bg-t-accent px-4 py-2.5 text-[14px] font-bold text-white transition-colors active:opacity-90 disabled:opacity-50"
            style="box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25)"
            :disabled="isSaving"
            @click="saveVitals"
        >
            <template v-if="isSaving">Saving...</template>
            <template v-else-if="saveSuccess">Saved</template>
            <template v-else>Save Vitals</template>
        </button>
    </div>
</template>
