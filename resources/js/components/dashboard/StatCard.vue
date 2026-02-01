<script setup lang="ts">
/**
 * StatCard Component
 *
 * A reusable stat card component for displaying metrics with optional trend indicators.
 * Used for New Hires, Separations, and Turnover Rate cards.
 */
import { computed } from 'vue';

interface Props {
    title: string;
    value: string | number;
    subtitle: string;
    /**
     * Optional trend percentage change.
     * Positive values show green up arrow, negative values show red down arrow.
     * Null means no trend indicator is displayed.
     */
    trend?: number | null;
    /**
     * Whether this is a metric where lower values are better (e.g., separations).
     * When true, positive changes show red (bad) and negative changes show green (good).
     */
    invertTrendColors?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    trend: null,
    invertTrendColors: false,
});

const hasTrend = computed(
    () => props.trend !== null && props.trend !== undefined,
);

const isPositiveTrend = computed(() => {
    if (!hasTrend.value) return false;
    return props.invertTrendColors ? props.trend! <= 0 : props.trend! >= 0;
});

const trendText = computed(() => {
    if (!hasTrend.value) return '';
    const direction = props.trend! >= 0 ? 'up' : 'down';
    return `${direction} ${Math.abs(props.trend!)}% vs last month`;
});
</script>

<template>
    <div
        class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
        data-test="stat-card"
    >
        <div class="flex items-start justify-between">
            <div>
                <p
                    class="text-sm font-medium text-slate-500 dark:text-slate-400"
                >
                    {{ title }}
                </p>
                <p
                    class="mt-2 text-4xl font-bold text-slate-900 dark:text-slate-100"
                >
                    {{ value }}
                </p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ subtitle }}
                </p>
                <div
                    v-if="hasTrend"
                    class="mt-2 flex items-center gap-1 text-sm"
                    :class="
                        isPositiveTrend ? 'text-emerald-600' : 'text-red-600'
                    "
                    data-test="trend-indicator"
                >
                    <!-- Up Arrow (positive change or negative change when inverted) -->
                    <svg
                        v-if="trend !== null && trend >= 0"
                        class="h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 17a.75.75 0 0 1-.75-.75V5.612L5.29 9.77a.75.75 0 0 1-1.08-1.04l5.25-5.5a.75.75 0 0 1 1.08 0l5.25 5.5a.75.75 0 1 1-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0 1 10 17Z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <!-- Down Arrow (negative change or positive change when inverted) -->
                    <svg
                        v-else
                        class="h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 3a.75.75 0 0 1 .75.75v10.638l3.96-4.158a.75.75 0 1 1 1.08 1.04l-5.25 5.5a.75.75 0 0 1-1.08 0l-5.25-5.5a.75.75 0 1 1 1.08-1.04l3.96 4.158V3.75A.75.75 0 0 1 10 3Z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <span>{{ trendText }}</span>
                </div>
            </div>
            <div class="rounded-lg bg-slate-100 p-2 dark:bg-slate-800">
                <slot name="icon">
                    <!-- Default placeholder icon -->
                    <svg
                        class="h-6 w-6 text-slate-600 dark:text-slate-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5"
                        />
                    </svg>
                </slot>
            </div>
        </div>
    </div>
</template>
