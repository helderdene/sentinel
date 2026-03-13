<script setup lang="ts">
import {
    CategoryScale,
    Chart as ChartJS,
    LinearScale,
    LineElement,
    PointElement,
} from 'chart.js';
import { TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement);

const props = defineProps<{
    title: string;
    value: string;
    unit: string;
    trend: number;
    sparklineData: number[];
    color: string;
}>();

const COLORS: Record<string, string> = {
    blue: '#2563eb',
    green: '#16a34a',
    amber: '#d97706',
    red: '#dc2626',
    purple: '#7c3aed',
};

const lineColor = computed(() => COLORS[props.color] ?? '#2563eb');

const chartData = computed(() => ({
    labels: props.sparklineData.map((_, i) => String(i)),
    datasets: [
        {
            data: props.sparklineData,
            borderColor: lineColor.value,
            backgroundColor: 'transparent',
            borderWidth: 2,
            tension: 0.4,
            pointRadius: 0,
            fill: false,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { enabled: false },
    },
    scales: {
        x: { display: false },
        y: { display: false },
    },
    elements: {
        point: { radius: 0 },
        line: { borderWidth: 2, tension: 0.4 },
    },
};
</script>

<template>
    <div
        class="rounded-[var(--radius)] border border-border bg-card p-4 shadow-[var(--shadow-1)]"
    >
        <p
            class="mb-1 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
        >
            {{ title }}
        </p>

        <div class="mb-2 flex items-end justify-between">
            <div class="flex items-baseline gap-1">
                <span class="font-mono text-2xl font-semibold text-foreground">
                    {{ value }}
                </span>
                <span class="text-sm text-t-text-dim">
                    {{ unit }}
                </span>
            </div>

            <div
                v-if="trend !== 0"
                class="flex items-center gap-0.5 text-xs font-medium"
                :class="trend > 0 ? 'text-t-online' : 'text-t-p1'"
            >
                <TrendingUp v-if="trend > 0" class="h-3.5 w-3.5" />
                <TrendingDown v-else class="h-3.5 w-3.5" />
                <span>{{ Math.abs(trend).toFixed(1) }}%</span>
            </div>
        </div>

        <div class="h-10">
            <Line
                v-if="sparklineData.length > 1"
                :data="chartData"
                :options="chartOptions"
            />
        </div>
    </div>
</template>
