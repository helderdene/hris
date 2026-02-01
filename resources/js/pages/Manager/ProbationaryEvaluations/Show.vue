<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface Employee {
    id: number;
    employee_number: string;
    full_name: string;
    department: string | null;
    position: string | null;
    hire_date: string;
    employment_type: string;
    employment_type_label: string;
}

interface CriteriaRating {
    criteria_id: number;
    name: string;
    description: string | null;
    weight: number;
    min_rating: number;
    max_rating: number;
    is_required: boolean;
    rating: number | null;
    comments: string | null;
}

interface PreviousEvaluation {
    id: number;
    milestone: string;
    milestone_label: string;
    overall_rating: number | null;
    strengths: string | null;
    areas_for_improvement: string | null;
    manager_comments: string | null;
    criteria_ratings: CriteriaRating[];
    status: string;
    approved_at: string | null;
}

interface Evaluation {
    id: number;
    employee_id: number;
    employee: Employee;
    evaluator_id: number;
    evaluator_name: string;
    evaluator_position: string | null;
    milestone: string;
    milestone_label: string;
    milestone_short_label: string;
    milestone_color: string;
    milestone_date: string;
    due_date: string;
    status: string;
    status_label: string;
    status_color: string;
    criteria_ratings: CriteriaRating[];
    overall_rating: number | null;
    strengths: string | null;
    areas_for_improvement: string | null;
    manager_comments: string | null;
    recommendation: string | null;
    recommendation_label: string | null;
    recommendation_conditions: string | null;
    extension_months: number | null;
    recommendation_reason: string | null;
    previous_evaluation: PreviousEvaluation | null;
    can_be_edited: boolean;
    is_overdue: boolean;
    is_final_evaluation: boolean;
    requires_recommendation: boolean;
}

interface RecommendationOption {
    value: string;
    label: string;
    shortLabel: string;
    color: string;
    description: string;
    requiresConditions: boolean;
    requiresExtensionMonths: boolean;
    requiresReason: boolean;
}

const props = defineProps<{
    evaluation: Evaluation;
    recommendations: RecommendationOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'Probationary Evaluations', href: '/manager/probationary-evaluations' },
    { title: props.evaluation.employee.full_name, href: '#' },
];

const form = useForm({
    criteria_ratings: props.evaluation.criteria_ratings,
    overall_rating: props.evaluation.overall_rating,
    strengths: props.evaluation.strengths || '',
    areas_for_improvement: props.evaluation.areas_for_improvement || '',
    manager_comments: props.evaluation.manager_comments || '',
    recommendation: props.evaluation.recommendation || '',
    recommendation_conditions: props.evaluation.recommendation_conditions || '',
    extension_months: props.evaluation.extension_months || null,
    recommendation_reason: props.evaluation.recommendation_reason || '',
});

const selectedRecommendation = computed(() => {
    return props.recommendations.find(r => r.value === form.recommendation);
});

// Calculate overall rating from criteria
watch(
    () => form.criteria_ratings,
    (ratings) => {
        if (!ratings || ratings.length === 0) return;

        let totalWeight = 0;
        let weightedSum = 0;

        for (const rating of ratings) {
            if (rating.rating !== null) {
                totalWeight += rating.weight;
                weightedSum += rating.rating * rating.weight;
            }
        }

        if (totalWeight > 0) {
            form.overall_rating = Math.round((weightedSum / totalWeight) * 100) / 100;
        }
    },
    { deep: true }
);

function saveAsDraft(): void {
    form.put(`/manager/probationary-evaluations/${props.evaluation.id}`, {
        preserveScroll: true,
    });
}

function submitForReview(): void {
    if (!confirm('Are you sure you want to submit this evaluation for HR review? You will not be able to edit it after submission.')) {
        return;
    }

    router.post(`/manager/probationary-evaluations/${props.evaluation.id}/submit`, form.data(), {
        preserveScroll: true,
    });
}

