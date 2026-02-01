<script setup lang="ts">
import LoanFormModal from '@/components/LoanFormModal.vue';
import RecordPaymentModal from '@/components/RecordPaymentModal.vue';
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

interface Loan {
    id: number;
    employee_id: number;
    employee?: {
        id: number;
        employee_number: string;
        full_name: string;
    };
    loan_type: string;
    loan_type_label: string;
    loan_type_category: string;
    loan_code: string;
    reference_number: string | null;
    monthly_deduction: number;
    remaining_balance: number;
    monthly_deduction_formatted: string;
    remaining_balance_formatted: string;
    progress_percentage: number;
    status: string;
    status_label: string;
    status_color: string;
    start_date: string;
    expected_end_date: string | null;
}

interface LoanTypeOption {
    value: string;
    label: string;
}

interface LoanStatusOption {
    value: string;
    label: string;
    color: string;
}

interface Filters {
    status: string | null;
    loan_type: string | null;
    employee_id: number | null;
    category: string | null;
}

interface Summary {
    total_loans: number;
    active_loans: number;
    total_outstanding: number;
    total_monthly_deductions: number;
}

const props = defineProps<{
    loans: { data: Loan[] };
    employees: Employee[];
    loanTypes: Record<string, LoanTypeOption[]>;
    loanStatuses: LoanStatusOption[];
    filters: Filters;
    summary: Summary;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/organization/payroll-periods' },
    { title: 'Loans', href: '/payroll/loans' },
];

const selectedStatus = ref(props.filters.status || 'all');
const selectedType = ref(props.filters.loan_type || 'all');
const selectedEmployee = ref(
    props.filters.employee_id ? String(props.filters.employee_id) : 'all',
);

// Modal states
const isLoanModalOpen = ref(false);
const editingLoan = ref<Loan | null>(null);
const isPaymentModalOpen = ref(false);
const paymentLoan = ref<Loan | null>(null);

const loansData = computed(() => props.loans?.data ?? []);

const flatLoanTypes = computed(() => {
    const types: LoanTypeOption[] = [];
    for (const category in props.loanTypes) {
        types.push(...props.loanTypes[category]);
    }
    return types;
});

const employeeOptions = computed(() => [
    { value: 'all', label: 'All Employees' },
    ...props.employees.map((emp) => ({
        value: String(emp.id),
        label: `${emp.full_name} (${emp.employee_number})`,
    })),
]);

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

