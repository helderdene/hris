<script setup lang="ts">
import {
    AttendanceSection,
    CompensationSection,
    DateRangeFilter,
    DepartmentFilter,
    LeaveSection,
    MetricCard,
    PerformanceSection,
    RecruitmentSection,
} from '@/Components/analytics';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Users, UserPlus, UserMinus, Activity } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * TypeScript interfaces for analytics data
 */
interface Department {
    id: number;
    name: string;
}

interface Filters {
    startDate: string | null;
    endDate: string | null;
    departmentIds: number[] | null;
}

interface HeadcountMetrics {
    total: number;
    active: number;
    newHires: number;
    separations: number;
}

interface AttendanceMetrics {
    attendanceRate: number;
    presentCount: number;
    absentCount: number;
    lateCount: number;
    totalRecords: number;
}

interface AttendanceTrendItem {
    date: string;
    rate: number;
    present: number;
    absent: number;
}

interface AttendanceByDepartmentItem {
    department: string;
    departmentId: number;
    rate: number;
    present: number;
    total: number;
}

interface LeaveMetrics {
    totalApplications: number;
    approvedCount: number;
    pendingCount: number;
    rejectedCount: number;
    totalDaysUsed: number;
    approvalRate: number;
}

interface LeaveTypeBreakdownItem {
    type: string;
    count: number;
    days: number;
    color: string;
}

interface CompensationMetrics {
    totalExpense: number;
    averageSalary: number;
    totalGrossPay: number;
    totalDeductions: number;
    employeeCount: number;
}

interface SalaryDistributionItem {
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

interface RecruitmentMetrics {
    openPositions: number;
    totalApplications: number;
    hiredCount: number;
    rejectedCount: number;
    avgTimeToHire: number | null;
    offerAcceptanceRate: number;
}

interface RecruitmentPipelineItem {
    stage: string;
    count: number;
    label: string;
}

interface PerformanceMetrics {
    totalParticipants: number;
    completedEvaluations: number;
    completionRate: number;
    averageRating: number | null;
    acknowledgedCount: number;
}

interface RatingDistributionItem {
    rating: string;
    count: number;
    label: string;
}

interface Props {
    filters: Filters;
    departments: Department[];
    headcount: HeadcountMetrics;
    attendance?: AttendanceMetrics;
    attendanceTrend?: AttendanceTrendItem[];
    attendanceByDepartment?: AttendanceByDepartmentItem[];
    leave?: LeaveMetrics;
    leaveTypeBreakdown?: LeaveTypeBreakdownItem[];
    compensation?: CompensationMetrics;
    salaryDistribution?: SalaryDistributionItem[];
    payrollTrend?: PayrollTrendItem[];
    recruitment?: RecruitmentMetrics;
    recruitmentPipeline?: RecruitmentPipelineItem[];
    performance?: PerformanceMetrics;
    ratingDistribution?: RatingDistributionItem[];
}

const props = defineProps<Props>();

const { tenantName, primaryColor } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR Analytics', href: '/hr/analytics' },
];

// Local filter state
const selectedStartDate = ref(props.filters.startDate || '');
const selectedEndDate = ref(props.filters.endDate || '');
const selectedDepartmentIds = ref<number[]>(props.filters.departmentIds || []);

// Apply filters
function applyFilters() {
    const params: Record<string, string | string[]> = {};

    if (selectedStartDate.value) {
        params.start_date = selectedStartDate.value;
    }

    if (selectedEndDate.value) {
        params.end_date = selectedEndDate.value;
    }

    if (selectedDepartmentIds.value.length > 0) {
        params.department_ids = selectedDepartmentIds.value.join(',');
    }

    router.get('/hr/analytics', params, {
        preserveState: true,
        preserveScroll: true,
    });
}

// Watch for filter changes and apply
watch([selectedStartDate, selectedEndDate, selectedDepartmentIds], () => {
    applyFilters();
}, { deep: true });

// Format currency
function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
}

// Computed for formatted payroll expense
const formattedPayrollExpense = computed(() => {
    if (!props.compensation) return '...';
    return formatCurrency(props.compensation.totalExpense);
});
</script>

<template>
    <Head :title="`HR Analytics - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header with Filters -->
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                data-test="page-header"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        HR Analytics Dashboard
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Comprehensive workforce metrics and insights
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <DateRangeFilter
                        v-model:start-date="selectedStartDate"
                        v-model:end-date="selectedEndDate"
                    />
                    <DepartmentFilter
                        v-model="selectedDepartmentIds"
                        :departments="departments"
                    />
                </div>
            </div>

            <!-- Headcount KPI Cards -->
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                data-test="headcount-cards"
            >
                <MetricCard
                    title="Total Employees"
                    :value="headcount.total"
                    :subtitle="`${headcount.active} active`"
                    :background-color="primaryColor"
                    is-highlighted
                >
                    <template #icon>
                        <Users class="h-6 w-6" />
                    </template>
                </MetricCard>

                <MetricCard
                    title="New Hires"
                    :value="headcount.newHires"
                    subtitle="This month"
                >
                    <template #icon>
                        <UserPlus class="h-6 w-6" />
                    </template>
                </MetricCard>

                <MetricCard
                    title="Separations"
                    :value="headcount.separations"
                    subtitle="This month"
                >
                    <template #icon>
                        <UserMinus class="h-6 w-6" />
                    </template>
                </MetricCard>

                <MetricCard
                    title="Attendance Rate"
                    :value="attendance ? `${attendance.attendanceRate}%` : '...'"
                    :subtitle="attendance ? `${attendance.lateCount} late arrivals` : 'Loading...'"
                >
                    <template #icon>
                        <Activity class="h-6 w-6" />
                    </template>
                </MetricCard>
            </div>

            <!-- Attendance Section -->
            <AttendanceSection
                :metrics="attendance"
                :trend-data="attendanceTrend"
                :by-department="attendanceByDepartment"
            />

            <!-- Leave & Compensation Row -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <LeaveSection
                    :metrics="leave"
                    :type-breakdown="leaveTypeBreakdown"
                />

                <CompensationSection
                    :metrics="compensation"
                    :salary-distribution="salaryDistribution"
                    :payroll-trend="payrollTrend"
                />
            </div>

            <!-- Recruitment & Performance Row -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <RecruitmentSection
                    :metrics="recruitment"
                    :pipeline="recruitmentPipeline"
                />

                <PerformanceSection
                    :metrics="performance"
                    :rating-distribution="ratingDistribution"
                />
            </div>
        </div>
    </TenantLayout>
</template>
