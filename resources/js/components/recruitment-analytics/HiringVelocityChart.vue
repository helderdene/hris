<script setup lang="ts">
/**
 * HiringVelocityChart Component
 *
 * Multi-line chart showing applications, hires, and velocity trends.
 */
import { chartColors, useLineChartOptions, useDarkMode } from '@/composables/useCharts';
import { Line } from 'vue-chartjs';
import { computed, onMounted, ref } from 'vue';

interface TrendItem {
    month: string;
    applications: number;
    hires: number;
    velocity: number;
}

interface Props {
    data: TrendItem[];
}

const props = defineProps<Props>();

const isDark = ref(useDarkMode());

onMounted(() => {
    const observer = new MutationObserver(() => {
        isDark.value = useDarkMode();
    });
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

const chartData = computed(() => {
    const labels = props.data.map((item) => {
        const [year, month] = item.month.split('-');
        const date = new Date(parseInt(year), parseInt(month) - 1);
        return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
    });

    return {
        labels,
        datasets: [
            {
                label: 'Applications',
                data: props.data.map((item) => item.applications),
                borderColor: chartColors.primary,
                backgroundColor: 'transparent',
                tension: 0.4,
                yAxisID: 'y',
            },
            {
                label: 'Hires',
                data: props.data.map((item) => item.hires),
                borderColor: chartColors.success,
                backgroundColor: 'transparent',
                tension: 0.4,
                yAxisID: 'y',
            },
            {
                label: 'Velocity (%)',
                data: props.data.map((item) => item.velocity),
                borderColor: chartColors.warning,
                backgroundColor: `${chartColors.warning}20`,
                fill: true,
                tension: 0.4,
                yAxisID: 'y1',
            },
        ],
    };
});

const chartOptions = computed(() => ({
    ...useLineChartOptions(isDark.value),
    scales: {
        x: {
            ...useLineChartOptions(isDark.value).scales?.x,
        },
        y: {
            type: 'linear' as const,
            display: true,
            position: 'left' as const,
            grid: {
                color: isDark.value ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)',
            },
            ticks: {
                color: isDark.value ? '#e2e8f0' : '#334155',
            },
            title: {
                display: true,
                text: 'Count',
                color: isDark.value ? '#e2e8f0' : '#334155',
            },
        },
        y1: {
            type: 'linear' as const,
            display: true,
            position: 'right' as const,
            min: 0,
            max: 100,
            grid: {
                drawOnChartArea: false,
            },
            ticks: {
                color: isDark.value ? '#e2e8f0' : '#334155',
                callback: (value: number | string) => `${value}%`,
            },
            title: {
                display: true,
                text: 'Velocity',
                color: isDark.value ? '#e2e8f0' : '#334155',
            },
        },
    },
}));
</script>

<template>
    <div class="h-64">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
