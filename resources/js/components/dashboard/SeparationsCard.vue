<script setup lang="ts">
/**
 * SeparationsCard Component
 *
 * Displays the count of employee separations for the current month with trend indicator.
 * Uses StatCard as the base component with inverted trend colors (lower is better).
 */
import StatCard from './StatCard.vue';

interface Props {
    count: number;
    percentageChange: number | null;
}

defineProps<Props>();
</script>

<template>
    <StatCard
        title="Separations"
        :value="count"
        subtitle="This month"
        :trend="percentageChange"
        :invert-trend-colors="true"
        data-test="separations-card"
    >
        <template #icon>
            <!-- User Minus Icon -->
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
                    d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z"
                />
            </svg>
        </template>
    </StatCard>
</template>
