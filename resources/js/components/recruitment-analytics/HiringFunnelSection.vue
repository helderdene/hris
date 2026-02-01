<script setup lang="ts">
/**
 * HiringFunnelSection Component
 *
 * Displays hiring funnel metrics with conversion rates and dropout analysis.
 */
import FunnelConversionChart from './FunnelConversionChart.vue';
import StageDropoutChart from './StageDropoutChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Filter, TrendingDown, ArrowRight } from 'lucide-vue-next';

interface FunnelItem {
    stage: string;
    label: string;
    count: number;
    conversionRate: number | null;
    color: string;
}

interface DropoutItem {
    stage: string;
    label: string;
    rejected: number;
    withdrawn: number;
    topRejectionReasons: { reason: string; count: number }[];
}

interface Props {
    funnelMetrics?: FunnelItem[];
    dropoutAnalysis?: DropoutItem[];
}

const props = defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Filter class="h-5 w-5" />
                Hiring Funnel
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!funnelMetrics">
                <div class="space-y-6">
                    <div class="h-64 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    <div class="grid grid-cols-6 gap-2">
                        <div v-for="i in 6" :key="i" class="h-16 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Funnel Chart -->
                    <div>
                        <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Pipeline Stages
                        </h4>
                        <FunnelConversionChart
                            v-if="funnelMetrics.length > 0"
                            :data="funnelMetrics"
                        />
                        <div
                            v-else
                            class="flex h-64 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                        >
                            <p class="text-sm text-slate-500">No funnel data available</p>
                        </div>
                    </div>

                    <!-- Conversion Rates -->
                    <div>
                        <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Stage-to-Stage Conversion
                        </h4>
                        <div class="space-y-3">
                            <template v-for="(item, index) in funnelMetrics" :key="item.stage">
                                <div
                                    v-if="index > 0"
                                    class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ funnelMetrics[index - 1].label }}
                                        </span>
                                        <ArrowRight class="h-3 w-3 text-slate-400" />
                                        <span class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ item.label }}
                                        </span>
                                    </div>
                                    <span
                                        class="text-sm font-semibold"
                                        :class="[
                                            item.conversionRate !== null && item.conversionRate >= 50
                                                ? 'text-emerald-600'
                                                : item.conversionRate !== null && item.conversionRate >= 25
                                                  ? 'text-amber-600'
                                                  : 'text-red-600',
                                        ]"
                                    >
                                        {{ item.conversionRate !== null ? `${item.conversionRate}%` : '-' }}
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Dropout Analysis -->
                <div v-if="dropoutAnalysis && dropoutAnalysis.length > 0" class="mt-6">
                    <h4 class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <TrendingDown class="h-4 w-4 text-red-500" />
                        Dropout Analysis by Stage
                    </h4>
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <StageDropoutChart :data="dropoutAnalysis" />
                        <div class="space-y-3">
                            <div
                                v-for="item in dropoutAnalysis"
                                :key="item.stage"
                                class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                        {{ item.label }}
                                    </span>
                                    <div class="flex gap-3 text-xs">
                                        <span class="text-red-600">{{ item.rejected }} rejected</span>
                                        <span class="text-slate-500">{{ item.withdrawn }} withdrawn</span>
                                    </div>
                                </div>
                                <div v-if="item.topRejectionReasons.length > 0" class="mt-2">
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Top reasons:</p>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <span
                                            v-for="reason in item.topRejectionReasons"
                                            :key="reason.reason"
                                            class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                                        >
                                            {{ reason.reason }} ({{ reason.count }})
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
