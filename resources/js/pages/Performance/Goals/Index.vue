<script setup lang="ts">
import GoalCard from '@/components/Goals/GoalCard.vue';
import GoalFilters from '@/components/Goals/GoalFilters.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Goal {
    id: number;
    goal_type: string;
    goal_type_label: string;
    title: string;
    category: string | null;
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
    key_results_count?: number;
    milestones_count?: number;
    milestones_completed?: number;
    employee_name?: string;
    department_name?: string;
}

interface Department {
    id: number;
    name: string;
}

interface Statistics {
    total_goals: number;
    okrs: number;
    smart_goals: number;
    active_goals: number;
    completed_goals: number;
    overdue_goals: number;
    average_progress: number;
}

interface EnumOption {
    value: string;
    label: string;
}

interface Filters {
    goal_type: string | null;
    status: string | null;
    department_id: number | null;
}

const props = defineProps<{
    goals: {
        data: Goal[];
        links: unknown;
        meta: unknown;
    };
    departments: Department[];
    statistics: Statistics;
    goalTypes: EnumOption[];
    goalStatuses: EnumOption[];
    filters: Filters;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Performance', href: '/performance' },
    { title: 'Goals', href: '/performance/goals' },
];

const goalsData = computed(() => props.goals?.data ?? []);
const selectedDepartmentId = ref<string>(props.filters.department_id?.toString() || 'all');

function handleFilterChange(newFilters: { goal_type: string | null; status: string | null }) {
    router.get(
        '/performance/goals',
        {
            goal_type: newFilters.goal_type || undefined,
            status: newFilters.status || undefined,
            department_id: selectedDepartmentId.value === 'all' ? undefined : selectedDepartmentId.value,
        },
        { preserveState: true },
    );
}

function handleDepartmentChange(departmentId: string) {
    selectedDepartmentId.value = departmentId;
    router.get(
        '/performance/goals',
        {
            goal_type: props.filters.goal_type || undefined,
            status: props.filters.status || undefined,
            department_id: departmentId === 'all' ? undefined : departmentId,
        },
        { preserveState: true },
    );
}

function viewGoal(goal: Goal) {
    router.visit(`/performance/goals/${goal.id}`);
}

function exportGoals() {
    const params = new URLSearchParams();
    if (props.filters.goal_type) params.append('goal_type', props.filters.goal_type);
    if (props.filters.status) params.append('status', props.filters.status);
    if (props.filters.department_id) params.append('department_id', props.filters.department_id.toString());

    window.open(`/api/performance/goals/export?${params.toString()}`, '_blank');
}
</script>

<template>
    <Head :title="`Goals Management - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Goals Management
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        View and manage all goals across the organization.
                    </p>
                </div>

                <Button variant="outline" @click="exportGoals">
                    <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export
                </Button>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-7">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                        {{ statistics.total_goals }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Total
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {{ statistics.okrs }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        OKRs
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-2xl font-bold text-teal-600 dark:text-teal-400">
                        {{ statistics.smart_goals }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        SMART Goals
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ statistics.active_goals }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Active
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ statistics.completed_goals }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Completed
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                        {{ statistics.overdue_goals }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Overdue
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                        {{ statistics.average_progress?.toFixed(0) ?? 0 }}%
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Avg Progress
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Department Filter -->
                <Select
                    :model-value="selectedDepartmentId"
                    @update:model-value="handleDepartmentChange"
                >
                    <SelectTrigger class="w-[200px]">
                        <SelectValue placeholder="All Departments" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Departments</SelectItem>
                        <SelectItem
                            v-for="department in departments"
                            :key="department.id"
                            :value="department.id.toString()"
                        >
                            {{ department.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <GoalFilters
                    :goal-types="goalTypes"
                    :goal-statuses="goalStatuses"
                    :filters="{ goal_type: filters.goal_type, status: filters.status }"
                    @change="handleFilterChange"
                />
            </div>

            <!-- Goals List -->
            <div class="flex flex-col gap-4">
                <div
                    v-for="goal in goalsData"
                    :key="goal.id"
                    class="group"
                >
                    <div class="mb-1 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span class="font-medium text-slate-700 dark:text-slate-300">
                            {{ goal.employee_name }}
                        </span>
                        <span v-if="goal.department_name">
                            &middot; {{ goal.department_name }}
                        </span>
                    </div>
                    <GoalCard
                        :goal="goal"
                        @click="viewGoal(goal)"
                    />
                </div>

                <!-- Empty State -->
                <div
                    v-if="goalsData.length === 0"
                    class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5"
                        />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No goals found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        No goals match your current filters.
                    </p>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
