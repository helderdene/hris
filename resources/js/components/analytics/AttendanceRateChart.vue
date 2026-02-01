<script setup lang="ts">
/**
 * AttendanceRateChart Component
 *
 * Line chart showing attendance rate trends over time.
 */
import { chartColors, useLineChartOptions, useDarkMode } from '@/composables/useCharts';
import { Line } from 'vue-chartjs';
import { computed, onMounted, ref, watch } from 'vue';

interface TrendItem {
    date: string;
    rate: number;
    present: number;
    absent: number;
}

interface Props {
    data: TrendItem[];
}

const props = defineProps<Props>();

const isDark = ref(useDarkMode());

// Watch for dark mode changes
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
        const date = new Date(item.date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });

    return {
        labels,
        datasets: [
            {
                label: 'Attendance Rate (%)',
                data: props.data.map((item) => item.rate),
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
            min: 0,
            max: 100,
        },
    },
}));
</script>

<template>
    <div class="h-64">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
