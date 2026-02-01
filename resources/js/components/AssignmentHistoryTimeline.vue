<script setup lang="ts">
import { type EmployeeAssignmentHistory } from '@/types/assignment';
import { computed } from 'vue';

interface Props {
    history: EmployeeAssignmentHistory[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

/**
 * Format a date string for display.
 * Uses shorter format on mobile via abbreviated months.
 */
function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

/**
 * Format a date string for display (short version for mobile).
 */
function formatDateShort(dateStr: string | null): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * Format a timestamp for display.
 */
function formatTimestamp(dateStr: string | null): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Get icon color based on assignment type.
 */
function getTypeColor(type: string): string {
    switch (type) {
        case 'position':
            return 'bg-blue-500 dark:bg-blue-400';
        case 'department':
            return 'bg-green-500 dark:bg-green-400';
        case 'location':
            return 'bg-amber-500 dark:bg-amber-400';
        case 'supervisor':
            return 'bg-purple-500 dark:bg-purple-400';
        default:
            return 'bg-slate-500 dark:bg-slate-400';
    }
}

/**
 * Get badge color based on assignment type.
 */
function getTypeBadgeClasses(type: string): string {
    switch (type) {
        case 'position':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 'department':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
        case 'location':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case 'supervisor':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
    }
}

/**
 * Check if history array has entries.
 */
const hasHistory = computed(() => props.history && props.history.length > 0);

/**
 * Skeleton items for loading state.
 */
const skeletonItems = [1, 2, 3];
</script>

<template>
    <div class="relative">
        <!-- Loading State with Skeleton -->
        <div v-if="loading" class="space-y-4 sm:space-y-6">
            <div
                v-for="item in skeletonItems"
                :key="item"
                class="relative flex gap-3 pl-6 sm:gap-4 sm:pl-8"
            >
                <!-- Timeline line and dot skeleton -->
                <div
                    class="absolute top-0 left-0 h-full w-px bg-slate-200 dark:bg-slate-700"
                >
                    <div
                        class="absolute top-1 -left-1 h-3 w-3 animate-pulse rounded-full bg-slate-200 sm:-left-1.5 sm:h-4 sm:w-4 dark:bg-slate-700"
                    />
                </div>

                <!-- Content skeleton -->
                <div class="flex-1 space-y-2 pb-4 sm:space-y-3 sm:pb-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            class="h-5 w-20 animate-pulse rounded bg-slate-200 sm:w-24 dark:bg-slate-700"
                        />
                        <div
                            class="h-4 w-24 animate-pulse rounded bg-slate-200 sm:w-32 dark:bg-slate-700"
                        />
                    </div>
                    <div
                        class="h-14 w-full animate-pulse rounded-lg bg-slate-100 sm:h-16 dark:bg-slate-800"
                    />
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="!hasHistory"
            class="flex flex-col items-center justify-center py-8 text-center sm:py-12"
        >
            <div
                class="rounded-full bg-slate-100 p-2.5 sm:p-3 dark:bg-slate-800"
            >
                <svg
                    class="h-5 w-5 text-slate-400 sm:h-6 sm:w-6 dark:text-slate-500"
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
            </div>
            <h3
                class="mt-3 text-sm font-medium text-slate-900 sm:mt-4 dark:text-slate-100"
            >
                No assignment history yet
            </h3>
            <p
                class="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400"
            >
                Assignment changes will appear here once recorded.
            </p>
        </div>

        <!-- Timeline Content -->
        <div v-else class="relative">
            <!-- Vertical timeline line - adjusted position for mobile -->
            <div
                class="absolute top-2 left-[5px] h-[calc(100%-2rem)] w-0.5 bg-slate-200 sm:left-[7px] dark:bg-slate-700"
            />

            <!-- Timeline entries -->
            <div
                v-for="(entry, index) in history"
                :key="entry.id"
                class="relative pb-4 last:pb-0 sm:pb-6"
                data-test="timeline-entry"
            >
                <!-- Timeline dot - smaller on mobile -->
                <div
                    class="absolute top-1.5 left-0 flex h-3 w-3 items-center justify-center rounded-full ring-2 ring-white sm:h-4 sm:w-4 sm:ring-4 dark:ring-slate-900"
                    :class="getTypeColor(entry.assignment_type.value)"
                >
                    <div
                        class="h-1 w-1 rounded-full bg-white sm:h-1.5 sm:w-1.5"
                    />
                </div>

                <!-- Entry content - reduced left margin on mobile -->
                <div class="ml-6 sm:ml-8">
                    <!-- Header row with type badge and date -->
                    <div
                        class="flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2"
                    >
                        <span
                            class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-medium sm:px-2.5"
                            :class="
                                getTypeBadgeClasses(entry.assignment_type.value)
                            "
                        >
                            {{ entry.assignment_type.label }}
                        </span>
                        <!-- Show shorter date on mobile, full date on larger screens -->
                        <span
                            class="text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                        >
                            <span class="sm:hidden">{{
                                formatDateShort(entry.effective_date)
                            }}</span>
                            <span class="hidden sm:inline"
                                >Effective
                                {{ formatDate(entry.effective_date) }}</span
                            >
                        </span>
                    </div>

                    <!-- Change details card -->
                    <div
                        class="mt-2 rounded-lg border border-slate-200 bg-white p-3 sm:p-4 dark:border-slate-700 dark:bg-slate-800"
                    >
                        <!-- Change description - vertical layout on small mobile, horizontal on larger -->
                        <div
                            class="flex flex-col gap-1 text-sm sm:flex-row sm:items-center sm:gap-2"
                        >
                            <span class="text-slate-500 dark:text-slate-400">
                                <span
                                    class="mr-1 text-xs text-slate-400 sm:hidden dark:text-slate-500"
                                    >From:</span
                                >
                                {{ entry.previous_value_name || 'None' }}
                            </span>
                            <!-- Arrow hidden on very small screens, shown inline on larger -->
                            <svg
                                class="hidden h-4 w-4 shrink-0 text-slate-400 sm:block dark:text-slate-500"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"
                                />
                            </svg>
                            <span
                                class="font-medium text-slate-900 dark:text-slate-100"
                            >
                                <span
                                    class="mr-1 text-xs font-normal text-slate-400 sm:hidden dark:text-slate-500"
                                    >To:</span
                                >
                                {{ entry.new_value_name }}
                            </span>
                        </div>

                        <!-- Remarks if present -->
                        <p
                            v-if="entry.remarks"
                            class="mt-2 text-xs text-slate-600 sm:text-sm dark:text-slate-300"
                        >
                            {{ entry.remarks }}
                        </p>

                        <!-- Metadata row - stack vertically on mobile -->
                        <div
                            class="mt-2 flex flex-col gap-1 border-t border-slate-100 pt-2 text-xs text-slate-500 sm:mt-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-4 sm:gap-y-1 sm:pt-3 dark:border-slate-700 dark:text-slate-400"
                        >
                            <span
                                v-if="entry.changed_by_name"
                                class="flex items-center gap-1"
                            >
                                <svg
                                    class="h-3 w-3 sm:h-3.5 sm:w-3.5"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                                    />
                                </svg>
                                <span class="truncate">{{
                                    entry.changed_by_name
                                }}</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <svg
                                    class="h-3 w-3 sm:h-3.5 sm:w-3.5"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                    />
                                </svg>
                                {{ formatTimestamp(entry.created_at) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
