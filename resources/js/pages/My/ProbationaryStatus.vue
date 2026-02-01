<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Circle, Clock } from 'lucide-vue-next';
import { computed } from 'vue';

interface Evaluation {
    id: number;
    milestone: string;
    milestone_label: string;
    status: string;
    status_label: string;
    status_color: string;
    milestone_date: string;
    due_date: string;
    overall_rating: number | null;
    strengths: string | null;
    areas_for_improvement: string | null;
    recommendation: string | null;
    recommendation_label: string | null;
    approved_at: string | null;
}

interface Props {
    employee: {
        id: number;
        full_name: string;
        employment_type: string;
        employment_type_label: string;
        hire_date: string;
        regularization_date: string | null;
        position: string | null;
        department: string | null;
    };
    evaluations: Evaluation[];
    probation_end_date: string | null;
    days_remaining: number | null;
    probation_progress: number;
}

const props = defineProps<Props>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Dashboard', href: '/my/dashboard' },
    { title: 'Probationary Status', href: '/my/probationary-status' },
];

const isRegularized = computed(() => props.employee.employment_type !== 'probationary');

const thirdMonthEvaluation = computed(() => {
    return props.evaluations.find((e) => e.milestone === 'third_month');
});

const fifthMonthEvaluation = computed(() => {
    return props.evaluations.find((e) => e.milestone === 'fifth_month');
});

