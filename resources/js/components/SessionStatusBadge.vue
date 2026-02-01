<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    status: string;
    label?: string;
}>();

const badgeClass = computed(() => {
    const colorMap: Record<string, string> = {
        draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        scheduled: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        in_progress: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        completed: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };

    return colorMap[props.status] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
});

const displayLabel = computed(() => {
    if (props.label) return props.label;

    const labelMap: Record<string, string> = {
        draft: 'Draft',
        scheduled: 'Scheduled',
        in_progress: 'In Progress',
        completed: 'Completed',
        cancelled: 'Cancelled',
    };

    return labelMap[props.status] || props.status;
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
            badgeClass,
        ]"
    >
        {{ displayLabel }}
    </span>
</template>
