<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Clock, FileText, Languages, Mail } from 'lucide-vue-next';
import { computed } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    content: string;
    availableLangs: string[];
    currentLang: 'en' | 'tl';
}>();

const heading = computed(() =>
    props.currentLang === 'tl' ? 'Paunawa sa Privacy' : 'Privacy Notice',
);

const lede = computed(() =>
    props.currentLang === 'tl'
        ? 'Kung paano nangongolekta, gumagamit, nag-iimbak, at nagpoprotekta ang CDRRMO Butuan ng personal na impormasyon sa loob ng IRMS, kasama ang biometric na datos na nakuha sa mga camera para sa emergency response.'
        : 'How CDRRMO Butuan collects, uses, stores, and protects personal information within IRMS — including biometric data captured by cameras for emergency response.',
);

const effectiveLabel = computed(() =>
    props.currentLang === 'tl' ? 'Petsang pag-aaral' : 'Last reviewed',
);

const readTimeLabel = computed(() =>
    props.currentLang === 'tl' ? '~6 minuto' : '~6 min read',
);

function switchLang(l: 'en' | 'tl'): void {
    if (l === props.currentLang) {
        return;
    }

    router.get('/privacy', { lang: l }, { preserveScroll: false });
}
</script>

<template>
    <Head
        :title="
            props.currentLang === 'tl'
                ? 'Paunawa sa Privacy — IRMS'
                : 'Privacy Notice — IRMS'
        "
    />

    <section class="relative mx-auto max-w-5xl px-6 pb-10 pt-12 lg:pt-16">
        <div class="flex flex-col gap-6">
            <div
                class="inline-flex w-fit items-center gap-2 rounded-full border border-[var(--t-border)] bg-white/80 px-3 py-1 text-[11px] font-medium uppercase tracking-[0.16em] text-[var(--t-text-mid)]"
            >
                <FileText class="size-3.5" />
                <span>RA 10173 · DPA Compliance</span>
            </div>

            <h1
                class="max-w-3xl text-3xl font-semibold tracking-tight text-[var(--t-text)] sm:text-4xl lg:text-5xl"
            >
                {{ heading }}
            </h1>

            <p
                class="max-w-3xl text-base leading-relaxed text-[var(--t-text-mid)] sm:text-lg"
            >
                {{ lede }}
            </p>

            <dl
                class="flex flex-wrap gap-x-8 gap-y-3 text-xs text-[var(--t-text-dim)]"
            >
                <div class="flex items-center gap-2">
                    <Clock class="size-4 text-[var(--t-text-faint)]" />
                    <dt class="sr-only">{{ effectiveLabel }}</dt>
                    <dd>
                        <span class="font-medium text-[var(--t-text-mid)]">
                            {{ effectiveLabel }}:
                        </span>
                        2026-04-22
                    </dd>
                </div>
                <div class="flex items-center gap-2">
                    <Mail class="size-4 text-[var(--t-text-faint)]" />
                    <dd>
                        <a
                            href="mailto:dpo@cdrrmo.butuan.gov.ph"
                            class="font-medium text-[var(--t-accent)] hover:underline"
                        >
                            dpo@cdrrmo.butuan.gov.ph
                        </a>
                    </dd>
                </div>
                <div class="flex items-center gap-2 text-[var(--t-text-faint)]">
                    <span aria-hidden="true">·</span>
                    <dd>{{ readTimeLabel }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="sticky top-0 z-10 border-y border-[var(--t-border)]/60 bg-[var(--t-bg)]/85 backdrop-blur">
        <div
            class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-3"
        >
            <div
                class="flex items-center gap-2 text-xs text-[var(--t-text-dim)]"
            >
                <Languages class="size-4" />
                <span>{{ currentLang === 'tl' ? 'Wika' : 'Language' }}</span>
            </div>
            <div
                role="tablist"
                aria-label="Language"
                class="inline-flex rounded-full border border-[var(--t-border)] bg-white p-0.5 shadow-sm"
            >
                <button
                    type="button"
                    role="tab"
                    :aria-selected="props.currentLang === 'en'"
                    :class="[
                        'rounded-full px-4 py-1.5 text-xs font-semibold transition-colors',
                        props.currentLang === 'en'
                            ? 'bg-[var(--t-accent)] text-white shadow-sm'
                            : 'text-[var(--t-text-mid)] hover:text-[var(--t-text)]',
                    ]"
                    @click="switchLang('en')"
                >
                    English
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="props.currentLang === 'tl'"
                    :class="[
                        'rounded-full px-4 py-1.5 text-xs font-semibold transition-colors',
                        props.currentLang === 'tl'
                            ? 'bg-[var(--t-accent)] text-white shadow-sm'
                            : 'text-[var(--t-text-mid)] hover:text-[var(--t-text)]',
                    ]"
                    @click="switchLang('tl')"
                >
                    Filipino
                </button>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-5xl px-6 py-10 lg:py-14">
        <article
            class="rounded-2xl border border-[var(--t-border)] bg-white p-6 shadow-sm ring-1 ring-black/[0.02] sm:p-10 lg:p-14"
        >
            <!-- v-html is safe here: server-sanitized via league/commonmark
                 html_input=strip (Phase 22 T-22-08-03 mitigation). -->
            <div
                v-if="props.content"
                class="privacy-prose"
                v-html="props.content"
            />
            <p
                v-else
                class="text-center text-sm italic text-[var(--t-text-dim)]"
            >
                Privacy Notice content is being prepared. Please check back shortly.
            </p>
        </article>
    </section>
