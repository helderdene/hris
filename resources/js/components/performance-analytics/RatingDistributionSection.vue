<script setup lang="ts">
/**
 * RatingDistributionSection Component
 *
 * Displays performance rating distribution with bar chart.
 */
import { useBarChartOptions, useDarkMode } from '@/composables/useCharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Bar } from 'vue-chartjs';
import { Star } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface RatingItem {
    rating: string;
    count: number;
    label: string;
    percentage: number;
}

interface Props {
    data?: RatingItem[];
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
    if (!props.data) return { labels: [], datasets: [] };

    return {
        labels: props.data.map((item) => item.label),
        datasets: [
            {
                label: 'Employees',
                data: props.data.map((item) => item.count),
                backgroundColor: props.data.map((item) => ratingColors[item.rating] || '#64748b'),
                borderRadius: 4,
            },
        ],
    };
});

const chartOptions = computed(() => useBarChartOptions(isDark.value));

const totalRated = computed(() => {
    if (!props.data) return 0;
    return props.data.reduce((sum, item) => sum + item.count, 0);
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Star class="h-5 w-5" />
                Rating Distribution
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!data">
                <div class="space-y-4">
                    <div class="h-48 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    <div class="grid grid-cols-5 gap-2">
                        <div v-for="i in 5" :key="i" class="h-12 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                </div>
            </template>

            <template v-else>
                <!-- Chart -->
                <div v-if="totalRated > 0" class="h-48">
                    <Bar :data="chartData" :options="chartOptions" />
                </div>
                <div
                    v-else
                    class="flex h-48 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                >
                    <p class="text-sm text-slate-500">No ratings available</p>
                </div>

                <!-- Legend/Stats -->
                <div class="mt-4 grid grid-cols-5 gap-2">
                    <div
                        v-for="item in data"
                        :key="item.rating"
                        class="text-center"
                    >
                        <div
                            class="mx-auto mb-1 h-3 w-3 rounded-full"
                            :style="{ backgroundColor: ratingColors[item.rating] }"
                        />
                        <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                            {{ item.count }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            {{ item.percentage }}%
                        </div>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
