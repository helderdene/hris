<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { AlertCircle, Calendar, Clock, TrendingUp, UserCheck } from 'lucide-vue-next';

interface DtrSummary {
    period: {
        start_date: string;
        end_date: string;
        total_days: number;
    };
    attendance: {
        present_days: number;
        absent_days: number;
        holiday_days: number;
        rest_days: number;
        no_schedule_days: number;
        attendance_rate: number;
    };
    time_summary: {
        total_work_hours: number;
        average_daily_work_hours: number;
    };
    late_undertime: {
        total_late_hours: number;
        late_days: number;
        total_undertime_hours: number;
        undertime_days: number;
    };
    overtime: {
        total_overtime_hours: number;
        approved_overtime_hours: number;
        pending_overtime_hours: number;
        overtime_days: number;
    };
    review: {
        needs_review_count: number;
    };
}

defineProps<{
    summary: DtrSummary;
}>();
</script>

<template>
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
        <!-- Attendance Rate -->
        <Card>
            <CardContent class="flex flex-col gap-2 p-4">
                <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <UserCheck class="h-4 w-4" />
                    <span>Attendance Rate</span>
                </div>
                <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    {{ summary.attendance.attendance_rate }}%
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    {{ summary.attendance.present_days }} present / {{ summary.attendance.present_days + summary.attendance.absent_days }} work days
                </div>
            </CardContent>
        </Card>

        <!-- Total Work Hours -->
        <Card>
            <CardContent class="flex flex-col gap-2 p-4">
                <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <Clock class="h-4 w-4" />
                    <span>Total Work Hours</span>
                </div>
                <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    {{ summary.time_summary.total_work_hours.toFixed(1) }}h
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Avg {{ summary.time_summary.average_daily_work_hours.toFixed(1) }}h/day
                </div>
            </CardContent>
        </Card>

        <!-- Late & Undertime -->
        <Card>
            <CardContent class="flex flex-col gap-2 p-4">
                <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <Calendar class="h-4 w-4" />
                    <span>Late / Undertime</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-red-600 dark:text-red-400">
                        {{ summary.late_undertime.total_late_hours.toFixed(1) }}h
                    </span>
                    <span class="text-slate-400">/</span>
                    <span class="text-xl font-bold text-amber-600 dark:text-amber-400">
                        {{ summary.late_undertime.total_undertime_hours.toFixed(1) }}h
                    </span>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    {{ summary.late_undertime.late_days }} late days, {{ summary.late_undertime.undertime_days }} UT days
                </div>
            </CardContent>
        </Card>

        <!-- Overtime -->
        <Card>
            <CardContent class="flex flex-col gap-2 p-4">
                <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <TrendingUp class="h-4 w-4" />
                    <span>Overtime</span>
                </div>
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ summary.overtime.total_overtime_hours.toFixed(1) }}h
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-green-600 dark:text-green-400">
                        {{ summary.overtime.approved_overtime_hours.toFixed(1) }}h approved
                    </span>
                    <span v-if="summary.overtime.pending_overtime_hours > 0" class="text-amber-600 dark:text-amber-400">
                        {{ summary.overtime.pending_overtime_hours.toFixed(1) }}h pending
                    </span>
                </div>
            </CardContent>
        </Card>

        <!-- Needs Review -->
        <Card>
            <CardContent class="flex flex-col gap-2 p-4">
                <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <AlertCircle class="h-4 w-4" />
                    <span>Needs Review</span>
                </div>
                <div
                    class="text-2xl font-bold"
                    :class="summary.review.needs_review_count > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-slate-100'"
                >
                    {{ summary.review.needs_review_count }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    {{ summary.review.needs_review_count === 0 ? 'All records clear' : 'Records need attention' }}
                </div>
            </CardContent>
        </Card>
    </div>
</template>
