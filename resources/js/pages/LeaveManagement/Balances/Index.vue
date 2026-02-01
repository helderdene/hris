<script setup lang="ts">
import BalanceAdjustmentModal from '@/components/BalanceAdjustmentModal.vue';
import YearEndProcessingDialog from '@/components/YearEndProcessingDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface LeaveType {
    id: number;
    name: string;
    code: string;
    category: string | null;
    category_label: string | null;
}

interface Department {
    id: number;
    name: string;
}

interface AdjustmentType {
    value: string;
    label: string;
}

interface Summary {
    total_employees: number;
    total_credits: number;
    total_used: number;
    total_pending: number;
    total_available: number;
    utilization_rate: number;
}

interface Filters {
    year: number;
    leave_type_id: number | null;
    department_id: number | null;
}

interface LeaveBalance {
    id: number;
    employee_id: number;
    leave_type_id: number;
    year: number;
    brought_forward: number;
    earned: number;
    used: number;
    pending: number;
    adjustments: number;
    expired: number;
    total_credits: number;
    available: number;
    carry_over_expiry_date: string | null;
    has_expiring_carry_over: boolean;
    employee: {
        id: number;
        employee_number: string;
        full_name: string;
        department: string | null;
        position: string | null;
    };
    leave_type: {
        id: number;
        name: string;
        code: string;
        leave_category: string | null;
        leave_category_label: string | null;
        allow_carry_over: boolean;
        max_carry_over_days: number | null;
    };
}

const props = defineProps<{
    filters: Filters;
    availableYears: number[];
    leaveTypes: LeaveType[];
    departments: Department[];
    adjustmentTypes: AdjustmentType[];
    summary: Summary;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Leave Balances', href: '/organization/leave-balances' },
];

const activeTab = ref('balances');
const selectedYear = ref(String(props.filters.year));
const selectedLeaveTypeId = ref(
    props.filters.leave_type_id ? String(props.filters.leave_type_id) : 'all',
);
const selectedDepartmentId = ref(
    props.filters.department_id ? String(props.filters.department_id) : 'all',
);

// Data loading
const balances = ref<LeaveBalance[]>([]);
const loading = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);

// Modal state
const isAdjustModalOpen = ref(false);
const adjustingBalance = ref<LeaveBalance | null>(null);
const isYearEndDialogOpen = ref(false);

// Safe accessors
const yearsData = computed(() => props.availableYears ?? []);
const leaveTypesData = computed(() => props.leaveTypes ?? []);
const departmentsData = computed(() => props.departments ?? []);
const summaryData = computed(() => props.summary ?? {
    total_employees: 0,
    total_credits: 0,
    total_used: 0,
    total_pending: 0,
    total_available: 0,
    utilization_rate: 0,
});

const leaveTypeOptions = computed(() => [
    { value: 'all', label: 'All Leave Types' },
    ...leaveTypesData.value.map((lt) => ({
        value: String(lt.id),
        label: lt.name,
    })),
]);

const departmentOptions = computed(() => [
    { value: 'all', label: 'All Departments' },
    ...departmentsData.value.map((d) => ({
        value: String(d.id),
        label: d.name,
    })),
]);

