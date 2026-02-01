<script setup lang="ts">
/**
 * OfferTrendChart Component
 *
 * Line chart showing offer acceptance trends over time.
 */
import { chartColors, useLineChartOptions, useDarkMode } from '@/composables/useCharts';
import { Line } from 'vue-chartjs';
import { computed, onMounted, ref } from 'vue';

interface TrendItem {
    month: string;
    total: number;
    accepted: number;
    acceptanceRate: number;
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
                label: 'Acceptance Rate (%)',
                data: props.data.map((item) => item.acceptanceRate),
                borderColor: chartColors.success,
                backgroundColor: `${chartColors.success}20`,
                fill: true,
                tension: 0.4,
                yAxisID: 'y',
            },
            {
                label: 'Total Offers',
                data: props.data.map((item) => item.total),
                borderColor: chartColors.primary,
                backgroundColor: 'transparent',
                borderDash: [5, 5],
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
            min: 0,
            max: 100,
            grid: {
                color: isDark.value ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)',
            },
            ticks: {
                color: isDark.value ? '#e2e8f0' : '#334155',
                callback: (value: number | string) => `${value}%`,
            },
        },
        y1: {
            type: 'linear' as const,
            display: true,
            position: 'right' as const,
            grid: {
                drawOnChartArea: false,
            },
            ticks: {
                color: isDark.value ? '#e2e8f0' : '#334155',
            },
        },
    },
}));
</script>

<template>
    <div class="h-48">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
