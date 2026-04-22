<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    content: string;
    availableLangs: string[];
    currentLang: 'en' | 'tl';
}>();

function switchLang(l: 'en' | 'tl'): void {
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
    <article
        class="prose mx-auto max-w-[680px] px-6 py-12 lg:py-16"
    >
        <div class="mb-8 flex items-center gap-2 not-prose">
            <Button
                :variant="props.currentLang === 'en' ? 'default' : 'outline'"
                size="sm"
                @click="switchLang('en')"
            >
                English
            </Button>
            <Button
                :variant="props.currentLang === 'tl' ? 'default' : 'outline'"
                size="sm"
                @click="switchLang('tl')"
            >
                Filipino
            </Button>
        </div>
        <!-- v-html is safe here: server-sanitized via league/commonmark
             html_input=strip (Phase 22 T-22-08-03 mitigation). -->
        <div v-if="props.content" v-html="props.content" />
        <p v-else class="text-sm italic text-gray-500">
            Privacy Notice content is being prepared. Please check back shortly.
        </p>
    </article>
</template>
