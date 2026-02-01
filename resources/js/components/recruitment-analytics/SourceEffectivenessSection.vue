<script setup lang="ts">
/**
 * SourceEffectivenessSection Component
 *
 * Displays source effectiveness metrics and quality analysis.
 */
import SourceComparisonChart from './SourceComparisonChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Share2, Award, TrendingUp } from 'lucide-vue-next';

interface SourceItem {
    source: string;
    label: string;
    applications: number;
    hires: number;
    hireRate: number;
    color: string;
}

interface QualityItem {
    source: string;
    label: string;
    screeningPassRate: number;
    interviewPassRate: number;
    offerAcceptRate: number;
}

interface Props {
    sourceEffectiveness?: SourceItem[];
    sourceQualityMetrics?: QualityItem[];
}

const props = defineProps<Props>();

function getTopSource(): SourceItem | null {
    if (!props.sourceEffectiveness || props.sourceEffectiveness.length === 0) return null;
    return props.sourceEffectiveness.reduce((best, current) =>
        current.hireRate > best.hireRate ? current : best
    );
}

function getRateColor(rate: number): string {
    if (rate >= 70) return 'text-emerald-600';
    if (rate >= 40) return 'text-amber-600';
    return 'text-red-600';
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Share2 class="h-5 w-5" />
                Source Effectiveness
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!sourceEffectiveness">
                <div class="space-y-4">
                    <div class="h-48 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    <div class="grid grid-cols-3 gap-4">
                        <div v-for="i in 3" :key="i" class="h-20 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                </div>
            </template>

            <template v-else>
                <!-- Top Source Highlight -->
                <div v-if="getTopSource()" class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-900/20">
                    <div class="flex items-center gap-2">
                        <Award class="h-5 w-5 text-emerald-600" />
                        <span class="text-sm font-medium text-emerald-700 dark:text-emerald-400">
                            Best Performing Source
                        </span>
                    </div>
                    <p class="mt-1 text-lg font-bold text-emerald-800 dark:text-emerald-300">
                        {{ getTopSource()?.label }}
                    </p>
                    <p class="text-sm text-emerald-600 dark:text-emerald-400">
                        {{ getTopSource()?.hireRate }}% hire rate ({{ getTopSource()?.hires }}/{{ getTopSource()?.applications }})
                    </p>
                </div>

                <!-- Source Comparison Chart -->
                <div class="mb-6">
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        Applications vs Hires by Source
                    </h4>
                    <SourceComparisonChart
                        v-if="sourceEffectiveness.length > 0"
                        :data="sourceEffectiveness"
                    />
                </div>

                <!-- Source Stats -->
                <div class="mb-6 grid grid-cols-3 gap-3">
                    <div
                        v-for="item in sourceEffectiveness"
                        :key="item.source"
                        class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                        :style="{ borderLeftColor: item.color, borderLeftWidth: '3px' }"
                    >
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.label }}</p>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ item.hireRate }}%
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ item.hires }} / {{ item.applications }} hired
                        </p>
                    </div>
                </div>

                <!-- Quality Metrics -->
                <div v-if="sourceQualityMetrics && sourceQualityMetrics.length > 0">
                    <h4 class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <TrendingUp class="h-4 w-4" />
                        Source Quality Metrics
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-700">
                                    <th class="py-2 text-left font-medium text-slate-500 dark:text-slate-400">Source</th>
                                    <th class="py-2 text-right font-medium text-slate-500 dark:text-slate-400">Screening Pass</th>
                                    <th class="py-2 text-right font-medium text-slate-500 dark:text-slate-400">Interview Pass</th>
                                    <th class="py-2 text-right font-medium text-slate-500 dark:text-slate-400">Offer Accept</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in sourceQualityMetrics"
                                    :key="item.source"
                                    class="border-b border-slate-100 dark:border-slate-800"
                                >
                                    <td class="py-2 text-slate-700 dark:text-slate-300">{{ item.label }}</td>
                                    <td class="py-2 text-right font-medium" :class="getRateColor(item.screeningPassRate)">
                                        {{ item.screeningPassRate }}%
                                    </td>
                                    <td class="py-2 text-right font-medium" :class="getRateColor(item.interviewPassRate)">
                                        {{ item.interviewPassRate }}%
                                    </td>
                                    <td class="py-2 text-right font-medium" :class="getRateColor(item.offerAcceptRate)">
                                        {{ item.offerAcceptRate }}%
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
