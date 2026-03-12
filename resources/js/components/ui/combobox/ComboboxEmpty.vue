<script setup lang="ts">
import type { ComboboxEmptyProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { ComboboxEmpty } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<
    ComboboxEmptyProps & { class?: HTMLAttributes['class'] }
>();
</script>

<template>
    <ComboboxEmpty
        data-slot="combobox-empty"
        :class="cn('py-6 text-center text-sm', props.class)"
    >
        <slot />
    </ComboboxEmpty>
</template>
