<script setup lang="ts">
/**
 * DepartmentCard Component
 *
 * Displays a single department's headcount with a colored bottom border.
 * Clickable card that navigates to the employee list filtered by department.
 */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Department {
    id: number;
    name: string;
    employees_count: number;
    color?: string;
}

interface Props {
    department: Department;
    index?: number;
}

const props = withDefaults(defineProps<Props>(), {
    index: 0,
});

/**
 * Color palette for department cards.
 * Colors rotate based on the card index.
 */
const departmentColors = [
    '#14B8A6', // Teal
    '#10B981', // Emerald
    '#8B5CF6', // Purple
    '#F59E0B', // Amber
    '#EC4899', // Pink
    '#3B82F6', // Blue
];

const borderColor = computed(() => {
    if (props.department.color) {
        return props.department.color;
    }
    return departmentColors[props.index % departmentColors.length];
});

const employeeListUrl = computed(() => {
    return `/employees?department_id=${props.department.id}`;
});
</script>

<template>
    <Link
        :href="employeeListUrl"
        class="flex min-w-[160px] flex-col rounded-xl border border-b-4 border-slate-200 bg-white p-4 transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
        :style="{ borderBottomColor: borderColor }"
        :data-test="`department-card-${department.id}`"
    >
        <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
            <!-- Building/Department Icon -->
            <svg
                class="h-4 w-4"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"
                />
            </svg>
            <span class="truncate text-sm">{{ department.name }}</span>
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
            {{ department.employees_count }}
        </p>
        <p class="text-sm text-slate-500 dark:text-slate-400">employees</p>
    </Link>
</template>
