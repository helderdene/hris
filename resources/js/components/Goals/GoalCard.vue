<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import GoalProgressBar from '@/components/Goals/GoalProgressBar.vue';
import GoalStatusBadge from '@/components/Goals/GoalStatusBadge.vue';
import { computed } from 'vue';

interface Goal {
    id: number;
    goal_type: string;
    goal_type_label: string;
    goal_type_color?: string;
    title: string;
    category: string | null;
    priority: string;
    priority_label: string;
    priority_color?: string;
    status: string;
    status_label: string;
    status_color?: string;
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

const props = withDefaults(
    defineProps<{
        goal: Goal;
        showActions?: boolean;
    }>(),
    {
        showActions: false,
    },
);

const emit = defineEmits<{
    click: [];
    delete: [goalId: number];
}>();

function handleDelete(event: Event) {
    event.stopPropagation();
    if (confirm('Are you sure you want to delete this goal? This action cannot be undone.')) {
        emit('delete', props.goal.id);
    }
}

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

const daysRemainingText = computed(() => {
    if (props.goal.status === 'completed') return 'Completed';
    if (props.goal.status === 'cancelled') return 'Cancelled';
    if (props.goal.is_overdue) return `${Math.abs(props.goal.days_remaining)} days overdue`;
    if (props.goal.days_remaining === 0) return 'Due today';
    if (props.goal.days_remaining === 1) return '1 day left';
    return `${props.goal.days_remaining} days left`;
});

const subItemsText = computed(() => {
    if (props.goal.goal_type === 'okr_objective' && props.goal.key_results_count) {
        return `${props.goal.key_results_count} Key Results`;
    }
    if (props.goal.goal_type === 'smart_goal' && props.goal.milestones_count) {
        const completed = props.goal.milestones_completed || 0;
        return `${completed}/${props.goal.milestones_count} Milestones`;
    }
    return null;
});
</script>

<template>
    <div
        class="group cursor-pointer rounded-xl border border-slate-200 bg-white p-4 transition-all hover:border-slate-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600"
        @click="emit('click')"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <!-- Top row: badges -->
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
                </div>

                <!-- Title -->
                <h3
                    class="mb-1 truncate text-base font-semibold text-slate-900 group-hover:text-slate-700 dark:text-slate-100 dark:group-hover:text-slate-200"
                >
                    {{ goal.title }}
                </h3>

                <!-- Meta info -->
                <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                    <span v-if="goal.category" class="flex items-center gap-1">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                        </svg>
                        {{ goal.category }}
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <span :class="{ 'text-red-500 dark:text-red-400': goal.is_overdue }">
                            {{ daysRemainingText }}
                        </span>
                    </span>
                    <span v-if="subItemsText" class="flex items-center gap-1">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        {{ subItemsText }}
                    </span>
                </div>
            </div>

            <!-- Right side: Priority, Progress, and Actions -->
            <div class="flex flex-shrink-0 items-start gap-3">
                <div class="flex flex-col items-end gap-3">
                    <Badge :class="priorityColor">
                        {{ goal.priority_label }}
                    </Badge>
                    <div class="w-24">
                        <GoalProgressBar
                            :progress="goal.progress_percentage"
                            :is-overdue="goal.is_overdue"
                            size="sm"
                        />
                    </div>
                </div>

                <!-- Actions Menu -->
                <DropdownMenu v-if="showActions">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            @click.stop
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                            </svg>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            v-if="goal.status === 'draft'"
                            class="text-red-600 focus:text-red-600 dark:text-red-400"
                            @click="handleDelete"
                        >
                            <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            Delete
                        </DropdownMenuItem>
                        <DropdownMenuItem v-else disabled class="text-slate-400">
                            <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            Only drafts can be deleted
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </div>
</template>
