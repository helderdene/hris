<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import GoalAlignmentTree from '@/components/Goals/GoalAlignmentTree.vue';
import GoalCommentThread from '@/components/Goals/GoalCommentThread.vue';
import GoalProgressBar from '@/components/Goals/GoalProgressBar.vue';
import GoalStatusBadge from '@/components/Goals/GoalStatusBadge.vue';
import KeyResultList from '@/components/Goals/KeyResultList.vue';
import MilestoneList from '@/components/Goals/MilestoneList.vue';
import ProgressUpdateModal from '@/components/Goals/ProgressUpdateModal.vue';
import { Badge } from '@/components/ui/badge';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface KeyResult {
    id: number;
    title: string;
    description?: string;
    metric_type: string;
    metric_unit?: string;
    target_value: number;
    starting_value: number;
    current_value: number | null;
    achievement_percentage: number | null;
    weight: number;
    status: string;
    status_label?: string;
}

interface Milestone {
    id: number;
    title: string;
    description?: string;
    due_date?: string;
    is_completed: boolean;
    completed_at?: string;
}

interface Comment {
    id: number;
    user: {
        id: number;
        name: string;
        initials: string;
    };
    comment: string;
    is_private: boolean;
    created_at: string;
    created_at_formatted: string;
}

interface ParentGoal {
    id: number;
    title: string;
    goal_type: string;
    goal_type_label: string;
    owner_name?: string;
    progress_percentage: number;
}

interface ChildGoal {
    id: number;
    title: string;
    goal_type: string;
    goal_type_label: string;
    owner_name?: string;
    progress_percentage: number;
}

interface Goal {
    id: number;
    goal_type: string;
    goal_type_label: string;
    title: string;
    description?: string;
    category?: string;
    visibility: string;
    visibility_label: string;
    priority: string;
    priority_label: string;
    status: string;
    status_label: string;
    approval_status: string;
    approval_status_label: string;
    start_date: string;
    due_date: string;
    progress_percentage: number;
    is_overdue: boolean;
    days_remaining: number;
    owner_notes?: string;
    manager_feedback?: string;
    key_results: KeyResult[];
    milestones: Milestone[];
    comments: Comment[];
    parent_goal?: ParentGoal | null;
    child_goals?: ChildGoal[];
    can_edit: boolean;
    can_submit_for_approval: boolean;
    can_approve: boolean;
}

const props = defineProps<{
    goal: Goal;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'My Goals', href: '/my/goals' },
    { title: props.goal.title, href: `/my/goals/${props.goal.id}` },
];

const isOkr = computed(() => props.goal.goal_type === 'okr_objective');

const priorityColor = computed(() => {
    const colors: Record<string, string> = {
        low: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
        medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        high: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        critical: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };
    return colors[props.goal.priority] || colors.medium;
});

const goalTypeColor = computed(() => {
    return props.goal.goal_type === 'okr_objective'
        ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
        : 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400';
});

// Progress update modal
const isProgressModalOpen = ref(false);
const selectedKeyResult = ref<KeyResult | null>(null);

function openProgressModal(keyResult: KeyResult) {
    selectedKeyResult.value = keyResult;
    isProgressModalOpen.value = true;
}

function handleProgressUpdated() {
    router.reload({ only: ['goal'] });
}

// Milestone toggle
function toggleMilestone(index: number) {
    const milestone = props.goal.milestones[index];
    router.post(`/api/performance/goals/${props.goal.id}/milestones/${milestone.id}/toggle`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['goal'] });
        },
    });
}

// Submit for approval
const submitForm = useForm({});

function submitForApproval() {
    submitForm.post(`/api/performance/goals/${props.goal.id}/submit-approval`, {
        onSuccess: () => {
            router.reload({ only: ['goal'] });
        },
    });
}

function handleEdit() {
    router.visit(`/my/goals/${props.goal.id}/edit`);
}
</script>

