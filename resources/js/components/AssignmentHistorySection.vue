<script setup lang="ts">
import AssignmentHistoryTimeline from '@/Components/AssignmentHistoryTimeline.vue';
import { type EmployeeAssignmentHistory } from '@/types/assignment';
import { computed } from 'vue';

interface Props {
    history?: EmployeeAssignmentHistory[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    history: () => [],
    loading: false,
});

/**
 * Sorted history entries with most recent first.
 */
const sortedHistory = computed(() => {
    if (!props.history || props.history.length === 0) {
        return [];
    }

    return [...props.history].sort((a, b) => {
        const dateA = new Date(a.created_at).getTime();
        const dateB = new Date(b.created_at).getTime();
        return dateB - dateA; // Most recent first
    });
});
</script>

<template>
    <div class="space-y-3 sm:space-y-4">
        <!-- Section Header -->
        <div
            class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h2
                    class="text-base font-semibold text-slate-900 sm:text-lg dark:text-slate-100"
                >
                    Assignment History
                </h2>
                <p
                    class="mt-0.5 text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                >
                    Chronological record of position, department, location, and
                    supervisor changes.
                </p>
            </div>
        </div>

        <!-- Timeline Component - responsive padding -->
        <div
            class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6 dark:border-slate-700 dark:bg-slate-900"
        >
            <AssignmentHistoryTimeline
                :history="sortedHistory"
                :loading="loading"
            />
        </div>
    </div>
</template>
