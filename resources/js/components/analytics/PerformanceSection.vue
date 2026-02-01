<script setup lang="ts">
/**
 * PerformanceSection Component
 *
 * Displays performance evaluation metrics and rating distribution.
 */
import RatingDistributionChart from './RatingDistributionChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Award, CheckCircle, Star, Users } from 'lucide-vue-next';

interface PerformanceMetrics {
    totalParticipants: number;
    completedEvaluations: number;
    completionRate: number;
    averageRating: number | null;
    acknowledgedCount: number;
}

interface RatingItem {
    rating: string;
    count: number;
    label: string;
}

interface Props {
    metrics?: PerformanceMetrics;
    ratingDistribution?: RatingItem[];
}

const props = defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Star class="h-5 w-5" />
                Performance Overview
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!metrics">
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
                            <Users class="h-4 w-4 text-blue-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Participants</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.totalParticipants }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <CheckCircle class="h-4 w-4 text-emerald-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Completed</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.completedEvaluations }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Star class="h-4 w-4 text-amber-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Avg. Score</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.averageRating !== null ? metrics.averageRating.toFixed(1) : '-' }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Award class="h-4 w-4 text-purple-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Acknowledged</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.acknowledgedCount }}
                        </p>
                    </div>
                </div>

                <!-- Completion Rate -->
                <div class="mb-4 rounded-lg bg-slate-50 p-3 dark:bg-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Completion Rate</span>
                        <span class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            {{ metrics.completionRate }}%
                        </span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                        <div
                            class="h-full rounded-full bg-blue-500 transition-all"
                            :style="{ width: `${metrics.completionRate}%` }"
                        />
                    </div>
                </div>

                <!-- Rating Distribution Chart -->
                <div>
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        Rating Distribution
                    </h4>
                    <RatingDistributionChart
                        v-if="ratingDistribution && ratingDistribution.length > 0"
                        :data="ratingDistribution"
                    />
                    <div
                        v-else
                        class="flex h-48 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                    >
                        <p class="text-sm text-slate-500">No performance data available</p>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
