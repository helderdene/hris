<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import GoalProgressBar from '@/components/Goals/GoalProgressBar.vue';
import { Link } from '@inertiajs/vue3';

interface Goal {
    id: number;
    title: string;
    goal_type: string;
    goal_type_label: string;
    owner_name?: string;
    progress_percentage: number;
}

const props = defineProps<{
    parentGoal?: Goal | null;
    currentGoal: Goal;
    childGoals?: Goal[];
}>();

function getGoalTypeColor(goalType: string): string {
    return goalType === 'okr_objective'
        ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
        : 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400';
}
</script>

<template>
    <div class="space-y-3">
        <!-- Parent Goal -->
        <div v-if="parentGoal" class="relative">
            <Link
                :href="`/my/goals/${parentGoal.id}`"
                class="block rounded-lg border border-slate-200 bg-slate-50 p-3 transition-colors hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-slate-600"
            >
                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                    </svg>
                    <span>Parent Goal</span>
                </div>
                <div class="mt-1 flex items-center gap-2">
                    <Badge :class="getGoalTypeColor(parentGoal.goal_type)" class="text-xs">
                        {{ parentGoal.goal_type_label }}
                    </Badge>
                    <span class="font-medium text-slate-900 dark:text-slate-100">
                        {{ parentGoal.title }}
                    </span>
                </div>
                <div v-if="parentGoal.owner_name" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Owner: {{ parentGoal.owner_name }}
                </div>
            </Link>
            <!-- Connector line -->
            <div class="absolute left-6 top-full h-3 w-px bg-slate-300 dark:bg-slate-600" />
        </div>

        <!-- Current Goal -->
        <div class="relative">
            <div
                v-if="parentGoal"
                class="absolute -top-3 left-6 h-3 w-px bg-slate-300 dark:bg-slate-600"
            />
            <div class="rounded-lg border-2 border-blue-500 bg-white p-3 dark:border-blue-400 dark:bg-slate-900">
                <div class="flex items-center gap-2 text-xs font-medium text-blue-600 dark:text-blue-400">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                    </svg>
                    <span>Current Goal</span>
                </div>
                <div class="mt-1 flex items-center gap-2">
                    <Badge :class="getGoalTypeColor(currentGoal.goal_type)" class="text-xs">
                        {{ currentGoal.goal_type_label }}
                    </Badge>
                    <span class="font-medium text-slate-900 dark:text-slate-100">
                        {{ currentGoal.title }}
                    </span>
                </div>
                <div class="mt-2 w-32">
                    <GoalProgressBar :progress="currentGoal.progress_percentage" size="sm" />
                </div>
            </div>
            <!-- Connector line for children -->
            <div
                v-if="childGoals && childGoals.length > 0"
                class="absolute bottom-0 left-6 h-3 w-px translate-y-full bg-slate-300 dark:bg-slate-600"
            />
        </div>

        <!-- Child Goals -->
        <div v-if="childGoals && childGoals.length > 0" class="relative ml-6 space-y-2">
            <div class="absolute -top-3 left-0 h-3 w-px bg-slate-300 dark:bg-slate-600" />
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">
                Aligned Goals ({{ childGoals.length }})
            </div>
            <Link
                v-for="child in childGoals"
                :key="child.id"
                :href="`/my/goals/${child.id}`"
                class="block rounded-lg border border-slate-200 bg-slate-50 p-3 transition-colors hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-slate-600"
            >
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 8.25 7.5 7.5 7.5-7.5" />
                    </svg>
                    <Badge :class="getGoalTypeColor(child.goal_type)" class="text-xs">
                        {{ child.goal_type_label }}
                    </Badge>
                    <span class="font-medium text-slate-900 dark:text-slate-100">
                        {{ child.title }}
                    </span>
                </div>
                <div v-if="child.owner_name" class="ml-6 mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Owner: {{ child.owner_name }}
                </div>
                <div class="ml-6 mt-2 w-24">
                    <GoalProgressBar :progress="child.progress_percentage" size="sm" :show-label="false" />
                </div>
            </Link>
        </div>
    </div>
</template>
