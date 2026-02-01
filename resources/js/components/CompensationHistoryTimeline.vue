<script setup lang="ts">
import { type CompensationHistory } from '@/types/compensation';
import { computed } from 'vue';

interface Props {
    history: CompensationHistory[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

/**
 * Format a date string for display.
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
 * Format salary for display using PHP currency.
 */
function formatSalary(salary: string | null): string {
    if (!salary) return '-';
    const num = parseFloat(salary);
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
}

/**
 * Check if history array has entries.
 */
const hasHistory = computed(() => props.history && props.history.length > 0);

/**
 * Skeleton items for loading state.
 */
const skeletonItems = [1, 2, 3];

/**
 * Get badge text based on whether it's a new record or update.
 */
function getBadgeText(entry: CompensationHistory): string {
    if (!entry.previous_basic_pay) {
        return 'Initial Setup';
    }

    const previousPay = parseFloat(entry.previous_basic_pay);
    const newPay = parseFloat(entry.new_basic_pay);

    if (newPay > previousPay) {
        return 'Salary Increase';
    } else if (newPay < previousPay) {
        return 'Salary Adjustment';
    } else if (entry.previous_pay_type !== entry.new_pay_type) {
        return 'Pay Type Change';
    }

    return 'Update';
}

/**
 * Get badge color classes based on change type.
 */
function getBadgeClasses(entry: CompensationHistory): string {
    if (!entry.previous_basic_pay) {
        return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
    }

    const previousPay = parseFloat(entry.previous_basic_pay);
    const newPay = parseFloat(entry.new_basic_pay);

    if (newPay > previousPay) {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
    } else if (newPay < previousPay) {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
    }

    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
}
</script>

<template>
    <div class="relative" data-test="compensation-history-timeline">
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
                            class="h-5 w-24 animate-pulse rounded bg-slate-200 sm:w-28 dark:bg-slate-700"
                        />
                        <div
                            class="h-4 w-24 animate-pulse rounded bg-slate-200 sm:w-32 dark:bg-slate-700"
                        />
                    </div>
                    <div
                        class="h-16 w-full animate-pulse rounded-lg bg-slate-100 sm:h-20 dark:bg-slate-800"
                    />
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="!hasHistory"
            class="flex flex-col items-center justify-center py-8 text-center sm:py-12"
            data-test="empty-state"
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
                No compensation history
            </h3>
            <p
                class="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400"
            >
                Compensation changes will appear here once recorded.
            </p>
        </div>

        <!-- Timeline Content -->
        <div v-else class="relative">
            <!-- Vertical timeline line -->
            <div
                class="absolute top-2 left-[5px] h-[calc(100%-2rem)] w-0.5 bg-green-200 sm:left-[7px] dark:bg-green-800"
            />

            <!-- Timeline entries -->
            <div
                v-for="(entry, index) in history"
                :key="entry.id"
                class="relative pb-4 last:pb-0 sm:pb-6"
                data-test="timeline-entry"
            >
                <!-- Timeline dot - green color scheme -->
                <div
                    class="absolute top-1.5 left-0 flex h-3 w-3 items-center justify-center rounded-full bg-green-500 ring-2 ring-white sm:h-4 sm:w-4 sm:ring-4 dark:bg-green-400 dark:ring-slate-900"
                >
                    <div
                        class="h-1 w-1 rounded-full bg-white sm:h-1.5 sm:w-1.5"
                    />
                </div>

                <!-- Entry content -->
                <div class="ml-6 sm:ml-8">
                    <!-- Header row with badge and date -->
                    <div
                        class="flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2"
                    >
                        <span
                            class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-medium sm:px-2.5"
                            :class="getBadgeClasses(entry)"
                        >
                            {{ getBadgeText(entry) }}
                        </span>
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
                        <!-- Basic Pay Change -->
                        <div class="text-sm">
                            <span
                                class="font-medium text-slate-700 dark:text-slate-300"
                                >Basic Pay:</span
                            >
                            <div
                                class="mt-1 flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2"
                            >
                                <span
                                    class="text-slate-500 dark:text-slate-400"
                                >
                                    <span
                                        class="mr-1 text-xs text-slate-400 sm:hidden dark:text-slate-500"
                                        >From:</span
                                    >
                                    {{
                                        entry.previous_basic_pay
                                            ? formatSalary(
                                                  entry.previous_basic_pay,
                                              )
                                            : 'N/A'
                                    }}
                                </span>
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
                                    {{ formatSalary(entry.new_basic_pay) }}
                                </span>
                            </div>
                        </div>

                        <!-- Pay Type Change (if different) -->
                        <div
                            v-if="
                                entry.previous_pay_type !==
                                    entry.new_pay_type ||
                                !entry.previous_pay_type
                            "
                            class="mt-2 text-sm"
                        >
                            <span
                                class="font-medium text-slate-700 dark:text-slate-300"
                                >Pay Type:</span
                            >
                            <div
                                class="mt-1 flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2"
                            >
                                <span
                                    class="text-slate-500 dark:text-slate-400"
                                >
                                    <span
                                        class="mr-1 text-xs text-slate-400 sm:hidden dark:text-slate-500"
                                        >From:</span
                                    >
                                    {{ entry.previous_pay_type_label || 'N/A' }}
                                </span>
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
                                    {{ entry.new_pay_type_label }}
                                </span>
                            </div>
                        </div>

                        <!-- Remarks if present -->
                        <p
                            v-if="entry.remarks"
                            class="mt-2 text-xs text-slate-600 sm:text-sm dark:text-slate-300"
                        >
                            <span class="font-medium">Note:</span>
                            {{ entry.remarks }}
                        </p>

                        <!-- Metadata row -->
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
