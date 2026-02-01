<script setup lang="ts">
/**
 * SalaryDistributionChart Component
 *
 * Bar chart showing salary distribution across bands.
 */
import { chartColors, useBarChartOptions, useDarkMode } from '@/composables/useCharts';
import { Bar } from 'vue-chartjs';
import { computed, onMounted, ref } from 'vue';

interface SalaryBandItem {
    band: string;
    count: number;
    min: number;
    max: number | null;
}

interface Props {
    data: SalaryBandItem[];
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
    labels: props.data.map((item) => item.band),
    datasets: [
        {
            label: 'Employees',
            data: props.data.map((item) => item.count),
            backgroundColor: chartColors.primary,
            borderRadius: 4,
        },
    ],
}));

const chartOptions = computed(() => useBarChartOptions(isDark.value));
</script>

<template>
    <div class="h-48">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
