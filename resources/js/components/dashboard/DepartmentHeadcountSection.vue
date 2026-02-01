<script setup lang="ts">
/**
 * DepartmentHeadcountSection Component
 *
 * Section displaying department headcount cards in a horizontally scrollable container.
 * Includes a header with title and link to the organization chart.
 */
import { useTenant } from '@/composables/useTenant';
import { Link } from '@inertiajs/vue3';
import DepartmentCard from './DepartmentCard.vue';

interface DepartmentHeadcount {
    id: number;
    name: string;
    employees_count: number;
    color?: string;
}

interface Props {
    departmentHeadcounts: DepartmentHeadcount[];
}

defineProps<Props>();

const { primaryColor } = useTenant();
</script>

<template>
    <div
        class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
        data-test="department-headcount-section"
    >
        <!-- Section Header -->
        <div class="flex items-center justify-between">
            <h3
                class="text-lg font-semibold text-slate-900 dark:text-slate-100"
            >
                Department Headcount
            </h3>
            <Link
                href="/organization/org-chart"
                class="flex items-center gap-1 text-sm font-medium"
                :style="{ color: primaryColor }"
                data-test="view-org-chart-link"
            >
                View Organization Chart
                <!-- Arrow Right Icon -->
                <svg
                    class="h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z"
                        clip-rule="evenodd"
                    />
                </svg>
            </Link>
        </div>

        <!-- Horizontally Scrollable Department Cards -->
        <div
            class="scrollbar-hide mt-4 flex gap-4 overflow-x-auto pb-2"
            data-test="department-cards-container"
        >
            <DepartmentCard
                v-for="(department, index) in departmentHeadcounts"
                :key="department.id"
                :department="department"
                :index="index"
            />
        </div>

        <!-- Empty State -->
        <div
            v-if="!departmentHeadcounts || departmentHeadcounts.length === 0"
            class="flex flex-col items-center justify-center py-8 text-center"
        >
            <svg
                class="h-12 w-12 text-slate-300 dark:text-slate-600"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"
                />
            </svg>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                No departments with active employees found.
            </p>
        </div>
    </div>
</template>

<style scoped>
/* Hide scrollbar but keep scroll functionality */
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
