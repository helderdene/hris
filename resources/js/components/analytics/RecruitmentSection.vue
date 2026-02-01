<script setup lang="ts">
/**
 * RecruitmentSection Component
 *
 * Displays recruitment metrics and pipeline visualization.
 */
import RecruitmentFunnelChart from './RecruitmentFunnelChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Briefcase, Clock, ThumbsUp, UserCheck, UserPlus, Users } from 'lucide-vue-next';

interface RecruitmentMetrics {
    openPositions: number;
    totalApplications: number;
    hiredCount: number;
    rejectedCount: number;
    avgTimeToHire: number | null;
    offerAcceptanceRate: number;
}

interface PipelineItem {
    stage: string;
    count: number;
    label: string;
}

interface Props {
    metrics?: RecruitmentMetrics;
    pipeline?: PipelineItem[];
}

const props = defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Briefcase class="h-5 w-5" />
                Recruitment Pipeline
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!metrics">
                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div v-for="i in 6" :key="i" class="h-16 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                    <div class="h-48 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                </div>
            </template>

            <template v-else>
                <!-- Stats Grid -->
                <div class="mb-6 grid grid-cols-3 gap-3">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Briefcase class="h-4 w-4 text-blue-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Open</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.openPositions }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Users class="h-4 w-4 text-purple-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Applied</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.totalApplications }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <UserCheck class="h-4 w-4 text-emerald-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Hired</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.hiredCount }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4 text-amber-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Time to Hire</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.avgTimeToHire !== null ? `${metrics.avgTimeToHire}d` : '-' }}
                        </p>
                    </div>

                    <div class="col-span-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <ThumbsUp class="h-4 w-4 text-teal-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Offer Acceptance Rate</span>
                        </div>
                        <div class="mt-1 flex items-center gap-2">
                            <p class="text-xl font-bold text-slate-900 dark:text-slate-100">
                                {{ metrics.offerAcceptanceRate }}%
                            </p>
                            <div class="flex-1">
                                <div class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div
                                        class="h-full rounded-full bg-teal-500 transition-all"
                                        :style="{ width: `${metrics.offerAcceptanceRate}%` }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pipeline Chart -->
                <div>
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        Application Pipeline
                    </h4>
                    <RecruitmentFunnelChart
                        v-if="pipeline && pipeline.length > 0"
                        :data="pipeline"
                    />
                    <div
                        v-else
                        class="flex h-48 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                    >
                        <p class="text-sm text-slate-500">No pipeline data available</p>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
