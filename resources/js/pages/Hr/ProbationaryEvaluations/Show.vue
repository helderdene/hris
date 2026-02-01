<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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

interface Evaluator {
    id: number;
    employee_number: string;
    full_name: string;
    department: string | null;
    position: string | null;
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

interface Approval {
    id: number;
    approval_level: number;
    approver_type: string;
    approver_name: string | null;
    approver_position: string | null;
    decision: string;
    decision_label: string;
    decision_color: string;
    remarks: string | null;
    decided_at: string | null;
}

interface Evaluation {
    id: number;
    employee_id: number;
    employee: Employee;
    evaluator_id: number;
    evaluator_name: string;
    evaluator_position: string | null;
    evaluator: Evaluator;
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
    recommendation_short_label: string | null;
    recommendation_color: string | null;
    recommendation_conditions: string | null;
    extension_months: number | null;
    recommendation_reason: string | null;
    previous_evaluation: PreviousEvaluation | null;
    approvals: Approval[];
    submitted_at: string | null;
    approved_at: string | null;
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
}

const props = defineProps<{
    evaluation: Evaluation;
    recommendations: RecommendationOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR Management', href: '/employees' },
    { title: 'Probationary Evaluations', href: '/hr/probationary-evaluations' },
    { title: props.evaluation.employee.full_name, href: '#' },
];

// Dialog state
const isApproveDialogOpen = ref(false);
const isRejectDialogOpen = ref(false);
const isRevisionDialogOpen = ref(false);

const approveForm = useForm({
    remarks: '',
});

const rejectForm = useForm({
    reason: '',
});

const revisionForm = useForm({
    reason: '',
});

const canTakeAction = ['submitted', 'hr_review'].includes(props.evaluation.status);

function handleApprove(): void {
    approveForm.post(`/hr/probationary-evaluations/${props.evaluation.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => {
            isApproveDialogOpen.value = false;
        },
    });
}

function handleReject(): void {
    rejectForm.post(`/hr/probationary-evaluations/${props.evaluation.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => {
            isRejectDialogOpen.value = false;
        },
    });
}

function handleRequestRevision(): void {
    revisionForm.post(`/hr/probationary-evaluations/${props.evaluation.id}/request-revision`, {
        preserveScroll: true,
        onSuccess: () => {
            isRevisionDialogOpen.value = false;
        },
    });
}

function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatDateTime(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
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

function getRatingLabel(rating: number): string {
    switch (rating) {
        case 1:
            return 'Poor';
        case 2:
            return 'Below Average';
        case 3:
            return 'Average';
        case 4:
            return 'Good';
        case 5:
            return 'Excellent';
        default:
            return '';
    }
}
</script>

<template>
    <Head :title="`Review Evaluation - ${evaluation.employee.full_name} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <div class="flex flex-col gap-6">
                <!-- Page Header -->
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            Review: {{ evaluation.milestone_label }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ evaluation.employee.full_name }}'s probationary evaluation
                        </p>
                    </div>
                    <span
                        class="inline-flex items-center rounded-md px-3 py-1 text-sm font-medium"
                        :class="getStatusBadgeClasses(evaluation.status_color)"
                    >
                        {{ evaluation.status_label }}
                    </span>
                </div>

