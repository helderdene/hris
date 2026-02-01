<script setup lang="ts">
/**
 * KpiAchievementSection Component
 *
 * Displays KPI achievement distribution and metrics.
 */
import { useBarChartOptions, useDarkMode, chartColors } from '@/composables/useCharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Bar } from 'vue-chartjs';
import { BarChart3, TrendingUp, Award, Target } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface StatusItem {
    status: string;
    label: string;
    count: number;
}

interface DistributionItem {
    range: string;
    count: number;
}

interface Props {
    data?: {
        byStatus: StatusItem[];
        achievementDistribution: DistributionItem[];
        averageAchievement: number;
        totalKpis: number;
        overachievingCount: number;
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

const chartData = computed(() => {
    if (!props.data) return { labels: [], datasets: [] };

    const colors = ['#ef4444', '#f59e0b', '#22c55e', '#3b82f6', '#8b5cf6'];

    return {
        labels: props.data.achievementDistribution.map((d) => d.range),
        datasets: [
            {
                label: 'KPIs',
                data: props.data.achievementDistribution.map((d) => d.count),
                backgroundColor: colors,
                borderRadius: 4,
            },
        ],
    };
});

const chartOptions = computed(() => useBarChartOptions(isDark.value));

function getStatusColor(status: string): string {
    const colors: Record<string, string> = {
        pending: 'bg-slate-500',
        in_progress: 'bg-blue-500',
        completed: 'bg-emerald-500',
    };
    return colors[status] || 'bg-slate-400';
}

const hasKpis = computed(() => {
    if (!props.data) return false;
    return props.data.totalKpis > 0;
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <BarChart3 class="h-5 w-5" />
                KPI Achievement
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!data">
                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div v-for="i in 3" :key="i" class="h-20 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                    <div class="h-48 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                </div>
            </template>

            <template v-else>
                <!-- Stats Grid -->
                <div class="mb-6 grid grid-cols-3 gap-4">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Target class="h-4 w-4 text-blue-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Total KPIs</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ data.totalKpis }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <TrendingUp class="h-4 w-4 text-emerald-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Avg. Achievement</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ data.averageAchievement }}%
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Award class="h-4 w-4 text-purple-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Over 100%</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ data.overachievingCount }}
                        </p>
                    </div>
                </div>

                <!-- Achievement Distribution Chart -->
                <div class="mb-6">
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        Achievement Distribution
                    </h4>
                    <div v-if="hasKpis" class="h-48">
                        <Bar :data="chartData" :options="chartOptions" />
                    </div>
                    <div
                        v-else
                        class="flex h-48 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                    >
                        <p class="text-sm text-slate-500">No KPIs in selected period</p>
                    </div>
                </div>

                <!-- By Status -->
                <div>
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        By Status
                    </h4>
                    <div class="flex gap-4">
                        <div
                            v-for="item in data.byStatus"
                            :key="item.status"
                            class="flex-1 rounded-lg border border-slate-200 p-3 text-center dark:border-slate-700"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <div :class="['h-2 w-2 rounded-full', getStatusColor(item.status)]" />
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ item.label }}</span>
                            </div>
                            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">
                                {{ item.count }}
                            </p>
                        </div>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
