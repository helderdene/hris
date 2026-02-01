<script setup lang="ts">
/**
 * DevelopmentPlanSection Component
 *
 * Displays development plan completion metrics and progress.
 */
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { BookOpen, CheckCircle, AlertTriangle, Clock, Target } from 'lucide-vue-next';

interface StatusItem {
    status: string;
    label: string;
    count: number;
}

interface Props {
    data?: {
        byStatus: StatusItem[];
        completionRate: number;
        overdueCount: number;
        averageProgress: number;
        totalPlans: number;
    };
}

const props = defineProps<Props>();

function getStatusColor(status: string): string {
    const colors: Record<string, string> = {
        draft: 'bg-slate-500',
        pending_approval: 'bg-yellow-500',
        approved: 'bg-blue-500',
        in_progress: 'bg-indigo-500',
        completed: 'bg-emerald-500',
        cancelled: 'bg-red-500',
    };
    return colors[status] || 'bg-slate-400';
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <BookOpen class="h-5 w-5" />
                Development Plans
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!data">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div v-for="i in 4" :key="i" class="h-20 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                    <div class="h-32 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                </div>
            </template>

            <template v-else>
                <!-- Stats Grid -->
                <div class="mb-6 grid grid-cols-2 gap-4">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Target class="h-4 w-4 text-blue-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Total Plans</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ data.totalPlans }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <CheckCircle class="h-4 w-4 text-emerald-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Completion Rate</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ data.completionRate }}%
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4 text-indigo-500" />
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

                <!-- By Status -->
                <div>
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        By Status
                    </h4>
                    <div class="space-y-2">
                        <div
                            v-for="item in data.byStatus"
                            :key="item.status"
                            class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700"
                        >
                            <div class="flex items-center gap-2">
                                <div :class="['h-3 w-3 rounded-full', getStatusColor(item.status)]" />
                                <span class="text-sm text-slate-600 dark:text-slate-400">
                                    {{ item.label }}
                                </span>
                            </div>
                            <span class="font-semibold text-slate-900 dark:text-slate-100">
                                {{ item.count }}
                            </span>
                        </div>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
