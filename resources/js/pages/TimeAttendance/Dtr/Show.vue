<script setup lang="ts">
import DtrStatusBadge from '@/components/Dtr/DtrStatusBadge.vue';
import DtrSummaryCard from '@/components/Dtr/DtrSummaryCard.vue';
import PunchTimeline from '@/components/Dtr/PunchTimeline.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, ChevronUp } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Punch {
    id: number;
    punch_type: string;
    punch_type_label: string;
    punched_at: string;
    punched_at_full: string;
    is_valid: boolean;
}

interface DtrRecord {
    id: number;
    employee_id: number;
    date: string;
    formatted_date: string;
    day_of_week: string;
    status: string;
    status_label: string;
    first_in: string | null;
    last_out: string | null;
    total_work_hours: number;
    total_work_minutes: number;
    late_minutes: number;
    late_formatted: string;
    undertime_minutes: number;
    undertime_formatted: string;
    overtime_minutes: number;
    overtime_formatted: string;
    overtime_approved: boolean;
    night_diff_minutes: number;
    needs_review: boolean;
    review_reason: string | null;
    remarks: string | null;
    punches: Punch[];
}

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
    department?: { id: number; name: string };
    position?: { id: number; title: string };
    work_location?: { id: number; name: string };
}

interface DtrSummary {
    period: {
        start_date: string;
        end_date: string;
        total_days: number;
    };
    attendance: {
        present_days: number;
        absent_days: number;
        holiday_days: number;
        rest_days: number;
        no_schedule_days: number;
        attendance_rate: number;
    };
    time_summary: {
        total_work_hours: number;
        average_daily_work_hours: number;
    };
    late_undertime: {
        total_late_hours: number;
        late_days: number;
        total_undertime_hours: number;
        undertime_days: number;
    };
    overtime: {
        total_overtime_hours: number;
        approved_overtime_hours: number;
        pending_overtime_hours: number;
        overtime_days: number;
    };
    review: {
        needs_review_count: number;
    };
}

interface Filters {
    date_from: string | null;
    date_to: string | null;
}

const props = defineProps<{
    employee: Employee;
    records: { data: DtrRecord[] };
    summary: DtrSummary;
    filters: Filters;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Daily Time Record', href: '/time-attendance/dtr' },
    { title: props.employee.full_name, href: `/time-attendance/dtr/${props.employee.id}` },
];

// Filter state
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

// Track expanded rows
const expandedRows = ref<Set<number>>(new Set());

function toggleRow(id: number) {
    if (expandedRows.value.has(id)) {
        expandedRows.value.delete(id);
    } else {
        expandedRows.value.add(id);
    }
}

