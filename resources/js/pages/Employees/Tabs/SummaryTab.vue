<script setup lang="ts">
import PerformanceGrowthChart from '@/components/PerformanceGrowthChart.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface AttendanceRecap {
    days_present: number;
    days_absent: number;
    days_on_leave: number;
    total_late_minutes: number;
    total_late_formatted: string;
}

interface OvertimeSummary {
    approved_hours: number;
    approved_hours_formatted: string;
    request_count: number;
    total_overtime_minutes: number;
}

interface LeaveBalance {
    leave_type: string;
    total_credits: number;
    used: number;
    pending: number;
    available: number;
}

interface PerformanceSummary {
    final_overall_score: number | null;
    final_rating: string | null;
    final_rating_label: string | null;
    kpi_achievement: number | null;
    goal_progress: number | null;
    cycle_name: string | null;
}

interface GrowthPoint {
    cycle_name: string;
    overall_score: number | null;
    kpi_achievement: number | null;
}

interface SummaryData {
    period: string;
    attendance: AttendanceRecap;
    overtime: OvertimeSummary;
    leave_balances: LeaveBalance[];
    performance: PerformanceSummary | null;
    performance_growth: GrowthPoint[];
}

interface Props {
    data?: SummaryData | null;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: null,
    loading: false,
});

const emit = defineEmits<{
    'period-change': [period: string];
}>();

const periods = [
    { value: 'today', label: 'Today' },
    { value: 'this_week', label: 'This Week' },
    { value: 'this_month', label: 'This Month' },
];

function handlePeriodChange(period: string) {
    emit('period-change', period);
}

function ratingColor(rating: string | null): string {
    if (!rating) {
        return 'text-slate-500 dark:text-slate-400';
    }
    const map: Record<string, string> = {
        exceptional:
            'text-emerald-600 dark:text-emerald-400',
        exceeds_expectations:
            'text-blue-600 dark:text-blue-400',
        meets_expectations:
            'text-slate-600 dark:text-slate-300',
        needs_improvement:
            'text-amber-600 dark:text-amber-400',
        unsatisfactory: 'text-red-600 dark:text-red-400',
    };
    return map[rating] ?? 'text-slate-500 dark:text-slate-400';
}

function ratingBadgeBg(rating: string | null): string {
    if (!rating) {
        return 'bg-slate-100 dark:bg-slate-800';
    }
    const map: Record<string, string> = {
        exceptional: 'bg-emerald-50 dark:bg-emerald-900/30',
        exceeds_expectations: 'bg-blue-50 dark:bg-blue-900/30',
        meets_expectations: 'bg-slate-100 dark:bg-slate-800',
        needs_improvement: 'bg-amber-50 dark:bg-amber-900/30',
        unsatisfactory: 'bg-red-50 dark:bg-red-900/30',
    };
    return map[rating] ?? 'bg-slate-100 dark:bg-slate-800';
}

function leaveProgressColor(used: number, total: number): string {
    if (total <= 0) return 'bg-blue-500';
    const pct = (used / total) * 100;
    if (pct > 90) return 'bg-red-500';
    if (pct >= 75) return 'bg-amber-500';
    return 'bg-blue-500';
}

function leaveProgressWidth(used: number, total: number): string {
    if (total <= 0) return '0%';
    return Math.min((used / total) * 100, 100) + '%';
}
</script>

