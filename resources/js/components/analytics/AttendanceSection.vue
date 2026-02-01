<script setup lang="ts">
/**
 * AttendanceSection Component
 *
 * Displays attendance metrics including rate chart and department breakdown.
 */
import AttendanceRateChart from './AttendanceRateChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Clock, UserCheck, UserX, AlertCircle } from 'lucide-vue-next';

interface AttendanceMetrics {
    attendanceRate: number;
    presentCount: number;
    absentCount: number;
    lateCount: number;
    totalRecords: number;
}

interface TrendItem {
    date: string;
    rate: number;
    present: number;
    absent: number;
}

interface DepartmentItem {
    department: string;
    departmentId: number;
    rate: number;
    present: number;
    total: number;
}

interface Props {
    metrics?: AttendanceMetrics;
    trendData?: TrendItem[];
    byDepartment?: DepartmentItem[];
}

const props = defineProps<Props>();

function getRateColor(rate: number): string {
    if (rate >= 95) return 'text-emerald-600';
    if (rate >= 85) return 'text-amber-600';
    return 'text-red-600';
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Clock class="h-5 w-5" />
                Attendance Overview
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!metrics">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <div class="h-64 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                    <div class="space-y-4">
                        <div v-for="i in 4" :key="i" class="h-12 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Chart -->
                    <div class="lg:col-span-2">
                        <AttendanceRateChart
                            v-if="trendData && trendData.length > 0"
                            :data="trendData"
                        />
                        <div
                            v-else
                            class="flex h-64 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                        >
                            <p class="text-sm text-slate-500">No trend data available</p>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="space-y-4">
                        <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                            <div class="flex items-center gap-3">
                                <div class="rounded-full bg-emerald-100 p-2 dark:bg-emerald-900">
                                    <UserCheck class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Present</p>
                                    <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                        {{ metrics.presentCount.toLocaleString() }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                            <div class="flex items-center gap-3">
                                <div class="rounded-full bg-red-100 p-2 dark:bg-red-900">
                                    <UserX class="h-4 w-4 text-red-600 dark:text-red-400" />
                                </div>
                                <div>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Absent</p>
                                    <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                        {{ metrics.absentCount.toLocaleString() }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                            <div class="flex items-center gap-3">
                                <div class="rounded-full bg-amber-100 p-2 dark:bg-amber-900">
                                    <AlertCircle class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Late Arrivals</p>
                                    <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                        {{ metrics.lateCount.toLocaleString() }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                            <div class="flex items-center gap-3">
                                <div class="rounded-full bg-blue-100 p-2 dark:bg-blue-900">
                                    <Clock class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Total Records</p>
                                    <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                        {{ metrics.totalRecords.toLocaleString() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department breakdown -->
                <div v-if="byDepartment && byDepartment.length > 0" class="mt-6">
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        Attendance by Department
                    </h4>
                    <div class="space-y-2">
                        <div
                            v-for="dept in byDepartment"
                            :key="dept.departmentId"
                            class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-2 dark:border-slate-700"
                        >
                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                {{ dept.department }}
                            </span>
                            <span
                                class="text-sm font-medium"
                                :class="getRateColor(dept.rate)"
                            >
                                {{ dept.rate }}%
                            </span>
                        </div>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
