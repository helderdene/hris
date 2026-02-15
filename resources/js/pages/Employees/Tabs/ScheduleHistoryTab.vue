<script setup lang="ts">
interface ScheduleAssignment {
    id: number;
    schedule_name: string | null;
    schedule_type: string | null;
    shift_name: string | null;
    effective_date: string;
    end_date: string | null;
    is_current: boolean;
    is_upcoming: boolean;
}

interface Props {
    history?: ScheduleAssignment[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    history: () => [],
    loading: false,
});

function formatDate(date: string | null): string {
    if (!date) {
        return '-';
    }
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function statusLabel(assignment: ScheduleAssignment): string {
    if (assignment.is_upcoming) {
        return 'Upcoming';
    }
    if (assignment.is_current) {
        return 'Current';
    }
    return 'Past';
}

function statusClasses(assignment: ScheduleAssignment): string {
    if (assignment.is_upcoming) {
        return 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-900/30 dark:text-blue-300 dark:ring-blue-400/30';
    }
    if (assignment.is_current) {
        return 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-900/30 dark:text-green-300 dark:ring-green-400/30';
    }
    return 'bg-slate-50 text-slate-600 ring-slate-500/10 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-400/20';
}
</script>

<template>
    <div class="space-y-3 sm:space-y-4">
        <!-- Section Header -->
        <div>
            <h2
                class="text-base font-semibold text-slate-900 sm:text-lg dark:text-slate-100"
            >
                Schedule History
            </h2>
            <p
                class="mt-0.5 text-xs text-slate-500 sm:text-sm dark:text-slate-400"
            >
                Work schedule assignments and their effective dates.
            </p>
        </div>

        <!-- Loading Skeleton -->
        <div
            v-if="loading"
            class="rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="divide-y divide-slate-200 dark:divide-slate-700">
                <div
                    v-for="i in 3"
                    :key="i"
                    class="flex items-center gap-4 p-4"
                >
                    <div class="flex-1 space-y-2">
                        <div
                            class="h-4 w-40 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                        />
                        <div
                            class="h-3 w-24 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                        />
                    </div>
                    <div
                        class="h-3 w-20 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                    />
                    <div
                        class="h-5 w-16 animate-pulse rounded-full bg-slate-200 dark:bg-slate-700"
                    />
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="!history || history.length === 0"
            class="rounded-lg border border-slate-200 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900"
        >
            <svg
                class="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                />
            </svg>
            <p
                class="mt-2 text-sm text-slate-500 dark:text-slate-400"
            >
                No schedule assignments found.
            </p>
        </div>

        <!-- Schedule History Table -->
        <div
            v-else
            class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                Schedule
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                Type
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                Shift
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                Effective Date
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                End Date
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <tr
                            v-for="assignment in history"
                            :key="assignment.id"
                            class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        >
                            <td
                                class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ assignment.schedule_name || '-' }}
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-3 text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ assignment.schedule_type || '-' }}
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-3 text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ assignment.shift_name || '-' }}
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-3 text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ formatDate(assignment.effective_date) }}
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-3 text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ formatDate(assignment.end_date) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                        statusClasses(assignment),
                                    ]"
                                >
                                    {{ statusLabel(assignment) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
