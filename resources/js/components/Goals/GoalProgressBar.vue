<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        progress: number;
        showLabel?: boolean;
        size?: 'sm' | 'md' | 'lg';
        isOverdue?: boolean;
    }>(),
    {
        showLabel: true,
        size: 'md',
        isOverdue: false,
    },
);

const progressValue = computed(() => Math.min(Math.max(props.progress, 0), 100));

const progressColor = computed(() => {
    if (props.isOverdue && progressValue.value < 100) {
        return 'bg-red-500';
    }
    if (progressValue.value >= 100) return 'bg-green-500';
    if (progressValue.value >= 75) return 'bg-emerald-500';
    if (progressValue.value >= 50) return 'bg-blue-500';
    if (progressValue.value >= 25) return 'bg-amber-500';
    return 'bg-slate-400';
});

const sizeClasses = computed(() => {
    const sizes = {
        sm: 'h-1.5',
        md: 'h-2',
        lg: 'h-3',
    };
    return sizes[props.size];
});
</script>

<template>
    <div class="w-full">
        <div v-if="showLabel" class="mb-1 flex items-center justify-between text-xs">
            <span class="text-slate-500 dark:text-slate-400">Progress</span>
            <span class="font-medium text-slate-700 dark:text-slate-300">
                {{ progressValue.toFixed(0) }}%
            </span>
        </div>
        <div
            :class="[
                'w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700',
                sizeClasses,
            ]"
        >
            <div
                :class="['transition-all duration-300 ease-out rounded-full', sizeClasses, progressColor]"
                :style="{ width: `${progressValue}%` }"
            />
        </div>
    </div>
</template>
