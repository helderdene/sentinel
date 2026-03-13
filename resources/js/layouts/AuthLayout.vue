<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh items-center justify-center bg-background p-6">
        <div
            class="w-full max-w-md rounded-[14px] border border-border bg-card p-10 shadow-[var(--shadow-4)]"
            style="animation: fadeUp 400ms ease"
        >
            <!-- CDRRMO branding -->
            <div class="mb-8 flex flex-col items-center gap-2">
                <div
                    class="flex size-[52px] items-center justify-center rounded-[10px]"
                    style="
                        background: color-mix(
                            in srgb,
                            var(--t-brand) 10%,
                            transparent
                        );
                    "
                >
                    <!-- Shield/crest SVG icon for CDRRMO -->
                    <svg
                        class="size-7 text-t-brand"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        <path d="M12 8v4" />
                        <path d="M12 16h.01" />
                    </svg>
                </div>
                <span class="text-base font-semibold text-t-brand">
                    CDRRMO Butuan City
                </span>
                <span class="text-xs text-muted-foreground">
                    IRMS &mdash; Incident Response Management System
                </span>
            </div>

            <!-- Page title -->
            <div class="mb-6 text-center">
                <h1 class="text-lg font-semibold text-foreground">
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>

            <slot />
        </div>
    </div>
</template>
