<script setup lang="ts">
import {
    DepartmentHeadcountSection,
    EmploymentTypeChart,
    NewHiresCard,
    QuickActionsSection,
    SeparationsCard,
    TenureDistributionChart,
    TotalHeadcountCard,
    TurnoverRateCard,
} from '@/Components/dashboard';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

/**
 * TypeScript interfaces for all metrics data from the controller
 */
interface HeadcountMetrics {
    total: number;
    active: number;
}

interface TrendMetrics {
    count: number;
    percentageChange: number | null;
}

interface TurnoverMetrics {
    rate: number;
    averageTenure: number;
}

interface TenureDistribution {
    lessThan1Year: number;
    oneToThreeYears: number;
    threeToFiveYears: number;
    fiveToTenYears: number;
    moreThan10Years: number;
}

interface EmploymentTypeBreakdown {
    regular: number;
    probationary: number;
    contractual: number;
    project_based: number;
    [key: string]: number;
}

interface DepartmentHeadcount {
    id: number;
    name: string;
    employees_count: number;
    color?: string;
}

interface Props {
    headcount: HeadcountMetrics;
    newHires: TrendMetrics;
    separations: TrendMetrics;
    turnover: TurnoverMetrics;
    tenureDistribution: TenureDistribution;
    employmentTypeBreakdown: EmploymentTypeBreakdown;
    departmentHeadcounts: DepartmentHeadcount[];
}

const props = defineProps<Props>();

const { tenantName, primaryColor } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Employee Dashboard', href: '/employees/dashboard' },
];
</script>

<template>
    <Head :title="`Employee Dashboard - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                data-test="page-header"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Employee Dashboard
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Overview of your workforce metrics and trends
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Button
                        variant="outline"
                        disabled
                        data-test="export-report-button"
                    >
                        <svg
                            class="mr-2 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                            />
                        </svg>
                        Export Report
                    </Button>
                    <Button
                        disabled
                        :style="{ backgroundColor: primaryColor }"
                        class="text-white"
                        data-test="import-employees-button"
                    >
                        <svg
                            class="mr-2 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"
                            />
                        </svg>
                        Import Employees
                    </Button>
                </div>
            </div>

            <!-- Stat Cards Grid - 4 columns desktop, 2 columns tablet, 1 column mobile -->
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                data-test="stat-cards-grid"
            >
                <!-- Total Headcount Card - Primary colored background -->
                <TotalHeadcountCard
                    :total="headcount.total"
                    :active="headcount.active"
                    :background-color="primaryColor"
                />

                <!-- New Hires Card -->
                <NewHiresCard
                    :count="newHires.count"
                    :percentage-change="newHires.percentageChange"
                />

                <!-- Separations Card -->
                <SeparationsCard
                    :count="separations.count"
                    :percentage-change="separations.percentageChange"
                />

                <!-- Turnover Rate Card -->
                <TurnoverRateCard
                    :rate="turnover.rate"
                    :average-tenure="turnover.averageTenure"
                />
            </div>

            <!-- Charts Section - 2 column layout, responsive -->
            <div
                class="grid grid-cols-1 gap-4 lg:grid-cols-2"
                data-test="charts-section"
            >
                <!-- Tenure Distribution Chart -->
                <TenureDistributionChart
                    :distribution="tenureDistribution"
                    data-test="tenure-distribution-section"
                />

                <!-- Employment Status Chart -->
                <EmploymentTypeChart
                    :breakdown="employmentTypeBreakdown"
                    data-test="employment-status-section"
                />
            </div>

            <!-- Department Headcount Section -->
            <DepartmentHeadcountSection
                :department-headcounts="departmentHeadcounts"
            />

            <!-- Quick Actions Section -->
            <QuickActionsSection />
        </div>
    </TenantLayout>
</template>