</template>

<style scoped>
.privacy-prose {
    color: var(--t-text-mid);
    font-size: 0.9375rem;
    line-height: 1.65;
}

.privacy-prose :deep(h1) {
    margin-top: 0;
    margin-bottom: 1.25rem;
    font-size: 1.875rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--t-text);
}

.privacy-prose :deep(h2) {
    margin-top: 2.5rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--t-border);
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--t-text);
}

.privacy-prose :deep(h2:first-child) {
    margin-top: 0;
}

.privacy-prose :deep(h3) {
    margin-top: 1.75rem;
    margin-bottom: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--t-text);
}

.privacy-prose :deep(p) {
    margin: 1rem 0;
}

.privacy-prose :deep(a) {
    color: var(--t-accent);
    font-weight: 500;
    text-decoration: none;
}

.privacy-prose :deep(a:hover) {
    text-decoration: underline;
}

.privacy-prose :deep(strong) {
    color: var(--t-text);
    font-weight: 600;
}

.privacy-prose :deep(ul),
.privacy-prose :deep(ol) {
    margin: 1rem 0;
    padding-left: 1.5rem;
}

.privacy-prose :deep(ul) {
    list-style-type: disc;
}

.privacy-prose :deep(ol) {
    list-style-type: decimal;
}

.privacy-prose :deep(li) {
    margin: 0.4rem 0;
}

.privacy-prose :deep(li::marker) {
    color: var(--t-text-faint);
}

.privacy-prose :deep(code) {
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    background-color: var(--t-bg);
    color: var(--t-text);
    font-size: 0.85em;
}

.privacy-prose :deep(blockquote) {
    margin: 1.25rem 0;
    padding: 0.75rem 1rem;
    border-left: 3px solid var(--t-accent);
    background-color: color-mix(in oklab, var(--t-accent) 5%, transparent);
    color: var(--t-text-mid);
    font-style: italic;
}

.privacy-prose :deep(hr) {
    margin: 2rem 0;
    border: 0;
    border-top: 1px solid var(--t-border);
}

.privacy-prose :deep(table) {
    width: 100%;
    margin: 1.25rem 0;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.privacy-prose :deep(th),
.privacy-prose :deep(td) {
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--t-border);
    text-align: left;
}

.privacy-prose :deep(th) {
    font-weight: 600;
    color: var(--t-text);
    background-color: var(--t-bg);
}
</style>
