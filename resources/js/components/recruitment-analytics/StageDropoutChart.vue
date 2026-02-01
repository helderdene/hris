<script setup lang="ts">
/**
 * StageDropoutChart Component
 *
 * Stacked bar chart showing rejected/withdrawn by stage.
 */
import { chartColors, useBarChartOptions, useDarkMode } from '@/composables/useCharts';
import { Bar } from 'vue-chartjs';
import { computed, onMounted, ref } from 'vue';

interface DropoutItem {
    stage: string;
    label: string;
    rejected: number;
    withdrawn: number;
}

interface Props {
    data: DropoutItem[];
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
            label: 'Rejected',
            data: props.data.map((item) => item.rejected),
            backgroundColor: chartColors.danger,
            borderRadius: 4,
        },
        {
            label: 'Withdrawn',
            data: props.data.map((item) => item.withdrawn),
            backgroundColor: chartColors.secondary,
            borderRadius: 4,
        },
    ],
}));

const chartOptions = computed(() => ({
    ...useBarChartOptions(isDark.value),
    plugins: {
        ...useBarChartOptions(isDark.value).plugins,
        legend: {
            display: true,
            position: 'top' as const,
            labels: {
                color: isDark.value ? '#e2e8f0' : '#334155',
                usePointStyle: true,
                padding: 12,
            },
        },
    },
    scales: {
        ...useBarChartOptions(isDark.value).scales,
        x: {
            ...useBarChartOptions(isDark.value).scales?.x,
            stacked: true,
        },
        y: {
            ...useBarChartOptions(isDark.value).scales?.y,
            stacked: true,
        },
    },
}));
</script>

<template>
    <div class="h-48">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
