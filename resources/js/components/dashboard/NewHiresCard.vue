<script setup lang="ts">
/**
 * NewHiresCard Component
 *
 * Displays the count of new hires for the current month with trend indicator.
 * Uses StatCard as the base component.
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
        title="New Hires"
        :value="count"
        subtitle="This month"
        :trend="percentageChange"
        data-test="new-hires-card"
    >
        <template #icon>
            <!-- User Plus Icon -->
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
                    d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"
                />
            </svg>
        </template>
    </StatCard>
</template>
