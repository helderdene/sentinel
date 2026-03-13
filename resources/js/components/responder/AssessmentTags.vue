<script setup lang="ts">
import { computed, ref } from 'vue';
import { updateAssessmentTags } from '@/actions/App/Http/Controllers/ResponderController';
import type { ResponderIncident } from '@/types/responder';

const props = defineProps<{
    incident: ResponderIncident;
}>();

const ALL_TAGS = [
    'Conscious',
    'Breathing',
    'Bleeding',
    'Unresponsive',
    'Fracture',
    'Burns',
    'Shock',
    'Chest Pain',
    'Head Trauma',
    'Airway Compromised',
    'Anaphylaxis',
] as const;

const activeTags = ref<Set<string>>(
    new Set(props.incident.assessment_tags ?? []),
);

const activeCount = computed(() => activeTags.value.size);

defineExpose({ activeCount });

async function toggleTag(tag: string): Promise<void> {
    const newTags = new Set(activeTags.value);

    if (newTags.has(tag)) {
        newTags.delete(tag);
    } else {
        newTags.add(tag);
    }

    activeTags.value = newTags;

    const xsrfToken = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

    try {
        await fetch(
            updateAssessmentTags.url({
                incident: String(props.incident.id),
            }),
            {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken,
                },
                body: JSON.stringify({
                    assessment_tags: [...newTags],
                }),
            },
        );
    } catch {
        // Revert on failure
        if (newTags.has(tag)) {
            newTags.delete(tag);
        } else {
            newTags.add(tag);
        }

        activeTags.value = new Set(newTags);
    }
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <button
                v-for="tag in ALL_TAGS"
                :key="tag"
                type="button"
                class="flex min-h-[44px] items-center justify-center rounded-[10px] border px-3 py-2 text-[13px] font-medium transition-all duration-200 active:scale-95"
                :class="
                    activeTags.has(tag)
                        ? 'border-t-accent bg-t-accent/15 text-t-accent'
                        : 'border-t-border bg-t-surface text-t-text-dim hover:border-t-text-dim/40'
                "
                @click="toggleTag(tag)"
            >
                {{ tag }}
            </button>
        </div>
    </div>
</template>
