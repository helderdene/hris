<script setup lang="ts">
import AdjustmentFormModal from '@/components/AdjustmentFormModal.vue';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Employee {
    id: number;
    employee_number: string;
    full_name: string;
}

interface PayrollPeriod {
    id: number;
    name: string;
    cutoff_start: string;
    cutoff_end: string;
}

interface Adjustment {
    id: number;
    employee_id: number;
    employee?: {
        id: number;
        employee_number: string;
        full_name: string;
    };
    adjustment_category: string;
    adjustment_category_label: string;
    adjustment_category_color: string;
    adjustment_type: string;
    adjustment_type_label: string;
    adjustment_type_group: string;
    adjustment_code: string;
    name: string;
    amount: number;
    amount_formatted: string;
    frequency: string;
    frequency_label: string;
    frequency_color: string;
    has_balance_tracking: boolean;
    remaining_balance: number | null;
    remaining_balance_formatted: string | null;
    progress_percentage: number | null;
    status: string;
    status_label: string;
    status_color: string;
    recurring_start_date: string | null;
    recurring_end_date: string | null;
    created_at: string;
}

interface AdjustmentTypeOption {
    value: string;
    label: string;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface FrequencyOption {
    value: string;
    label: string;
    color: string;
}

interface RecurringIntervalOption {
    value: string;
    label: string;
    description: string;
}

interface Filters {
    status: string | null;
    category: string | null;
    adjustment_type: string | null;
    frequency: string | null;
    employee_id: number | null;
}

interface Summary {
    total_adjustments: number;
    active_adjustments: number;
    total_earnings: number;
    total_deductions: number;
}

const props = defineProps<{
    adjustments: { data: Adjustment[] };
    employees: Employee[];
    payrollPeriods: PayrollPeriod[];
    adjustmentTypes: Record<string, Record<string, AdjustmentTypeOption[]>>;
    adjustmentCategories: StatusOption[];
    adjustmentStatuses: StatusOption[];
    adjustmentFrequencies: FrequencyOption[];
    recurringIntervals: RecurringIntervalOption[];
    filters: Filters;
    summary: Summary;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/organization/payroll-periods' },
    { title: 'Adjustments', href: '/payroll/adjustments' },
];

const selectedTab = ref(props.filters.category || 'all');
const selectedStatus = ref(props.filters.status || 'all');
const selectedFrequency = ref(props.filters.frequency || 'all');
const selectedEmployee = ref(
    props.filters.employee_id ? String(props.filters.employee_id) : 'all',
);

// Modal states
const isFormModalOpen = ref(false);
const editingAdjustment = ref<Adjustment | null>(null);

const adjustmentsData = computed(() => props.adjustments?.data ?? []);

const employeeOptions = computed(() => [
    { value: 'all', label: 'All Employees' },
    ...props.employees.map((emp) => ({
        value: String(emp.id),
        label: `${emp.full_name} (${emp.employee_number})`,
    })),
]);

function getCategoryBadgeClasses(color: string): string {
    switch (color) {
        case 'green':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'red':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getStatusBadgeClasses(status: string): string {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'completed':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'on_hold':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'cancelled':
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getFrequencyBadgeClasses(frequency: string): string {
    switch (frequency) {
        case 'one_time':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'recurring':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function applyFilters() {
    const params: Record<string, string | number | undefined> = {};

    if (selectedTab.value !== 'all') {
        params.category = selectedTab.value;
    }
    if (selectedStatus.value !== 'all') {
        params.status = selectedStatus.value;
    }
    if (selectedFrequency.value !== 'all') {
        params.frequency = selectedFrequency.value;
    }
    if (selectedEmployee.value !== 'all') {
        params.employee_id = Number(selectedEmployee.value);
    }

    router.get('/payroll/adjustments', params, { preserveState: true });
}

function handleTabChange(value: string) {
    selectedTab.value = value;
    applyFilters();
}

function handleStatusChange(value: string) {
    selectedStatus.value = value;
    applyFilters();
}

function handleFrequencyChange(value: string) {
    selectedFrequency.value = value;
    applyFilters();
}

function handleEmployeeChange(value: string) {
    selectedEmployee.value = value;
    applyFilters();
}

function handleAddAdjustment() {
    editingAdjustment.value = null;
    isFormModalOpen.value = true;
}

function handleEditAdjustment(adjustment: Adjustment) {
    editingAdjustment.value = adjustment;
    isFormModalOpen.value = true;
}

async function handleStatusTransition(adjustment: Adjustment, newStatus: string) {
    const statusLabels: Record<string, string> = {
        active: 'Active',
        on_hold: 'On Hold',
        completed: 'Completed',
        cancelled: 'Cancelled',
    };

    if (
        !confirm(
            `Are you sure you want to change the status to "${statusLabels[newStatus]}"?`,
        )
    ) {
        return;
    }

    try {
        const response = await fetch(`/api/adjustments/${adjustment.id}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ status: newStatus }),
        });

        if (response.ok) {
            router.reload({ only: ['adjustments', 'summary'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to update status');
        }
    } catch {
        alert('An error occurred while updating the status');
    }
}

async function handleDeleteAdjustment(adjustment: Adjustment) {
    if (
        !confirm(
            `Are you sure you want to delete this adjustment (${adjustment.adjustment_code})?`,
        )
    ) {
        return;
    }

    try {
        const response = await fetch(`/api/adjustments/${adjustment.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            router.reload({ only: ['adjustments', 'summary'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete adjustment');
        }
    } catch {
        alert('An error occurred while deleting the adjustment');
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingAdjustment.value = null;
    router.reload({ only: ['adjustments', 'summary'] });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
}

function getAllowedTransitions(status: string): string[] {
    switch (status) {
        case 'active':
            return ['on_hold', 'completed', 'cancelled'];
        case 'on_hold':
            return ['active', 'cancelled'];
        default:
            return [];
    }
}
</script>

<template>
    <Head :title="`Adjustments - ${tenantName}`" />

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
                        Payroll Adjustments
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage allowances, bonuses, deductions, and loan-type adjustments.
                    </p>
                </div>
                <Button
                    @click="handleAddAdjustment"
                    :style="{ backgroundColor: primaryColor }"
                >
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
                            d="M12 4.5v15m7.5-7.5h-15"
                        />
                    </svg>
                    Add Adjustment
                </Button>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Total Adjustments
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ summary.total_adjustments }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Active Adjustments
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400"
                    >
                        {{ summary.active_adjustments }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Active Earnings
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400"
                    >
                        {{ formatCurrency(summary.total_earnings) }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Active Deductions
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400"
                    >
                        {{ formatCurrency(summary.total_deductions) }}
                    </div>
                </div>
            </div>

            <!-- Tabs and Filters -->
            <div class="flex flex-col gap-4">
                <Tabs :model-value="selectedTab" @update:model-value="handleTabChange">
                    <TabsList>
                        <TabsTrigger value="all">All</TabsTrigger>
                        <TabsTrigger value="earning">Earnings</TabsTrigger>
                        <TabsTrigger value="deduction">Deductions</TabsTrigger>
                    </TabsList>
                </Tabs>

                <div class="flex flex-wrap items-center gap-3">
                    <Select
                        :model-value="selectedStatus"
                        @update:model-value="handleStatusChange"
                    >
                        <SelectTrigger class="w-40">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem
                                v-for="status in adjustmentStatuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        :model-value="selectedFrequency"
                        @update:model-value="handleFrequencyChange"
                    >
                        <SelectTrigger class="w-40">
                            <SelectValue placeholder="Frequency" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Frequencies</SelectItem>
                            <SelectItem
                                v-for="freq in adjustmentFrequencies"
                                :key="freq.value"
                                :value="freq.value"
                            >
                                {{ freq.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        :model-value="selectedEmployee"
                        @update:model-value="handleEmployeeChange"
                    >
                        <SelectTrigger class="w-56">
                            <SelectValue placeholder="Employee" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in employeeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Adjustments Table -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="hidden md:block">
                    <table
                        class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                    >
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
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
                                    Adjustment
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Amount
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Frequency
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Status
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <tr
                                v-for="adjustment in adjustmentsData"
                                :key="adjustment.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ adjustment.employee?.full_name }}
                                    </div>
                                    <div
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ adjustment.employee?.employee_number }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                            :class="getCategoryBadgeClasses(adjustment.adjustment_category_color)"
                                        >
                                            {{ adjustment.adjustment_category_label }}
                                        </span>
                                        <span
                                            class="font-medium text-slate-900 dark:text-slate-100"
                                        >
                                            {{ adjustment.name }}
                                        </span>
                                    </div>
                                    <div
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ adjustment.adjustment_code }} · {{ adjustment.adjustment_type_group }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ formatCurrency(adjustment.amount) }}
                                    </div>
                                    <div
                                        v-if="adjustment.has_balance_tracking && adjustment.remaining_balance !== null"
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Bal: {{ adjustment.remaining_balance_formatted }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getFrequencyBadgeClasses(adjustment.frequency)"
                                    >
                                        {{ adjustment.frequency_label }}
                                    </span>
                                    <div
                                        v-if="adjustment.has_balance_tracking && adjustment.progress_percentage !== null"
                                        class="mt-1 flex items-center gap-2"
                                    >
                                        <div
                                            class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
                                        >
                                            <div
                                                class="h-full bg-green-500"
                                                :style="{ width: `${adjustment.progress_percentage}%` }"
                                            />
                                        </div>
                                        <span class="text-xs text-slate-500">
                                            {{ adjustment.progress_percentage }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <DropdownMenu
                                        v-if="getAllowedTransitions(adjustment.status).length > 0"
                                    >
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
                                                :class="getStatusBadgeClasses(adjustment.status)"
                                            >
                                                {{ adjustment.status_label }}
                                                <svg
                                                    class="h-3 w-3"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M19.5 8.25l-7.5 7.5-7.5-7.5"
                                                    />
                                                </svg>
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="start">
                                            <DropdownMenuLabel>Change Status</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                v-for="newStatus in getAllowedTransitions(adjustment.status)"
                                                :key="newStatus"
                                                @click="handleStatusTransition(adjustment, newStatus)"
                                            >
                                                {{
                                                    adjustmentStatuses.find(
                                                        (s) => s.value === newStatus,
                                                    )?.label || newStatus
                                                }}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(adjustment.status)"
                                    >
                                        {{ adjustment.status_label }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                                <span class="sr-only">Open menu</span>
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
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                v-if="adjustment.status === 'active' || adjustment.status === 'on_hold'"
                                                @click="handleEditAdjustment(adjustment)"
                                            >
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                @click="handleDeleteAdjustment(adjustment)"
                                            >
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile View -->
                <div class="md:hidden divide-y divide-slate-200 dark:divide-slate-700">
                    <div
                        v-for="adjustment in adjustmentsData"
                        :key="adjustment.id"
                        class="p-4 space-y-2"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ adjustment.employee?.full_name }}
                                </div>
                                <div class="text-sm text-slate-500">
                                    {{ adjustment.name }} · {{ adjustment.adjustment_code }}
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClasses(adjustment.status)"
                            >
                                {{ adjustment.status_label }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Amount:</span>
                            <span class="font-medium">{{ formatCurrency(adjustment.amount) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="adjustmentsData.length === 0"
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
                            d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No adjustments found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Get started by adding a payroll adjustment.
                    </p>
                    <div class="mt-6">
                        <Button
                            @click="handleAddAdjustment"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            Add Adjustment
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <AdjustmentFormModal
            v-model:open="isFormModalOpen"
            :adjustment="editingAdjustment"
            :employees="employees"
            :payroll-periods="payrollPeriods"
            :adjustment-types="adjustmentTypes"
            :recurring-intervals="recurringIntervals"
            @success="handleFormSuccess"
        />
    </TenantLayout>
</template>
