<script setup lang="ts">
import DtrStatusBadge from '@/components/Dtr/DtrStatusBadge.vue';
import DtrSummaryCard from '@/components/Dtr/DtrSummaryCard.vue';
import EnumSelect from '@/components/EnumSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertCircle, Clock } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Department {
    id: number;
    name: string;
}

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
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
    employee: {
        id: number;
        full_name: string;
        employee_number: string;
        department?: { name: string };
        position?: { title: string };
    } | null;
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

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedRecords {
    data: DtrRecord[];
    links: PaginationLink[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

interface Filters {
    date_from: string | null;
    date_to: string | null;
    department_id: string | null;
    employee_id: string | null;
    status: string | null;
    needs_review: string | null;
}

const props = defineProps<{
    records: PaginatedRecords;
    departments: Department[];
    employees: Employee[];
    summary: DtrSummary;
    filters: Filters;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Daily Time Record', href: '/time-attendance/dtr' },
];

// Filter state
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const departmentFilter = ref(props.filters?.department_id || '');
const employeeFilter = ref(props.filters?.employee_id || '');
const statusFilter = ref(props.filters?.status || '');
const needsReviewFilter = ref(props.filters?.needs_review || '');
const showFilters = ref(true);

// Status options
const statusOptions = [
    { value: '', label: 'All Status' },
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'holiday', label: 'Holiday' },
    { value: 'rest_day', label: 'Rest Day' },
    { value: 'no_schedule', label: 'No Schedule' },
];

const reviewOptions = [
    { value: '', label: 'All Records' },
    { value: '1', label: 'Needs Review' },
    { value: '0', label: 'No Issues' },
];

// Dropdown options
const departmentOptions = computed(() => {
    return [
        { value: '', label: 'All Departments' },
        ...(props.departments || []).map((dept) => ({
            value: dept.id.toString(),
            label: dept.name,
        })),
    ];
});

const employeeOptions = computed(() => {
    return [
        { value: '', label: 'All Employees' },
        ...(props.employees || []).map((emp) => ({
            value: emp.id.toString(),
            label: `${emp.full_name} (${emp.employee_number})`,
        })),
    ];
});

// Computed counts
const recordCount = computed(() => props.records?.data?.length || 0);
const totalCount = computed(() => props.records?.meta?.total || recordCount.value);

function applyFilters() {
    router.get(
        '/time-attendance/dtr',
        {
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            department_id: departmentFilter.value || undefined,
            employee_id: employeeFilter.value || undefined,
            status: statusFilter.value || undefined,
            needs_review: needsReviewFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function clearFilters() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    dateFrom.value = firstDay.toISOString().split('T')[0];
    dateTo.value = today.toISOString().split('T')[0];
    departmentFilter.value = '';
    employeeFilter.value = '';
    statusFilter.value = '';
    needsReviewFilter.value = '';
    router.get(
        '/time-attendance/dtr',
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

// Watch filter changes
watch([departmentFilter, employeeFilter, statusFilter, needsReviewFilter], () => {
    applyFilters();
});

function handleDateChange() {
    applyFilters();
}

function goToPage(url: string | null) {
    if (url) {
        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
        });
    }
}

function viewEmployeeDtr(employeeId: number) {
    router.visit(`/time-attendance/dtr/${employeeId}`, {
        data: {
            date_from: dateFrom.value,
            date_to: dateTo.value,
        },
    });
}
</script>

<template>
    <Head :title="`Daily Time Record - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Daily Time Record
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ recordCount }} of {{ totalCount }} records
                    </p>
                </div>
            </div>

            <!-- Summary Cards -->
            <DtrSummaryCard :summary="summary" />

            <!-- Filters Toggle -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <Button variant="outline" @click="showFilters = !showFilters">
                    <svg
                        class="mr-2 h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"
                        />
                    </svg>
                    Filters
                </Button>
            </div>

            <!-- Filter Panel -->
            <div
                v-if="showFilters"
                class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
            >
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
                <div class="w-full sm:w-48">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Department
                    </label>
                    <EnumSelect v-model="departmentFilter" :options="departmentOptions" placeholder="All Departments" />
                </div>
                <div class="w-full sm:w-56">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Employee
                    </label>
                    <EnumSelect v-model="employeeFilter" :options="employeeOptions" placeholder="All Employees" />
                </div>
                <div class="w-full sm:w-40">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Status
                    </label>
                    <EnumSelect v-model="statusFilter" :options="statusOptions" placeholder="All Status" />
                </div>
                <div class="w-full sm:w-40">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Review Status
                    </label>
                    <EnumSelect v-model="needsReviewFilter" :options="reviewOptions" placeholder="All Records" />
                </div>
                <Button variant="ghost" size="sm" @click="clearFilters" class="text-slate-600 dark:text-slate-400">
                    Clear filters
                </Button>
            </div>

            <!-- DTR Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <!-- Desktop Table -->
                <div class="hidden lg:block">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Employee
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Date
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Status
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Time In/Out
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Hours
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Late
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    UT
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    OT
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Review
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="record in records.data"
                                :key="record.id"
                                class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                @click="viewEmployeeDtr(record.employee_id)"
                            >
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div>
                                        <div class="font-medium text-slate-900 dark:text-slate-100">
                                            {{ record.employee?.full_name || 'Unknown' }}
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ record.employee?.employee_number }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm text-slate-900 dark:text-slate-100">
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
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1 text-sm">
                                        <span class="text-slate-900 dark:text-slate-100">{{ record.first_in || '--:--' }}</span>
                                        <span class="text-slate-400">-</span>
                                        <span class="text-slate-900 dark:text-slate-100">{{ record.last_out || '--:--' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">
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
                                            Approved
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                                        >
                                            Pending
                                        </span>
                                    </div>
                                    <span v-else class="text-sm text-slate-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <div v-if="record.needs_review" class="flex items-center justify-center">
                                        <AlertCircle class="h-4 w-4 text-amber-500" />
                                    </div>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List -->
                <div class="divide-y divide-slate-200 lg:hidden dark:divide-slate-700">
                    <div
                        v-for="record in records.data"
                        :key="record.id"
                        class="p-4 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        @click="viewEmployeeDtr(record.employee_id)"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ record.employee?.full_name || 'Unknown' }}
                                </div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ record.formatted_date }} ({{ record.day_of_week }})
                                </div>
                            </div>
                            <DtrStatusBadge :status="record.status" :label="record.status_label" />
                        </div>
                        <div class="mt-3 flex flex-wrap gap-4 text-sm">
                            <div class="flex items-center gap-1">
                                <Clock class="h-4 w-4 text-slate-400" />
                                <span>{{ record.first_in || '--:--' }} - {{ record.last_out || '--:--' }}</span>
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
                        {{
                            dateFrom || dateTo || departmentFilter || employeeFilter
                                ? 'Try adjusting your filters.'
                                : 'Time records will appear here once attendance logs are processed.'
                        }}
                    </p>
                </div>

                <!-- Pagination -->
                <div
                    v-if="records.links && records.links.length > 3"
                    class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50 sm:px-6"
                >
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-slate-700 dark:text-slate-300">
                                Showing page
                                <span class="font-medium">{{ records.meta?.current_page || 1 }}</span>
                                of
                                <span class="font-medium">{{ records.meta?.last_page || 1 }}</span>
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <button
                                    v-for="(link, index) in records.links"
                                    :key="index"
                                    :disabled="!link.url"
                                    @click="goToPage(link.url)"
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium"
                                    :class="[
                                        link.active
                                            ? 'z-10 bg-blue-600 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                                            : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 dark:text-slate-300 dark:ring-slate-600 dark:hover:bg-slate-700',
                                        !link.url ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                                        index === 0 ? 'rounded-l-md' : '',
                                        index === records.links.length - 1 ? 'rounded-r-md' : '',
                                    ]"
                                    v-html="link.label"
                                ></button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
