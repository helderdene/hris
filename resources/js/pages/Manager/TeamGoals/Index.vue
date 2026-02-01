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
import { Head, Link, router } from '@inertiajs/vue3';
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
    employee: {
        id: number;
        full_name: string;
    };
}

interface Employee {
    id: number;
    full_name: string;
    goals_count: number;
}

interface Statistics {
    total_goals: number;
    active_goals: number;
    completed_goals: number;
    overdue_goals: number;
    average_progress: number;
    pending_approvals: number;
}

interface EnumOption {
    value: string;
    label: string;
}

interface Filters {
    goal_type: string | null;
    status: string | null;
    employee_id: number | null;
}

const props = defineProps<{
    goals: {
        data: Goal[];
        links: unknown;
        meta: unknown;
    };
    employees: Employee[];
    statistics: Statistics;
    goalTypes: EnumOption[];
    goalStatuses: EnumOption[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'Team Goals', href: '/manager/team-goals' },
];

const goalsData = computed(() => props.goals?.data ?? []);
const selectedEmployeeId = ref<string>(props.filters.employee_id?.toString() || 'all');

function handleFilterChange(newFilters: { goal_type: string | null; status: string | null }) {
    router.get(
        '/manager/team-goals',
        {
            goal_type: newFilters.goal_type || undefined,
            status: newFilters.status || undefined,
            employee_id: selectedEmployeeId.value === 'all' ? undefined : selectedEmployeeId.value,
        },
        { preserveState: true },
    );
}

function handleEmployeeChange(employeeId: string) {
    selectedEmployeeId.value = employeeId;
    router.get(
        '/manager/team-goals',
        {
            goal_type: props.filters.goal_type || undefined,
            status: props.filters.status || undefined,
            employee_id: employeeId === 'all' ? undefined : employeeId,
        },
        { preserveState: true },
    );
}

function viewGoal(goal: Goal) {
    router.visit(`/manager/team-goals/${goal.id}`);
}
</script>

<template>
    <Head :title="`Team Goals - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Team Goals
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        View and manage your team members' goals.
                    </p>
                </div>

                <Link
                    v-if="statistics.pending_approvals > 0"
                    href="/manager/team-goals/approvals"
                    :style="{ backgroundColor: primaryColor }"
                    class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium text-white transition-colors hover:opacity-90"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    Pending Approvals ({{ statistics.pending_approvals }})
                </Link>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                        {{ statistics.total_goals }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Total Goals
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
                <!-- Employee Filter -->
                <Select
                    :model-value="selectedEmployeeId"
                    @update:model-value="handleEmployeeChange"
                >
                    <SelectTrigger class="w-[200px]">
                        <SelectValue placeholder="All Employees" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Employees</SelectItem>
                        <SelectItem
                            v-for="employee in employees"
                            :key="employee.id"
                            :value="employee.id.toString()"
                        >
                            {{ employee.full_name }} ({{ employee.goals_count }})
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
                    <div class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                        {{ goal.employee.full_name }}
                    </div>
                    <GoalCard
                        :goal="goal"
                        :show-actions="true"
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
                            d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                        />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No goals found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Your team members haven't created any goals yet, or no goals match your filters.
                    </p>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
