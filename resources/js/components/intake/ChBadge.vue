<script lang="ts">
export type ChannelKey = 'SMS' | 'APP' | 'VOICE' | 'IOT' | 'WALKIN';

/**
 * Maps IncidentChannel enum values to intake display keys.
 */
export const channelDisplayMap: Record<string, ChannelKey> = {
    phone: 'VOICE',
    sms: 'SMS',
    app: 'APP',
    iot: 'IOT',
    radio: 'WALKIN',
};
</script>

<script setup lang="ts">
import { computed } from 'vue';
import type { Component } from 'vue';
import IntakeIconApp from '@/components/intake/icons/IntakeIconApp.vue';
import IntakeIconIot from '@/components/intake/icons/IntakeIconIot.vue';
import IntakeIconSms from '@/components/intake/icons/IntakeIconSms.vue';
import IntakeIconVoice from '@/components/intake/icons/IntakeIconVoice.vue';
import IntakeIconWalkin from '@/components/intake/icons/IntakeIconWalkin.vue';

type Props = {
    ch: ChannelKey;
    small?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    small: false,
});

interface ChannelConfig {
    color: string;
    icon: Component;
    label: string;
}

const channels: Record<ChannelKey, ChannelConfig> = {
    SMS: { color: 'var(--t-ch-sms)', icon: IntakeIconSms, label: 'SMS' },
    APP: { color: 'var(--t-ch-app)', icon: IntakeIconApp, label: 'APP' },
    VOICE: {
        color: 'var(--t-ch-voice)',
        icon: IntakeIconVoice,
        label: 'VOICE',
    },
    IOT: { color: 'var(--t-ch-iot)', icon: IntakeIconIot, label: 'IOT' },
    WALKIN: {
        color: 'var(--t-ch-walkin)',
        icon: IntakeIconWalkin,
        label: 'WALK-IN',
    },
};

const config = computed(() => channels[props.ch]);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded font-mono font-bold whitespace-nowrap"
        :class="
            small ? 'px-1.5 py-[1px] text-[9px]' : 'px-2 py-[2px] text-[10px]'
        "
        :style="{
            backgroundColor: `color-mix(in srgb, ${config.color} 7%, transparent)`,
            borderWidth: '1px',
            borderStyle: 'solid',
            borderColor: `color-mix(in srgb, ${config.color} 21%, transparent)`,
            color: config.color,
        }"
    >
        <component
            :is="config.icon"
            :size="small ? 10 : 12"
            :color="config.color"
        />
        {{ config.label }}
    </span>
</template>
