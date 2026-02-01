<script setup lang="ts">
/**
 * LeaveTypePieChart Component
 *
 * Doughnut chart showing leave usage breakdown by type.
 */
import { useDoughnutChartOptions, useDarkMode } from '@/composables/useCharts';
import { Doughnut } from 'vue-chartjs';
import { computed, onMounted, ref } from 'vue';

interface LeaveTypeItem {
    type: string;
    count: number;
    days: number;
    color: string;
}

interface Props {
    data: LeaveTypeItem[];
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
    labels: props.data.map((item) => item.type),
    datasets: [
        {
            data: props.data.map((item) => item.days),
            backgroundColor: props.data.map((item) => item.color),
            borderWidth: 0,
        },
    ],
}));

const chartOptions = computed(() => useDoughnutChartOptions(isDark.value));
</script>

<template>
    <div class="h-64">
        <Doughnut :data="chartData" :options="chartOptions" />
    </div>
</template>
