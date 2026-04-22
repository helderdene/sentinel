<script setup lang="ts">
import { ArchiveX } from 'lucide-vue-next';
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 rounded-[var(--radius)] border border-dashed border-t-border-med bg-t-surface-alt p-6 text-center"
        role="img"
        aria-label="Image purged by retention policy"
    >
        <ArchiveX
            class="size-8 text-t-text-faint"
            aria-hidden="true"
        />
        <p
            class="font-mono text-[10px] tracking-[1.5px] text-t-text-faint uppercase"
        >
            IMAGE PURGED BY RETENTION POLICY
        </p>
    </div>
</template>
