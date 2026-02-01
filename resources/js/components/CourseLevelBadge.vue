<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    level: string | null;
    label?: string;
}>();

const levelConfig = computed(() => {
    if (!props.level) {
        return null;
    }

    switch (props.level) {
        case 'beginner':
            return {
                label: props.label || 'Beginner',
                bgClass: 'bg-green-100 dark:bg-green-900/30',
                textClass: 'text-green-700 dark:text-green-400',
            };
        case 'intermediate':
            return {
                label: props.label || 'Intermediate',
                bgClass: 'bg-amber-100 dark:bg-amber-900/30',
                textClass: 'text-amber-700 dark:text-amber-400',
            };
        case 'advanced':
            return {
                label: props.label || 'Advanced',
                bgClass: 'bg-red-100 dark:bg-red-900/30',
                textClass: 'text-red-700 dark:text-red-400',
            };
        default:
            return {
                label: props.label || props.level,
                bgClass: 'bg-slate-100 dark:bg-slate-800',
                textClass: 'text-slate-700 dark:text-slate-300',
            };
    }
});
</script>

<template>
    <span
        v-if="levelConfig"
        :class="[
            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
            levelConfig.bgClass,
            levelConfig.textClass,
        ]"
    >
        {{ levelConfig.label }}
    </span>
</template>
