<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    status: string;
}>();

const statusConfig = computed(() => {
    switch (props.status) {
        case 'draft':
            return {
                label: 'Draft',
                bgClass: 'bg-slate-100 dark:bg-slate-800',
                textClass: 'text-slate-700 dark:text-slate-300',
            };
        case 'published':
            return {
                label: 'Published',
                bgClass: 'bg-green-100 dark:bg-green-900/30',
                textClass: 'text-green-700 dark:text-green-400',
            };
        case 'archived':
            return {
                label: 'Archived',
                bgClass: 'bg-amber-100 dark:bg-amber-900/30',
                textClass: 'text-amber-700 dark:text-amber-400',
            };
        default:
            return {
                label: props.status,
                bgClass: 'bg-slate-100 dark:bg-slate-800',
                textClass: 'text-slate-700 dark:text-slate-300',
            };
    }
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
            statusConfig.bgClass,
            statusConfig.textClass,
        ]"
    >
        {{ statusConfig.label }}
    </span>
</template>
