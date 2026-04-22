<script setup lang="ts">
import { ShieldCheck } from 'lucide-vue-next';
// Citizen-facing public layout. Always light-mode (D-30 isolation from
// operator UI). Uses project design tokens so it visually ties into the
// authenticated IRMS surfaces without inheriting their full chrome.
</script>

<template>
    <div
        class="light relative min-h-screen bg-[var(--t-bg)] text-[var(--t-text)]"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 h-[320px] bg-gradient-to-b from-[color-mix(in_oklab,var(--t-accent)_10%,transparent)] to-transparent"
        />

        <header
            class="relative border-b border-[var(--t-border)]/80 bg-[var(--t-bg)]/70 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-4"
            >
                <a
                    href="/privacy"
                    class="flex items-center gap-3 text-[var(--t-text)]"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-full bg-[var(--t-accent)] text-white shadow-sm"
                        aria-hidden="true"
                    >
                        <ShieldCheck class="size-5" />
                    </span>
                    <span class="flex flex-col leading-tight">
                        <span
                            class="text-sm font-semibold uppercase tracking-[0.14em]"
                        >
                            CDRRMO · Butuan City
                        </span>
                        <span
                            class="text-xs text-[var(--t-text-dim)]"
                        >
                            Incident Response Management System
                        </span>
                    </span>
                </a>
                <a
                    href="/login"
                    class="hidden rounded-md border border-[var(--t-border)] px-3 py-1.5 text-xs font-medium text-[var(--t-text-mid)] hover:border-[var(--t-border-med)] hover:text-[var(--t-text)] sm:inline-flex"
                >
                    Staff sign-in
                </a>
            </div>
        </header>

        <main class="relative">
            <slot />
        </main>

        <footer
            class="mt-16 border-t border-[var(--t-border)]/80 bg-white/60 py-8 text-center"
        >
            <div
                class="mx-auto max-w-5xl px-6 text-xs text-[var(--t-text-dim)]"
            >
                <p class="font-semibold text-[var(--t-text-mid)]">
                    City Disaster Risk Reduction and Management Office — Butuan City
                </p>
                <p class="mt-1">
                    Data Privacy Officer:
                    <a
                        href="mailto:dpo@cdrrmo.butuan.gov.ph"
                        class="font-medium text-[var(--t-accent)] hover:underline"
                    >
                        dpo@cdrrmo.butuan.gov.ph
                    </a>
                </p>
                <p class="mt-3">
                    Compliant with Republic Act 10173 (Data Privacy Act of 2012).
                </p>
            </div>
        </footer>
    </div>
</template>