                <!-- Employee & Evaluator Info -->
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Employee Information
                        </h2>
                        <div class="mt-4 space-y-3">
                            <div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">Name</div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ evaluation.employee.full_name }}
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">Employee #</div>
                                    <div class="text-slate-900 dark:text-slate-100">
                                        {{ evaluation.employee.employee_number }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">Department</div>
                                    <div class="text-slate-900 dark:text-slate-100">
                                        {{ evaluation.employee.department || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">Position</div>
                                    <div class="text-slate-900 dark:text-slate-100">
                                        {{ evaluation.employee.position || 'N/A' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">Hire Date</div>
                                    <div class="text-slate-900 dark:text-slate-100">
                                        {{ formatDate(evaluation.employee.hire_date) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Evaluation Details
                        </h2>
                        <div class="mt-4 space-y-3">
                            <div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">Evaluator</div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ evaluation.evaluator_name }}
                                </div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ evaluation.evaluator_position }}
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">Milestone Date</div>
                                    <div class="text-slate-900 dark:text-slate-100">
                                        {{ formatDate(evaluation.milestone_date) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">Submitted</div>
                                    <div class="text-slate-900 dark:text-slate-100">
                                        {{ formatDateTime(evaluation.submitted_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Previous Evaluation (if exists) -->
                <div
                    v-if="evaluation.previous_evaluation"
                    class="rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Previous Evaluation: {{ evaluation.previous_evaluation.milestone_label }}
                    </h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">Overall Rating</div>
                            <div class="text-xl font-semibold text-slate-900 dark:text-slate-100">
                                {{ evaluation.previous_evaluation.overall_rating?.toFixed(2) || 'N/A' }} / 5
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <div class="text-sm text-slate-500 dark:text-slate-400">Approved</div>
                            <div class="text-slate-900 dark:text-slate-100">
                                {{ formatDate(evaluation.previous_evaluation.approved_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Overall Rating -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Overall Rating
                    </h2>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="text-4xl font-bold text-slate-900 dark:text-slate-100">
                            {{ evaluation.overall_rating?.toFixed(2) || '-' }}
                        </span>
                        <span class="text-xl text-slate-500 dark:text-slate-400">/ 5.00</span>
                    </div>
                </div>

                <!-- Criteria Ratings -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Criteria Ratings
                    </h2>
                    <div class="mt-4 space-y-4">
                        <div
                            v-for="criteria in evaluation.criteria_ratings"
                            :key="criteria.criteria_id"
                            class="rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/30"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ criteria.name }}
                                    </h3>
                                    <p v-if="criteria.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        {{ criteria.description }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                                        {{ criteria.rating || '-' }}
                                    </div>
                                    <div v-if="criteria.rating" class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ getRatingLabel(criteria.rating) }}
                                    </div>
                                </div>
                            </div>
                            <p v-if="criteria.comments" class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                                "{{ criteria.comments }}"
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Narrative Feedback -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Narrative Feedback
                    </h2>
                    <div class="mt-4 space-y-6">
                        <div v-if="evaluation.strengths">
                            <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">Strengths</h3>
                            <p class="mt-2 whitespace-pre-wrap text-slate-600 dark:text-slate-400">
                                {{ evaluation.strengths }}
                            </p>
                        </div>
                        <div v-if="evaluation.areas_for_improvement">
                            <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">Areas for Improvement</h3>
                            <p class="mt-2 whitespace-pre-wrap text-slate-600 dark:text-slate-400">
                                {{ evaluation.areas_for_improvement }}
                            </p>
                        </div>
                        <div v-if="evaluation.manager_comments">
                            <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">Additional Comments</h3>
                            <p class="mt-2 whitespace-pre-wrap text-slate-600 dark:text-slate-400">
                                {{ evaluation.manager_comments }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Recommendation (if final evaluation) -->
                <div
                    v-if="evaluation.recommendation"
                    class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                >
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Regularization Recommendation
                    </h2>
                    <div class="mt-4">
                        <span
                            class="inline-flex items-center rounded-md px-3 py-1 text-sm font-medium"
                            :class="getStatusBadgeClasses(evaluation.recommendation_color || 'slate')"
                        >
                            {{ evaluation.recommendation_label }}
                        </span>

                        <div v-if="evaluation.recommendation_conditions" class="mt-4">
                            <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">Conditions</h3>
                            <p class="mt-2 whitespace-pre-wrap text-slate-600 dark:text-slate-400">
                                {{ evaluation.recommendation_conditions }}
                            </p>
                        </div>

                        <div v-if="evaluation.extension_months" class="mt-4">
                            <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">Extension Period</h3>
                            <p class="mt-2 text-slate-600 dark:text-slate-400">
                                {{ evaluation.extension_months }} month(s)
                            </p>
                        </div>

                        <div v-if="evaluation.recommendation_reason" class="mt-4">
                            <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">Reason</h3>
                            <p class="mt-2 whitespace-pre-wrap text-slate-600 dark:text-slate-400">
                                {{ evaluation.recommendation_reason }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Approval History -->
                <div
                    v-if="evaluation.approvals.length > 0"
                    class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                >
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Approval History
                    </h2>
                    <div class="mt-4 space-y-4">
                        <div
                            v-for="approval in evaluation.approvals"
                            :key="approval.id"
                            class="flex items-start gap-4 rounded-lg border border-slate-100 p-4 dark:border-slate-700"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ approval.approver_name || 'Pending Assignment' }}
                                    </span>
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(approval.decision_color)"
                                    >
                                        {{ approval.decision_label }}
                                    </span>
                                </div>
                                <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ approval.approver_position }} &middot; Level {{ approval.approval_level }}
                                </div>
                                <p v-if="approval.remarks" class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                    "{{ approval.remarks }}"
                                </p>
                            </div>
                            <div v-if="approval.decided_at" class="text-sm text-slate-500 dark:text-slate-400">
                                {{ formatDateTime(approval.decided_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div
                    v-if="canTakeAction"
                    class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-slate-700"
                >
                    <Button
                        variant="outline"
                        @click="isRevisionDialogOpen = true"
                    >
                        Request Revision
                    </Button>
                    <Button
                        variant="destructive"
                        @click="isRejectDialogOpen = true"
                    >
                        Reject
                    </Button>
                    <Button
                        :style="{ backgroundColor: primaryColor }"
                        class="text-white hover:opacity-90"
                        @click="isApproveDialogOpen = true"
                    >
                        Approve
                    </Button>
                </div>
            </div>
        </div>

        <!-- Approve Dialog -->
        <Dialog v-model:open="isApproveDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Approve Evaluation</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to approve this probationary evaluation for {{ evaluation.employee.full_name }}?
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Label for="approve-remarks">Remarks (Optional)</Label>
                    <Textarea
                        id="approve-remarks"
                        v-model="approveForm.remarks"
                        placeholder="Add any remarks..."
                        class="mt-2"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="isApproveDialogOpen = false">
                        Cancel
                    </Button>
                    <Button
                        :disabled="approveForm.processing"
                        :style="{ backgroundColor: primaryColor }"
                        class="text-white hover:opacity-90"
                        @click="handleApprove"
                    >
                        Approve
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Reject Dialog -->
        <Dialog v-model:open="isRejectDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reject Evaluation</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to reject this evaluation? Please provide a reason.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Label for="reject-reason">Reason <span class="text-red-500">*</span></Label>
                    <Textarea
                        id="reject-reason"
                        v-model="rejectForm.reason"
                        placeholder="Explain why this evaluation is being rejected..."
                        class="mt-2"
                        required
                    />
                    <p v-if="rejectForm.errors.reason" class="mt-1 text-sm text-red-600">
                        {{ rejectForm.errors.reason }}
                    </p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="isRejectDialogOpen = false">
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="rejectForm.processing || !rejectForm.reason"
                        @click="handleReject"
                    >
                        Reject
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Request Revision Dialog -->
        <Dialog v-model:open="isRevisionDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Request Revision</DialogTitle>
                    <DialogDescription>
                        Send this evaluation back to the manager for revisions. Please explain what needs to be changed.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Label for="revision-reason">Reason <span class="text-red-500">*</span></Label>
                    <Textarea
                        id="revision-reason"
                        v-model="revisionForm.reason"
                        placeholder="Explain what revisions are needed..."
                        class="mt-2"
                        required
                    />
                    <p v-if="revisionForm.errors.reason" class="mt-1 text-sm text-red-600">
                        {{ revisionForm.errors.reason }}
                    </p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="isRevisionDialogOpen = false">
                        Cancel
                    </Button>
                    <Button
                        :disabled="revisionForm.processing || !revisionForm.reason"
                        @click="handleRequestRevision"
                    >
                        Request Revision
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
