<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Employee {
    id: number;
    employee_number: string;
    full_name: string;
    department_id: number | null;
    department_name: string | null;
    position_id: number | null;
    position_title: string | null;
}

interface ParentGoal {
    id: number;
    title: string;
    goal_type: string | null;
    goal_type_label: string | null;
    status: string | null;
    status_label: string | null;
    progress_percentage: number;
}

interface ChildGoal {
    id: number;
    goal_type: string;
    goal_type_label: string;
    title: string;
    status: string;
    status_label: string;
    progress_percentage: number;
    employee_name?: string;
}

interface ProgressEntry {
    id: number;
    progress_value: number;
    notes: string | null;
    recorded_at: string;
    recorded_by_user: {
        id: number;
        name: string;
    } | null;
}

interface KeyResult {
    id: number;
    title: string;
    description: string | null;
    metric_type: string | null;
    metric_type_label: string | null;
    metric_unit: string | null;
    target_value: number;
    starting_value: number;
    current_value: number;
    achievement_percentage: number;
    weight: number;
    status: string | null;
    formatted_current_value: string;
    formatted_target_value: string;
    progress_entries?: ProgressEntry[];
}

interface Milestone {
    id: number;
    title: string;
    description: string | null;
    due_date: string | null;
    is_completed: boolean;
    completed_at: string | null;
    is_overdue: boolean;
    completed_by_user: {
        id: number;
        name: string;
    } | null;
}

interface Comment {
    id: number;
    comment: string;
    created_at: string;
    user: {
        id: number;
        name: string;
    };
}

interface ApprovedByUser {
    id: number;
    name: string;
    email: string;
}

interface Goal {
    id: number;
    employee_id: number;
    goal_type: string | null;
    goal_type_label: string | null;
    goal_type_color: string | null;
    title: string;
    description: string | null;
    category: string | null;
    visibility: string | null;
    visibility_label: string | null;
    priority: string | null;
    priority_label: string | null;
    priority_color: string | null;
    status: string | null;
    status_label: string | null;
    status_color: string | null;
    approval_status: string | null;
    approval_status_label: string | null;
    approval_status_color: string | null;
    approved_at: string | null;
    start_date: string | null;
    due_date: string | null;
    completed_at: string | null;
    progress_percentage: number;
    weight: number | null;
    final_score: number | null;
    owner_notes: string | null;
    manager_feedback: string | null;
    is_overdue: boolean;
    days_remaining: number;
    employee?: Employee;
    parent_goal?: ParentGoal | null;
    child_goals?: ChildGoal[];
    key_results?: KeyResult[];
    milestones?: Milestone[];
    progress_entries?: ProgressEntry[];
    comments?: Comment[];
    approved_by_user?: ApprovedByUser | null;
}

interface EnumOption {
    value: string;
    label: string;
    description: string;
    color: string;
}

const props = defineProps<{
    goal: Goal;
    goalTypes: EnumOption[];
    goalStatuses: EnumOption[];
    approvalStatuses: EnumOption[];
    priorities: EnumOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Performance', href: '/performance' },
    { title: 'Goals', href: '/performance/goals' },
    { title: props.goal.title, href: '#' },
];

const isOkr = computed(() => props.goal.goal_type === 'okr_objective');

const keyResults = computed(() => props.goal.key_results ?? []);
const milestones = computed(() => props.goal.milestones ?? []);
const progressEntries = computed(() => props.goal.progress_entries ?? []);
const comments = computed(() => props.goal.comments ?? []);
const childGoals = computed(() => props.goal.child_goals ?? []);

const completedMilestones = computed(() => milestones.value.filter(m => m.is_completed).length);

function goBack() {
    router.visit('/performance/goals');
}

function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString();
}

function formatDateTime(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString();
}

function getStatusBadgeClass(color: string | null): string {
    if (!color) return 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300';
    return color;
}

function getKeyResultProgressColor(percentage: number): string {
    if (percentage >= 100) return 'bg-green-500';
    if (percentage >= 70) return 'bg-blue-500';
    if (percentage >= 40) return 'bg-amber-500';
    return 'bg-red-500';
}
</script>

