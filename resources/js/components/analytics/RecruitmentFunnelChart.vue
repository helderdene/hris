<script setup lang="ts">
/**
 * RecruitmentFunnelChart Component
 *
 * Horizontal bar chart showing recruitment pipeline stages.
 */
import { pieChartColors, useBarChartOptions, useDarkMode } from '@/composables/useCharts';
import { Bar } from 'vue-chartjs';
import { computed, onMounted, ref } from 'vue';

interface PipelineItem {
    stage: string;
    count: number;
    label: string;
}

interface Props {
    data: PipelineItem[];
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
            backgroundColor: props.data.map((_, index) => pieChartColors[index % pieChartColors.length]),
            borderRadius: 4,
        },
    ],
}));

const chartOptions = computed(() => ({
    ...useBarChartOptions(isDark.value),
    indexAxis: 'y' as const,
}));
</script>

<template>
    <div class="h-48">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
