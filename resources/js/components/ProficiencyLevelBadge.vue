<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    level: number;
    name?: string;
    showLevel?: boolean;
    size?: 'sm' | 'md' | 'lg';
}>();

const levelNames: Record<number, string> = {
    1: 'Novice',
    2: 'Beginner',
    3: 'Competent',
    4: 'Proficient',
    5: 'Expert',
};

const displayName = computed(() => {
    return props.name || levelNames[props.level] || `Level ${props.level}`;
});

const colorClasses = computed(() => {
    switch (props.level) {
        case 1:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-300';
        case 2:
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case 3:
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 4:
            return 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300';
        case 5:
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'px-1.5 py-0.5 text-xs';
        case 'lg':
            return 'px-3 py-1.5 text-sm';
        case 'md':
        default:
            return 'px-2 py-1 text-xs';
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-md font-medium"
        :class="[colorClasses, sizeClasses]"
    >
        <span v-if="showLevel" class="font-semibold">{{ level }}</span>
        <span>{{ displayName }}</span>
    </span>
</template>
