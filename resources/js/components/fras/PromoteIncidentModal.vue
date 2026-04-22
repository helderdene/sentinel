<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { RecognitionEventRow } from '@/components/fras/EventHistoryTable.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Plan 22-05 ships `@/actions/App/Http/Controllers/FrasEventHistoryController`
 * (promote action) in parallel. Until that wave merges, we compose the URL
 * manually — the controller route `/fras/events/{event}/promote` is a stable
 * contract defined in UI-SPEC §2 + the 22-07 plan threat model. When the
 * Wayfinder generator picks the controller up, swap `promoteUrl()` for:
 *
 *   import { promote } from '@/actions/App/Http/Controllers/FrasEventHistoryController';
 *   form.post(promote(props.event.id).url, …);
 *
 * @see .planning/phases/22-alert-feed-event-history-responder-context-dpa-compliance/22-07-PLAN.md
 */
function promoteUrl(eventId: string): string {
    return `/fras/events/${eventId}/promote`;
}

const props = defineProps<{
    event: RecognitionEventRow | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

type IncidentPriority = 'P1' | 'P2' | 'P3' | 'P4';

const PRIORITY_OPTIONS: Array<{
    value: IncidentPriority;
    label: string;
    tokenVar: string;
}> = [
    { value: 'P1', label: 'P1 — Critical', tokenVar: 'var(--t-p1)' },
    { value: 'P2', label: 'P2 — High', tokenVar: 'var(--t-p2)' },
    { value: 'P3', label: 'P3 — Medium', tokenVar: 'var(--t-p3)' },
    { value: 'P4', label: 'P4 — Low', tokenVar: 'var(--t-p4)' },
];

const form = useForm<{ priority: IncidentPriority; reason: string }>({
    priority: 'P2',
    reason: '',
});

watch(
    () => props.event,
    (next, prev) => {
        if (next !== null && next?.id !== prev?.id) {
            form.reset();
            form.clearErrors();
        }
    },
);

const canSubmit = computed(
    () =>
        form.reason.length >= 8 &&
        form.reason.length <= 500 &&
        (['P1', 'P2', 'P3', 'P4'] as IncidentPriority[]).includes(
            form.priority,
        ),
);

const counterClass = computed(() =>
    form.reason.length < 8 || form.reason.length > 500
        ? 'text-t-p1'
        : 'text-t-text-faint',
);

const openModel = computed({
    get: () => props.event !== null,
    set: (v: boolean) => {
        if (!v) {
            emit('close');
        }
    },
});

function submit(): void {
    if (!props.event || !canSubmit.value) {
        return;
    }

    form.post(promoteUrl(props.event.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Dialog v-model:open="openModel">
        <DialogContent class="max-w-lg space-y-6 p-6">
            <DialogHeader>
                <DialogTitle>Promote to Incident</DialogTitle>
                <DialogDescription>
                    Create an incident from this recognition event. You can't
                    undo this, but you can reassign the incident afterward.
                </DialogDescription>
            </DialogHeader>

            <template v-if="event">
                <!-- Priority picker -->
                <div class="space-y-2">
                    <label
                        class="font-mono text-[9px] tracking-[2px] text-t-text-faint uppercase"
                    >
                        Priority
                    </label>
                    <div
                        role="radiogroup"
                        aria-label="Incident priority"
                        class="grid grid-cols-2 gap-2 sm:grid-cols-4"
                    >
                        <button
                            v-for="opt in PRIORITY_OPTIONS"
                            :key="opt.value"
                            type="button"
                            role="radio"
                            :aria-checked="form.priority === opt.value"
                            class="inline-flex items-center justify-center rounded-[var(--radius)] border px-3 py-2 text-xs font-semibold transition-colors"
                            :style="
                                form.priority === opt.value
                                    ? {
                                          backgroundColor: opt.tokenVar,
                                          borderColor: opt.tokenVar,
                                          color: 'white',
                                      }
                                    : {}
                            "
                            :class="
                                form.priority === opt.value
                                    ? ''
                                    : 'border-t-border bg-transparent text-t-text hover:border-t-accent/40'
                            "
                            @click="form.priority = opt.value"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                    <p
                        v-if="form.errors.priority"
                        class="text-xs text-t-p1"
                        role="alert"
                    >
                        {{ form.errors.priority }}
                    </p>
                </div>

                <!-- Reason textarea -->
                <div class="space-y-2">
                    <label
                        for="promote-reason"
                        class="font-mono text-[9px] tracking-[2px] text-t-text-faint uppercase"
                    >
                        Reason (required, 8–500 characters)
                    </label>
                    <textarea
                        id="promote-reason"
                        v-model="form.reason"
                        rows="4"
                        maxlength="500"
                        placeholder="e.g., Visible weapon at entrance; suspect matches BOLO."
                        class="w-full rounded-md border border-t-border bg-transparent px-3 py-2 text-sm text-t-text shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-t-accent focus-visible:ring-[3px] focus-visible:ring-t-accent/30"
                    />
                    <div class="flex items-center justify-between">
                        <p
                            v-if="form.errors.reason"
                            class="text-xs text-t-p1"
                            role="alert"
                        >
                            {{ form.errors.reason }}
                        </p>
                        <p
                            v-else
                            class="text-xs text-t-text-faint"
                        >
                            Reason will be appended to the incident notes for
                            audit.
                        </p>
                        <p
                            class="font-mono text-[10px]"
                            :class="counterClass"
                            aria-live="polite"
                        >
                            {{ form.reason.length }}/500
                        </p>
                    </div>
                </div>
            </template>

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="form.processing"
                    @click="emit('close')"
                >
                    Cancel
                </Button>
                <Button
                    :disabled="!canSubmit || form.processing"
                    @click="submit"
                >
                    {{
                        form.processing
                            ? 'Promoting…'
                            : 'Promote to Incident'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
