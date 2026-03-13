import type { IncidentType } from '@/types';
import { ref } from 'vue';

const selectedType = ref<IncidentType | null>(null);
const description = ref('');
const callerContact = ref('');
const callerName = ref('');
const locationText = ref('');
const barangayId = ref<number | null>(null);
const latitude = ref<number | null>(null);
const longitude = ref<number | null>(null);

export function useReportDraft() {
    function setType(type: IncidentType): void {
        selectedType.value = type;
    }

    function reset(): void {
        selectedType.value = null;
        description.value = '';
        callerContact.value = '';
        callerName.value = '';
        locationText.value = '';
        barangayId.value = null;
        latitude.value = null;
        longitude.value = null;
    }

    return {
        selectedType,
        description,
        callerContact,
        callerName,
        locationText,
        barangayId,
        latitude,
        longitude,
        setType,
        reset,
    };
}
