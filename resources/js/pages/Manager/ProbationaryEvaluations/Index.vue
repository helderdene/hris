<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
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
}

interface Summary {
    pending: number;
    draft: number;
    revision_requested: number;
    overdue: number;
}

const props = defineProps<{
    evaluations: { data: Evaluation[] };
    summary: Summary;
    statuses: EnumOption[];
    milestones: EnumOption[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'Probationary Evaluations', href: '/manager/probationary-evaluations' },
];

const selectedStatus = ref(props.filters.status || 'all');
const selectedMilestone = ref(props.filters.milestone || 'all');

const evaluationsData = computed(() => props.evaluations?.data ?? []);

function applyFilters(): void {
    const params: Record<string, string | undefined> = {};

    if (selectedStatus.value !== 'all') {
        params.status = selectedStatus.value;
    }
    if (selectedMilestone.value !== 'all') {
        params.milestone = selectedMilestone.value;
    }

    router.get('/manager/probationary-evaluations', params, { preserveState: true });
}

function handleStatusChange(value: string): void {
    selectedStatus.value = value;
    applyFilters();
}

function handleMilestoneChange(value: string): void {
    selectedMilestone.value = value;
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

function formatDate(dateString: string): string {
    if (!dateString) return '';
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
                    Evaluate your probationary team members at their milestone dates.
                </p>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        Pending
                    </div>
                    <div class="mt-1 text-2xl font-semibold text-slate-600 dark:text-slate-300">
                        {{ summary.pending }}
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        In Draft
                    </div>
                    <div class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">
                        {{ summary.draft }}
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        Revision Requested
                    </div>
                    <div class="mt-1 text-2xl font-semibold text-orange-600 dark:text-orange-400">
                        {{ summary.revision_requested }}
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
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
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
            </div>

            <!-- Evaluations List -->
            <div class="flex flex-col gap-4">
                <div
                    v-for="evaluation in evaluationsData"
                    :key="evaluation.id"
                    class="rounded-xl border border-slate-200 bg-white p-5 transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                                    {{ evaluation.employee.full_name }}
                                </h3>
                                <span
                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                    :class="getStatusBadgeClasses(evaluation.milestone_color)"
                                >
                                    {{ evaluation.milestone_label }}
                                </span>
                                <span
                                    v-if="evaluation.is_overdue"
                                    class="inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-300"
                                >
                                    Overdue
                                </span>
                            </div>
                            <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ evaluation.employee.employee_number }}
                                <span v-if="evaluation.employee.department">
                                    &middot; {{ evaluation.employee.department }}
                                </span>
                                <span v-if="evaluation.employee.position">
                                    &middot; {{ evaluation.employee.position }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-slate-500 dark:text-slate-400">Milestone:</span>
                                    <span class="text-slate-700 dark:text-slate-300">
                                        {{ formatDate(evaluation.milestone_date) }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-slate-500 dark:text-slate-400">Due:</span>
                                    <span
                                        :class="evaluation.is_overdue ? 'font-medium text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-300'"
                                    >
                                        {{ formatDate(evaluation.due_date) }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-slate-500 dark:text-slate-400">Status:</span>
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(evaluation.status_color)"
                                    >
                                        {{ evaluation.status_label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link
                                :href="`/manager/probationary-evaluations/${evaluation.id}`"
                                :style="{ backgroundColor: primaryColor }"
                                class="inline-flex items-center justify-center gap-1.5 rounded-md px-4 py-2 text-sm font-medium text-white transition-colors hover:opacity-90"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                </svg>
                                {{ evaluation.status === 'pending' ? 'Start Evaluation' : 'Continue' }}
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="evaluationsData.length === 0"
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
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No evaluations found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        You don't have any probationary evaluations pending, or no evaluations match your filters.
                    </p>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
