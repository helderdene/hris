<script setup lang="ts">
/**
 * EmploymentTypeChart Component
 *
 * Displays a stacked horizontal bar chart showing the distribution of employees
 * by employment type with a color-coded legend.
 * Uses CSS-based rendering without external charting libraries.
 */
import { computed } from 'vue';

interface EmploymentTypeBreakdown {
    regular: number;
    probationary: number;
    contractual: number;
    project_based: number;
    [key: string]: number;
}

interface Props {
    breakdown: EmploymentTypeBreakdown;
}

const props = defineProps<Props>();

interface TypeConfig {
    key: string;
    label: string;
    color: string;
    bgClass: string;
    dotClass: string;
}

const typeConfigs: TypeConfig[] = [
    {
        key: 'regular',
        label: 'Regular',
        color: '#10b981',
        bgClass: 'bg-emerald-500',
        dotClass: 'bg-emerald-500',
    },
    {
        key: 'probationary',
        label: 'Probationary',
        color: '#3b82f6',
        bgClass: 'bg-blue-500',
        dotClass: 'bg-blue-500',
    },
    {
        key: 'contractual',
        label: 'Contractual',
        color: '#f59e0b',
        bgClass: 'bg-amber-500',
        dotClass: 'bg-amber-500',
    },
    {
        key: 'project_based',
        label: 'Project-based',
        color: '#94a3b8',
        bgClass: 'bg-slate-400',
        dotClass: 'bg-slate-400',
    },
];

const total = computed(() => {
    return (
        (props.breakdown.regular || 0) +
        (props.breakdown.probationary || 0) +
        (props.breakdown.contractual || 0) +
        (props.breakdown.project_based || 0)
    );
});

/**
 * Get the count for a specific employment type.
 */
function getCount(key: string): number {
    return props.breakdown[key] || 0;
}

/**
 * Calculate the width percentage for a segment.
 */
function getSegmentWidth(key: string): string {
    if (total.value === 0) return '0%';
    const count = getCount(key);
    return `${(count / total.value) * 100}%`;
}
</script>

<template>
    <div
        class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
        data-test="employment-type-chart"
    >
        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
            Employment Status
        </h3>
        <div class="mt-4">
            <!-- Stacked Bar -->
            <div
                class="flex h-8 overflow-hidden rounded-lg"
                data-test="stacked-bar"
            >
                <div
                    v-for="config in typeConfigs"
                    v-show="getCount(config.key) > 0"
                    :key="config.key"
                    class="flex items-center justify-center text-xs font-medium text-white transition-all duration-300"
                    :class="config.bgClass"
                    :style="{ width: getSegmentWidth(config.key) }"
                    :data-test="`segment-${config.key}`"
                />
            </div>

            <!-- Legend -->
            <div class="mt-4 grid grid-cols-2 gap-3" data-test="legend">
                <div
                    v-for="config in typeConfigs"
                    :key="config.key"
                    class="flex items-center gap-2"
                    :data-test="`legend-${config.key}`"
                >
                    <div
                        class="h-3 w-3 rounded-full"
                        :class="config.dotClass"
                    />
                    <span class="text-sm text-slate-600 dark:text-slate-400">
                        <span
                            class="font-medium text-slate-900 dark:text-slate-100"
                            :data-test="`legend-count-${config.key}`"
                            >{{ getCount(config.key) }}</span
                        >
                        {{ config.label }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
