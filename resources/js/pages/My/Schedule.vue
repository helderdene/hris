<script setup lang="ts">
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface ScheduleAssignment {
    id: number;
    schedule_name: string | null;
    schedule_type: string | null;
    shift_name: string | null;
    time_configuration: Record<string, unknown> | null;
    effective_date: string;
    end_date: string | null;
    is_current: boolean;
    is_upcoming: boolean;
}

const props = defineProps<{
    scheduleHistory: ScheduleAssignment[];
    currentSchedule: ScheduleAssignment | null;
    hasEmployeeProfile: boolean;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'My Schedule', href: '/my/schedule' },
];

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

/**
 * Extract work hours summary from time_configuration if available.
 */
function getWorkHours(config: Record<string, unknown> | null): string | null {
    if (!config) {
        return null;
    }

    const startTime = config.start_time as string | undefined;
    const endTime = config.end_time as string | undefined;

    if (startTime && endTime) {
        return `${startTime} - ${endTime}`;
    }

    return null;
}
</script>

<template>
    <Head :title="`My Schedule - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">
                    My Schedule
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    View your current and historical work schedule assignments.
                </p>
            </div>

            <!-- No Employee Profile -->
            <div
                v-if="!hasEmployeeProfile"
                class="rounded-lg border border-slate-200 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    No employee profile found. Please contact HR for assistance.
                </p>
            </div>

            <template v-else>
                <!-- Current Schedule Card -->
                <div
                    v-if="currentSchedule"
                    class="rounded-lg border border-green-200 bg-green-50/50 p-5 dark:border-green-800 dark:bg-green-900/20"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-green-600 dark:text-green-400">
                                Current Schedule
                            </p>
                            <h2 class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">
                                {{ currentSchedule.schedule_name || 'Unnamed Schedule' }}
                            </h2>
                            <div class="mt-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-slate-600 dark:text-slate-300">
                                <span v-if="currentSchedule.schedule_type">
                                    Type: <strong>{{ currentSchedule.schedule_type }}</strong>
                                </span>
                                <span v-if="currentSchedule.shift_name">
                                    Shift: <strong>{{ currentSchedule.shift_name }}</strong>
                                </span>
                                <span v-if="getWorkHours(currentSchedule.time_configuration)">
                                    Hours: <strong>{{ getWorkHours(currentSchedule.time_configuration) }}</strong>
                                </span>
                                <span>
                                    Effective: <strong>{{ formatDate(currentSchedule.effective_date) }}</strong>
                                </span>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-900/50 dark:text-green-300 dark:ring-green-400/30">
                            Active
                        </span>
                    </div>
                </div>

                <!-- No Current Schedule -->
                <div
                    v-else
                    class="rounded-lg border border-amber-200 bg-amber-50/50 p-5 dark:border-amber-800 dark:bg-amber-900/20"
                >
                    <p class="text-sm text-amber-700 dark:text-amber-300">
                        No active schedule assignment. Please contact HR for assistance.
                    </p>
                </div>

                <!-- Schedule History Table -->
                <div>
                    <h2 class="mb-3 text-base font-semibold text-slate-900 dark:text-slate-100">
                        Schedule History
                    </h2>

                    <div
                        v-if="scheduleHistory.length === 0"
                        class="rounded-lg border border-slate-200 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900"
                    >
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            No schedule assignments found.
                        </p>
                    </div>

                    <div
                        v-else
                        class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-800/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            Schedule
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            Type
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            Shift
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            Effective Date
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            End Date
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                    <tr
                                        v-for="assignment in scheduleHistory"
                                        :key="assignment.id"
                                        class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                    >
                                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-900 dark:text-slate-100">
                                            {{ assignment.schedule_name || '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                            {{ assignment.schedule_type || '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                            {{ assignment.shift_name || '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                            {{ formatDate(assignment.effective_date) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
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
        </div>
    </TenantLayout>
</template>