<template>
    <Head :title="`${goal.title} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <Badge :class="goalTypeColor">
                            {{ goal.goal_type_label }}
                        </Badge>
                        <GoalStatusBadge
                            :status="goal.status"
                            :label="goal.status_label"
                        />
                        <Badge v-if="goal.approval_status !== 'not_required'" variant="outline">
                            {{ goal.approval_status_label }}
                        </Badge>
                        <Badge :class="priorityColor">
                            {{ goal.priority_label }}
                        </Badge>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ goal.title }}
                    </h1>
                    <p v-if="goal.description" class="mt-2 text-slate-600 dark:text-slate-400">
                        {{ goal.description }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        v-if="goal.can_submit_for_approval && goal.status === 'draft'"
                        variant="outline"
                        @click="submitForApproval"
                        :disabled="submitForm.processing"
                    >
                        Submit for Approval
                    </Button>
                    <Button v-if="goal.can_edit" @click="handleEdit">
                        Edit Goal
                    </Button>
                </div>
            </div>

            <!-- Progress and Dates -->
            <Card>
                <CardContent class="pt-6">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <GoalProgressBar
                                :progress="goal.progress_percentage"
                                :is-overdue="goal.is_overdue"
                                size="lg"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">Start Date</div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ new Date(goal.start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">Due Date</div>
                                <div
                                    :class="[
                                        'font-medium',
                                        goal.is_overdue ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-slate-100',
                                    ]"
                                >
                                    {{ new Date(goal.due_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">Category</div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ goal.category || 'Not set' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">Visibility</div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ goal.visibility_label }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Key Results (for OKRs) -->
            <Card v-if="isOkr">
                <CardHeader>
                    <CardTitle>Key Results</CardTitle>
                </CardHeader>
                <CardContent>
                    <KeyResultList
                        v-if="goal.key_results.length > 0"
                        :key-results="goal.key_results"
                        :show-actions="goal.can_edit"
                        @update-progress="openProgressModal"
                    />
                    <div v-else class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                        No key results defined for this objective.
                    </div>
                </CardContent>
            </Card>

            <!-- Milestones (for SMART Goals) -->
            <Card v-else>
                <CardHeader>
                    <CardTitle>Milestones</CardTitle>
                </CardHeader>
                <CardContent>
                    <MilestoneList
                        v-if="goal.milestones.length > 0"
                        :milestones="goal.milestones"
                        :editable="false"
                        @toggle="toggleMilestone"
                    />
                    <div v-else class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                        No milestones defined for this goal.
                    </div>
                </CardContent>
            </Card>

            <!-- Goal Alignment -->
            <Card v-if="goal.parent_goal || (goal.child_goals && goal.child_goals.length > 0)">
                <CardHeader>
                    <CardTitle>Goal Alignment</CardTitle>
                </CardHeader>
                <CardContent>
                    <GoalAlignmentTree
                        :parent-goal="goal.parent_goal"
                        :current-goal="{
                            id: goal.id,
                            title: goal.title,
                            goal_type: goal.goal_type,
                            goal_type_label: goal.goal_type_label,
                            progress_percentage: goal.progress_percentage,
                        }"
                        :child-goals="goal.child_goals"
                    />
                </CardContent>
            </Card>

            <!-- Manager Feedback -->
            <Card v-if="goal.manager_feedback">
                <CardHeader>
                    <CardTitle>Manager Feedback</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
                        {{ goal.manager_feedback }}
                    </p>
                </CardContent>
            </Card>

            <!-- Comments -->
            <Card>
                <CardHeader>
                    <CardTitle>Discussion</CardTitle>
                </CardHeader>
                <CardContent>
                    <GoalCommentThread
                        :goal-id="goal.id"
                        :comments="goal.comments"
                        @comment-added="router.reload({ only: ['goal'] })"
                    />
                </CardContent>
            </Card>
        </div>

        <!-- Progress Update Modal -->
        <ProgressUpdateModal
            v-model:open="isProgressModalOpen"
            :goal-id="goal.id"
            :key-result="selectedKeyResult"
            @success="handleProgressUpdated"
        />
    </TenantLayout>
</template>