function formatDate(dateString: string | null): string {
    if (!dateString) return 'Not set';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function getStatusBadgeClass(status: string): string {
    const classes: Record<string, string> = {
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        draft: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        submitted: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
        hr_review: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        approved: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        revision_requested: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
    };
    return classes[status] || 'bg-slate-100 text-slate-800 dark:bg-slate-900/30 dark:text-slate-300';
}

function getMilestoneIcon(evaluation: Evaluation | undefined, isPast: boolean) {
    if (!evaluation) {
        return isPast ? Circle : Circle;
    }
    if (evaluation.status === 'approved') {
        return CheckCircle2;
    }
    return Clock;
}

function getMilestoneIconClass(evaluation: Evaluation | undefined): string {
    if (!evaluation) {
        return 'text-slate-300 dark:text-slate-600';
    }
    if (evaluation.status === 'approved') {
        return 'text-green-500';
    }
    if (['submitted', 'hr_review', 'draft'].includes(evaluation.status)) {
        return 'text-blue-500';
    }
    return 'text-amber-500';
}
</script>

<template>
    <Head :title="`Probationary Status - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    My Probationary Status
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Track your probationary period progress and evaluation results.
                </p>
            </div>

            <!-- Status Overview Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Employment Status</CardTitle>
                            <CardDescription>Your current employment status and probation progress</CardDescription>
                        </div>
                        <Badge
                            :class="
                                isRegularized
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                            "
                        >
                            {{ employee.employment_type_label }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Employee</p>
                                <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                    {{ employee.full_name }}
                                </p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ employee.position || 'No Position' }}
                                    <span v-if="employee.department"> · {{ employee.department }}</span>
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Hire Date</p>
                                    <p class="text-slate-900 dark:text-slate-100">
                                        {{ formatDate(employee.hire_date) }}
                                    </p>
                                </div>
                                <div v-if="isRegularized">
                                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Regularization Date
                                    </p>
                                    <p class="text-slate-900 dark:text-slate-100">
                                        {{ formatDate(employee.regularization_date) }}
                                    </p>
                                </div>
                                <div v-else-if="probation_end_date">
                                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Target End Date
                                    </p>
                                    <p class="text-slate-900 dark:text-slate-100">
                                        {{ formatDate(probation_end_date) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Section (only for probationary) -->
                        <div v-if="!isRegularized" class="flex flex-col justify-center">
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="text-slate-500 dark:text-slate-400">Probation Progress</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ Math.round(probation_progress) }}%
                                </span>
                            </div>
                            <Progress :model-value="probation_progress" class="h-3" />
                            <p v-if="days_remaining !== null" class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                <span v-if="days_remaining > 0">{{ days_remaining }} days remaining</span>
                                <span v-else class="text-amber-600">Probation period ended</span>
                            </p>
                        </div>

                        <!-- Regularized Success Message -->
                        <div
                            v-else
                            class="flex flex-col items-center justify-center rounded-lg bg-green-50 p-6 dark:bg-green-900/20"
                        >
                            <CheckCircle2 class="h-12 w-12 text-green-500" />
                            <p class="mt-2 font-medium text-green-700 dark:text-green-300">
                                Successfully Regularized
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Timeline -->
            <Card>
                <CardHeader>
                    <CardTitle>Evaluation Timeline</CardTitle>
                    <CardDescription>
                        Your probationary evaluation milestones and progress
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="relative">
                        <!-- Timeline Line -->
                        <div
                            class="absolute left-4 top-0 h-full w-0.5 bg-slate-200 dark:bg-slate-700"
                            aria-hidden="true"
                        ></div>

                        <div class="space-y-8">
                            <!-- 3rd Month Milestone -->
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white dark:bg-slate-900">
                                    <component
                                        :is="getMilestoneIcon(thirdMonthEvaluation, true)"
                                        :class="['h-6 w-6', getMilestoneIconClass(thirdMonthEvaluation)]"
                                    />
                                </div>
                                <div class="flex-1 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-medium text-slate-900 dark:text-slate-100">
                                            3rd Month Evaluation
                                        </h3>
                                        <Badge
                                            v-if="thirdMonthEvaluation"
                                            :class="getStatusBadgeClass(thirdMonthEvaluation.status)"
                                        >
                                            {{ thirdMonthEvaluation.status_label }}
                                        </Badge>
                                        <Badge v-else class="bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                            Upcoming
                                        </Badge>
                                    </div>
                                    <p v-if="thirdMonthEvaluation" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        Due: {{ formatDate(thirdMonthEvaluation.due_date) }}
                                    </p>

                                    <!-- Evaluation Results (if approved) -->
                                    <div
                                        v-if="thirdMonthEvaluation?.status === 'approved'"
                                        class="mt-4 space-y-3 border-t border-slate-200 pt-4 dark:border-slate-700"
                                    >
                                        <div v-if="thirdMonthEvaluation.overall_rating" class="flex items-center gap-2">
                                            <span class="text-sm text-slate-500 dark:text-slate-400">Overall Rating:</span>
                                            <span class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ thirdMonthEvaluation.overall_rating.toFixed(2) }} / 5.00
                                            </span>
                                        </div>
                                        <div v-if="thirdMonthEvaluation.strengths">
                                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Strengths:</p>
                                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                                                {{ thirdMonthEvaluation.strengths }}
                                            </p>
                                        </div>
                                        <div v-if="thirdMonthEvaluation.areas_for_improvement">
                                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                                Areas for Improvement:
                                            </p>
                                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                                                {{ thirdMonthEvaluation.areas_for_improvement }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 5th Month Milestone -->
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white dark:bg-slate-900">
                                    <component
                                        :is="getMilestoneIcon(fifthMonthEvaluation, !!thirdMonthEvaluation?.status)"
                                        :class="['h-6 w-6', getMilestoneIconClass(fifthMonthEvaluation)]"
                                    />
                                </div>
                                <div class="flex-1 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-medium text-slate-900 dark:text-slate-100">
                                            5th Month Evaluation (Final)
                                        </h3>
                                        <Badge
                                            v-if="fifthMonthEvaluation"
                                            :class="getStatusBadgeClass(fifthMonthEvaluation.status)"
                                        >
                                            {{ fifthMonthEvaluation.status_label }}
                                        </Badge>
                                        <Badge v-else class="bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                            Upcoming
                                        </Badge>
                                    </div>
                                    <p v-if="fifthMonthEvaluation" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        Due: {{ formatDate(fifthMonthEvaluation.due_date) }}
                                    </p>

                                    <!-- Evaluation Results (if approved) -->
                                    <div
                                        v-if="fifthMonthEvaluation?.status === 'approved'"
                                        class="mt-4 space-y-3 border-t border-slate-200 pt-4 dark:border-slate-700"
                                    >
                                        <div v-if="fifthMonthEvaluation.overall_rating" class="flex items-center gap-2">
                                            <span class="text-sm text-slate-500 dark:text-slate-400">Overall Rating:</span>
                                            <span class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ fifthMonthEvaluation.overall_rating.toFixed(2) }} / 5.00
                                            </span>
                                        </div>
                                        <div v-if="fifthMonthEvaluation.recommendation_label" class="flex items-center gap-2">
                                            <span class="text-sm text-slate-500 dark:text-slate-400">Recommendation:</span>
                                            <Badge
                                                :class="
                                                    fifthMonthEvaluation.recommendation === 'recommend' ||
                                                    fifthMonthEvaluation.recommendation === 'recommend_with_conditions'
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                                                "
                                            >
                                                {{ fifthMonthEvaluation.recommendation_label }}
                                            </Badge>
                                        </div>
                                        <div v-if="fifthMonthEvaluation.strengths">
                                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Strengths:</p>
                                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                                                {{ fifthMonthEvaluation.strengths }}
                                            </p>
                                        </div>
                                        <div v-if="fifthMonthEvaluation.areas_for_improvement">
                                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                                Areas for Improvement:
                                            </p>
                                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                                                {{ fifthMonthEvaluation.areas_for_improvement }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Regularization Milestone -->
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white dark:bg-slate-900">
                                    <CheckCircle2
                                        v-if="isRegularized"
                                        class="h-6 w-6 text-green-500"
                                    />
                                    <Circle
                                        v-else
                                        class="h-6 w-6 text-slate-300 dark:text-slate-600"
                                    />
                                </div>
                                <div
                                    class="flex-1 rounded-lg border p-4"
                                    :class="
                                        isRegularized
                                            ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20'
                                            : 'border-slate-200 dark:border-slate-700'
                                    "
                                >
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-medium text-slate-900 dark:text-slate-100">
                                            Regularization
                                        </h3>
                                        <Badge
                                            v-if="isRegularized"
                                            class="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300"
                                        >
                                            Completed
                                        </Badge>
                                        <Badge
                                            v-else
                                            class="bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                                        >
                                            Pending
                                        </Badge>
                                    </div>
                                    <p
                                        v-if="isRegularized"
                                        class="mt-1 text-sm text-green-700 dark:text-green-300"
                                    >
                                        Regularized on {{ formatDate(employee.regularization_date) }}
                                    </p>
                                    <p v-else class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        Pending completion of final evaluation
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
