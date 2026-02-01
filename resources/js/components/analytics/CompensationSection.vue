<script setup lang="ts">
/**
 * CompensationSection Component
 *
 * Displays compensation metrics including salary distribution.
 */
import SalaryDistributionChart from './SalaryDistributionChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DollarSign, TrendingUp, Users, Wallet } from 'lucide-vue-next';

interface CompensationMetrics {
    totalExpense: number;
    averageSalary: number;
    totalGrossPay: number;
    totalDeductions: number;
    employeeCount: number;
}

interface SalaryBandItem {
    band: string;
    count: number;
    min: number;
    max: number | null;
}

interface PayrollTrendItem {
    period: string;
    expense: number;
    headcount: number;
}

interface Props {
    metrics?: CompensationMetrics;
    salaryDistribution?: SalaryBandItem[];
    payrollTrend?: PayrollTrendItem[];
}

const props = defineProps<Props>();

function formatCurrency(value: number): string {
    if (value >= 1000000) {
        return `₱${(value / 1000000).toFixed(1)}M`;
    }
    if (value >= 1000) {
        return `₱${(value / 1000).toFixed(0)}K`;
    }
    return `₱${value.toFixed(0)}`;
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <DollarSign class="h-5 w-5" />
                Compensation Overview
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
                            <Wallet class="h-4 w-4 text-emerald-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Total Expense</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ formatCurrency(metrics.totalExpense) }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <TrendingUp class="h-4 w-4 text-blue-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Avg. Salary</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ formatCurrency(metrics.averageSalary) }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <DollarSign class="h-4 w-4 text-purple-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Deductions</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ formatCurrency(metrics.totalDeductions) }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <Users class="h-4 w-4 text-amber-500" />
                            <span class="text-xs text-slate-500 dark:text-slate-400">Employees</span>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ metrics.employeeCount }}
                        </p>
                    </div>
                </div>

                <!-- Salary Distribution Chart -->
                <div>
                    <h4 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                        Salary Distribution
                    </h4>
                    <SalaryDistributionChart
                        v-if="salaryDistribution && salaryDistribution.length > 0"
                        :data="salaryDistribution"
                    />
                    <div
                        v-else
                        class="flex h-48 items-center justify-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700"
                    >
                        <p class="text-sm text-slate-500">No salary data available</p>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
