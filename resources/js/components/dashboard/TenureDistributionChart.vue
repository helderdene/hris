<script setup lang="ts">
/**
 * TenureDistributionChart Component
 *
 * Displays a horizontal bar chart showing the distribution of employees
 * by tenure (years of service) across 5 predefined buckets.
 * Uses CSS-based rendering without external charting libraries.
 */
import { computed } from 'vue';

interface TenureDistribution {
    lessThan1Year: number;
    oneToThreeYears: number;
    threeToFiveYears: number;
    fiveToTenYears: number;
    moreThan10Years: number;
}

interface Props {
    distribution: TenureDistribution;
}

const props = defineProps<Props>();

interface TenureBucket {
    key: keyof TenureDistribution;
    label: string;
    count: number;
}

const buckets = computed<TenureBucket[]>(() => [
    {
        key: 'lessThan1Year',
        label: '< 1 year',
        count: props.distribution.lessThan1Year,
    },
    {
        key: 'oneToThreeYears',
        label: '1-3 years',
        count: props.distribution.oneToThreeYears,
    },
    {
        key: 'threeToFiveYears',
        label: '3-5 years',
        count: props.distribution.threeToFiveYears,
    },
    {
        key: 'fiveToTenYears',
        label: '5-10 years',
        count: props.distribution.fiveToTenYears,
    },
    {
        key: 'moreThan10Years',
        label: '> 10 years',
        count: props.distribution.moreThan10Years,
    },
]);

const maxCount = computed(() => {
    return Math.max(
        props.distribution.lessThan1Year,
        props.distribution.oneToThreeYears,
        props.distribution.threeToFiveYears,
        props.distribution.fiveToTenYears,
        props.distribution.moreThan10Years,
        1, // Prevent division by zero
    );
});

/**
 * Calculate the width percentage for a bar.
 * Minimum width of 10% to ensure small values are visible.
 */
function getBarWidth(count: number): string {
    if (count === 0) return '0%';
    const percentage = (count / maxCount.value) * 100;
    return `${Math.max(percentage, 10)}%`;
}
</script>

<template>
    <div
        class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
        data-test="tenure-distribution-chart"
    >
        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
            Tenure Distribution
        </h3>
        <div class="mt-4 space-y-3">
            <div
                v-for="bucket in buckets"
                :key="bucket.key"
                class="flex items-center gap-4"
                :data-test="`tenure-bucket-${bucket.key}`"
            >
                <span
                    class="w-20 shrink-0 text-sm text-slate-600 dark:text-slate-400"
                >
                    {{ bucket.label }}
                </span>
                <div
                    class="relative h-6 flex-1 rounded bg-slate-100 dark:bg-slate-800"
                >
                    <div
                        class="absolute inset-y-0 left-0 flex items-center justify-end rounded bg-emerald-500 px-2 text-sm font-medium text-white transition-all duration-300"
                        :style="{ width: getBarWidth(bucket.count) }"
                        :data-test="`tenure-bar-${bucket.key}`"
                    >
                        <span v-if="bucket.count > 0">{{ bucket.count }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
