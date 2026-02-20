<script setup lang="ts">
const props = defineProps<{
    status: string;
    label: string;
}>();

const colorClass = {
    pending_approval: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    approved: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    pre_registered: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
    checked_in: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    checked_out: 'bg-slate-100 text-slate-800 dark:bg-slate-900/30 dark:text-slate-400',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    cancelled: 'bg-slate-100 text-slate-600 dark:bg-slate-900/30 dark:text-slate-500',
    no_show: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
} as Record<string, string>;
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
            colorClass[status] || 'bg-slate-100 text-slate-800',
        ]"
    >
        {{ label }}
    </span>
</template>
