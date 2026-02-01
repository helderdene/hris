<script setup lang="ts">
/**
 * RatingTrendSection Component
 *
 * Displays rating trends over performance cycles using a line chart.
 */
import { useLineChartOptions, useDarkMode } from '@/composables/useCharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Line } from 'vue-chartjs';
import { TrendingUp } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface SeriesItem {
    rating: string;
    label: string;
    data: number[];
}

interface Props {
    data?: {
        cycles: string[];
        series: SeriesItem[];
    };
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

const chartData = computed(() => {
    if (!props.data || props.data.cycles.length === 0) {
        return { labels: [], datasets: [] };
    }

    return {
        labels: props.data.cycles,
        datasets: props.data.series.map((s) => ({
            label: s.label,
            data: s.data,
            borderColor: ratingColors[s.rating] || '#64748b',
            backgroundColor: ratingColors[s.rating] || '#64748b',
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 6,
        })),
    };
});

const chartOptions = computed(() => ({
    ...useLineChartOptions(isDark.value),
    plugins: {
        ...useLineChartOptions(isDark.value).plugins,
        legend: {
            display: true,
            position: 'bottom' as const,
            labels: {
                color: isDark.value ? '#e2e8f0' : '#334155',
                usePointStyle: true,
                padding: 16,
            },
        },
    },
}));

const hasData = computed(() => {
    if (!props.data) return false;
    return props.data.cycles.length > 0;
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <TrendingUp class="h-5 w-5" />
                Rating Trends Over Time
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!data">
                <div class="h-64 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
            </template>

            <template v-else>
                <div v-if="hasData" class="h-64">
                    <Line :data="chartData" :options="chartOptions" />
                </div>
                <div
                    v-else
                    class="flex h-64 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                >
                    <div class="text-center">
                        <TrendingUp class="mx-auto h-8 w-8 text-slate-400" />
                        <p class="mt-2 text-sm text-slate-500">
                            Not enough data to show trends
                        </p>
                        <p class="text-xs text-slate-400">
                            Trends will appear after multiple performance cycles
                        </p>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
