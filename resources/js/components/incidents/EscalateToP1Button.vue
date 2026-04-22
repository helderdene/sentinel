<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';
import { overridePriority } from '@/actions/App/Http/Controllers/IntakeStationController';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

interface TimelineEntry {
    event_data?: Record<string, unknown> | null;
}

interface IncidentForEscalate {
    id: string;
    priority: string;
    timeline?: TimelineEntry[];
}

const props = defineProps<{ incident: IncidentForEscalate }>();

const showButton = computed(() => {
    const firstEntry = props.incident.timeline?.[0];
    const source = firstEntry?.event_data?.source;

    return source === 'fras_recognition' && props.incident.priority !== 'P1';
});

const form = useForm({
    priority: 'P1',
    trigger: 'fras_escalate_button',
});

function escalate(): void {
    form.post(overridePriority(props.incident.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <TooltipProvider v-if="showButton" :delay-duration="150">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    variant="destructive"
                    :disabled="form.processing"
                    aria-label="Escalate incident to priority P1"
                    @click="escalate"
                >
                    <TrendingUp class="size-4" />
                    {{ form.processing ? 'Escalating…' : 'Escalate to P1' }}
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                Raise priority to P1 and notify dispatcher. Required for
                supervisor approval.
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
