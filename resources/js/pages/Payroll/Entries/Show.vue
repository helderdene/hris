<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    Check,
    ChevronDown,
    Clock,
    DollarSign,
    Download,
    Minus,
    Plus,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PayrollPeriod {
    id: number;
    name: string;
    date_range: string;
    pay_date: string;
    formatted_pay_date: string;
    payroll_cycle?: {
        id: number;
        name: string;
    };
}

interface Earning {
    id: number;
    earning_type: string;
    earning_type_label: string;
    earning_code: string;
    description: string;
    quantity: string;
    quantity_unit: string | null;
    rate: string;
    multiplier: string;
    amount: string;
    is_taxable: boolean;
    computation_breakdown: string;
}

interface Deduction {
    id: number;
    deduction_type: string;
    deduction_type_label: string;
    deduction_code: string;
    description: string;
    basis_amount: string;
    rate: string;
    rate_percentage: string;
    amount: string;
    is_employee_share: boolean;
    is_employer_share: boolean;
    share_type_label: string;
}

interface PayrollEntry {
    id: number;
    payroll_period_id: number;
    employee_id: number;
    employee_number: string;
    employee_name: string;
    department_name: string | null;
    position_name: string | null;
    basic_salary_snapshot: string;
    pay_type_snapshot: string;
    pay_type_label: string;
    days_worked: string;
    total_regular_minutes: number;
    total_late_minutes: number;
    total_undertime_minutes: number;
    total_overtime_minutes: number;
    total_night_diff_minutes: number;
    absent_days: string;
    holiday_days: string;
    basic_pay: string;
    overtime_pay: string;
    night_diff_pay: string;
    holiday_pay: string;
    allowances_total: string;
    bonuses_total: string;
    gross_pay: string;
    sss_employee: string;
    sss_employer: string;
    philhealth_employee: string;
    philhealth_employer: string;
    pagibig_employee: string;
    pagibig_employer: string;
    withholding_tax: string;
    other_deductions_total: string;
    total_deductions: string;
    total_employer_contributions: string;
    net_pay: string;
    status: string;
    status_label: string;
    status_color: string;
    computed_at: string | null;
    computed_at_formatted: string | null;
    approved_at: string | null;
    approved_at_formatted: string | null;
    remarks: string | null;
    can_recompute: boolean;
    allowed_transitions: Array<{ value: string; label: string }>;
    payroll_period: PayrollPeriod;
    earning_items?: Earning[];
    deduction_items?: Deduction[];
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

const props = defineProps<{
    entry: PayrollEntry;
    statusOptions: StatusOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll Periods', href: '/organization/payroll-periods' },
    {
        title: props.entry.payroll_period.name,
        href: `/payroll/periods/${props.entry.payroll_period.id}/entries`,
    },
    { title: props.entry.employee_name, href: `/payroll/entries/${props.entry.id}` },
];

const isUpdatingStatus = ref(false);

function formatCurrency(value: string | number): string {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(num);
}

function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours === 0) return `${mins}m`;
    if (mins === 0) return `${hours}h`;
    return `${hours}h ${mins}m`;
}

