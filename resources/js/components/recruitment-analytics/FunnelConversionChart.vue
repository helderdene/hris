<script setup lang="ts">
/**
 * FunnelConversionChart Component
 *
 * Horizontal bar chart for pipeline stages with conversion rates.
 */
import { useBarChartOptions, useDarkMode } from '@/composables/useCharts';
import { Bar } from 'vue-chartjs';
import { computed, onMounted, ref } from 'vue';

interface FunnelItem {
    stage: string;
    label: string;
    count: number;
    conversionRate: number | null;
    color: string;
}

interface Props {
    data: FunnelItem[];
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

const chartData = computed(() => ({
    labels: props.data.map((item) => item.label),
    datasets: [
        {
            label: 'Applications',
            data: props.data.map((item) => item.count),
            backgroundColor: props.data.map((item) => item.color),
            borderRadius: 4,
        },
    ],
}));

const chartOptions = computed(() => ({
    ...useBarChartOptions(isDark.value),
    indexAxis: 'y' as const,
    plugins: {
        ...useBarChartOptions(isDark.value).plugins,
        tooltip: {
            ...useBarChartOptions(isDark.value).plugins?.tooltip,
            callbacks: {
                afterLabel: (context: { dataIndex: number }) => {
                    const item = props.data[context.dataIndex];
                    if (item.conversionRate !== null) {
                        return `Conversion: ${item.conversionRate}%`;
                    }
                    return '';
                },
            },
        },
    },
}));
</script>

<template>
    <div class="h-64">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
