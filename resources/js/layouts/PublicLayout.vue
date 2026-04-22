<script setup lang="ts">
// Minimal CDRRMO-branded layout for citizen-facing public pages.
// No sidebar, no auth nav, no dark-mode override — per UI-SPEC §Design System
// citizen surfaces are always light-mode (D-30 isolation from operator UI).
</script>

<template>
    <div class="min-h-screen bg-white text-gray-900">
        <header class="border-b border-gray-200 py-4 text-center">
            <div class="mx-auto max-w-[680px] px-6">
                <p
                    class="text-sm font-semibold uppercase tracking-wider text-gray-900"
                >
                    CDRRMO · Butuan City
                </p>
                <p class="text-xs text-gray-500">
                    Incident Response Management System (IRMS)
                </p>
            </div>
        </header>
        <main>
            <slot />
        </main>
    </div>
</template>
