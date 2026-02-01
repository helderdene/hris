<script setup lang="ts">
/**
 * TimeToFillSection Component
 *
 * Displays time-to-fill metrics by stage, trends, and department breakdown.
 */
import TimeToFillChart from './TimeToFillChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Clock, AlertTriangle, Building2 } from 'lucide-vue-next';

interface StageItem {
    stage: string;
    label: string;
    avgDays: number | null;
}

interface TrendItem {
    month: string;
    avgDays: number;
}

interface DepartmentItem {
    department: string;
    departmentId: number;
    avgDays: number;
    count: number;
}

interface TimeToFillMetrics {
    byStage: StageItem[];
    bottleneck: string | null;
    totalAvgDays: number | null;
}

interface Props {
    metrics?: TimeToFillMetrics;
    trendData?: TrendItem[];
    byDepartment?: DepartmentItem[];
}

const props = defineProps<Props>();

function getDaysColor(days: number | null): string {
    if (days === null) return 'text-slate-500';
    if (days <= 14) return 'text-emerald-600';
    if (days <= 30) return 'text-amber-600';
    return 'text-red-600';
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Clock class="h-5 w-5" />
                Time-to-Fill Analysis
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!metrics">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div v-for="i in 2" :key="i" class="h-24 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                    <div class="h-48 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                </div>
            </template>

            <template v-else>
                <!-- Summary Cards -->
                <div class="mb-6 grid grid-cols-2 gap-4">
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4 text-blue-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Avg Time to Fill</span>
                        </div>
                        <p class="mt-1 text-2xl font-bold" :class="getDaysColor(metrics.totalAvgDays)">
                            {{ metrics.totalAvgDays !== null ? `${metrics.totalAvgDays} days` : '-' }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="h-4 w-4 text-amber-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Bottleneck Stage</span>
                        </div>
                        <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">
                            {{ metrics.bottleneck || 'None identified' }}
                        </p>
                    </div>
                </div>

                <!-- Stage Breakdown -->
                <div class="mb-6">
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        Time by Stage
                    </h4>
                    <div class="space-y-2">
                        <div
                            v-for="item in metrics.byStage"
                            :key="item.stage"
                            class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-2 dark:border-slate-700"
                        >
                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                {{ item.label }}
                            </span>
                            <span class="text-sm font-semibold" :class="getDaysColor(item.avgDays)">
                                {{ item.avgDays !== null ? `${item.avgDays} days` : '-' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Trend Chart -->
                <div class="mb-6">
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        Time-to-Fill Trend
                    </h4>
                    <TimeToFillChart
                        v-if="trendData && trendData.length > 0"
                        :data="trendData"
                    />
                    <div
                        v-else
                        class="flex h-48 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                    >
                        <p class="text-sm text-slate-500">No trend data available</p>
                    </div>
                </div>

                <!-- By Department -->
                <div v-if="byDepartment && byDepartment.length > 0">
                    <h4 class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <Building2 class="h-4 w-4" />
                        By Department
                    </h4>
                    <div class="space-y-2">
                        <div
                            v-for="dept in byDepartment"
                            :key="dept.departmentId"
                            class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-2 dark:border-slate-700"
                        >
                            <div>
                                <span class="text-sm text-slate-700 dark:text-slate-300">
                                    {{ dept.department }}
                                </span>
                                <span class="ml-2 text-xs text-slate-500">
                                    ({{ dept.count }} hires)
                                </span>
                            </div>
                            <span class="text-sm font-semibold" :class="getDaysColor(dept.avgDays)">
                                {{ dept.avgDays }} days
                            </span>
                        </div>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
