<script setup lang="ts">
import GoalCard from '@/components/Goals/GoalCard.vue';
import GoalFilters from '@/components/Goals/GoalFilters.vue';
import GoalFormModal from '@/components/Goals/GoalFormModal.vue';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

interface Goal {
    id: number;
    goal_type: string;
    goal_type_label: string;
    goal_type_color: string;
    title: string;
    category: string | null;
    priority: string;
    priority_label: string;
    priority_color: string;
    status: string;
    status_label: string;
    status_color: string;
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
}

interface Statistics {
    total: number;
    draft: number;
    pending_approval: number;
    active: number;
    completed: number;
    cancelled: number;
    overdue: number;
    okrs: number;
    smart_goals: number;
    average_progress: number;
}

interface EnumOption {
    value: string;
    label: string;
    description?: string;
    color?: string;
}

interface Filters {
    goal_type: string | null;
    status: string | null;
}

const props = defineProps<{
    goals: {
        data: Goal[];
        links: unknown;
        meta: unknown;
    };
    statistics: Statistics;
    goalTypes: EnumOption[];
    goalStatuses: EnumOption[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'My Goals', href: '/my/goals' },
];

const isFormModalOpen = ref(false);
const goalsData = computed(() => props.goals?.data ?? []);

function handleFilterChange(newFilters: Filters) {
    router.get(
        '/my/goals',
        {
            goal_type: newFilters.goal_type || undefined,
            status: newFilters.status || undefined,
        },
        { preserveState: true },
    );
}

function handleCreateGoal() {
    router.visit('/my/goals/create');
}

async function handleDeleteGoal(goalId: number) {
    try {
        await axios.delete(`/api/my/goals/${goalId}`);
        router.reload();
    } catch (error) {
        console.error('Error deleting goal:', error);
        alert('Failed to delete goal. Only draft goals can be deleted.');
    }
}
</script>

<template>
    <Head :title="`My Goals - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        My Goals
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Track your OKRs and SMART goals.
                    </p>
                </div>

                <Button
                    @click="handleCreateGoal"
                    :style="{ backgroundColor: primaryColor }"
                >
                    <svg
                        class="mr-2 h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 4.5v15m7.5-7.5h-15"
                        />
                    </svg>
                    Create Goal
                </Button>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                        {{ statistics.active }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Active Goals
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ statistics.completed }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Completed
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                        {{ statistics.overdue }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Overdue
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                        {{ statistics.average_progress?.toFixed(0) ?? 0 }}%
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Avg Progress
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <GoalFilters
                :goal-types="goalTypes"
                :goal-statuses="goalStatuses"
                :filters="filters"
                @change="handleFilterChange"
            />

            <!-- Goals List -->
            <div class="flex flex-col gap-4">
                <GoalCard
                    v-for="goal in goalsData"
                    :key="goal.id"
                    :goal="goal"
                    :show-actions="true"
                    @click="router.visit(`/my/goals/${goal.id}`)"
                    @delete="handleDeleteGoal"
                />

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
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No goals yet
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Create your first goal to start tracking your progress.
                    </p>
                    <div class="mt-6">
                        <Button
                            @click="handleCreateGoal"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            Create Your First Goal
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Modal -->
        <GoalFormModal
            v-model:open="isFormModalOpen"
            :goal-types="goalTypes"
            @success="router.reload()"
        />
    </TenantLayout>
</template>
