<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Stats {
    total_assignments: number;
    completed_assignments: number;
    overdue_assignments: number;
    in_progress_assignments: number;
    pending_assignments: number;
    compliance_rate: number;
    active_courses: number;
    active_rules: number;
}

const props = defineProps<{
    stats: Stats;
}>();

const { tenantName, primaryColor } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compliance', href: '/compliance' },
];

const statsData = computed(() => props.stats ?? {
    total_assignments: 0,
    completed_assignments: 0,
    overdue_assignments: 0,
    in_progress_assignments: 0,
    pending_assignments: 0,
    compliance_rate: 0,
    active_courses: 0,
    active_rules: 0,
});

function getComplianceRateColor(rate: number): string {
    if (rate >= 90) return 'text-green-600 dark:text-green-400';
    if (rate >= 70) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
}
</script>

<template>
    <Head :title="`Compliance Dashboard - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Compliance Dashboard
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Overview of mandatory training and compliance status.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link href="/compliance/courses">
                        <Button variant="outline">
                            Manage Courses
                        </Button>
                    </Link>
                    <Link href="/compliance/assignments">
                        <Button :style="{ backgroundColor: primaryColor }">
                            View Assignments
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Compliance Rate</CardDescription>
                        <CardTitle :class="getComplianceRateColor(statsData.compliance_rate)" class="text-3xl">
                            {{ statsData.compliance_rate }}%
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ statsData.completed_assignments }} of {{ statsData.total_assignments }} completed
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Overdue</CardDescription>
                        <CardTitle class="text-3xl text-red-600 dark:text-red-400">
                            {{ statsData.overdue_assignments }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Training past due date
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>In Progress</CardDescription>
                        <CardTitle class="text-3xl text-blue-600 dark:text-blue-400">
                            {{ statsData.in_progress_assignments }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Currently being completed
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Pending</CardDescription>
                        <CardTitle class="text-3xl text-amber-600 dark:text-amber-400">
                            {{ statsData.pending_assignments }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Not yet started
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Compliance Courses</CardTitle>
                        <CardDescription>
                            {{ statsData.active_courses }} active courses
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                            Manage mandatory training courses, modules, and assessments.
                        </p>
                        <Link href="/compliance/courses">
                            <Button variant="outline" class="w-full">
                                Manage Courses
                            </Button>
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Assignment Rules</CardTitle>
                        <CardDescription>
                            {{ statsData.active_rules }} active rules
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                            Configure auto-assignment rules for departments, positions, and more.
                        </p>
                        <Link href="/compliance/rules">
                            <Button variant="outline" class="w-full">
                                Manage Rules
                            </Button>
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Training Assignments</CardTitle>
                        <CardDescription>
                            {{ statsData.total_assignments }} total assignments
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                            View and manage employee training assignments and progress.
                        </p>
                        <Link href="/compliance/assignments">
                            <Button variant="outline" class="w-full">
                                View Assignments
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <!-- Overdue Alert -->
            <Card v-if="statsData.overdue_assignments > 0" class="border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/20">
                <CardHeader>
                    <CardTitle class="text-lg text-red-700 dark:text-red-400">
                        Attention Required
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-red-600 dark:text-red-400">
                        There are {{ statsData.overdue_assignments }} overdue compliance training assignments
                        that require immediate attention.
                    </p>
                    <Link href="/compliance/assignments?is_overdue=1" class="mt-4 inline-block">
                        <Button variant="destructive">
                            View Overdue Assignments
                        </Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
