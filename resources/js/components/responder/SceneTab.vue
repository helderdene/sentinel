<script setup lang="ts">
import { computed, ref } from 'vue';
import AssessmentTags from '@/components/responder/AssessmentTags.vue';
import ChecklistSection from '@/components/responder/ChecklistSection.vue';
import VitalsForm from '@/components/responder/VitalsForm.vue';
import type { ResponderIncident } from '@/types/responder';

const props = defineProps<{
    incident: ResponderIncident;
}>();

const openSection = ref<'checklist' | 'vitals' | 'assessment' | null>(
    'checklist',
);

const vitalsRef = ref<InstanceType<typeof VitalsForm> | null>(null);
const assessmentRef = ref<InstanceType<typeof AssessmentTags> | null>(null);

function toggleSection(section: 'checklist' | 'vitals' | 'assessment'): void {
    openSection.value = openSection.value === section ? null : section;
}

const checklistProgress = computed(() => {
    const data = props.incident.checklist_data;

    if (!data) {
        return '0';
    }

    return String(Object.values(data).filter(Boolean).length);
});

const checklistTotal = computed(() => {
    const data = props.incident.checklist_data;

    if (!data) {
        return '7';
    }

    return String(Object.keys(data).length || '7');
});

const vitalsCount = computed(() => {
    return String(vitalsRef.value?.filledCount ?? 0);
});

const assessmentCount = computed(() => {
    return String(assessmentRef.value?.activeCount ?? 0);
});

interface AccordionSection {
    id: 'checklist' | 'vitals' | 'assessment';
    label: string;
    icon: string;
    progress: string;
    total: string;
}

const sections = computed<AccordionSection[]>(() => [
    {
        id: 'checklist',
        label: 'Checklist',
        icon: 'clipboard',
        progress: checklistProgress.value,
        total: checklistTotal.value,
    },
    {
        id: 'vitals',
        label: 'Vitals',
        icon: 'heart',
        progress: vitalsCount.value,
        total: '4',
    },
    {
        id: 'assessment',
        label: 'Assessment',
        icon: 'tag',
        progress: assessmentCount.value,
        total: '11',
    },
]);
</script>

<template>
    <div class="flex flex-1 flex-col overflow-y-auto">
        <div
            v-for="section in sections"
            :key="section.id"
            class="border-b border-t-border last:border-b-0"
        >
            <!-- Section header -->
            <button
                type="button"
                class="active:bg-t-bg-dim/30 flex min-h-[44px] w-full items-center gap-3 px-4 py-3 text-left transition-colors"
                :aria-expanded="openSection === section.id"
                @click="toggleSection(section.id)"
            >
                <!-- Icon -->
                <div class="flex h-6 w-6 shrink-0 items-center justify-center">
                    <!-- Clipboard icon -->
                    <svg
                        v-if="section.icon === 'clipboard'"
                        class="h-5 w-5 text-t-text-dim"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            d="M5.5 3A2.5 2.5 0 003 5.5v9A2.5 2.5 0 005.5 17h9a2.5 2.5 0 002.5-2.5v-9A2.5 2.5 0 0014.5 3h-9zM7 7a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm1 3a1 1 0 100 2h4a1 1 0 100-2H8zm-1 5a1 1 0 011-1h2a1 1 0 110 2H8a1 1 0 01-1-1z"
                        />
                    </svg>

                    <!-- Heart icon -->
                    <svg
                        v-else-if="section.icon === 'heart'"
                        class="h-5 w-5 text-t-text-dim"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            d="M9.653 16.915l-.005-.003-.019-.01a20.759 20.759 0 01-1.162-.682 22.045 22.045 0 01-2.582-1.9C4.045 12.733 2 10.352 2 7.5a4.5 4.5 0 018-2.828A4.5 4.5 0 0118 7.5c0 2.852-2.044 5.233-3.885 6.82a22.049 22.049 0 01-3.744 2.582l-.019.01-.005.003h-.002a.723.723 0 01-.692 0h-.002z"
                        />
                    </svg>

                    <!-- Tag icon -->
                    <svg
                        v-else
                        class="h-5 w-5 text-t-text-dim"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M5.5 3A2.5 2.5 0 003 5.5v2.879a2.5 2.5 0 00.732 1.767l6.5 6.5a2.5 2.5 0 003.536 0l2.878-2.878a2.5 2.5 0 000-3.536l-6.5-6.5A2.5 2.5 0 008.38 3H5.5zM6 7a1 1 0 100-2 1 1 0 000 2z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>

                <!-- Label and progress -->
                <span class="flex-1 text-sm font-semibold text-t-text">
                    {{ section.label }}
                </span>

                <span class="font-mono text-xs text-t-text-dim">
                    {{ section.progress }}/{{ section.total }}
                </span>

                <!-- Chevron -->
                <svg
                    class="h-4 w-4 shrink-0 text-t-text-dim transition-transform duration-200"
                    :class="
                        openSection === section.id ? 'rotate-180' : 'rotate-0'
                    "
                    viewBox="0 0 20 20"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>

            <!-- Accordion content with CSS grid animation -->
            <div
                class="grid transition-[grid-template-rows] duration-200 ease-out"
                :class="
                    openSection === section.id
                        ? 'grid-rows-[1fr]'
                        : 'grid-rows-[0fr]'
                "
            >
                <div class="overflow-hidden">
                    <div class="px-4 pt-1 pb-4">
                        <ChecklistSection
                            v-if="section.id === 'checklist'"
                            :incident="props.incident"
                        />

                        <VitalsForm
                            v-else-if="section.id === 'vitals'"
                            ref="vitalsRef"
                            :incident="props.incident"
                        />

                        <AssessmentTags
                            v-else-if="section.id === 'assessment'"
                            ref="assessmentRef"
                            :incident="props.incident"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
