<script setup lang="ts">
import { computed } from 'vue';
import IntakeIconShield from '@/components/intake/icons/IntakeIconShield.vue';

type RoleKey = 'operator' | 'supervisor' | 'admin';

type Props = {
    roleKey: RoleKey;
    small?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    small: false,
});

interface RoleConfig {
    color: string;
    label: string;
}

const roles: Record<RoleKey, RoleConfig> = {
    operator: { color: 'var(--t-role-operator)', label: 'OPERATOR' },
    supervisor: { color: 'var(--t-role-supervisor)', label: 'SUPERVISOR' },
    admin: { color: 'var(--t-role-admin)', label: 'ADMIN' },
};

const config = computed(() => roles[props.roleKey] ?? roles.operator);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded font-mono font-bold whitespace-nowrap uppercase"
        :class="
            small ? 'px-1.5 py-[1px] text-[8px]' : 'px-2 py-[2px] text-[9px]'
        "
        :style="{
            backgroundColor: `color-mix(in srgb, ${config.color} 10%, transparent)`,
            borderWidth: '1px',
            borderStyle: 'solid',
            borderColor: `color-mix(in srgb, ${config.color} 30%, transparent)`,
            color: config.color,
        }"
    >
        <IntakeIconShield :size="small ? 9 : 11" :color="config.color" />
        {{ config.label }}
    </span>
</template>
