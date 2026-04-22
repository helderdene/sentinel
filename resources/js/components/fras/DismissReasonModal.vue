<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    open: boolean;
    eventId: string | null;
}>();

const emit = defineEmits<{
    close: [];
    'update:open': [open: boolean];
}>();

type DismissReason = 'false_match' | 'test_event' | 'duplicate' | 'other';

const REASONS: Array<{ value: DismissReason; label: string }> = [
    { value: 'false_match', label: 'False match' },
    { value: 'test_event', label: 'Test event' },
    { value: 'duplicate', label: 'Duplicate alert' },
    { value: 'other', label: 'Other' },
];

const form = useForm<{
    reason: DismissReason | '';
    reason_note: string;
}>({
    reason: '',
    reason_note: '',
});

const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => {
        emit('update:open', value);

        if (!value) {
            emit('close');
        }
    },
});

/**
 * Reset the form whenever the modal transitions from closed to open, or
 * when the target event changes mid-session.
 */
watch(
    () => [props.open, props.eventId] as const,
    ([open]) => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
    },
);

const noteLength = computed(() => form.reason_note.length);

const canSubmit = computed(() => {
    if (form.processing) {
        return false;
    }

    if (!form.reason) {
        return false;
    }

    if (form.reason === 'other') {
        return noteLength.value > 0 && noteLength.value <= 500;
    }

    return true;
});

function submit(): void {
    if (!props.eventId || !canSubmit.value) {
        return;
    }

    form.post(`/fras/alerts/${props.eventId}/dismiss`, {
        preserveScroll: true,
        onSuccess: () => {
            isOpen.value = false;
        },
    });
}

function close(): void {
    isOpen.value = false;
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-w-md space-y-6 p-6">
            <DialogHeader>
                <DialogTitle>Dismiss Alert</DialogTitle>
                <DialogDescription>
                    Dismissed alerts are removed from the live feed for all
                    operators. Choose a reason for audit.
                </DialogDescription>
            </DialogHeader>

            <fieldset
                role="radiogroup"
                aria-labelledby="dismiss-reason-label"
                aria-required="true"
                class="space-y-2"
            >
                <legend
                    id="dismiss-reason-label"
                    class="font-mono text-[9px] uppercase tracking-[2px] text-t-text-faint"
                >
                    REASON (required)
                </legend>
                <label
                    v-for="option in REASONS"
                    :key="option.value"
                    class="flex cursor-pointer items-center gap-2 rounded-[var(--radius)] border border-t-border px-3 py-2 hover:bg-t-bg/40"
                    :class="{
                        'border-t-accent bg-t-bg/30': form.reason === option.value,
                    }"
                >
                    <input
                        v-model="form.reason"
                        type="radio"
                        name="dismiss-reason"
                        :value="option.value"
                        class="size-4 accent-t-accent"
                    />
                    <span class="text-sm">{{ option.label }}</span>
                </label>
            </fieldset>

            <div v-if="form.reason === 'other'" class="space-y-1">
                <label
                    for="dismiss-note"
                    class="font-mono text-[9px] uppercase tracking-[2px] text-t-text-faint"
                >
                    DETAIL (required when "Other" is selected)
                </label>
                <textarea
                    id="dismiss-note"
                    v-model="form.reason_note"
                    aria-label="Dismissal detail"
                    aria-required="true"
                    aria-describedby="dismiss-note-counter"
                    rows="3"
                    maxlength="500"
                    placeholder="Describe why this alert should be dismissed."
                    class="w-full rounded-[var(--radius)] border border-t-border bg-background p-2 text-sm focus:border-t-accent focus:outline-none"
                ></textarea>
                <div
                    id="dismiss-note-counter"
                    class="text-right font-mono text-[10px] text-t-text-faint"
                >
                    {{ noteLength }}/500
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="close">Cancel</Button>
                <Button
                    variant="destructive"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    {{ form.processing ? 'Dismissing…' : 'Dismiss Alert' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
