<script setup lang="ts">
/**
 * TotalHeadcountCard Component
 *
 * Displays the total employee headcount with a distinctive primary color background.
 * Shows total count as primary metric and active count as secondary text.
 */

interface Props {
    total: number;
    active: number;
    backgroundColor?: string;
}

const props = withDefaults(defineProps<Props>(), {
    backgroundColor: '#3b82f6', // Default blue color
});
</script>

<template>
    <div
        class="relative overflow-hidden rounded-xl p-5 text-white"
        :style="{ backgroundColor: props.backgroundColor }"
        data-test="total-headcount-card"
    >
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm font-medium text-white/80">Total Headcount</p>
                <p class="mt-2 text-4xl font-bold">
                    {{ total }}
                </p>
                <p class="mt-1 text-sm text-white/80">{{ active }} active</p>
            </div>
            <div class="rounded-lg bg-white/20 p-2">
                <!-- User Group Icon -->
                <svg
                    class="h-6 w-6"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                    />
                </svg>
            </div>
        </div>
    </div>
</template>