async function loadBalances() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        params.set('year', selectedYear.value);
        params.set('page', String(currentPage.value));
        params.set('per_page', '25');

        if (selectedLeaveTypeId.value !== 'all') {
            params.set('leave_type_id', selectedLeaveTypeId.value);
        }
        if (selectedDepartmentId.value !== 'all') {
            params.set('department_id', selectedDepartmentId.value);
        }

        const response = await fetch(
            `/api/organization/leave-balances?${params.toString()}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        if (response.ok) {
            const data = await response.json();
            balances.value = data.data ?? [];
            totalPages.value = data.meta?.last_page ?? 1;
        }
    } catch (error) {
        console.error('Failed to load balances:', error);
    } finally {
        loading.value = false;
    }
}

function handleYearChange(year: string) {
    selectedYear.value = year;
    currentPage.value = 1;
    router.get(
        '/organization/leave-balances',
        {
            year: year,
            leave_type_id:
                selectedLeaveTypeId.value !== 'all'
                    ? selectedLeaveTypeId.value
                    : undefined,
            department_id:
                selectedDepartmentId.value !== 'all'
                    ? selectedDepartmentId.value
                    : undefined,
        },
        { preserveState: true },
    );
    loadBalances();
}

function handleLeaveTypeChange(leaveTypeId: string) {
    selectedLeaveTypeId.value = leaveTypeId;
    currentPage.value = 1;
    router.get(
        '/organization/leave-balances',
        {
            year: selectedYear.value,
            leave_type_id: leaveTypeId !== 'all' ? leaveTypeId : undefined,
            department_id:
                selectedDepartmentId.value !== 'all'
                    ? selectedDepartmentId.value
                    : undefined,
        },
        { preserveState: true },
    );
    loadBalances();
}

function handleDepartmentChange(departmentId: string) {
    selectedDepartmentId.value = departmentId;
    currentPage.value = 1;
    router.get(
        '/organization/leave-balances',
        {
            year: selectedYear.value,
            leave_type_id:
                selectedLeaveTypeId.value !== 'all'
                    ? selectedLeaveTypeId.value
                    : undefined,
            department_id: departmentId !== 'all' ? departmentId : undefined,
        },
        { preserveState: true },
    );
    loadBalances();
}

function handleAdjustBalance(balance: LeaveBalance) {
    adjustingBalance.value = balance;
    isAdjustModalOpen.value = true;
}

function handleAdjustmentSuccess() {
    isAdjustModalOpen.value = false;
    adjustingBalance.value = null;
    loadBalances();
    router.reload({ only: ['summary'] });
}

async function handleInitializeBalances() {
    if (
        !confirm(
            `Initialize leave balances for all active employees for ${selectedYear.value}?`,
        )
    ) {
        return;
    }

    try {
        const response = await fetch('/api/organization/leave-balances/initialize', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ year: parseInt(selectedYear.value) }),
        });

        const data = await response.json();

        if (response.ok) {
            alert(data.message);
            loadBalances();
            router.reload({ only: ['summary'] });
        } else {
            alert(data.message || 'Failed to initialize balances');
        }
    } catch {
        alert('An error occurred while initializing balances');
    }
}

function handleYearEndSuccess() {
    isYearEndDialogOpen.value = false;
    loadBalances();
    router.reload({ only: ['summary', 'availableYears'] });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function formatNumber(value: number): string {
    return value.toFixed(2);
}

onMounted(() => {
    loadBalances();
});
</script>

<template>
    <Head :title="`Leave Balances - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Leave Balances
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage employee leave credits and balances.
                    </p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Employees
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ summaryData.total_employees }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Total Credits
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400"
                    >
                        {{ formatNumber(summaryData.total_credits) }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Used
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-amber-600 dark:text-amber-400"
                    >
                        {{ formatNumber(summaryData.total_used) }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Available
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400"
                    >
                        {{ formatNumber(summaryData.total_available) }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Utilization
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ summaryData.utilization_rate }}%
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="mb-4">
                    <TabsTrigger value="balances">Balances</TabsTrigger>
                    <TabsTrigger value="processing">Processing</TabsTrigger>
                </TabsList>

                <!-- Balances Tab -->
                <TabsContent value="balances">
                    <div class="flex flex-col gap-4">
                        <!-- Filters -->
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex flex-wrap items-center gap-3">
                                <Select
                                    :model-value="selectedYear"
                                    @update:model-value="handleYearChange"
                                >
                                    <SelectTrigger class="w-32">
                                        <SelectValue placeholder="Year" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="year in yearsData"
                                            :key="year"
                                            :value="String(year)"
                                        >
                                            {{ year }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <Select
                                    :model-value="selectedLeaveTypeId"
                                    @update:model-value="handleLeaveTypeChange"
                                >
                                    <SelectTrigger class="w-48">
                                        <SelectValue placeholder="Leave Type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in leaveTypeOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <Select
                                    :model-value="selectedDepartmentId"
                                    @update:model-value="handleDepartmentChange"
                                >
                                    <SelectTrigger class="w-48">
                                        <SelectValue placeholder="Department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in departmentOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button
                                variant="outline"
                                @click="handleInitializeBalances"
                            >
                                Initialize Balances
                            </Button>
                        </div>

                        <!-- Table -->
                        <div
                            class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                        >
                            <div v-if="loading" class="px-6 py-12 text-center">
                                <div
                                    class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-slate-300 border-t-blue-600"
                                ></div>
                                <p
                                    class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Loading balances...
                                </p>
                            </div>

                            <div v-else-if="balances.length > 0" class="hidden md:block">
                                <table
                                    class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                                >
                                    <thead
                                        class="bg-slate-50 dark:bg-slate-800/50"
                                    >
                                        <tr>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Employee
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Leave Type
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                B/F
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Earned
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Used
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Pending
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Available
                                            </th>
                                            <th
                                                scope="col"
                                                class="relative px-6 py-3"
                                            >
                                                <span class="sr-only"
                                                    >Actions</span
                                                >
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-200 dark:divide-slate-700"
                                    >
                                        <tr
                                            v-for="balance in balances"
                                            :key="balance.id"
                                            class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                        >
                                            <td class="px-6 py-4">
                                                <div
                                                    class="font-medium text-slate-900 dark:text-slate-100"
                                                >
                                                    {{
                                                        balance.employee
                                                            ?.full_name ||
                                                        'Unknown'
                                                    }}
                                                </div>
                                                <div
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        balance.employee
                                                            ?.employee_number
                                                    }}
                                                    -
                                                    {{
                                                        balance.employee
                                                            ?.department ||
                                                        'No Dept'
                                                    }}
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <div
                                                    class="text-sm text-slate-900 dark:text-slate-100"
                                                >
                                                    {{
                                                        balance.leave_type
                                                            ?.name || 'Unknown'
                                                    }}
                                                </div>
                                                <div
                                                    class="text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        balance.leave_type?.code
                                                    }}
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right text-sm whitespace-nowrap"
                                            >
                                                <span
                                                    :class="
                                                        balance.brought_forward >
                                                        0
                                                            ? 'text-blue-600 dark:text-blue-400'
                                                            : 'text-slate-400 dark:text-slate-500'
                                                    "
                                                >
                                                    {{
                                                        formatNumber(
                                                            balance.brought_forward,
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right text-sm whitespace-nowrap"
                                            >
                                                {{
                                                    formatNumber(balance.earned)
                                                }}
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right text-sm whitespace-nowrap"
                                            >
                                                <span
                                                    :class="
                                                        balance.used > 0
                                                            ? 'text-amber-600 dark:text-amber-400'
                                                            : 'text-slate-400 dark:text-slate-500'
                                                    "
                                                >
                                                    {{
                                                        formatNumber(
                                                            balance.used,
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right text-sm whitespace-nowrap"
                                            >
                                                <span
                                                    :class="
                                                        balance.pending > 0
                                                            ? 'text-purple-600 dark:text-purple-400'
                                                            : 'text-slate-400 dark:text-slate-500'
                                                    "
                                                >
                                                    {{
                                                        formatNumber(
                                                            balance.pending,
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right text-sm font-semibold whitespace-nowrap"
                                            >
                                                <span
                                                    :class="
                                                        balance.available > 0
                                                            ? 'text-green-600 dark:text-green-400'
                                                            : 'text-red-600 dark:text-red-400'
                                                    "
                                                >
                                                    {{
                                                        formatNumber(
                                                            balance.available,
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                            >
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        as-child
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            class="h-8 w-8 p-0"
                                                        >
                                                            <span
                                                                class="sr-only"
                                                                >Open menu</span
                                                            >
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
                                                                    d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"
                                                                />
                                                            </svg>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                    >
                                                        <DropdownMenuLabel
                                                            >Actions</DropdownMenuLabel
                                                        >
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            @click="
                                                                handleAdjustBalance(
                                                                    balance,
                                                                )
                                                            "
                                                        >
                                                            Adjust Balance
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-else-if="!loading"
                                class="px-6 py-12 text-center"
                            >
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
                                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
                                    />
                                </svg>
                                <h3
                                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    No leave balances
                                </h3>
                                <p
                                    class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Initialize balances for employees to start
                                    tracking leave credits.
                                </p>
                                <div class="mt-6">
                                    <Button
                                        @click="handleInitializeBalances"
                                        :style="{
                                            backgroundColor: primaryColor,
                                        }"
                                    >
                                        Initialize Balances
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- Processing Tab -->
                <TabsContent value="processing">
                    <div class="flex flex-col gap-4">
                        <div
                            class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                        >
                            <h2
                                class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                            >
                                Year-End Processing
                            </h2>
                            <p
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                Process year-end carry-over and forfeiture of
                                leave balances. This will calculate unused
                                balances, apply carry-over rules, and initialize
                                new year balances.
                            </p>
                            <div class="mt-4">
                                <Button
                                    @click="isYearEndDialogOpen = true"
                                    :style="{ backgroundColor: primaryColor }"
                                >
                                    Process Year-End
                                </Button>
                            </div>
                        </div>

                        <div
                            class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                        >
                            <h2
                                class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                            >
                                Monthly Accrual
                            </h2>
                            <p
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                Monthly accrual is processed automatically on
                                the 1st of each month for leave types with
                                monthly accrual rules.
                            </p>
                        </div>

                        <div
                            class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                        >
                            <h2
                                class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                            >
                                Carry-Over Expiry
                            </h2>
                            <p
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                Carried-over balances are automatically expired
                                when they pass their expiry date. This is
                                checked daily.
                            </p>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>

        <!-- Modals -->
        <BalanceAdjustmentModal
            v-model:open="isAdjustModalOpen"
            :balance="adjustingBalance"
            :adjustment-types="adjustmentTypes"
            @success="handleAdjustmentSuccess"
        />

        <YearEndProcessingDialog
            v-model:open="isYearEndDialogOpen"
            :available-years="yearsData"
            @success="handleYearEndSuccess"
        />
    </TenantLayout>
</template>
