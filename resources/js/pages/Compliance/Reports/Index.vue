<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface DepartmentStat {
    id: number;
    name: string;
    total_employees: number;
    compliant_employees: number;
    compliance_rate: number;
}

const props = defineProps<{
    departmentStats: DepartmentStat[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compliance', href: '/compliance' },
    { title: 'Reports', href: '/compliance/reports' },
];

const departmentStatsData = computed(() => props.departmentStats ?? []);

const overallStats = computed(() => {
    const stats = departmentStatsData.value;
    const totalEmployees = stats.reduce((sum, d) => sum + d.total_employees, 0);
    const compliantEmployees = stats.reduce((sum, d) => sum + d.compliant_employees, 0);
    const complianceRate = totalEmployees > 0
        ? Math.round((compliantEmployees / totalEmployees) * 100 * 10) / 10
        : 0;

    return { totalEmployees, compliantEmployees, complianceRate };
});

function getComplianceColor(rate: number): string {
    if (rate >= 90) return 'text-green-600 dark:text-green-400';
    if (rate >= 70) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
}

function getProgressColor(rate: number): string {
    if (rate >= 90) return 'bg-green-500';
    if (rate >= 70) return 'bg-amber-500';
    return 'bg-red-500';
}
</script>

<template>
    <Head :title="`Compliance Reports - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Compliance Reports
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        View compliance status across departments and teams.
                    </p>
                </div>
            </div>

            <!-- Overall Summary -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Overall Compliance</CardDescription>
                        <CardTitle :class="getComplianceColor(overallStats.complianceRate)" class="text-3xl">
                            {{ overallStats.complianceRate }}%
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Progress :model-value="overallStats.complianceRate" class="h-2" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Total Employees</CardDescription>
                        <CardTitle class="text-3xl">
                            {{ overallStats.totalEmployees }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Across all departments
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Compliant Employees</CardDescription>
                        <CardTitle class="text-3xl text-green-600 dark:text-green-400">
                            {{ overallStats.compliantEmployees }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Completed all required training
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Department Breakdown -->
            <Card>
                <CardHeader>
                    <CardTitle>Department Compliance</CardTitle>
                    <CardDescription>
                        Compliance status breakdown by department
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="departmentStatsData.length > 0" class="flex flex-col gap-4">
                        <div
                            v-for="dept in departmentStatsData"
                            :key="dept.id"
                            class="flex items-center gap-4"
                        >
                            <div class="w-48 truncate font-medium text-slate-900 dark:text-slate-100">
                                {{ dept.name }}
                            </div>
                            <div class="flex-1">
                                <div class="relative h-4 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div
                                        :class="getProgressColor(dept.compliance_rate)"
                                        class="absolute inset-y-0 left-0 rounded-full transition-all"
                                        :style="{ width: `${dept.compliance_rate}%` }"
                                    />
                                </div>
                            </div>
                            <div :class="getComplianceColor(dept.compliance_rate)" class="w-16 text-right font-semibold">
                                {{ dept.compliance_rate }}%
                            </div>
                            <div class="w-24 text-right text-sm text-slate-500 dark:text-slate-400">
                                {{ dept.compliant_employees }}/{{ dept.total_employees }}
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-8 text-center"
                    >
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            No department data available.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
