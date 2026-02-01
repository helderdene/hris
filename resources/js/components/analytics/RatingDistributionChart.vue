<script setup lang="ts">
/**
 * RatingDistributionChart Component
 *
 * Bar chart showing performance rating distribution.
 */
import { useBarChartOptions, useDarkMode } from '@/composables/useCharts';
import { Bar } from 'vue-chartjs';
import { computed, onMounted, ref } from 'vue';

interface RatingItem {
    rating: string;
    count: number;
    label: string;
}

interface Props {
    data: RatingItem[];
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

// Color mapping for ratings
const ratingColors: Record<string, string> = {
    exceptional: '#22c55e', // green
    exceeds_expectations: '#3b82f6', // blue
    meets_expectations: '#8b5cf6', // purple
    needs_improvement: '#f59e0b', // amber
    unsatisfactory: '#ef4444', // red
};

const chartData = computed(() => ({
    labels: props.data.map((item) => item.label),
    datasets: [
        {
            label: 'Employees',
            data: props.data.map((item) => item.count),
            backgroundColor: props.data.map((item) => ratingColors[item.rating] || '#64748b'),
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
