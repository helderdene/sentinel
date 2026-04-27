<script setup lang="ts">
import { ChevronRight, UserRound, X } from 'lucide-vue-next';
import { ref } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

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
const showImageModal = ref(false);
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
                <!-- 80x80 face thumbnail. Responders ARE allowed to fetch the
                     face crop (post-Phase-22 CDRRMO override); the @error handler
                     still trips imgFailed → UserRound fallback if the image is
                     missing on disk or the signed URL has expired. Scene imagery
                     is NEVER rendered here (D-26 still applies to scenes).
                     Click opens a larger preview in a dialog. -->
                <button
                    v-if="props.data.face_image_url && !imgFailed"
                    type="button"
                    class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius)] bg-t-surface-alt transition-opacity hover:opacity-80 focus-visible:ring-2 focus-visible:ring-t-accent focus-visible:outline-none"
                    aria-label="View larger face image"
                    @click="showImageModal = true"
                >
                    <img
                        :src="props.data.face_image_url"
                        alt=""
                        class="size-full object-cover"
                        @error="imgFailed = true"
                    />
                </button>
                <div
                    v-else
                    class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius)] bg-t-surface-alt"
                >
                    <UserRound class="size-10 text-t-text-faint" />
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

    <Dialog v-model:open="showImageModal">
        <DialogContent
            class="max-w-md gap-3 border-t-border bg-t-surface p-4 sm:max-w-lg"
        >
            <DialogTitle class="text-sm font-semibold text-t-text">
                {{ props.data.personnel_name ?? 'Person of Interest' }}
            </DialogTitle>
            <DialogDescription
                class="font-mono text-xs text-t-text-faint"
            >
                {{ props.data.camera_label }} ·
                {{ props.data.camera_name }} ·
                {{ props.data.captured_at }}
            </DialogDescription>
            <div
                class="flex aspect-square w-full items-center justify-center overflow-hidden rounded-[var(--radius)] bg-t-surface-alt"
            >
                <img
                    v-if="props.data.face_image_url && !imgFailed"
                    :src="props.data.face_image_url"
                    alt=""
                    class="size-full object-contain"
                    @error="imgFailed = true"
                />
                <UserRound v-else class="size-24 text-t-text-faint" />
            </div>
            <DialogClose
                class="absolute top-3 right-3 inline-flex size-7 items-center justify-center rounded-full text-t-text-dim transition-colors hover:bg-t-surface-alt hover:text-t-text focus-visible:ring-2 focus-visible:ring-t-accent focus-visible:outline-none"
                aria-label="Close"
            >
                <X class="size-4" />
            </DialogClose>
        </DialogContent>
    </Dialog>
</template>
