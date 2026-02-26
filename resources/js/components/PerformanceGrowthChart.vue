<script setup lang="ts">
import { chartColors, useDarkMode, useLineChartOptions } from '@/composables/useCharts';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';

interface GrowthPoint {
    cycle_name: string;
    overall_score: number | null;
    kpi_achievement: number | null;
}

const props = defineProps<{
    data: GrowthPoint[];
}>();

const isDark = useDarkMode();

const chartData = computed(() => ({
    labels: props.data.map((d) => d.cycle_name),
    datasets: [
        {
            label: 'Overall Score',
            data: props.data.map((d) => d.overall_score),
            borderColor: chartColors.primary,
            backgroundColor: chartColors.primary + '20',
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 6,
        },
        {
            label: 'KPI Achievement',
            data: props.data.map((d) => d.kpi_achievement),
            borderColor: chartColors.success,
            backgroundColor: chartColors.success + '20',
            fill: false,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 6,
        },
    ],
}));

const chartOptions = computed(() => {
    const options = useLineChartOptions(isDark);
    return {
        ...options,
        scales: {
            ...options.scales,
            y: {
                ...options.scales?.y,
                min: 0,
                max: 100,
                ticks: {
                    ...options.scales?.y?.ticks,
                    stepSize: 20,
                },
            },
        },
    };
});
</script>

<template>
    <div v-if="data.length === 0" class="flex items-center justify-center rounded-lg border border-dashed border-slate-300 p-8 dark:border-slate-600">
        <p class="text-sm text-slate-500 dark:text-slate-400">
            No performance cycle data available yet.
        </p>
    </div>
    <div v-else class="h-64">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
