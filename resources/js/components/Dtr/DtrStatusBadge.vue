<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    status: string;
    label: string;
}>();

const badgeClasses = computed(() => {
    const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';

    switch (props.status) {
        case 'present':
            return `${baseClasses} bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400`;
        case 'absent':
            return `${baseClasses} bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400`;
        case 'holiday':
            return `${baseClasses} bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400`;
        case 'rest_day':
            return `${baseClasses} bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300`;
        case 'no_schedule':
            return `${baseClasses} bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400`;
        default:
            return `${baseClasses} bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300`;
    }
});
</script>

<template>
    <span :class="badgeClasses">{{ label }}</span>
</template>
