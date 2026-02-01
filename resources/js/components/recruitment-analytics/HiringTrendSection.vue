<script setup lang="ts">
/**
 * HiringTrendSection Component
 *
 * Displays hiring velocity trends and seasonal patterns.
 */
import HiringVelocityChart from './HiringVelocityChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TrendingUp, Calendar } from 'lucide-vue-next';
import { useBarChartOptions, useDarkMode, chartColors } from '@/composables/useCharts';
import { Bar } from 'vue-chartjs';
import { computed, onMounted, ref } from 'vue';

interface VelocityItem {
    month: string;
    applications: number;
    hires: number;
    velocity: number;
}

interface SeasonalItem {
    month: number;
    monthName: string;
    avgApplications: number;
    avgHires: number;
}

interface Props {
    velocityTrend?: VelocityItem[];
    seasonalPatterns?: SeasonalItem[];
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

const seasonalChartData = computed(() => {
    if (!props.seasonalPatterns) return { labels: [], datasets: [] };

    return {
        labels: props.seasonalPatterns.map((item) => item.monthName.substring(0, 3)),
        datasets: [
            {
                label: 'Avg Applications',
                data: props.seasonalPatterns.map((item) => item.avgApplications),
                backgroundColor: chartColors.primary,
                borderRadius: 4,
            },
            {
                label: 'Avg Hires',
                data: props.seasonalPatterns.map((item) => item.avgHires),
                backgroundColor: chartColors.success,
                borderRadius: 4,
            },
        ],
    };
});

const seasonalChartOptions = computed(() => ({
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
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <TrendingUp class="h-5 w-5" />
                Hiring Trends
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!velocityTrend">
                <div class="space-y-4">
                    <div class="h-64 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    <div class="h-48 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                </div>
            </template>

            <template v-else>
                <!-- Hiring Velocity Chart -->
                <div class="mb-8">
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        Hiring Velocity Over Time
                    </h4>
                    <HiringVelocityChart
                        v-if="velocityTrend.length > 0"
                        :data="velocityTrend"
                    />
                    <div
                        v-else
                        class="flex h-64 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                    >
                        <p class="text-sm text-slate-500">No velocity data available</p>
                    </div>
                </div>

                <!-- Seasonal Patterns -->
                <div v-if="seasonalPatterns && seasonalPatterns.length > 0">
                    <h4 class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <Calendar class="h-4 w-4" />
                        Seasonal Patterns (2-Year Average)
                    </h4>
                    <div class="h-48">
                        <Bar :data="seasonalChartData" :options="seasonalChartOptions" />
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
