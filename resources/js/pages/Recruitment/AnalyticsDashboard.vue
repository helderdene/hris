<script setup lang="ts">
import {
    DateRangeFilter,
    DepartmentFilter,
    MetricCard,
} from '@/components/analytics';
import {
    HiringFunnelSection,
    HiringTrendSection,
    InterviewerPerformanceSection,
    OfferAnalyticsSection,
    RequisitionAnalyticsSection,
    SourceEffectivenessSection,
    TimeToFillSection,
} from '@/components/recruitment-analytics';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Briefcase, Clock, FileText, ThumbsUp, Users } from 'lucide-vue-next';
import { ref, watch } from 'vue';

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

interface SummaryMetrics {
    activeRequisitions: number;
    openPositions: number;
    totalApplications: number;
    avgTimeToFill: number | null;
    offerAcceptanceRate: number;
}

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

interface StageItem {
    stage: string;
    label: string;
    avgDays: number | null;
}

interface TimeToFillMetrics {
    byStage: StageItem[];
    bottleneck: string | null;
    totalAvgDays: number | null;
}

interface TimeToFillTrendItem {
    month: string;
    avgDays: number;
}

interface TimeToFillByDepartmentItem {
    department: string;
    departmentId: number;
    avgDays: number;
    count: number;
}

interface SourceItem {
    source: string;
    label: string;
    applications: number;
    hires: number;
    hireRate: number;
    color: string;
}

interface SourceQualityItem {
    source: string;
    label: string;
    screeningPassRate: number;
    interviewPassRate: number;
    offerAcceptRate: number;
}

interface OfferMetrics {
    total: number;
    accepted: number;
    declined: number;
    pending: number;
    acceptanceRate: number;
    avgResponseDays: number | null;
}

interface OfferTrendItem {
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

interface InterviewMetrics {
    total: number;
    completed: number;
    cancelled: number;
    noShows: number;
    completionRate: number;
    avgDurationMinutes: number | null;
}

interface LeaderboardItem {
    employeeId: number;
    name: string;
    totalInterviews: number;
    completedInterviews: number;
    avgRating: number | null;
    passThroughRate: number;
}

interface SchedulingMetrics {
    avgDaysToSchedule: number | null;
    scheduledThisWeek: number;
    scheduledNextWeek: number;
    rescheduledCount: number;
}

interface VelocityItem {
    month: string;
    applications: number;
    hires: number;
    velocity: number;
}

interface SeasonalItem {
    month: number;
    monthName: string;
    avgApplications: number;
    avgHires: number;
}

interface Props {
    filters: Filters;
    departments: Department[];
    summary: SummaryMetrics;
    funnelMetrics?: FunnelItem[];
    dropoutAnalysis?: DropoutItem[];
    timeToFillMetrics?: TimeToFillMetrics;
    timeToFillTrend?: TimeToFillTrendItem[];
    timeToFillByDepartment?: TimeToFillByDepartmentItem[];
    sourceEffectiveness?: SourceItem[];
    sourceQualityMetrics?: SourceQualityItem[];
    offerMetrics?: OfferMetrics;
    offerAcceptanceTrend?: OfferTrendItem[];
    offerDeclineReasons?: DeclineReason[];
    requisitionMetrics?: RequisitionMetrics;
    requisitionsByUrgency?: UrgencyItem[];
    headcountVsHires?: HeadcountItem[];
    interviewMetrics?: InterviewMetrics;
    interviewerLeaderboard?: LeaderboardItem[];
    interviewSchedulingMetrics?: SchedulingMetrics;
    hiringVelocityTrend?: VelocityItem[];
    seasonalPatterns?: SeasonalItem[];
}

const props = defineProps<Props>();

const { tenantName, primaryColor } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/requisitions' },
    { title: 'Analytics', href: '/recruitment/analytics' },
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

    router.get('/recruitment/analytics', params, {
        preserveState: true,
        preserveScroll: true,
    });
}

// Watch for filter changes and apply
watch([selectedStartDate, selectedEndDate, selectedDepartmentIds], () => {
    applyFilters();
}, { deep: true });
</script>

<template>
    <Head :title="`Recruitment Analytics - ${tenantName}`" />

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
                        Recruitment Analytics
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Comprehensive hiring metrics and insights
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

            <!-- KPI Cards -->
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5"
                data-test="summary-cards"
            >
                <MetricCard
                    title="Active Requisitions"
                    :value="summary.activeRequisitions"
                    subtitle="Approved"
                    :background-color="primaryColor"
                    is-highlighted
                >
                    <template #icon>
                        <Briefcase class="h-6 w-6" />
                    </template>
                </MetricCard>

                <MetricCard
                    title="Open Positions"
                    :value="summary.openPositions"
                    subtitle="Published"
                >
                    <template #icon>
                        <FileText class="h-6 w-6" />
                    </template>
                </MetricCard>

                <MetricCard
                    title="Applications"
                    :value="summary.totalApplications"
                    subtitle="In period"
                >
                    <template #icon>
                        <Users class="h-6 w-6" />
                    </template>
                </MetricCard>

                <MetricCard
                    title="Avg Time to Fill"
                    :value="summary.avgTimeToFill !== null ? `${summary.avgTimeToFill}d` : '-'"
                    subtitle="Applied to hired"
                >
                    <template #icon>
                        <Clock class="h-6 w-6" />
                    </template>
                </MetricCard>

                <MetricCard
                    title="Offer Acceptance"
                    :value="`${summary.offerAcceptanceRate}%`"
                    subtitle="Acceptance rate"
                >
                    <template #icon>
                        <ThumbsUp class="h-6 w-6" />
                    </template>
                </MetricCard>
            </div>

            <!-- Hiring Funnel (Full Width) -->
            <HiringFunnelSection
                :funnel-metrics="funnelMetrics"
                :dropout-analysis="dropoutAnalysis"
            />

            <!-- Time-to-Fill & Source Effectiveness Row -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <TimeToFillSection
                    :metrics="timeToFillMetrics"
                    :trend-data="timeToFillTrend"
                    :by-department="timeToFillByDepartment"
                />

                <SourceEffectivenessSection
                    :source-effectiveness="sourceEffectiveness"
                    :source-quality-metrics="sourceQualityMetrics"
                />
            </div>

            <!-- Offer & Requisition Analytics Row -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <OfferAnalyticsSection
                    :metrics="offerMetrics"
                    :acceptance-trend="offerAcceptanceTrend"
                    :decline-reasons="offerDeclineReasons"
                />

                <RequisitionAnalyticsSection
                    :metrics="requisitionMetrics"
                    :by-urgency="requisitionsByUrgency"
                    :headcount-vs-hires="headcountVsHires"
                />
            </div>

            <!-- Interviewer Performance (Full Width) -->
            <InterviewerPerformanceSection
                :metrics="interviewMetrics"
                :leaderboard="interviewerLeaderboard"
                :scheduling-metrics="interviewSchedulingMetrics"
            />

            <!-- Hiring Trends (Full Width) -->
            <HiringTrendSection
                :velocity-trend="hiringVelocityTrend"
                :seasonal-patterns="seasonalPatterns"
            />
        </div>
    </TenantLayout>
</template>
