<script setup lang="ts">
/**
 * Performance Analytics Dashboard
 *
 * Comprehensive performance metrics including evaluations,
 * ratings, development plans, goals, and KPIs.
 */
import {
    DateRangeFilter,
    DepartmentFilter,
    MetricCard,
} from '@/components/analytics';
import {
    DepartmentBreakdownSection,
    DevelopmentPlanSection,
    EvaluationCompletionSection,
    GoalAchievementSection,
    KpiAchievementSection,
    RatingDistributionSection,
    RatingTrendSection,
} from '@/components/performance-analytics';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    Award,
    BookOpen,
    CheckCircle,
    Star,
    Target,
    TrendingUp,
} from 'lucide-vue-next';
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
    totalEvaluations: number;
    completedEvaluations: number;
    averageRating: number | null;
    activeDevelopmentPlans: number;
    activeGoals: number;
    goalsAchieved: number;
}

interface StatusItem {
    status: string;
    label: string;
    count: number;
}

interface CycleItem {
    cycle: string;
    total: number;
    completed: number;
    rate: number;
}

interface EvaluationCompletionMetrics {
    byStatus: StatusItem[];
    byCycle: CycleItem[];
    overallRate: number;
}

interface RatingDistributionItem {
    rating: string;
    count: number;
    label: string;
    percentage: number;
}

interface RatingTrendSeries {
    rating: string;
    label: string;
    data: number[];
}

interface RatingTrendsData {
    cycles: string[];
    series: RatingTrendSeries[];
}

interface DevelopmentPlanMetrics {
    byStatus: StatusItem[];
    completionRate: number;
    overdueCount: number;
    averageProgress: number;
    totalPlans: number;
}

interface PriorityItem {
    priority: string;
    count: number;
    achieved: number;
}

interface GoalAchievementMetrics {
    byStatus: StatusItem[];
    byPriority: PriorityItem[];
    achievementRate: number;
    averageProgress: number;
    totalGoals: number;
    overdueCount: number;
}

interface DistributionItem {
    range: string;
    count: number;
}

interface KpiAchievementMetrics {
    byStatus: StatusItem[];
    achievementDistribution: DistributionItem[];
    averageAchievement: number;
    totalKpis: number;
    overachievingCount: number;
}

interface DepartmentMetrics {
    department: string;
    departmentId: number;
    evaluations: number;
    completedEvaluations: number;
    averageRating: number | null;
    developmentPlans: number;
    goals: number;
    goalsAchieved: number;
}

interface Props {
    filters: Filters;
    departments: Department[];
    summary: SummaryMetrics;
    evaluationCompletion?: EvaluationCompletionMetrics;
    ratingDistribution?: RatingDistributionItem[];
    ratingTrends?: RatingTrendsData;
    developmentPlans?: DevelopmentPlanMetrics;
    goalAchievement?: GoalAchievementMetrics;
    kpiAchievement?: KpiAchievementMetrics;
    byDepartment?: DepartmentMetrics[];
}

const props = defineProps<Props>();

const { tenantName, primaryColor } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Performance Analytics', href: '/performance/analytics' },
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

    router.get('/performance/analytics', params, {
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
    <Head :title="`Performance Analytics - ${tenantName}`" />

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
                        Performance Analytics
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Comprehensive performance metrics and insights
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

            <!-- Summary KPI Cards -->
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
                data-test="summary-cards"
            >
                <MetricCard
                    title="Total Evaluations"
                    :value="summary.totalEvaluations"
                    :subtitle="`${summary.completedEvaluations} completed`"
                    :background-color="primaryColor"
                    is-highlighted
                >
                    <template #icon>
                        <CheckCircle class="h-6 w-6" />
                    </template>
                </MetricCard>

                <MetricCard
                    title="Average Rating"
                    :value="summary.averageRating !== null ? summary.averageRating.toFixed(1) : '-'"
                    subtitle="Final score"
                >
                    <template #icon>
                        <Star class="h-6 w-6" />
                    </template>
                </MetricCard>

                <MetricCard
                    title="Active Dev. Plans"
                    :value="summary.activeDevelopmentPlans"
                    subtitle="In progress"
                >
                    <template #icon>
                        <BookOpen class="h-6 w-6" />
                    </template>
                </MetricCard>

                <MetricCard
                    title="Active Goals"
                    :value="summary.activeGoals"
                    subtitle="Being tracked"
                >
                    <template #icon>
                        <Target class="h-6 w-6" />
                    </template>
                </MetricCard>

                <MetricCard
                    title="Goals Achieved"
                    :value="summary.goalsAchieved"
                    subtitle="In selected period"
                >
                    <template #icon>
                        <Award class="h-6 w-6" />
                    </template>
                </MetricCard>

                <MetricCard
                    title="Completion Rate"
                    :value="summary.totalEvaluations > 0 ? `${Math.round((summary.completedEvaluations / summary.totalEvaluations) * 100)}%` : '-'"
                    subtitle="Evaluations"
                >
                    <template #icon>
                        <TrendingUp class="h-6 w-6" />
                    </template>
                </MetricCard>
            </div>

            <!-- Evaluation & Rating Row -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <EvaluationCompletionSection :data="evaluationCompletion" />
                <RatingDistributionSection :data="ratingDistribution" />
            </div>

            <!-- Rating Trends (Full Width) -->
            <RatingTrendSection :data="ratingTrends" />

            <!-- Development Plans & Goals Row -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <DevelopmentPlanSection :data="developmentPlans" />
                <GoalAchievementSection :data="goalAchievement" />
            </div>

            <!-- KPI Achievement (Full Width) -->
            <KpiAchievementSection :data="kpiAchievement" />

            <!-- Department Breakdown (Full Width) -->
            <DepartmentBreakdownSection :data="byDepartment" />
        </div>
    </TenantLayout>
</template>
