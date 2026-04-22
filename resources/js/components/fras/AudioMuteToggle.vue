<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bell, BellOff } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const props = defineProps<{ muted: boolean }>();

/**
 * Persist the mute preference to `users.fras_audio_muted` via POST to
 * `fras.settings.audio-mute.update`. The server round-trip is the
 * source of truth — the next Inertia page reload hydrates
 * `auth.user.fras_audio_muted` fresh, which re-drives this prop.
 *
 * Plan 22-05 owns the backend route. Until that lands we post to the
 * route by path; Wayfinder will auto-generate a typed action once the
 * controller is merged.
 */
function toggle(): void {
    router.post(
        '/fras/settings/audio-mute',
        { muted: !props.muted },
        { preserveScroll: true },
    );
}
</script>

<template>
    <TooltipProvider :delay-duration="100">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    variant="ghost"
                    size="icon"
                    :aria-label="`Toggle FRAS audio alerts. Currently ${muted ? 'muted' : 'enabled'}.`"
                    @click="toggle"
                >
                    <BellOff v-if="muted" class="size-5" />
                    <Bell v-else class="size-5" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                {{
                    muted
                        ? 'Audio muted. Click to restore critical alert tones.'
                        : 'Audio enabled. Click to mute critical alert tones.'
                }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