function getCategoryBadgeClasses(category: string): string {
    switch (category) {
        case 'SSS':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'Pag-IBIG':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
        case 'Company':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function applyFilters() {
    const params: Record<string, string | number | undefined> = {};

    if (selectedStatus.value !== 'all') {
        params.status = selectedStatus.value;
    }
    if (selectedType.value !== 'all') {
        params.loan_type = selectedType.value;
    }
    if (selectedEmployee.value !== 'all') {
        params.employee_id = Number(selectedEmployee.value);
    }

    router.get('/payroll/loans', params, { preserveState: true });
}

function handleStatusChange(value: string) {
    selectedStatus.value = value;
    applyFilters();
}

function handleTypeChange(value: string) {
    selectedType.value = value;
    applyFilters();
}

function handleEmployeeChange(value: string) {
    selectedEmployee.value = value;
    applyFilters();
}

function handleAddLoan() {
    editingLoan.value = null;
    isLoanModalOpen.value = true;
}

function handleEditLoan(loan: Loan) {
    editingLoan.value = loan;
    isLoanModalOpen.value = true;
}

function handleRecordPayment(loan: Loan) {
    paymentLoan.value = loan;
    isPaymentModalOpen.value = true;
}

async function handleStatusTransition(loan: Loan, newStatus: string) {
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
        const response = await fetch(`/api/loans/${loan.id}/status`, {
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
            router.reload({ only: ['loans', 'summary'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to update status');
        }
    } catch {
        alert('An error occurred while updating the status');
    }
}

async function handleDeleteLoan(loan: Loan) {
    if (
        !confirm(
            `Are you sure you want to delete this loan (${loan.loan_code})?`,
        )
    ) {
        return;
    }

    try {
        const response = await fetch(`/api/loans/${loan.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            router.reload({ only: ['loans', 'summary'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete loan');
        }
    } catch {
        alert('An error occurred while deleting the loan');
    }
}

function handleLoanFormSuccess() {
    isLoanModalOpen.value = false;
    editingLoan.value = null;
    router.reload({ only: ['loans', 'summary'] });
}

function handlePaymentSuccess() {
    isPaymentModalOpen.value = false;
    paymentLoan.value = null;
    router.reload({ only: ['loans', 'summary'] });
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
    <Head :title="`Loans - ${tenantName}`" />

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
                        Employee Loans
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage employee loans with automatic payroll deduction.
                    </p>
                </div>
                <Button
                    @click="handleAddLoan"
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
                    Add Loan
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
                        Total Loans
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ summary.total_loans }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Active Loans
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400"
                    >
                        {{ summary.active_loans }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Total Outstanding
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ formatCurrency(summary.total_outstanding) }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Monthly Deductions
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ formatCurrency(summary.total_monthly_deductions) }}
                    </div>
                </div>
            </div>

            <!-- Filters -->
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
                            v-for="status in loanStatuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="selectedType"
                    @update:model-value="handleTypeChange"
                >
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Loan Type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Types</SelectItem>
                        <template
                            v-for="(types, category) in loanTypes"
                            :key="category"
                        >
                            <div
                                class="px-2 py-1.5 text-xs font-semibold text-slate-500"
                            >
                                {{ category }}
                            </div>
                            <SelectItem
                                v-for="type in types"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </SelectItem>
                        </template>
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

            <!-- Loans Table -->
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
                                    Loan Details
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Monthly
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Balance
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Progress
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
                                v-for="loan in loansData"
                                :key="loan.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ loan.employee?.full_name }}
                                    </div>
                                    <div
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ loan.employee?.employee_number }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                            :class="
                                                getCategoryBadgeClasses(
                                                    loan.loan_type_category,
                                                )
                                            "
                                        >
                                            {{ loan.loan_type_category }}
                                        </span>
                                        <span
                                            class="font-medium text-slate-900 dark:text-slate-100"
                                        >
                                            {{ loan.loan_type_label }}
                                        </span>
                                    </div>
                                    <div
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ loan.loan_code }}
                                        <span v-if="loan.reference_number">
                                            · {{ loan.reference_number }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            formatCurrency(
                                                loan.monthly_deduction,
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            formatCurrency(
                                                loan.remaining_balance,
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="h-2 w-24 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
                                        >
                                            <div
                                                class="h-full bg-green-500"
                                                :style="{
                                                    width: `${loan.progress_percentage}%`,
                                                }"
                                            />
                                        </div>
                                        <span
                                            class="text-sm text-slate-600 dark:text-slate-400"
                                        >
                                            {{ loan.progress_percentage }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <DropdownMenu
                                        v-if="
                                            getAllowedTransitions(loan.status)
                                                .length > 0
                                        "
                                    >
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
                                                :class="
                                                    getStatusBadgeClasses(
                                                        loan.status,
                                                    )
                                                "
                                            >
                                                {{ loan.status_label }}
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
                                            <DropdownMenuLabel
                                                >Change Status</DropdownMenuLabel
                                            >
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                v-for="newStatus in getAllowedTransitions(
                                                    loan.status,
                                                )"
                                                :key="newStatus"
                                                @click="
                                                    handleStatusTransition(
                                                        loan,
                                                        newStatus,
                                                    )
                                                "
                                            >
                                                {{
                                                    loanStatuses.find(
                                                        (s) =>
                                                            s.value ===
                                                            newStatus,
                                                    )?.label || newStatus
                                                }}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            getStatusBadgeClasses(loan.status)
                                        "
                                    >
                                        {{ loan.status_label }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 w-8 p-0"
                                            >
                                                <span class="sr-only"
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
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel
                                                >Actions</DropdownMenuLabel
                                            >
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                v-if="
                                                    loan.status === 'active' ||
                                                    loan.status === 'on_hold'
                                                "
                                                @click="handleEditLoan(loan)"
                                            >
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    loan.status === 'active' ||
                                                    loan.status === 'on_hold'
                                                "
                                                @click="
                                                    handleRecordPayment(loan)
                                                "
                                            >
                                                Record Payment
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                @click="handleDeleteLoan(loan)"
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

                <!-- Empty State -->
                <div
                    v-if="loansData.length === 0"
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
                            d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No loans found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Get started by adding an employee loan.
                    </p>
                    <div class="mt-6">
                        <Button
                            @click="handleAddLoan"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            Add Loan
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <LoanFormModal
            v-model:open="isLoanModalOpen"
            :loan="editingLoan"
            :employees="employees"
            :loan-types="loanTypes"
            @success="handleLoanFormSuccess"
        />

        <RecordPaymentModal
            v-model:open="isPaymentModalOpen"
            :loan="paymentLoan"
            @success="handlePaymentSuccess"
        />
    </TenantLayout>
</template>
