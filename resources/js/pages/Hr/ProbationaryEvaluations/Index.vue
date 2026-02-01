<script setup lang="ts">
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

interface Department {
    id: number;
    name: string;
}

interface Employee {
    id: number;
    employee_number: string;
    full_name: string;
    department: string | null;
    position: string | null;
}

interface Evaluation {
    id: number;
    employee_id: number;
    employee: Employee;
    evaluator_name: string;
    milestone: string;
    milestone_label: string;
    milestone_color: string;
    milestone_date: string;
    due_date: string;
    status: string;
    status_label: string;
    status_color: string;
    overall_rating: number | null;
    recommendation: string | null;
    recommendation_short_label: string | null;
    recommendation_color: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    is_overdue: boolean;
    is_final_evaluation: boolean;
}

interface EnumOption {
    value: string;
    label: string;
    color: string;
}

interface Filters {
    status: string | null;
    milestone: string | null;
    department_id: number | null;
    awaiting_action: boolean;
}

interface Summary {
    pending_evaluations: number;
    awaiting_hr: number;
    overdue: number;
    this_month: number;
}

const props = defineProps<{
    evaluations: { data: Evaluation[] };
    departments: Department[];
    summary: Summary;
    statuses: EnumOption[];
    milestones: EnumOption[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR Management', href: '/employees' },
    { title: 'Probationary Evaluations', href: '/hr/probationary-evaluations' },
];

const selectedStatus = ref(props.filters.status || 'all');
const selectedMilestone = ref(props.filters.milestone || 'all');
const selectedDepartment = ref(props.filters.department_id ? String(props.filters.department_id) : 'all');
const awaitingAction = ref(props.filters.awaiting_action);

const evaluationsData = computed(() => props.evaluations?.data ?? []);

function applyFilters(): void {
    const params: Record<string, string | number | boolean | undefined> = {};

    if (selectedStatus.value !== 'all') {
        params.status = selectedStatus.value;
    }
    if (selectedMilestone.value !== 'all') {
        params.milestone = selectedMilestone.value;
    }
    if (selectedDepartment.value !== 'all') {
        params.department_id = Number(selectedDepartment.value);
    }
    if (awaitingAction.value) {
        params.awaiting_action = true;
    }

    router.get('/hr/probationary-evaluations', params, { preserveState: true });
}

function handleStatusChange(value: string): void {
    selectedStatus.value = value;
    applyFilters();
}

function handleMilestoneChange(value: string): void {
    selectedMilestone.value = value;
    applyFilters();
}

function handleDepartmentChange(value: string): void {
    selectedDepartment.value = value;
    applyFilters();
}

function toggleAwaitingAction(): void {
    awaitingAction.value = !awaitingAction.value;
    applyFilters();
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

function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head :title="`Probationary Evaluations - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Probationary Evaluations
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Review and approve probationary evaluation submissions from managers.
                </p>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        Awaiting Manager
                    </div>
                    <div class="mt-1 text-2xl font-semibold text-slate-600 dark:text-slate-300">
                        {{ summary.pending_evaluations }}
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        Awaiting HR Review
                    </div>
                    <div class="mt-1 text-2xl font-semibold text-purple-600 dark:text-purple-400">
                        {{ summary.awaiting_hr }}
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        Overdue
                    </div>
                    <div class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">
                        {{ summary.overdue }}
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        This Month
                    </div>
                    <div class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">
                        {{ summary.this_month }}
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <Button
                    :variant="awaitingAction ? 'default' : 'outline'"
                    size="sm"
                    @click="toggleAwaitingAction"
                >
                    Awaiting Action Only
                </Button>

                <Select :model-value="selectedStatus" @update:model-value="handleStatusChange">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem v-for="status in statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="selectedMilestone" @update:model-value="handleMilestoneChange">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Milestone" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Milestones</SelectItem>
                        <SelectItem v-for="milestone in milestones" :key="milestone.value" :value="milestone.value">
                            {{ milestone.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="selectedDepartment" @update:model-value="handleDepartmentChange">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Department" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Departments</SelectItem>
                        <SelectItem v-for="dept in departments" :key="dept.id" :value="String(dept.id)">
                            {{ dept.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Evaluations Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <!-- Desktop Table -->
                <div class="hidden md:block">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Employee
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Milestone
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Rating
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Recommendation
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Submitted
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="evaluation in evaluationsData"
                                :key="evaluation.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ evaluation.employee.full_name }}
                                    </div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ evaluation.employee.employee_number }}
                                        <span v-if="evaluation.employee.department">
                                            &middot; {{ evaluation.employee.department }}
                                        </span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(evaluation.milestone_color)"
                                    >
                                        {{ evaluation.milestone_label }}
                                    </span>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ formatDate(evaluation.milestone_date) }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(evaluation.status_color)"
                                    >
                                        {{ evaluation.status_label }}
                                    </span>
                                    <span
                                        v-if="evaluation.is_overdue"
                                        class="ml-1 inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-300"
                                    >
                                        Overdue
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-900 dark:text-slate-100">
                                    {{ evaluation.overall_rating?.toFixed(2) || '-' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        v-if="evaluation.recommendation"
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(evaluation.recommendation_color || 'slate')"
                                    >
                                        {{ evaluation.recommendation_short_label }}
                                    </span>
                                    <span v-else class="text-sm text-slate-400 dark:text-slate-500">-</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                    {{ formatDate(evaluation.submitted_at) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <Link
                                        :href="`/hr/probationary-evaluations/${evaluation.id}`"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        Review
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card Layout -->
                <div class="md:hidden">
                    <div
                        v-for="evaluation in evaluationsData"
                        :key="evaluation.id"
                        class="border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-700"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ evaluation.employee.full_name }}
                                </div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ evaluation.employee.employee_number }}
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClasses(evaluation.status_color)"
                            >
                                {{ evaluation.status_label }}
                            </span>
                        </div>
                        <div class="mt-2 flex items-center gap-3 text-sm">
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClasses(evaluation.milestone_color)"
                            >
                                {{ evaluation.milestone_label }}
                            </span>
                            <span v-if="evaluation.overall_rating" class="text-slate-600 dark:text-slate-400">
                                Rating: {{ evaluation.overall_rating.toFixed(2) }}
                            </span>
                        </div>
                        <div class="mt-3">
                            <Link
                                :href="`/hr/probationary-evaluations/${evaluation.id}`"
                                :style="{ backgroundColor: primaryColor }"
                                class="inline-flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium text-white transition-colors hover:opacity-90"
                            >
                                Review
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="evaluationsData.length === 0"
                    class="px-6 py-12 text-center"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No evaluations found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        No probationary evaluations match your current filters.
                    </p>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