<template>
    <Head :title="`${goal.title} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Back Navigation -->
            <div class="flex items-center justify-between">
                <button
                    @click="goBack"
                    class="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                >
                    <svg
                        class="h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"
                        />
                    </svg>
                    Back to Goals
                </button>
            </div>

            <!-- Goal Header -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-if="goal.goal_type_label"
                                class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                :class="getStatusBadgeClass(goal.goal_type_color)"
                            >
                                {{ goal.goal_type_label }}
                            </span>
                            <span
                                v-if="goal.priority_label"
                                class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                :class="getStatusBadgeClass(goal.priority_color)"
                            >
                                {{ goal.priority_label }}
                            </span>
                        </div>
                        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ goal.title }}
                        </h1>
                        <p v-if="goal.description" class="mt-2 text-slate-600 dark:text-slate-400">
                            {{ goal.description }}
                        </p>

                        <!-- Employee Info -->
                        <div v-if="goal.employee" class="mt-4 flex items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            <span class="font-medium text-slate-700 dark:text-slate-300">{{ goal.employee.full_name }}</span>
                            <span v-if="goal.employee.position_title">{{ goal.employee.position_title }}</span>
                            <span v-if="goal.employee.department_name">· {{ goal.employee.department_name }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        <span
                            class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium"
                            :class="getStatusBadgeClass(goal.status_color)"
                        >
                            {{ goal.status_label }}
                        </span>
                        <span
                            v-if="goal.approval_status_label"
                            class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium"
                            :class="getStatusBadgeClass(goal.approval_status_color)"
                        >
                            {{ goal.approval_status_label }}
                        </span>
                    </div>
                </div>

                <!-- Progress -->
                <div class="mt-6">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Progress</span>
                        <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ goal.progress_percentage }}%</span>
                    </div>
                    <Progress :model-value="Number(goal.progress_percentage) || 0" class="h-2" />
                </div>

                <!-- Dates -->
                <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Start Date</p>
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ formatDate(goal.start_date) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Due Date</p>
                        <p class="font-medium" :class="goal.is_overdue ? 'text-red-600' : 'text-slate-900 dark:text-slate-100'">
                            {{ formatDate(goal.due_date) }}
                            <span v-if="goal.is_overdue" class="text-xs text-red-500">(Overdue)</span>
                            <span v-else-if="goal.days_remaining >= 0" class="text-xs text-slate-500">
                                ({{ goal.days_remaining }} days remaining)
                            </span>
                        </p>
                    </div>
                    <div v-if="goal.completed_at">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Completed</p>
                        <p class="font-medium text-green-600">{{ formatDate(goal.completed_at) }}</p>
                    </div>
                    <div v-if="goal.weight">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Weight</p>
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ goal.weight }}%</p>
                    </div>
                </div>
            </div>

            <!-- Parent Goal -->
            <Card v-if="goal.parent_goal">
                <CardHeader>
                    <CardTitle class="text-base">Parent Goal</CardTitle>
                </CardHeader>
                <CardContent>
                    <Link
                        :href="`/performance/goals/${goal.parent_goal.id}`"
                        class="flex items-center justify-between rounded-lg border border-slate-200 p-4 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                    >
                        <div>
                            <span
                                v-if="goal.parent_goal.goal_type_label"
                                class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                            >
                                {{ goal.parent_goal.goal_type_label }}
                            </span>
                            <p class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ goal.parent_goal.title }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ goal.parent_goal.progress_percentage }}%</p>
                        </div>
                    </Link>
                </CardContent>
            </Card>

            <!-- Key Results (for OKRs) -->
            <Card v-if="isOkr && keyResults.length > 0">
                <CardHeader>
                    <CardTitle>Key Results</CardTitle>
                    <CardDescription>Measurable outcomes that define success for this objective.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div
                            v-for="kr in keyResults"
                            :key="kr.id"
                            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="font-medium text-slate-900 dark:text-slate-100">{{ kr.title }}</h4>
                                    <p v-if="kr.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        {{ kr.description }}
                                    </p>
                                </div>
                                <span
                                    class="ml-2 text-sm font-semibold"
                                    :class="kr.achievement_percentage >= 100 ? 'text-green-600' : 'text-slate-600 dark:text-slate-400'"
                                >
                                    {{ kr.achievement_percentage }}%
                                </span>
                            </div>
                            <div class="mt-3">
                                <div class="mb-1 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                    <span>{{ kr.formatted_current_value }} / {{ kr.formatted_target_value }}</span>
                                    <span v-if="kr.metric_type_label">{{ kr.metric_type_label }}</span>
                                </div>
                                <div class="h-2 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div
                                        class="h-2 rounded-full transition-all"
                                        :class="getKeyResultProgressColor(kr.achievement_percentage)"
                                        :style="{ width: `${Math.min(kr.achievement_percentage, 100)}%` }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Milestones -->
            <Card v-if="milestones.length > 0">
                <CardHeader>
                    <CardTitle>Milestones</CardTitle>
                    <CardDescription>
                        {{ completedMilestones }} of {{ milestones.length }} completed
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div
                            v-for="milestone in milestones"
                            :key="milestone.id"
                            class="flex items-start gap-3"
                        >
                            <div
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full"
                                :class="milestone.is_completed ? 'bg-green-100 dark:bg-green-900/30' : 'bg-slate-100 dark:bg-slate-700'"
                            >
                                <svg
                                    v-if="milestone.is_completed"
                                    class="h-3 w-3 text-green-600 dark:text-green-400"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="3"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <div
                                    v-else
                                    class="h-2 w-2 rounded-full"
                                    :class="milestone.is_overdue ? 'bg-red-500' : 'bg-slate-400'"
                                />
                            </div>
                            <div class="flex-1">
                                <p
                                    class="font-medium"
                                    :class="milestone.is_completed ? 'text-slate-500 line-through dark:text-slate-400' : 'text-slate-900 dark:text-slate-100'"
                                >
                                    {{ milestone.title }}
                                </p>
                                <p v-if="milestone.description" class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ milestone.description }}
                                </p>
                                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                    <span v-if="milestone.is_completed && milestone.completed_at">
                                        Completed {{ formatDate(milestone.completed_at) }}
                                        <span v-if="milestone.completed_by_user">by {{ milestone.completed_by_user.name }}</span>
                                    </span>
                                    <span v-else-if="milestone.due_date" :class="milestone.is_overdue ? 'text-red-500' : ''">
                                        Due: {{ formatDate(milestone.due_date) }}
                                        <span v-if="milestone.is_overdue">(Overdue)</span>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Child Goals -->
            <Card v-if="childGoals.length > 0">
                <CardHeader>
                    <CardTitle>Child Goals</CardTitle>
                    <CardDescription>Goals that contribute to this objective.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <Link
                            v-for="child in childGoals"
                            :key="child.id"
                            :href="`/performance/goals/${child.id}`"
                            class="flex items-center justify-between rounded-lg border border-slate-200 p-4 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                    >
                                        {{ child.goal_type_label }}
                                    </span>
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClass(null)"
                                    >
                                        {{ child.status_label }}
                                    </span>
                                </div>
                                <p class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ child.title }}</p>
                                <p v-if="child.employee_name" class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ child.employee_name }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ child.progress_percentage }}%</p>
                            </div>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Progress History -->
            <Card v-if="progressEntries.length > 0">
                <CardHeader>
                    <CardTitle>Progress History</CardTitle>
                    <CardDescription>Recent progress updates for this goal.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div
                            v-for="entry in progressEntries"
                            :key="entry.id"
                            class="flex items-start gap-3 border-l-2 border-slate-200 pl-4 dark:border-slate-700"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ entry.progress_value }}%
                                    </span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ formatDateTime(entry.recorded_at) }}
                                    </span>
                                </div>
                                <p v-if="entry.notes" class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                    {{ entry.notes }}
                                </p>
                                <p v-if="entry.recorded_by_user" class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                    by {{ entry.recorded_by_user.name }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Comments -->
            <Card v-if="comments.length > 0">
                <CardHeader>
                    <CardTitle>Comments</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div
                            v-for="comment in comments"
                            :key="comment.id"
                            class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ comment.user.name }}
                                </span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ formatDateTime(comment.created_at) }}
                                </span>
                            </div>
                            <p class="mt-2 text-slate-600 dark:text-slate-400">{{ comment.comment }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Notes & Feedback -->
            <div v-if="goal.owner_notes || goal.manager_feedback" class="grid gap-6 lg:grid-cols-2">
                <Card v-if="goal.owner_notes">
                    <CardHeader>
                        <CardTitle class="text-base">Owner Notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-slate-600 dark:text-slate-400">{{ goal.owner_notes }}</p>
                    </CardContent>
                </Card>

                <Card v-if="goal.manager_feedback">
                    <CardHeader>
                        <CardTitle class="text-base">Manager Feedback</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-slate-600 dark:text-slate-400">{{ goal.manager_feedback }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Approval Info -->
            <Card v-if="goal.approved_by_user && goal.approved_at">
                <CardHeader>
                    <CardTitle class="text-base">Approval Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-4 text-sm">
                        <div>
                            <p class="text-slate-500 dark:text-slate-400">Approved by</p>
                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ goal.approved_by_user.name }}</p>
                        </div>
                        <div>
                            <p class="text-slate-500 dark:text-slate-400">Approved on</p>
                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ formatDateTime(goal.approved_at) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
