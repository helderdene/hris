<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { computed } from 'vue';

interface Milestone {
    id?: number;
    title: string;
    description?: string;
    due_date?: string;
    is_completed?: boolean;
    completed_at?: string;
}

const props = defineProps<{
    milestone: Milestone;
    index: number;
}>();

const emit = defineEmits<{
    toggle: [];
}>();

const isOverdue = computed(() => {
    if (!props.milestone.due_date || props.milestone.is_completed) return false;
    return new Date(props.milestone.due_date) < new Date();
});

const formattedDueDate = computed(() => {
    if (!props.milestone.due_date) return null;
    return new Date(props.milestone.due_date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
});
</script>

<template>
    <div class="flex items-start gap-3">
        <Checkbox
            :id="`milestone-${index}`"
            :checked="milestone.is_completed"
            @update:checked="emit('toggle')"
            class="mt-0.5"
        />
        <div class="flex-1">
            <label
                :for="`milestone-${index}`"
                :class="[
                    'cursor-pointer text-sm font-medium',
                    milestone.is_completed
                        ? 'text-slate-400 line-through dark:text-slate-500'
                        : 'text-slate-900 dark:text-slate-100',
                ]"
            >
                {{ milestone.title }}
            </label>
            <div class="mt-0.5 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                <span v-if="milestone.description">{{ milestone.description }}</span>
                <span v-if="formattedDueDate" :class="{ 'text-red-500 dark:text-red-400': isOverdue }">
                    <svg class="inline-block h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    {{ formattedDueDate }}
                </span>
                <span v-if="milestone.is_completed && milestone.completed_at" class="text-green-600 dark:text-green-400">
                    Completed
                </span>
            </div>
        </div>
    </div>
</template>
