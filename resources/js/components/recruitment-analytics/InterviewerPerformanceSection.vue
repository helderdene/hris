<script setup lang="ts">
/**
 * InterviewerPerformanceSection Component
 *
 * Displays interview metrics, interviewer leaderboard, and scheduling efficiency.
 */
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Users, CheckCircle, XCircle, AlertTriangle, Award, Calendar } from 'lucide-vue-next';

interface InterviewMetrics {
    total: number;
    completed: number;
    cancelled: number;
    noShows: number;
    completionRate: number;
    avgDurationMinutes: number | null;
}

interface LeaderboardItem {
    employeeId: number;
    name: string;
    totalInterviews: number;
    completedInterviews: number;
    avgRating: number | null;
    passThroughRate: number;
}

interface SchedulingMetrics {
    avgDaysToSchedule: number | null;
    scheduledThisWeek: number;
    scheduledNextWeek: number;
    rescheduledCount: number;
}

interface Props {
    metrics?: InterviewMetrics;
    leaderboard?: LeaderboardItem[];
    schedulingMetrics?: SchedulingMetrics;
}

const props = defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Users class="h-5 w-5" />
                Interviewer Performance
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!metrics">
                <div class="space-y-4">
                    <div class="grid grid-cols-4 gap-4">
                        <div v-for="i in 4" :key="i" class="h-16 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                    <div class="h-48 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                </div>
            </template>

            <template v-else>
                <!-- Interview Stats -->
                <div class="mb-6 grid grid-cols-4 gap-3">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Users class="h-4 w-4 text-blue-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Total</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.total }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <CheckCircle class="h-4 w-4 text-emerald-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Completed</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-emerald-600">
                            {{ metrics.completed }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <XCircle class="h-4 w-4 text-red-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Cancelled</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-red-600">
                            {{ metrics.cancelled }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="h-4 w-4 text-amber-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">No Shows</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-amber-600">
                            {{ metrics.noShows }}
                        </p>
                    </div>
                </div>

                <!-- Completion Rate & Avg Duration -->
                <div class="mb-6 grid grid-cols-2 gap-4">
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Completion Rate</p>
                        <p class="mt-1 text-2xl font-bold" :class="[
                            metrics.completionRate >= 90 ? 'text-emerald-600' :
                            metrics.completionRate >= 70 ? 'text-amber-600' : 'text-red-600'
                        ]">
                            {{ metrics.completionRate }}%
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Avg Duration</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.avgDurationMinutes !== null ? `${metrics.avgDurationMinutes} min` : '-' }}
                        </p>
                    </div>
                </div>

                <!-- Scheduling Metrics -->
                <div v-if="schedulingMetrics" class="mb-6">
                    <h4 class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <Calendar class="h-4 w-4" />
                        Scheduling
                    </h4>
                    <div class="grid grid-cols-4 gap-3">
                        <div class="rounded-lg border border-slate-200 p-3 text-center dark:border-slate-700">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Avg Days to Schedule</p>
                            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">
                                {{ schedulingMetrics.avgDaysToSchedule !== null ? schedulingMetrics.avgDaysToSchedule : '-' }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-3 text-center dark:border-slate-700">
                            <p class="text-xs text-slate-500 dark:text-slate-400">This Week</p>
                            <p class="mt-1 text-lg font-bold text-blue-600">
                                {{ schedulingMetrics.scheduledThisWeek }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-3 text-center dark:border-slate-700">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Next Week</p>
                            <p class="mt-1 text-lg font-bold text-purple-600">
                                {{ schedulingMetrics.scheduledNextWeek }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-3 text-center dark:border-slate-700">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Rescheduled</p>
                            <p class="mt-1 text-lg font-bold text-amber-600">
                                {{ schedulingMetrics.rescheduledCount }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Interviewer Leaderboard -->
                <div v-if="leaderboard && leaderboard.length > 0">
                    <h4 class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <Award class="h-4 w-4 text-amber-500" />
                        Top Interviewers
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-700">
                                    <th class="py-2 text-left font-medium text-slate-500 dark:text-slate-400">#</th>
                                    <th class="py-2 text-left font-medium text-slate-500 dark:text-slate-400">Name</th>
                                    <th class="py-2 text-right font-medium text-slate-500 dark:text-slate-400">Interviews</th>
                                    <th class="py-2 text-right font-medium text-slate-500 dark:text-slate-400">Avg Rating</th>
                                    <th class="py-2 text-right font-medium text-slate-500 dark:text-slate-400">Pass Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in leaderboard"
                                    :key="item.employeeId"
                                    class="border-b border-slate-100 dark:border-slate-800"
                                >
                                    <td class="py-2">
                                        <span
                                            v-if="index < 3"
                                            class="flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold text-white"
                                            :class="[
                                                index === 0 ? 'bg-amber-500' :
                                                index === 1 ? 'bg-slate-400' : 'bg-amber-700'
                                            ]"
                                        >
                                            {{ index + 1 }}
                                        </span>
                                        <span v-else class="text-slate-500">{{ index + 1 }}</span>
                                    </td>
                                    <td class="py-2 font-medium text-slate-700 dark:text-slate-300">
                                        {{ item.name }}
                                    </td>
                                    <td class="py-2 text-right text-slate-600 dark:text-slate-400">
                                        {{ item.completedInterviews }}/{{ item.totalInterviews }}
                                    </td>
                                    <td class="py-2 text-right">
                                        <span v-if="item.avgRating !== null" class="font-medium text-amber-600">
                                            {{ item.avgRating }}/5
                                        </span>
                                        <span v-else class="text-slate-400">-</span>
                                    </td>
                                    <td class="py-2 text-right font-medium" :class="[
                                        item.passThroughRate >= 50 ? 'text-emerald-600' :
                                        item.passThroughRate >= 30 ? 'text-amber-600' : 'text-red-600'
                                    ]">
                                        {{ item.passThroughRate }}%
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
