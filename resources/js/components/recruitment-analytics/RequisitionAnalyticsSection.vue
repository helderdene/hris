<script setup lang="ts">
/**
 * RequisitionAnalyticsSection Component
 *
 * Displays requisition metrics, urgency breakdown, and headcount vs hires.
 */
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ClipboardList, Clock, AlertTriangle, Building2, Target } from 'lucide-vue-next';

interface RequisitionMetrics {
    open: number;
    approved: number;
    pending: number;
    rejected: number;
    fillRate: number;
    avgApprovalDays: number | null;
}

interface UrgencyItem {
    urgency: string;
    label: string;
    count: number;
    color: string;
}

interface HeadcountItem {
    department: string;
    departmentId: number;
    requestedHeadcount: number;
    hires: number;
    variance: number;
    variancePercent: number;
}

interface Props {
    metrics?: RequisitionMetrics;
    byUrgency?: UrgencyItem[];
    headcountVsHires?: HeadcountItem[];
}

const props = defineProps<Props>();

function getVarianceColor(variance: number): string {
    if (variance >= 0) return 'text-emerald-600';
    if (variance >= -2) return 'text-amber-600';
    return 'text-red-600';
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <ClipboardList class="h-5 w-5" />
                Requisition Analytics
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <template v-if="!metrics">
                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div v-for="i in 6" :key="i" class="h-16 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    </div>
                    <div class="h-32 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                </div>
            </template>

            <template v-else>
                <!-- Stats Grid -->
                <div class="mb-6 grid grid-cols-3 gap-3">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Open</p>
                        <p class="mt-1 text-xl font-bold text-blue-600">{{ metrics.open }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Approved</p>
                        <p class="mt-1 text-xl font-bold text-emerald-600">{{ metrics.approved }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Pending</p>
                        <p class="mt-1 text-xl font-bold text-amber-600">{{ metrics.pending }}</p>
                    </div>
                </div>

                <!-- Fill Rate & Approval Time -->
                <div class="mb-6 grid grid-cols-2 gap-4">
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Target class="h-4 w-4 text-emerald-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Fill Rate</span>
                        </div>
                        <p class="mt-1 text-2xl font-bold" :class="[
                            metrics.fillRate >= 80 ? 'text-emerald-600' :
                            metrics.fillRate >= 50 ? 'text-amber-600' : 'text-red-600'
                        ]">
                            {{ metrics.fillRate }}%
                        </p>
                        <p class="text-xs text-slate-500">of approved requisitions filled</p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4 text-blue-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Avg Approval Time</span>
                        </div>
                        <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.avgApprovalDays !== null ? `${metrics.avgApprovalDays} days` : '-' }}
                        </p>
                        <p class="text-xs text-slate-500">from submission to approval</p>
                    </div>
                </div>

                <!-- By Urgency -->
                <div v-if="byUrgency && byUrgency.length > 0" class="mb-6">
                    <h4 class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <AlertTriangle class="h-4 w-4" />
                        By Urgency Level
                    </h4>
                    <div class="grid grid-cols-4 gap-2">
                        <div
                            v-for="item in byUrgency"
                            :key="item.urgency"
                            class="rounded-lg border border-slate-200 p-3 text-center dark:border-slate-700"
                        >
                            <div
                                class="mx-auto mb-1 h-3 w-3 rounded-full"
                                :style="{ backgroundColor: item.color }"
                            />
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.label }}</p>
                            <p class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ item.count }}</p>
                        </div>
                    </div>
                </div>

                <!-- Headcount vs Hires -->
                <div v-if="headcountVsHires && headcountVsHires.length > 0">
                    <h4 class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <Building2 class="h-4 w-4" />
                        Headcount vs Actual Hires
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-700">
                                    <th class="py-2 text-left font-medium text-slate-500 dark:text-slate-400">Department</th>
                                    <th class="py-2 text-right font-medium text-slate-500 dark:text-slate-400">Requested</th>
                                    <th class="py-2 text-right font-medium text-slate-500 dark:text-slate-400">Hired</th>
                                    <th class="py-2 text-right font-medium text-slate-500 dark:text-slate-400">Variance</th>
                                    <th class="py-2 text-right font-medium text-slate-500 dark:text-slate-400">Fill %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in headcountVsHires"
                                    :key="item.departmentId"
                                    class="border-b border-slate-100 dark:border-slate-800"
                                >
                                    <td class="py-2 text-slate-700 dark:text-slate-300">{{ item.department }}</td>
                                    <td class="py-2 text-right text-slate-600 dark:text-slate-400">{{ item.requestedHeadcount }}</td>
                                    <td class="py-2 text-right text-slate-600 dark:text-slate-400">{{ item.hires }}</td>
                                    <td class="py-2 text-right font-medium" :class="getVarianceColor(item.variance)">
                                        {{ item.variance >= 0 ? '+' : '' }}{{ item.variance }}
                                    </td>
                                    <td class="py-2 text-right font-medium" :class="[
                                        item.variancePercent >= 100 ? 'text-emerald-600' :
                                        item.variancePercent >= 70 ? 'text-amber-600' : 'text-red-600'
                                    ]">
                                        {{ item.variancePercent }}%
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
