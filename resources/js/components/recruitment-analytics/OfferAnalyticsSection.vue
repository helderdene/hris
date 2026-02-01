<script setup lang="ts">
/**
 * OfferAnalyticsSection Component
 *
 * Displays offer metrics, acceptance trends, and decline reasons.
 */
import OfferTrendChart from './OfferTrendChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FileText, CheckCircle, XCircle, Clock, ThumbsUp } from 'lucide-vue-next';

interface OfferMetrics {
    total: number;
    accepted: number;
    declined: number;
    pending: number;
    acceptanceRate: number;
    avgResponseDays: number | null;
}

interface TrendItem {
    month: string;
    total: number;
    accepted: number;
    acceptanceRate: number;
}

interface DeclineReason {
    reason: string;
    count: number;
    percentage: number;
}

interface Props {
    metrics?: OfferMetrics;
    acceptanceTrend?: TrendItem[];
    declineReasons?: DeclineReason[];
}

const props = defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <FileText class="h-5 w-5" />
                Offer Analytics
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
                            <FileText class="h-4 w-4 text-blue-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Total Offers</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.total }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <ThumbsUp class="h-4 w-4 text-emerald-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Acceptance Rate</span>
                        </div>
                        <p class="mt-1 text-xl font-bold" :class="[
                            metrics.acceptanceRate >= 70 ? 'text-emerald-600' :
                            metrics.acceptanceRate >= 50 ? 'text-amber-600' : 'text-red-600'
                        ]">
                            {{ metrics.acceptanceRate }}%
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <CheckCircle class="h-4 w-4 text-emerald-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Accepted</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-emerald-600">
                            {{ metrics.accepted }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <XCircle class="h-4 w-4 text-red-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Declined</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-red-600">
                            {{ metrics.declined }}
                        </p>
                    </div>
                </div>

                <!-- Avg Response Time -->
                <div v-if="metrics.avgResponseDays !== null" class="mb-6 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4 text-amber-500" />
                            <span class="text-sm text-slate-500 dark:text-slate-400">Avg Response Time</span>
                        </div>
                        <span class="text-lg font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.avgResponseDays }} days
                        </span>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        {{ metrics.pending }} offers pending response
                    </div>
                </div>

                <!-- Acceptance Trend Chart -->
                <div class="mb-6">
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        Acceptance Trend
                    </h4>
                    <OfferTrendChart
                        v-if="acceptanceTrend && acceptanceTrend.length > 0"
                        :data="acceptanceTrend"
                    />
                    <div
                        v-else
                        class="flex h-48 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                    >
                        <p class="text-sm text-slate-500">No trend data available</p>
                    </div>
                </div>

                <!-- Decline Reasons -->
                <div v-if="declineReasons && declineReasons.length > 0">
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        Top Decline Reasons
                    </h4>
                    <div class="space-y-2">
                        <div
                            v-for="reason in declineReasons"
                            :key="reason.reason"
                            class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-700 dark:text-slate-300">
                                    {{ reason.reason }}
                                </span>
                                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                    {{ reason.count }} ({{ reason.percentage }}%)
                                </span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="h-full rounded-full bg-red-500 transition-all"
                                    :style="{ width: `${reason.percentage}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
