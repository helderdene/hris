<script setup lang="ts">
/**
 * TurnoverRateCard Component
 *
 * Displays the annualized turnover rate percentage and average tenure.
 * Uses StatCard as the base component without trend indicator.
 */
import { computed } from 'vue';
import StatCard from './StatCard.vue';

interface Props {
    rate: number;
    averageTenure: number;
}

const props = defineProps<Props>();

const formattedRate = computed(() => `${props.rate}%`);
const tenureSubtitle = computed(
    () => `Avg tenure: ${props.averageTenure} years`,
);
</script>

<template>
    <StatCard
        title="Turnover Rate"
        :value="formattedRate"
        :subtitle="tenureSubtitle"
        data-test="turnover-rate-card"
    >
        <template #icon>
            <!-- Trend/Chart Icon -->
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
                    d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"
                />
            </svg>
        </template>
    </StatCard>
</template>