<template>
    <div class="space-y-6">
        <!-- Period Filter -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900 sm:text-lg dark:text-slate-100">
                    Summary
                </h2>
                <p class="mt-0.5 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                    Employee overview and key metrics at a glance.
                </p>
            </div>
            <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-700 dark:bg-slate-800">
                <button
                    v-for="period in periods"
                    :key="period.value"
                    @click="handlePeriodChange(period.value)"
                    :class="[
                        'rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                        data?.period === period.value
                            ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-slate-100'
                            : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200',
                    ]"
                >
                    {{ period.label }}
                </button>
            </div>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="loading" class="space-y-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Attendance / Overtime skeleton -->
                <div
                    v-for="i in 2"
                    :key="'stat-' + i"
                    class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 animate-pulse rounded-lg bg-slate-200 dark:bg-slate-700" />
                        <div class="space-y-1.5">
                            <div class="h-4 w-28 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
                            <div class="h-3 w-40 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div
                            v-for="j in (i === 1 ? 4 : 2)"
                            :key="j"
                            class="h-16 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800"
                        />
                    </div>
                </div>
                <!-- Leave skeleton -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 animate-pulse rounded-lg bg-slate-200 dark:bg-slate-700" />
                        <div class="space-y-1.5">
                            <div class="h-4 w-28 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
                            <div class="h-3 w-40 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
                        </div>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div v-for="j in 3" :key="j" class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <div class="h-3.5 w-24 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
                                <div class="h-3.5 w-16 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
                            </div>
                            <div class="h-1.5 w-full animate-pulse rounded-full bg-slate-200 dark:bg-slate-700" />
                        </div>
                    </div>
                </div>
                <!-- Performance skeleton -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 animate-pulse rounded-lg bg-slate-200 dark:bg-slate-700" />
                        <div class="space-y-1.5">
                            <div class="h-4 w-28 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
                            <div class="h-3 w-40 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
                        </div>
                    </div>
                    <div class="mt-4 flex flex-col items-center gap-3">
                        <div class="h-16 w-16 animate-pulse rounded-full bg-slate-200 dark:bg-slate-700" />
                        <div class="h-5 w-24 animate-pulse rounded-full bg-slate-200 dark:bg-slate-700" />
                    </div>
                </div>
            </div>
            <!-- Growth chart skeleton -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 animate-pulse rounded-lg bg-slate-200 dark:bg-slate-700" />
                    <div class="space-y-1.5">
                        <div class="h-4 w-36 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
                        <div class="h-3 w-48 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
                    </div>
                </div>
                <div class="mt-4 h-52 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
            </div>
        </div>

        <!-- Content -->
        <template v-else-if="data">
            <!-- Stat Cards Grid -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Attendance Recap -->
                <Card>
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400 dark:shadow-lg dark:shadow-blue-500/10">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                        </div>
                        <div>
                            <CardTitle class="text-base text-slate-900 dark:text-slate-100">
                                Attendance Recap
                            </CardTitle>
                            <CardDescription>Attendance summary for the selected period</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-lg bg-green-50 p-3 dark:bg-green-900/20">
                                <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {{ data.attendance.days_present }}
                                </p>
                                <p class="mt-0.5 text-xs text-green-600/70 dark:text-green-400/70">
                                    Present
                                </p>
                            </div>
                            <div class="rounded-lg bg-red-50 p-3 dark:bg-red-900/20">
                                <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                                    {{ data.attendance.days_absent }}
                                </p>
                                <p class="mt-0.5 text-xs text-red-600/70 dark:text-red-400/70">
                                    Absent
                                </p>
                            </div>
                            <div class="rounded-lg bg-blue-50 p-3 dark:bg-blue-900/20">
                                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                    {{ data.attendance.days_on_leave }}
                                </p>
                                <p class="mt-0.5 text-xs text-blue-600/70 dark:text-blue-400/70">
                                    On Leave
                                </p>
                            </div>
                            <div class="rounded-lg bg-amber-50 p-3 dark:bg-amber-900/20">
                                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                    {{ data.attendance.total_late_formatted }}
                                </p>
                                <p class="mt-0.5 text-xs text-amber-600/70 dark:text-amber-400/70">
                                    Total Lateness
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Overtime -->
                <Card>
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-400 dark:shadow-lg dark:shadow-purple-500/10">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <div>
                            <CardTitle class="text-base text-slate-900 dark:text-slate-100">
                                Overtime
                            </CardTitle>
                            <CardDescription>Approved overtime for the selected period</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-purple-50 p-3 dark:bg-purple-900/20">
                                <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                    {{ data.overtime.approved_hours_formatted }}
                                </p>
                                <p class="mt-0.5 text-xs text-purple-600/70 dark:text-purple-400/70">
                                    Approved Hours
                                </p>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                                <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                                    {{ data.overtime.request_count }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    Requests
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Leave Balances -->
                <Card>
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-900/40 dark:text-teal-400 dark:shadow-lg dark:shadow-teal-500/10">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                            </svg>
                        </div>
                        <div>
                            <CardTitle class="text-base text-slate-900 dark:text-slate-100">
                                Leave Balances
                            </CardTitle>
                            <CardDescription>Current year leave credit balances</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="data.leave_balances.length === 0"
                            class="py-3 text-center text-sm text-slate-500 dark:text-slate-400"
                        >
                            No leave balances found.
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="balance in data.leave_balances"
                                :key="balance.leave_type"
                                class="space-y-1.5"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-700 dark:text-slate-300">
                                        {{ balance.leave_type }}
                                    </span>
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-slate-400 dark:text-slate-500">
                                            {{ balance.used }}/{{ balance.total_credits }} used
                                        </span>
                                        <span class="inline-flex min-w-[3rem] justify-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                            {{ balance.available }}
                                        </span>
                                    </div>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div
                                        :class="[
                                            'h-full rounded-full transition-all',
                                            leaveProgressColor(balance.used, balance.total_credits),
                                        ]"
                                        :style="{ width: leaveProgressWidth(balance.used, balance.total_credits) }"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Employee Performance -->
                <Card>
                    <CardHeader class="flex flex-row items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400 dark:shadow-lg dark:shadow-amber-500/10">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                            </svg>
                        </div>
                        <div>
                            <CardTitle class="text-base text-slate-900 dark:text-slate-100">
                                Employee Performance
                            </CardTitle>
                            <CardDescription>
                                {{ data.performance?.cycle_name ?? 'Latest performance cycle' }}
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="!data.performance"
                            class="py-3 text-center text-sm text-slate-500 dark:text-slate-400"
                        >
                            No performance data available.
                        </div>
                        <div v-else class="space-y-4">
                            <!-- Circular score badge -->
                            <div v-if="data.performance.final_overall_score != null" class="flex flex-col items-center gap-2">
                                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-900/20">
                                    <span class="text-xl font-bold text-amber-600 dark:text-amber-400">
                                        {{ data.performance.final_overall_score.toFixed(2) }}
                                    </span>
                                </div>
                                <span
                                    v-if="data.performance.final_rating_label"
                                    :class="[
                                        'inline-flex rounded-full px-3 py-0.5 text-xs font-semibold',
                                        ratingColor(data.performance.final_rating),
                                        ratingBadgeBg(data.performance.final_rating),
                                    ]"
                                >
                                    {{ data.performance.final_rating_label }}
                                </span>
                            </div>
                            <div v-else class="text-center">
                                <span class="text-sm text-slate-500 dark:text-slate-400">No score available</span>
                            </div>

                            <!-- KPI & Goal rows -->
                            <div class="space-y-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-500 dark:text-slate-400">
                                        KPI Achievement
                                    </span>
                                    <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                        {{ data.performance.kpi_achievement != null ? data.performance.kpi_achievement.toFixed(1) + '%' : '-' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-500 dark:text-slate-400">
                                        Goal Progress
                                    </span>
                                    <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                        {{ data.performance.goal_progress != null ? data.performance.goal_progress.toFixed(1) + '%' : '-' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Performance Growth Chart -->
            <Card>
                <CardHeader class="flex flex-row items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400 dark:shadow-lg dark:shadow-indigo-500/10">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                    </div>
                    <div>
                        <CardTitle class="text-base text-slate-900 dark:text-slate-100">
                            Performance Growth
                        </CardTitle>
                        <CardDescription>Score trends across performance cycles</CardDescription>
                    </div>
                </CardHeader>
                <CardContent>
                    <PerformanceGrowthChart :data="data.performance_growth" />
                </CardContent>
            </Card>
        </template>
    </div>
</template>