function formatDate(dateString: string): string {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function getStatusBadgeClasses(color: string): string {
    switch (color) {
        case 'slate':
            return 'bg-slate-100 text-slate-800 dark:bg-slate-700/50 dark:text-slate-300';
        case 'blue':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'amber':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'purple':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
        case 'orange':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300';
        case 'green':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'red':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}
</script>

<template>
    <Head :title="`Evaluate ${evaluation.employee.full_name} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <div class="flex flex-col gap-6">
                <!-- Page Header -->
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ evaluation.milestone_label }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Evaluate {{ evaluation.employee.full_name }}'s performance
                        </p>
                    </div>
                    <span
                        class="inline-flex items-center rounded-md px-3 py-1 text-sm font-medium"
                        :class="getStatusBadgeClasses(evaluation.status_color)"
                    >
                        {{ evaluation.status_label }}
                    </span>
                </div>

                <!-- Employee Info Card -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Employee Information
                    </h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">Name</div>
                            <div class="font-medium text-slate-900 dark:text-slate-100">
                                {{ evaluation.employee.full_name }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">Employee Number</div>
                            <div class="font-medium text-slate-900 dark:text-slate-100">
                                {{ evaluation.employee.employee_number }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">Department</div>
                            <div class="font-medium text-slate-900 dark:text-slate-100">
                                {{ evaluation.employee.department || 'Not assigned' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">Position</div>
                            <div class="font-medium text-slate-900 dark:text-slate-100">
                                {{ evaluation.employee.position || 'Not assigned' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">Hire Date</div>
                            <div class="font-medium text-slate-900 dark:text-slate-100">
                                {{ formatDate(evaluation.employee.hire_date) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">Milestone Date</div>
                            <div class="font-medium text-slate-900 dark:text-slate-100">
                                {{ formatDate(evaluation.milestone_date) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">Due Date</div>
                            <div
                                class="font-medium"
                                :class="evaluation.is_overdue ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-slate-100'"
                            >
                                {{ formatDate(evaluation.due_date) }}
                                <span v-if="evaluation.is_overdue" class="text-sm">(Overdue)</span>
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">Employment Type</div>
                            <div class="font-medium text-slate-900 dark:text-slate-100">
                                {{ evaluation.employee.employment_type_label }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Previous Evaluation (for 5th month) -->
                <div
                    v-if="evaluation.previous_evaluation"
                    class="rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Previous Evaluation: {{ evaluation.previous_evaluation.milestone_label }}
                    </h2>
                    <div class="mt-4 space-y-4">
                        <div class="flex items-center gap-4">
                            <div>
                                <span class="text-sm text-slate-500 dark:text-slate-400">Overall Rating:</span>
                                <span class="ml-2 font-semibold text-slate-900 dark:text-slate-100">
                                    {{ evaluation.previous_evaluation.overall_rating?.toFixed(2) || 'N/A' }} / 5
                                </span>
                            </div>
                        </div>
                        <div v-if="evaluation.previous_evaluation.strengths">
                            <div class="text-sm font-medium text-slate-700 dark:text-slate-300">Strengths:</div>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                {{ evaluation.previous_evaluation.strengths }}
                            </p>
                        </div>
                        <div v-if="evaluation.previous_evaluation.areas_for_improvement">
                            <div class="text-sm font-medium text-slate-700 dark:text-slate-300">Areas for Improvement:</div>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                {{ evaluation.previous_evaluation.areas_for_improvement }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Criteria Ratings -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Evaluation Criteria
                    </h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Rate each criterion from 1 (Poor) to 5 (Excellent)
                    </p>

                    <div class="mt-6 space-y-6">
                        <div
                            v-for="(criteria, index) in form.criteria_ratings"
                            :key="criteria.criteria_id"
                            class="rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/30"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <Label class="text-base font-medium text-slate-900 dark:text-slate-100">
                                        {{ criteria.name }}
                                        <span v-if="criteria.is_required" class="text-red-500">*</span>
                                    </Label>
                                    <p v-if="criteria.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        {{ criteria.description }}
                                    </p>
                                </div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    Weight: {{ criteria.weight }}
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <button
                                    v-for="rating in [1, 2, 3, 4, 5]"
                                    :key="rating"
                                    type="button"
                                    :disabled="!evaluation.can_be_edited"
                                    class="flex h-10 w-10 items-center justify-center rounded-lg border text-sm font-medium transition-colors"
                                    :class="[
                                        criteria.rating === rating
                                            ? 'border-blue-500 bg-blue-500 text-white'
                                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700',
                                        !evaluation.can_be_edited && 'cursor-not-allowed opacity-50',
                                    ]"
                                    @click="form.criteria_ratings[index].rating = rating"
                                >
                                    {{ rating }}
                                </button>
                            </div>

                            <div class="mt-4">
                                <Textarea
                                    v-model="form.criteria_ratings[index].comments"
                                    :disabled="!evaluation.can_be_edited"
                                    placeholder="Add comments for this criterion (optional)"
                                    class="min-h-[80px]"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Overall Rating Display -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Overall Rating
                    </h2>
                    <div class="mt-4">
                        <div class="flex items-center gap-4">
                            <div class="text-4xl font-bold text-slate-900 dark:text-slate-100">
                                {{ form.overall_rating?.toFixed(2) || '-' }}
                            </div>
                            <div class="text-xl text-slate-500 dark:text-slate-400">/ 5.00</div>
                        </div>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Calculated automatically from weighted criteria ratings
                        </p>
                    </div>
                </div>

                <!-- Narrative Feedback -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Narrative Feedback
                    </h2>

                    <div class="mt-6 space-y-6">
                        <div>
                            <Label for="strengths" class="text-sm font-medium">Strengths</Label>
                            <Textarea
                                id="strengths"
                                v-model="form.strengths"
                                :disabled="!evaluation.can_be_edited"
                                placeholder="Describe the employee's key strengths and accomplishments..."
                                class="mt-2 min-h-[120px]"
                            />
                        </div>

                        <div>
                            <Label for="areas_for_improvement" class="text-sm font-medium">Areas for Improvement</Label>
                            <Textarea
                                id="areas_for_improvement"
                                v-model="form.areas_for_improvement"
                                :disabled="!evaluation.can_be_edited"
                                placeholder="Identify areas where the employee can improve..."
                                class="mt-2 min-h-[120px]"
                            />
                        </div>

                        <div>
                            <Label for="manager_comments" class="text-sm font-medium">Additional Comments</Label>
                            <Textarea
                                id="manager_comments"
                                v-model="form.manager_comments"
                                :disabled="!evaluation.can_be_edited"
                                placeholder="Any additional comments or observations..."
                                class="mt-2 min-h-[120px]"
                            />
                        </div>
                    </div>
                </div>

                <!-- Recommendation (only for final evaluation) -->
                <div
                    v-if="evaluation.requires_recommendation"
                    class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                >
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Regularization Recommendation
                    </h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Based on your evaluation, provide your recommendation for the employee's status.
                    </p>

                    <div class="mt-6 space-y-6">
                        <div>
                            <Label for="recommendation" class="text-sm font-medium">
                                Recommendation <span class="text-red-500">*</span>
                            </Label>
                            <Select v-model="form.recommendation" :disabled="!evaluation.can_be_edited">
                                <SelectTrigger class="mt-2 w-full">
                                    <SelectValue placeholder="Select recommendation" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="rec in recommendations"
                                        :key="rec.value"
                                        :value="rec.value"
                                    >
                                        {{ rec.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="selectedRecommendation" class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                {{ selectedRecommendation.description }}
                            </p>
                        </div>

                        <!-- Conditional fields based on recommendation -->
                        <div v-if="selectedRecommendation?.requiresConditions">
                            <Label for="recommendation_conditions" class="text-sm font-medium">
                                Conditions for Regularization <span class="text-red-500">*</span>
                            </Label>
                            <Textarea
                                id="recommendation_conditions"
                                v-model="form.recommendation_conditions"
                                :disabled="!evaluation.can_be_edited"
                                placeholder="Specify the conditions the employee must meet..."
                                class="mt-2 min-h-[120px]"
                            />
                        </div>

                        <div v-if="selectedRecommendation?.requiresExtensionMonths">
                            <Label for="extension_months" class="text-sm font-medium">
                                Extension Period (Months) <span class="text-red-500">*</span>
                            </Label>
                            <Select
                                v-model="form.extension_months"
                                :disabled="!evaluation.can_be_edited"
                            >
                                <SelectTrigger class="mt-2 w-full max-w-xs">
                                    <SelectValue placeholder="Select extension period" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="1">1 Month</SelectItem>
                                    <SelectItem :value="2">2 Months</SelectItem>
                                    <SelectItem :value="3">3 Months</SelectItem>
                                    <SelectItem :value="4">4 Months</SelectItem>
                                    <SelectItem :value="5">5 Months</SelectItem>
                                    <SelectItem :value="6">6 Months</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div v-if="selectedRecommendation?.requiresReason">
                            <Label for="recommendation_reason" class="text-sm font-medium">
                                Reason for Not Recommending <span class="text-red-500">*</span>
                            </Label>
                            <Textarea
                                id="recommendation_reason"
                                v-model="form.recommendation_reason"
                                :disabled="!evaluation.can_be_edited"
                                placeholder="Provide detailed reasons for not recommending regularization..."
                                class="mt-2 min-h-[120px]"
                            />
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div
                    v-if="evaluation.can_be_edited"
                    class="flex items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-slate-700"
                >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="saveAsDraft"
                    >
                        Save as Draft
                    </Button>
                    <Button
                        type="button"
                        :disabled="form.processing"
                        :style="{ backgroundColor: primaryColor }"
                        class="text-white hover:opacity-90"
                        @click="submitForReview"
                    >
                        Submit for HR Review
                    </Button>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
