<script setup lang="ts">
/**
 * LeaveSection Component
 *
 * Displays leave utilization metrics and type breakdown chart.
 */
import LeaveTypePieChart from './LeaveTypePieChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Calendar, CheckCircle, Clock, XCircle } from 'lucide-vue-next';

interface LeaveMetrics {
    totalApplications: number;
    approvedCount: number;
    pendingCount: number;
    rejectedCount: number;
    totalDaysUsed: number;
    approvalRate: number;
}

interface LeaveTypeItem {
    type: string;
    count: number;
    days: number;
    color: string;
}

interface Props {
    metrics?: LeaveMetrics;
    typeBreakdown?: LeaveTypeItem[];
}

const props = defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Calendar class="h-5 w-5" />
                Leave Utilization
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!metrics">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div v-for="i in 4" :key="i" class="h-20 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                    <div class="h-64 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                </div>
            </template>

            <template v-else>
                <!-- Stats Grid -->
                <div class="mb-6 grid grid-cols-2 gap-4">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <CheckCircle class="h-4 w-4 text-emerald-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Approved</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.approvedCount }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4 text-amber-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Pending</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.pendingCount }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <XCircle class="h-4 w-4 text-red-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Rejected</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.rejectedCount }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Calendar class="h-4 w-4 text-blue-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Days Used</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.totalDaysUsed.toFixed(1) }}
                        </p>
                    </div>
                </div>

                <!-- Approval Rate -->
                <div class="mb-4 rounded-lg bg-slate-50 p-3 dark:bg-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Approval Rate</span>
                        <span class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            {{ metrics.approvalRate }}%
                        </span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                        <div
                            class="h-full rounded-full bg-emerald-500 transition-all"
                            :style="{ width: `${metrics.approvalRate}%` }"
                        />
                    </div>
                </div>

                <!-- Type Breakdown Chart -->
                <div>
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        Leave by Type
                    </h4>
                    <LeaveTypePieChart
                        v-if="typeBreakdown && typeBreakdown.length > 0"
                        :data="typeBreakdown"
                    />
                    <div
                        v-else
                        class="flex h-48 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                    >
                        <p class="text-sm text-slate-500">No leave data available</p>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
