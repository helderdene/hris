<script setup lang="ts">
/**
 * TimeToFillChart Component
 *
 * Line chart showing time-to-fill trends over time.
 */
import { chartColors, useLineChartOptions, useDarkMode } from '@/composables/useCharts';
import { Line } from 'vue-chartjs';
import { computed, onMounted, ref } from 'vue';

interface TrendItem {
    month: string;
    avgDays: number;
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
                label: 'Avg Days to Fill',
                data: props.data.map((item) => item.avgDays),
                borderColor: chartColors.primary,
                backgroundColor: `${chartColors.primary}20`,
                fill: true,
                tension: 0.4,
            },
        ],
    };
});

const chartOptions = computed(() => ({
    ...useLineChartOptions(isDark.value),
    scales: {
        ...useLineChartOptions(isDark.value).scales,
        y: {
            ...useLineChartOptions(isDark.value).scales?.y,
            beginAtZero: true,
            title: {
                display: true,
                text: 'Days',
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
