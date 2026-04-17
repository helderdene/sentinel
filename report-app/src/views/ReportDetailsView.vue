<script setup lang="ts">
import type { Barangay, CitizenReport } from '@/types';
import { PRIORITY_BG, PRIORITY_COLORS } from '@/types';
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import PriorityBadge from '@/components/PriorityBadge.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import StepIndicator from '@/components/StepIndicator.vue';
import { useApi } from '@/composables/useApi';
import type { ApiValidationErrors } from '@/composables/useApi';
import { useGeolocation } from '@/composables/useGeolocation';
import { useReportDraft } from '@/composables/useReportDraft';
import { useReportStorage } from '@/composables/useReportStorage';

const router = useRouter();
const geo = useGeolocation();
const draft = useReportDraft();
const { addReport } = useReportStorage();
const api = useApi();

const barangays = ref<Barangay[]>([]);
const submitting = ref(false);
const serverErrors = ref<Record<string, string[]>>({});
const rateLimited = ref(false);
const submitted = ref(false);

// Client-side validation
const clientErrors = ref<Record<string, string>>({});

// Redirect if no type selected
onMounted(async () => {
    if (!draft.selectedType.value) {
        router.replace('/report/type');

        return;
    }

    // Request GPS
    const granted = await geo.requestLocation();

    if (granted) {
        draft.latitude.value = geo.latitude.value;
        draft.longitude.value = geo.longitude.value;
    }

    // Fetch barangays
    try {
        const res = await api.get<{ data: Barangay[] }>(
            '/api/v1/citizen/barangays'
        );
        barangays.value = res.data;
    } catch {
        // Non-critical
    }
});

// Sync geo coords to draft
watch(
    [geo.latitude, geo.longitude],
    ([lat, lng]) => {
        if (lat !== null && lng !== null) {
            draft.latitude.value = lat;
            draft.longitude.value = lng;
        }
    }
);

const typeColor = computed(
    () =>
        PRIORITY_COLORS[draft.selectedType.value?.default_priority ?? 4] ??
        '#64748b'
);

const typeBg = computed(
    () =>
        PRIORITY_BG[draft.selectedType.value?.default_priority ?? 4] ??
        'rgba(100,116,139,.08)'
);

const barangayOptions = computed(() =>
    barangays.value.map((b) => ({ value: b.id, label: b.name }))
);

const descCharCount = computed(() => draft.description.value.length);

const isValid = computed(() => {
    const hasLocation =
        geo.status.value === 'granted' ||
        draft.barangayId.value !== null;
    const hasDescription = draft.description.value.length >= 10;
    const hasContact = draft.callerContact.value.trim().length > 0;

    return hasLocation && hasDescription && hasContact;
});

function validate(): boolean {
    clientErrors.value = {};

    if (
        geo.status.value !== 'granted' &&
        !draft.barangayId.value
    ) {
        clientErrors.value.barangay =
            'Please select a barangay or enable GPS.';
    }

    if (draft.description.value.length < 10) {
        clientErrors.value.description =
            'Description must be at least 10 characters.';
    }

    if (!draft.callerContact.value.trim()) {
        clientErrors.value.caller_contact =
            'Contact number is required.';
    }

    return Object.keys(clientErrors.value).length === 0;
}

