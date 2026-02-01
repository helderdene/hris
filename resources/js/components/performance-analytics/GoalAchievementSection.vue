<script setup lang="ts">
/**
 * GoalAchievementSection Component
 *
 * Displays goal achievement metrics and status breakdown.
 */
import { useDoughnutChartOptions, useDarkMode, pieChartColors } from '@/composables/useCharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Doughnut } from 'vue-chartjs';
import { Target, CheckCircle, AlertTriangle, TrendingUp } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface StatusItem {
    status: string;
    label: string;
    count: number;
}

interface PriorityItem {
    priority: string;
    count: number;
    achieved: number;
}

interface Props {
    data?: {
        byStatus: StatusItem[];
        byPriority: PriorityItem[];
        achievementRate: number;
        averageProgress: number;
        totalGoals: number;
        overdueCount: number;
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

function getStatusColor(status: string): string {
    const colors: Record<string, string> = {
        draft: '#64748b',
        pending_approval: '#f59e0b',
        active: '#3b82f6',
        completed: '#22c55e',
        cancelled: '#ef4444',
    };
    return colors[status] || '#64748b';
}

const chartData = computed(() => {
    if (!props.data) return { labels: [], datasets: [] };

    const activeStatuses = props.data.byStatus.filter((s) => s.count > 0);

    return {
        labels: activeStatuses.map((s) => s.label),
        datasets: [
            {
                data: activeStatuses.map((s) => s.count),
                backgroundColor: activeStatuses.map((s) => getStatusColor(s.status)),
                borderWidth: 0,
            },
        ],
    };
});

const chartOptions = computed(() => useDoughnutChartOptions(isDark.value));

const hasGoals = computed(() => {
    if (!props.data) return false;
    return props.data.totalGoals > 0;
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Target class="h-5 w-5" />
                Goal Achievement
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!data">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div v-for="i in 4" :key="i" class="h-20 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                    <div class="h-48 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                </div>
            </template>

            <template v-else>
                <!-- Stats Grid -->
                <div class="mb-6 grid grid-cols-2 gap-4">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Target class="h-4 w-4 text-blue-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Total Goals</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ data.totalGoals }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <CheckCircle class="h-4 w-4 text-emerald-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Achievement Rate</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ data.achievementRate }}%
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <TrendingUp class="h-4 w-4 text-indigo-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Avg. Progress</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ data.averageProgress }}%
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="h-4 w-4 text-amber-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Overdue</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ data.overdueCount }}
                        </p>
                    </div>
                </div>

                <!-- Chart -->
                <div v-if="hasGoals" class="mb-6 h-48">
                    <Doughnut :data="chartData" :options="chartOptions" />
                </div>
                <div
                    v-else
                    class="mb-6 flex h-48 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                >
                    <p class="text-sm text-slate-500">No goals in selected period</p>
                </div>

                <!-- By Priority -->
                <div>
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        By Priority
                    </h4>
                    <div class="space-y-2">
                        <div
                            v-for="item in data.byPriority"
                            :key="item.priority"
                            class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ item.priority }}
                                </span>
                                <span class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ item.achieved }}/{{ item.count }} achieved
                                </span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div
                                    class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                    :style="{ width: item.count > 0 ? `${(item.achieved / item.count) * 100}%` : '0%' }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