function getStatusBadgeClasses(status: string): string {
    switch (status) {
        case 'draft':
            return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
        case 'computed':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 'reviewed':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case 'approved':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
        case 'paid':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function goBack() {
    router.visit(`/payroll/periods/${props.entry.payroll_period.id}/entries`);
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleStatusChange(newStatus: string) {
    isUpdatingStatus.value = true;

    try {
        const response = await fetch(`/api/organization/payroll-entries/${props.entry.id}/status`, {
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
            router.reload();
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to update status');
        }
    } catch {
        alert('An error occurred while updating status');
    } finally {
        isUpdatingStatus.value = false;
    }
}

async function handleRecompute() {
    if (!confirm('Are you sure you want to recompute this payroll entry? This will recalculate all values.')) {
        return;
    }

    try {
        const response = await fetch(
            `/api/organization/payroll-periods/${props.entry.payroll_period.id}/recompute`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ employee_ids: [props.entry.employee_id] }),
            },
        );

        if (response.ok) {
            router.reload();
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to recompute');
        }
    } catch {
        alert('An error occurred while recomputing');
    }
}

const isDownloading = ref(false);

async function downloadPdf() {
    isDownloading.value = true;

    try {
        const response = await fetch(`/api/organization/payroll-entries/${props.entry.id}/pdf`, {
            method: 'GET',
            headers: {
                Accept: 'application/pdf',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `payslip_${props.entry.employee_number}_${new Date().toISOString().split('T')[0]}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to download PDF');
        }
    } catch {
        alert('An error occurred while downloading the PDF');
    } finally {
        isDownloading.value = false;
    }
}

// Computed values for summary sections
const employeeEarnings = computed(() => props.entry.earning_items || []);
const employeeDeductions = computed(() =>
    (props.entry.deduction_items || []).filter((d) => d.is_employee_share),
);
const employerContributions = computed(() =>
    (props.entry.deduction_items || []).filter((d) => d.is_employer_share),
);
</script>

<template>
    <Head :title="`Payslip - ${entry.employee_name} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" @click="goBack">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            Payslip
                        </h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ entry.payroll_period.name }} &middot; {{ entry.payroll_period.date_range }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="downloadPdf" :disabled="isDownloading">
                        <Download class="mr-2 h-4 w-4" />
                        {{ isDownloading ? 'Exporting...' : 'Export PDF' }}
                    </Button>
                    <DropdownMenu v-if="entry.allowed_transitions.length > 0 || entry.can_recompute">
                        <DropdownMenuTrigger as-child>
                            <Button :disabled="isUpdatingStatus">
                                Actions
                                <ChevronDown class="ml-2 h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-if="entry.can_recompute"
                                @click="handleRecompute"
                            >
                                Recompute
                            </DropdownMenuItem>
                            <DropdownMenuSeparator v-if="entry.can_recompute && entry.allowed_transitions.length > 0" />
                            <DropdownMenuItem
                                v-for="transition in entry.allowed_transitions"
                                :key="transition.value"
                                @click="handleStatusChange(transition.value)"
                            >
                                {{ transition.label }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column - Employee & Period Info -->
                <div class="space-y-6">
                    <!-- Employee Info Card -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <User class="h-4 w-4" />
                                Employee Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div>
                                <div class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                    {{ entry.employee_name }}
                                </div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ entry.employee_number }}
                                </div>
                            </div>
                            <Separator />
                            <div class="grid gap-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Department</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ entry.department_name || '-' }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Position</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ entry.position_name || '-' }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Pay Type</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ entry.pay_type_label }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Basic Salary</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(entry.basic_salary_snapshot) }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Period Info Card -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Calendar class="h-4 w-4" />
                                Pay Period
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="grid gap-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Period</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ entry.payroll_period.name }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Cutoff</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ entry.payroll_period.date_range }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Pay Date</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ entry.payroll_period.formatted_pay_date }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- DTR Summary Card -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Clock class="h-4 w-4" />
                                Attendance Summary
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="grid gap-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Days Worked</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ entry.days_worked }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Absent Days</span>
                                    <span class="font-medium text-red-600 dark:text-red-400">
                                        {{ entry.absent_days }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Holiday Days</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ entry.holiday_days }}
                                    </span>
                                </div>
                                <Separator />
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Late</span>
                                    <span class="font-medium text-amber-600 dark:text-amber-400">
                                        {{ formatMinutes(entry.total_late_minutes) }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Undertime</span>
                                    <span class="font-medium text-amber-600 dark:text-amber-400">
                                        {{ formatMinutes(entry.total_undertime_minutes) }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Overtime</span>
                                    <span class="font-medium text-green-600 dark:text-green-400">
                                        {{ formatMinutes(entry.total_overtime_minutes) }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Night Diff</span>
                                    <span class="font-medium text-blue-600 dark:text-blue-400">
                                        {{ formatMinutes(entry.total_night_diff_minutes) }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Status Card -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Check class="h-4 w-4" />
                                Status
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Current Status</span>
                                <Badge :class="getStatusBadgeClasses(entry.status)">
                                    {{ entry.status_label }}
                                </Badge>
                            </div>
                            <div v-if="entry.computed_at_formatted" class="flex justify-between text-sm">
                                <span class="text-slate-500 dark:text-slate-400">Computed</span>
                                <span class="text-slate-900 dark:text-slate-100">
                                    {{ entry.computed_at_formatted }}
                                </span>
                            </div>
                            <div v-if="entry.approved_at_formatted" class="flex justify-between text-sm">
                                <span class="text-slate-500 dark:text-slate-400">Approved</span>
                                <span class="text-slate-900 dark:text-slate-100">
                                    {{ entry.approved_at_formatted }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column - Earnings & Deductions -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Net Pay Summary -->
                    <Card class="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20">
                        <CardContent class="pt-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium text-green-700 dark:text-green-400">
                                        Net Pay
                                    </div>
                                    <div class="mt-1 text-3xl font-bold text-green-700 dark:text-green-400">
                                        {{ formatCurrency(entry.net_pay) }}
                                    </div>
                                </div>
                                <DollarSign class="h-12 w-12 text-green-500/50" />
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <div class="text-green-600/70 dark:text-green-500/70">Gross Pay</div>
                                    <div class="font-semibold text-green-700 dark:text-green-400">
                                        {{ formatCurrency(entry.gross_pay) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-green-600/70 dark:text-green-500/70">Deductions</div>
                                    <div class="font-semibold text-red-600 dark:text-red-400">
                                        -{{ formatCurrency(entry.total_deductions) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-green-600/70 dark:text-green-500/70">Employer Cost</div>
                                    <div class="font-semibold text-slate-600 dark:text-slate-400">
                                        {{ formatCurrency(entry.total_employer_contributions) }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Earnings Card -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Plus class="h-4 w-4 text-green-500" />
                                Earnings
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200 dark:border-slate-700">
                                            <th class="pb-2 text-left font-medium text-slate-500 dark:text-slate-400">
                                                Description
                                            </th>
                                            <th class="pb-2 text-right font-medium text-slate-500 dark:text-slate-400">
                                                Qty
                                            </th>
                                            <th class="pb-2 text-right font-medium text-slate-500 dark:text-slate-400">
                                                Rate
                                            </th>
                                            <th class="pb-2 text-right font-medium text-slate-500 dark:text-slate-400">
                                                Amount
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr v-for="earning in employeeEarnings" :key="earning.id">
                                            <td class="py-2">
                                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ earning.description }}
                                                </div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ earning.earning_code }}
                                                    <span v-if="!earning.is_taxable" class="ml-1 text-green-600">
                                                        (Non-taxable)
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="py-2 text-right text-slate-600 dark:text-slate-400">
                                                {{ earning.quantity }}{{ earning.quantity_unit ? ` ${earning.quantity_unit}` : '' }}
                                            </td>
                                            <td class="py-2 text-right text-slate-600 dark:text-slate-400">
                                                {{ formatCurrency(earning.rate) }}
                                                <span v-if="parseFloat(earning.multiplier) !== 1" class="text-xs">
                                                    x{{ earning.multiplier }}
                                                </span>
                                            </td>
                                            <td class="py-2 text-right font-medium text-slate-900 dark:text-slate-100">
                                                {{ formatCurrency(earning.amount) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-t border-slate-200 dark:border-slate-700">
                                            <td colspan="3" class="pt-2 text-right font-semibold text-slate-900 dark:text-slate-100">
                                                Total Earnings
                                            </td>
                                            <td class="pt-2 text-right font-bold text-green-600 dark:text-green-400">
                                                {{ formatCurrency(entry.gross_pay) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Deductions Card -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Minus class="h-4 w-4 text-red-500" />
                                Deductions
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200 dark:border-slate-700">
                                            <th class="pb-2 text-left font-medium text-slate-500 dark:text-slate-400">
                                                Description
                                            </th>
                                            <th class="pb-2 text-right font-medium text-slate-500 dark:text-slate-400">
                                                Basis
                                            </th>
                                            <th class="pb-2 text-right font-medium text-slate-500 dark:text-slate-400">
                                                Rate
                                            </th>
                                            <th class="pb-2 text-right font-medium text-slate-500 dark:text-slate-400">
                                                Amount
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr v-for="deduction in employeeDeductions" :key="deduction.id">
                                            <td class="py-2">
                                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ deduction.description }}
                                                </div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ deduction.deduction_code }}
                                                </div>
                                            </td>
                                            <td class="py-2 text-right text-slate-600 dark:text-slate-400">
                                                {{ formatCurrency(deduction.basis_amount) }}
                                            </td>
                                            <td class="py-2 text-right text-slate-600 dark:text-slate-400">
                                                {{ deduction.rate_percentage }}
                                            </td>
                                            <td class="py-2 text-right font-medium text-red-600 dark:text-red-400">
                                                {{ formatCurrency(deduction.amount) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-t border-slate-200 dark:border-slate-700">
                                            <td colspan="3" class="pt-2 text-right font-semibold text-slate-900 dark:text-slate-100">
                                                Total Deductions
                                            </td>
                                            <td class="pt-2 text-right font-bold text-red-600 dark:text-red-400">
                                                {{ formatCurrency(entry.total_deductions) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Employer Contributions Card -->
                    <Card v-if="employerContributions.length > 0">
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base text-slate-600 dark:text-slate-400">
                                Employer Contributions
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200 dark:border-slate-700">
                                            <th class="pb-2 text-left font-medium text-slate-500 dark:text-slate-400">
                                                Description
                                            </th>
                                            <th class="pb-2 text-right font-medium text-slate-500 dark:text-slate-400">
                                                Amount
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr v-for="contribution in employerContributions" :key="contribution.id">
                                            <td class="py-2">
                                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ contribution.description }}
                                                </div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ contribution.deduction_code }}
                                                </div>
                                            </td>
                                            <td class="py-2 text-right font-medium text-slate-600 dark:text-slate-400">
                                                {{ formatCurrency(contribution.amount) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-t border-slate-200 dark:border-slate-700">
                                            <td class="pt-2 text-right font-semibold text-slate-900 dark:text-slate-100">
                                                Total Employer Cost
                                            </td>
                                            <td class="pt-2 text-right font-bold text-slate-600 dark:text-slate-400">
                                                {{ formatCurrency(entry.total_employer_contributions) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>

<style>
@media print {
    /* Hide non-essential elements when printing */
    nav,
    button,
    .no-print {
        display: none !important;
    }

    /* Adjust layout for print */
    .lg\\:grid-cols-3 {
        display: block !important;
    }
}
</style>
