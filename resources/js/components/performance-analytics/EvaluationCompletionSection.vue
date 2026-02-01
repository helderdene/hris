<script setup lang="ts">
/**
 * EvaluationCompletionSection Component
 *
 * Displays evaluation completion metrics by status and cycle.
 */
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ClipboardCheck, CheckCircle, Clock, AlertTriangle } from 'lucide-vue-next';

interface StatusItem {
    status: string;
    label: string;
    count: number;
}

interface CycleItem {
    cycle: string;
    total: number;
    completed: number;
    rate: number;
}

interface Props {
    data?: {
        byStatus: StatusItem[];
        byCycle: CycleItem[];
        overallRate: number;
    };
}

const props = defineProps<Props>();

function getStatusColor(status: string): string {
    const colors: Record<string, string> = {
        not_started: 'bg-slate-500',
        self_pending: 'bg-amber-500',
        peer_pending: 'bg-blue-500',
        manager_pending: 'bg-purple-500',
        calibrating: 'bg-indigo-500',
        completed: 'bg-emerald-500',
    };
    return colors[status] || 'bg-slate-400';
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <ClipboardCheck class="h-5 w-5" />
                Evaluation Completion
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!data">
                <div class="space-y-4">
                    <div class="h-24 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    <div class="grid grid-cols-3 gap-4">
                        <div v-for="i in 3" :key="i" class="h-16 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                </div>
            </template>

            <template v-else>
                <!-- Overall Completion Rate -->
                <div class="mb-6 rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <CheckCircle class="h-5 w-5 text-emerald-500" />
                            <span class="font-medium text-slate-700 dark:text-slate-300">Overall Completion Rate</span>
                        </div>
                        <span class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                            {{ data.overallRate }}%
                        </span>
                    </div>
                    <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                        <div
                            class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                            :style="{ width: `${data.overallRate}%` }"
                        />
                    </div>
                </div>

                <!-- By Status -->
                <div class="mb-6">
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

                <!-- By Cycle -->
                <div v-if="data.byCycle.length > 0">
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        By Performance Cycle
                    </h4>
                    <div class="space-y-3">
                        <div
                            v-for="cycle in data.byCycle"
                            :key="cycle.cycle"
                            class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ cycle.cycle }}
                                </span>
                                <span class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ cycle.completed }}/{{ cycle.total }} ({{ cycle.rate }}%)
                                </span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div
                                    class="h-full rounded-full bg-blue-500 transition-all duration-500"
                                    :style="{ width: `${cycle.rate}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="flex h-24 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                >
                    <p class="text-sm text-slate-500">No performance cycles in selected period</p>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
