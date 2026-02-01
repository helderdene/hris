<script setup lang="ts">
/**
 * SourceComparisonChart Component
 *
 * Grouped bar chart comparing application sources.
 */
import { useBarChartOptions, useDarkMode } from '@/composables/useCharts';
import { Bar } from 'vue-chartjs';
import { computed, onMounted, ref } from 'vue';

interface SourceItem {
    source: string;
    label: string;
    applications: number;
    hires: number;
    hireRate: number;
    color: string;
}

interface Props {
    data: SourceItem[];
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
            data: props.data.map((item) => item.applications),
            backgroundColor: props.data.map((item) => item.color),
            borderRadius: 4,
        },
        {
            label: 'Hires',
            data: props.data.map((item) => item.hires),
            backgroundColor: props.data.map((item) => `${item.color}80`),
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
}));
</script>

<template>
    <div class="h-48">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
