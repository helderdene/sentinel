<script setup lang="ts">
import { ChevronRight, UserRound } from 'lucide-vue-next';
import { ref } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

export interface PersonOfInterestData {
    face_image_url: string | null;
    personnel_name: string | null;
    personnel_category: string | null;
    camera_label: string | null;
    camera_name: string | null;
    captured_at: string | null;
}

const props = defineProps<{
    data: PersonOfInterestData;
}>();

const isOpen = ref(false);
const imgFailed = ref(false);
</script>

<template>
    <Collapsible v-model:open="isOpen">
        <CollapsibleTrigger
            class="flex min-h-[44px] w-full items-center border-b border-t-border px-4 py-2 text-left"
            :aria-expanded="isOpen"
            aria-controls="poi-accordion-body"
            aria-label="Person of Interest — face recognition match details"
        >
            <span class="text-[13px] font-semibold text-t-text">
                Person of Interest
            </span>
            <span
                class="ml-auto inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[10px] uppercase tracking-[1.5px]"
                :class="{
                    'bg-[color-mix(in_srgb,var(--t-p1)_15%,transparent)] text-t-p1':
                        props.data.personnel_category === 'block' ||
                        props.data.personnel_category === 'lost_child',
                    'bg-[color-mix(in_srgb,var(--t-unit-onscene)_15%,transparent)] text-t-unit-onscene':
                        props.data.personnel_category === 'missing',
                }"
            >
                {{ props.data.personnel_category }}
            </span>
            <ChevronRight
                class="ml-2 size-4 shrink-0 text-t-text-dim transition-transform duration-200"
                :class="{ 'rotate-90': isOpen }"
            />
        </CollapsibleTrigger>
        <CollapsibleContent id="poi-accordion-body" class="space-y-3 p-4">
            <div class="flex items-start gap-4">
                <!-- 80x80 face thumbnail slot. Responders are denied by Phase 21
                     FrasEventFaceController (D-27 gate); on 403 the @error handler
                     trips imgFailed and the UserRound fallback renders (UI-SPEC
                     lines 520/526). Scene imagery is NEVER rendered here. -->
                <div
                    class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius)] bg-t-surface-alt"
                >
                    <img
                        v-if="props.data.face_image_url && !imgFailed"
                        :src="props.data.face_image_url"
                        alt=""
                        class="size-full object-cover"
                        @error="imgFailed = true"
                    />
                    <UserRound v-else class="size-10 text-t-text-faint" />
                </div>
                <div class="flex-1 space-y-1">
                    <p class="text-sm font-semibold text-t-text">
                        {{ props.data.personnel_name }}
                    </p>
                    <p class="font-mono text-xs text-t-text-faint">
                        {{ props.data.camera_label }} ·
                        {{ props.data.camera_name }}
                    </p>
                    <p class="font-mono text-[10px] text-t-text-faint">
                        {{ props.data.captured_at }}
                    </p>
                </div>
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