function applyFilters() {
    router.get(
        `/time-attendance/dtr/${props.employee.id}`,
        {
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function handleDateChange() {
    applyFilters();
}

function goBack() {
    router.visit('/time-attendance/dtr', {
        data: {
            date_from: dateFrom.value,
            date_to: dateTo.value,
        },
    });
}
</script>

<template>
    <Head :title="`DTR - ${employee.full_name} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Back Button and Employee Header -->
            <div class="flex flex-col gap-4">
                <Button variant="ghost" size="sm" class="w-fit" @click="goBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to DTR List
                </Button>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ employee.full_name }}
                        </h1>
                        <div class="mt-1 flex flex-wrap gap-3 text-sm text-slate-500 dark:text-slate-400">
                            <span>{{ employee.employee_number }}</span>
                            <span v-if="employee.department">{{ employee.department.name }}</span>
                            <span v-if="employee.position">{{ employee.position.title }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                <div class="w-full sm:w-40">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Date From
                    </label>
                    <Input v-model="dateFrom" type="date" @change="handleDateChange" />
                </div>
                <div class="w-full sm:w-40">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Date To
                    </label>
                    <Input v-model="dateTo" type="date" @change="handleDateChange" />
                </div>
            </div>

            <!-- Summary Cards -->
            <DtrSummaryCard :summary="summary" />

            <!-- DTR Records Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div class="hidden lg:block">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="w-10 px-4 py-3"></th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Date
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Status
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Time In
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Time Out
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Work Hours
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Late
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Undertime
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Overtime
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Night Diff
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <template v-for="record in records.data" :key="record.id">
                                <tr
                                    class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                    :class="{ 'bg-amber-50 dark:bg-amber-900/10': record.needs_review }"
                                    @click="toggleRow(record.id)"
                                >
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <component
                                            :is="expandedRows.has(record.id) ? ChevronUp : ChevronDown"
                                            class="h-4 w-4 text-slate-400"
                                        />
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                                {{ record.formatted_date }}
                                            </div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ record.day_of_week }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <DtrStatusBadge :status="record.status" :label="record.status_label" />
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">
                                        {{ record.first_in || '--:--' }}
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">
                                        {{ record.last_out || '--:--' }}
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">
                                        {{ record.total_work_hours.toFixed(1) }}h
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span v-if="record.late_minutes > 0" class="text-sm text-red-600 dark:text-red-400">
                                            {{ record.late_formatted }}
                                        </span>
                                        <span v-else class="text-sm text-slate-400">-</span>
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span v-if="record.undertime_minutes > 0" class="text-sm text-amber-600 dark:text-amber-400">
                                            {{ record.undertime_formatted }}
                                        </span>
                                        <span v-else class="text-sm text-slate-400">-</span>
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <div v-if="record.overtime_minutes > 0" class="flex items-center justify-center gap-1">
                                            <span class="text-sm text-green-600 dark:text-green-400">
                                                {{ record.overtime_formatted }}
                                            </span>
                                            <span
                                                v-if="record.overtime_approved"
                                                class="inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                            >
                                                OK
                                            </span>
                                        </div>
                                        <span v-else class="text-sm text-slate-400">-</span>
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span v-if="record.night_diff_minutes > 0" class="text-sm text-purple-600 dark:text-purple-400">
                                            {{ Math.floor(record.night_diff_minutes / 60) }}:{{ String(record.night_diff_minutes % 60).padStart(2, '0') }}
                                        </span>
                                        <span v-else class="text-sm text-slate-400">-</span>
                                    </td>
                                </tr>
                                <!-- Expanded Row with Punch Timeline -->
                                <tr v-if="expandedRows.has(record.id)">
                                    <td colspan="10" class="px-4 py-4 bg-slate-50 dark:bg-slate-800/30">
                                        <div class="flex flex-col gap-3">
                                            <div v-if="record.needs_review" class="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                                <span>{{ record.review_reason }}</span>
                                            </div>
                                            <div v-if="record.remarks" class="text-sm text-slate-600 dark:text-slate-400">
                                                <span class="font-medium">Remarks:</span> {{ record.remarks }}
                                            </div>
                                            <PunchTimeline v-if="record.punches && record.punches.length > 0" :punches="record.punches" />
                                            <div v-else class="text-sm text-slate-500 dark:text-slate-400">
                                                No punch records for this day.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List -->
                <div class="divide-y divide-slate-200 lg:hidden dark:divide-slate-700">
                    <div
                        v-for="record in records.data"
                        :key="record.id"
                        class="p-4"
                        :class="{ 'bg-amber-50 dark:bg-amber-900/10': record.needs_review }"
                    >
                        <div class="flex items-start justify-between" @click="toggleRow(record.id)">
                            <div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ record.formatted_date }}
                                </div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ record.day_of_week }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <DtrStatusBadge :status="record.status" :label="record.status_label" />
                                <component
                                    :is="expandedRows.has(record.id) ? ChevronUp : ChevronDown"
                                    class="h-4 w-4 text-slate-400"
                                />
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="text-slate-500">In:</span>
                                <span class="ml-1 font-medium">{{ record.first_in || '--:--' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500">Out:</span>
                                <span class="ml-1 font-medium">{{ record.last_out || '--:--' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500">Work:</span>
                                <span class="ml-1 font-medium">{{ record.total_work_hours.toFixed(1) }}h</span>
                            </div>
                            <div v-if="record.late_minutes > 0" class="text-red-600">
                                Late: {{ record.late_formatted }}
                            </div>
                            <div v-if="record.overtime_minutes > 0" class="text-green-600">
                                OT: {{ record.overtime_formatted }}
                            </div>
                        </div>
                        <!-- Expanded Content -->
                        <div v-if="expandedRows.has(record.id)" class="mt-4 rounded-lg bg-slate-100 p-3 dark:bg-slate-800">
                            <div v-if="record.needs_review" class="mb-2 flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>{{ record.review_reason }}</span>
                            </div>
                            <PunchTimeline v-if="record.punches && record.punches.length > 0" :punches="record.punches" />
                            <div v-else class="text-sm text-slate-500">No punch records.</div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!records.data || records.data.length === 0" class="px-6 py-12 text-center">
                    <svg
                        class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"
                        />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No time records found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Try adjusting the date range.
                    </p>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
