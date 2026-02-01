<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    employmentType: string | null;
}

const props = defineProps<Props>();

const badgeConfig = computed(() => {
    switch (props.employmentType) {
        case 'regular':
            return {
                label: 'Regular',
                classes:
                    'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800',
            };
        case 'probationary':
            return {
                label: 'Probationary',
                classes:
                    'bg-yellow-50 text-yellow-700 border-yellow-300 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-700',
            };
        case 'contractual':
            return {
                label: 'Contractual',
                classes:
                    'bg-orange-50 text-orange-700 border-orange-300 dark:bg-orange-900/20 dark:text-orange-300 dark:border-orange-700',
            };
        case 'project_based':
            return {
                label: 'Project-based',
                classes:
                    'bg-blue-50 text-blue-700 border-blue-300 dark:bg-blue-900/20 dark:text-blue-300 dark:border-blue-700',
            };
        case 'consultant':
            return {
                label: 'Consultant',
                classes:
                    'bg-purple-50 text-purple-700 border-purple-300 dark:bg-purple-900/20 dark:text-purple-300 dark:border-purple-700',
            };
        case 'intern':
            return {
                label: 'Intern',
                classes:
                    'bg-slate-50 text-slate-700 border-slate-300 dark:bg-slate-900/20 dark:text-slate-300 dark:border-slate-700',
            };
        default:
            return {
                label: props.employmentType || 'Unknown',
                classes:
                    'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700',
            };
    }
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
        :class="badgeConfig.classes"
    >
        {{ badgeConfig.label }}
    </span>
</template>