async function handleSubmit(): Promise<void> {
    if (!validate() || submitting.value || submitted.value) {
        return;
    }

    submitting.value = true;
    serverErrors.value = {};
    rateLimited.value = false;

    const payload: Record<string, unknown> = {
        incident_type_id: draft.selectedType.value!.id,
        description: draft.description.value,
        caller_contact: draft.callerContact.value,
    };

    if (draft.callerName.value.trim()) {
        payload.caller_name = draft.callerName.value;
    }

    if (draft.locationText.value.trim()) {
        payload.location_text = draft.locationText.value;
    }

    if (draft.latitude.value !== null) {
        payload.latitude = draft.latitude.value;
    }

    if (draft.longitude.value !== null) {
        payload.longitude = draft.longitude.value;
    }

    if (draft.barangayId.value !== null) {
        payload.barangay_id = draft.barangayId.value;
    }

    try {
        const res = await api.post<{ data: CitizenReport }>(
            '/api/v1/citizen/reports',
            payload
        );

        submitted.value = true;

        // Save to localStorage
        addReport({
            token: res.data.tracking_token,
            type: res.data.type,
            priority: res.data.priority,
            barangay: res.data.barangay ?? '',
            status: res.data.status,
            submittedAt: res.data.submitted_at,
            description: res.data.description,
        });

        // Navigate to confirmation
        router.push('/report/confirm');
    } catch (err) {
        const validationErr = err as ApiValidationErrors;

        if (validationErr?.errors) {
            serverErrors.value = validationErr.errors;
        } else if (
            err instanceof Error &&
            err.message.includes('429')
        ) {
            rateLimited.value = true;
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="flex h-full flex-col bg-t-bg">
        <!-- Header -->
        <div
            class="shrink-0 border-b border-t-border bg-t-surface px-4 py-3.5 shadow-[0_1px_4px_rgba(0,0,0,.04)]"
        >
            <div class="mb-3 flex items-center gap-2.5">
                <button
                    class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-[9px] border border-t-border bg-transparent"
                    @click="router.push('/report/type')"
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
                    <div
                        class="text-[16px] font-bold text-t-text"
                    >
                        Incident Details
                    </div>
                    <div class="text-[11px] text-t-text-dim">
                        Tell us what's happening
                    </div>
                </div>
            </div>
            <StepIndicator :current-step="2" />
        </div>

        <!-- Selected type chip -->
        <div
            v-if="draft.selectedType.value"
            class="shrink-0 px-4 pt-3.5"
        >
            <div
                class="flex items-center gap-2.5 rounded-[11px] border px-3.5 py-2.5"
                :style="{
                    backgroundColor: typeBg,
                    borderColor: `${typeColor}30`,
                }"
            >
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px]"
                    :style="{
                        backgroundColor: `${typeColor}18`,
                    }"
                >
                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 28 28"
                        fill="none"
                        :style="{ color: typeColor }"
                    >
                        <path
                            d="M14 3L26 25H2L14 3Z"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linejoin="round"
                        />
                        <line
                            x1="14"
                            y1="11"
                            x2="14"
                            y2="18"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />
                        <circle
                            cx="14"
                            cy="21.5"
                            r="1.3"
                            fill="currentColor"
                        />
                    </svg>
                </div>
                <div>
                    <div
                        class="text-[14px] font-bold text-t-text"
                    >
                        {{
                            draft.selectedType.value.name
                        }}
                    </div>
                    <PriorityBadge
                        :priority="
                            draft.selectedType.value
                                .default_priority
                        "
                        small
                    />
                </div>
            </div>
        </div>

        <!-- Form (scrollable) -->
        <div
            class="hide-scrollbar flex-1 overflow-y-auto px-4 pb-28 pt-3.5"
        >
            <!-- Location section -->
            <div class="mb-3.5">
                <label
                    class="mb-1.5 block text-[12px] font-semibold text-t-text-dim"
                >
                    Location
                </label>

                <!-- GPS status pill -->
                <div
                    v-if="geo.status.value === 'requesting'"
                    class="mb-2.5 flex items-center gap-2 rounded-lg px-3 py-2 text-[12px]"
                    style="
                        background: rgba(37, 99, 235, 0.08);
                        color: var(--t-accent);
                    "
                >
                    <div
                        class="h-3 w-3 animate-spin rounded-full border-2 border-current"
                        style="border-top-color: transparent"
                    />
                    Detecting location...
                </div>

                <div
                    v-if="geo.status.value === 'granted'"
                    class="mb-2.5 flex items-center gap-2 rounded-lg px-3 py-2 text-[12px]"
                    style="
                        background: rgba(22, 163, 74, 0.08);
                        color: var(--t-p4);
                    "
                >
                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 16 16"
                        fill="none"
                    >
                        <path
                            d="M8 1.5C5.51 1.5 3.5 3.51 3.5 6C3.5 9.5 8 14.5 8 14.5C8 14.5 12.5 9.5 12.5 6C12.5 3.51 10.49 1.5 8 1.5Z"
                            stroke="currentColor"
                            stroke-width="1.4"
                        />
                        <circle
                            cx="8"
                            cy="6"
                            r="1.8"
                            fill="currentColor"
                        />
                    </svg>
                    Location detected ({{
                        geo.latitude.value?.toFixed(4)
                    }},
                    {{ geo.longitude.value?.toFixed(4) }})
                </div>

                <div
                    v-if="geo.status.value === 'denied'"
                    class="mb-2.5 flex items-center gap-2 rounded-lg px-3 py-2 text-[12px]"
                    style="
                        background: rgba(234, 88, 12, 0.08);
                        color: var(--t-p2);
                    "
                >
                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 16 16"
                        fill="none"
                    >
                        <circle
                            cx="8"
                            cy="8"
                            r="6"
                            stroke="currentColor"
                            stroke-width="1.4"
                        />
                        <line
                            x1="5"
                            y1="5"
                            x2="11"
                            y2="11"
                            stroke="currentColor"
                            stroke-width="1.4"
                            stroke-linecap="round"
                        />
                    </svg>
                    Location unavailable
                </div>

                <!-- Barangay dropdown (always shown if denied, helpful even if granted) -->
                <div
                    v-if="geo.status.value !== 'requesting'"
                    class="mb-3"
                >
                    <label
                        class="mb-1.5 block text-[12px] font-semibold text-t-text-dim"
                    >
                        Barangay
                        <span
                            v-if="geo.status.value === 'denied'"
                            class="text-t-p1"
                            >*</span
                        >
                    </label>
                    <SearchableSelect
                        v-model="draft.barangayId.value"
                        :options="barangayOptions"
                        placeholder="Search barangay..."
                    />
                    <div
                        v-if="clientErrors.barangay"
                        class="mt-1 text-[11px] text-t-p1"
                    >
                        {{ clientErrors.barangay }}
                    </div>
                </div>

                <!-- Address / landmark -->
                <label
                    class="mb-1.5 block text-[12px] font-semibold text-t-text-dim"
                    >Landmark / Address</label
                >
                <input
                    v-model="draft.locationText.value"
                    type="text"
                    placeholder="e.g. Near the public market, beside school..."
                    class="w-full rounded-[10px] border-[1.5px] border-t-border bg-t-surface px-3.5 py-[11px] text-[14px] text-t-text outline-none transition-colors focus:border-t-accent"
                />
            </div>

            <!-- Description -->
            <div class="mb-3.5">
                <label
                    class="mb-1.5 block text-[12px] font-semibold text-t-text-dim"
                >
                    What happened?
                    <span class="text-t-p1">*</span>
                </label>
                <textarea
                    v-model="draft.description.value"
                    rows="4"
                    placeholder="Describe the situation clearly -- who, what, how many people are involved..."
                    class="w-full resize-none rounded-[10px] border-[1.5px] border-t-border bg-t-surface px-3.5 py-[11px] text-[14px] leading-relaxed text-t-text outline-none transition-colors focus:border-t-accent"
                />
                <div
                    class="mt-1 text-right text-[11px]"
                    :class="
                        descCharCount < 10
                            ? 'text-t-text-faint'
                            : 'text-t-p4'
                    "
                >
                    {{ descCharCount }} characters
                    {{
                        descCharCount < 10
                            ? `(need ${10 - descCharCount} more)`
                            : 'ok'
                    }}
                </div>
                <div
                    v-if="clientErrors.description"
                    class="mt-1 text-[11px] text-t-p1"
                >
                    {{ clientErrors.description }}
                </div>
                <div
                    v-if="serverErrors.description"
                    class="mt-1 text-[11px] text-t-p1"
                >
                    {{ serverErrors.description[0] }}
                </div>
            </div>

            <!-- Divider -->
            <div class="mb-4 mt-1 h-px bg-t-border" />
            <div class="mb-3 text-[11px] text-t-text-faint">
                Contact info helps us reach you for follow-up
            </div>

            <!-- Contact number -->
            <div class="mb-3.5">
                <label
                    class="mb-1.5 block text-[12px] font-semibold text-t-text-dim"
                >
                    Contact Number
                    <span class="text-t-p1">*</span>
                </label>
                <input
                    v-model="draft.callerContact.value"
                    type="tel"
                    placeholder="09XX-XXX-XXXX"
                    class="w-full rounded-[10px] border-[1.5px] border-t-border bg-t-surface px-3.5 py-[11px] text-[14px] text-t-text outline-none transition-colors focus:border-t-accent"
                />
                <div
                    v-if="clientErrors.caller_contact"
                    class="mt-1 text-[11px] text-t-p1"
                >
                    {{ clientErrors.caller_contact }}
                </div>
                <div
                    v-if="serverErrors.caller_contact"
                    class="mt-1 text-[11px] text-t-p1"
                >
                    {{ serverErrors.caller_contact[0] }}
                </div>
            </div>

            <!-- Name (optional) -->
            <div class="mb-3.5">
                <label
                    class="mb-1.5 block text-[12px] font-semibold text-t-text-dim"
                    >Your Name (optional)</label
                >
                <input
                    v-model="draft.callerName.value"
                    type="text"
                    placeholder="Full name (optional)"
                    class="w-full rounded-[10px] border-[1.5px] border-t-border bg-t-surface px-3.5 py-[11px] text-[14px] text-t-text outline-none transition-colors focus:border-t-accent"
                />
            </div>

            <!-- Rate limit error -->
            <div
                v-if="rateLimited"
                class="mb-3 rounded-lg border border-t-p1 bg-t-p1/5 px-4 py-3 text-[13px] text-t-p1"
            >
                Too many reports submitted. Please wait a minute
                before trying again.
            </div>

            <!-- General server error -->
            <div
                v-if="
                    serverErrors.incident_type_id ||
                    serverErrors.latitude ||
                    serverErrors.longitude
                "
                class="mb-3 rounded-lg border border-t-p2 bg-t-p2/5 px-4 py-3 text-[13px] text-t-p2"
            >
                {{
                    serverErrors.incident_type_id?.[0] ||
                    serverErrors.latitude?.[0] ||
                    serverErrors.longitude?.[0]
                }}
            </div>
        </div>

        <!-- Fixed submit button -->
        <div
            class="fixed inset-x-0 bottom-0 z-10 px-4 pb-6 pt-3"
            style="
                background: linear-gradient(
                    transparent,
                    var(--t-bg) 30%
                );
            "
        >
            <button
                :disabled="!isValid || submitting"
                class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-[13px] border-none py-[15px] text-[16px] font-bold tracking-wide transition-all duration-200"
                :style="{
                    backgroundColor:
                        isValid && !submitting
                            ? typeColor
                            : 'var(--t-border)',
                    color:
                        isValid && !submitting
                            ? '#fff'
                            : 'var(--t-text-faint)',
                    boxShadow:
                        isValid && !submitting
                            ? `0 6px 20px ${typeColor}45`
                            : 'none',
                }"
                @click="handleSubmit"
            >
                <template v-if="submitting">
                    <div
                        class="h-[18px] w-[18px] animate-spin rounded-full border-2"
                        style="
                            border-color: rgba(
                                255,
                                255,
                                255,
                                0.3
                            );
                            border-top-color: #fff;
                        "
                    />
                    Submitting...
                </template>
                <template v-else> Submit Report </template>
            </button>
        </div>
    </div>
</template>
