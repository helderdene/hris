<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
    department: string | null;
    position: string | null;
}

interface LoanApplication {
    id: number;
    reference_number: string;
    employee: Employee;
    loan_type: string;
    loan_type_label: string;
    loan_type_category: string;
    amount_requested: number;
    term_months: number;
    purpose: string | null;
    documents: Array<{ name: string; path: string; size: number; mime_type: string }> | null;
    status: string;
    status_label: string;
    status_color: string;
    reviewer: { full_name: string } | null;
    reviewer_remarks: string | null;
    reviewed_at: string | null;
    submitted_at: string | null;
    created_at: string;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

const props = defineProps<{
    applications: LoanApplication[];
    statuses: StatusOption[];
    filters: { status: string | null };
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Loan Approvals', href: '/loan-approvals' },
];

const selectedStatus = ref(props.filters.status || 'pending');

function reloadPage() {
    const params: Record<string, string> = {};
    if (selectedStatus.value !== 'all') {
        params.status = selectedStatus.value;
    }
    router.get(window.location.pathname, params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function handleStatusChange(value: string) {
    selectedStatus.value = value;
    reloadPage();
}

function getStatusBadgeClasses(color: string): string {
    switch (color) {
        case 'green':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'red':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        case 'amber':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'slate':
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

// Approve modal state
const showApproveDialog = ref(false);
const approvingApplication = ref<LoanApplication | null>(null);
const approveInterestRate = ref('');
const approveStartDate = ref('');
const approveRemarks = ref('');

// Reject modal state
const showRejectDialog = ref(false);
const rejectingApplication = ref<LoanApplication | null>(null);
const rejectRemarks = ref('');

const isProcessing = ref(false);

const approveTotal = computed(() => {
    if (!approvingApplication.value || !approveInterestRate.value) return null;
    const amount = approvingApplication.value.amount_requested;
    const rate = parseFloat(approveInterestRate.value);
    const months = approvingApplication.value.term_months;
    if (isNaN(rate)) return null;
    const totalAmount = amount + amount * rate * (months / 12);
    const monthlyDeduction = totalAmount / months;
    return { totalAmount, monthlyDeduction };
});

function handleApprove(application: LoanApplication) {
    approvingApplication.value = application;
    approveInterestRate.value = '';
    approveStartDate.value = '';
    approveRemarks.value = '';
    showApproveDialog.value = true;
}

function handleReject(application: LoanApplication) {
    rejectingApplication.value = application;
    rejectRemarks.value = '';
    showRejectDialog.value = true;
}

async function executeApprove() {
    if (!approvingApplication.value || !approveInterestRate.value || !approveStartDate.value) return;
    showApproveDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/loan-approvals/${approvingApplication.value.id}/approve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                interest_rate: parseFloat(approveInterestRate.value),
                start_date: approveStartDate.value,
                remarks: approveRemarks.value || null,
            }),
        });

        if (response.ok) {
            reloadPage();
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isProcessing.value = false;
        approvingApplication.value = null;
    }
}

async function executeReject() {
    if (!rejectingApplication.value || !rejectRemarks.value.trim()) return;
    showRejectDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/loan-approvals/${rejectingApplication.value.id}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                remarks: rejectRemarks.value,
            }),
        });

        if (response.ok) {
            reloadPage();
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isProcessing.value = false;
        rejectingApplication.value = null;
    }
}
</script>

<template>
    <Head :title="`Loan Approvals - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Loan Approvals
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Review and process employee loan applications.
                </p>
            </div>

            <!-- Status Tabs -->
            <Tabs :model-value="selectedStatus" @update:model-value="handleStatusChange">
                <TabsList>
                    <TabsTrigger value="pending">Pending</TabsTrigger>
                    <TabsTrigger value="approved">Approved</TabsTrigger>
                    <TabsTrigger value="rejected">Rejected</TabsTrigger>
                    <TabsTrigger value="all">All</TabsTrigger>
                </TabsList>
            </Tabs>

            <!-- Applications Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <!-- Desktop Table -->
                <div v-if="props.applications.length > 0" class="hidden md:block">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Reference
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Employee
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Loan Type
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Amount
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Term
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Submitted
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Status
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="application in props.applications"
                                :key="application.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-slate-900 dark:text-slate-100">
                                    {{ application.reference_number }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                        {{ application.employee.full_name }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ application.employee.employee_number }}
                                        <span v-if="application.employee.department"> &middot; {{ application.employee.department }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ application.loan_type_label }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ formatCurrency(application.amount_requested) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ application.term_months }} mo.
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ application.submitted_at ? formatDate(application.submitted_at) : '-' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(application.status_color)"
                                    >
                                        {{ application.status_label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <div v-if="application.status === 'pending'" class="flex items-center justify-end gap-2">
                                        <Button
                                            size="sm"
                                            :style="{ backgroundColor: primaryColor }"
                                            :disabled="isProcessing"
                                            @click="handleApprove(application)"
                                        >
                                            Approve
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
                                            :disabled="isProcessing"
                                            @click="handleReject(application)"
                                        >
                                            Reject
                                        </Button>
                                    </div>
                                    <div v-else-if="application.reviewer" class="text-xs text-slate-500 dark:text-slate-400">
                                        by {{ application.reviewer.full_name }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile View -->
                <div v-if="props.applications.length > 0" class="divide-y divide-slate-200 md:hidden dark:divide-slate-700">
                    <div
                        v-for="application in props.applications"
                        :key="application.id"
                        class="space-y-3 p-4"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ application.reference_number }}
                                </div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ application.employee.full_name }} &middot; {{ application.employee.employee_number }}
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClasses(application.status_color)"
                            >
                                {{ application.status_label }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-slate-400">{{ application.loan_type_label }}</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ formatCurrency(application.amount_requested) }}</span>
                        </div>
                        <div class="text-sm text-slate-500 dark:text-slate-400">
                            {{ application.term_months }} months
                        </div>
                        <div v-if="application.status === 'pending'" class="flex gap-2 pt-1">
                            <Button
                                size="sm"
                                :style="{ backgroundColor: primaryColor }"
                                :disabled="isProcessing"
                                @click="handleApprove(application)"
                            >
                                Approve
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                class="border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
                                :disabled="isProcessing"
                                @click="handleReject(application)"
                            >
                                Reject
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="props.applications.length === 0" class="px-6 py-12 text-center">
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
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No loan applications found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        There are no loan applications matching your current filter.
                    </p>
                </div>
            </div>
        </div>

        <!-- Approve Dialog -->
        <Dialog v-model:open="showApproveDialog">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Approve Loan Application</DialogTitle>
                    <DialogDescription>
                        Approve {{ approvingApplication?.reference_number }} for
                        {{ approvingApplication?.employee.full_name }} -
                        {{ formatCurrency(approvingApplication?.amount_requested ?? 0) }}
                        over {{ approvingApplication?.term_months }} months.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-4 py-4">
                    <div class="flex flex-col gap-2">
                        <Label for="interest_rate">Interest Rate</Label>
                        <Input
                            id="interest_rate"
                            v-model="approveInterestRate"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="e.g. 0.10 for 10%"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="start_date">Start Date</Label>
                        <Input
                            id="start_date"
                            v-model="approveStartDate"
                            type="date"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="approve_remarks">Remarks (optional)</Label>
                        <Textarea
                            id="approve_remarks"
                            v-model="approveRemarks"
                            placeholder="Optional remarks"
                            rows="3"
                        />
                    </div>

                    <!-- Deduction Preview -->
                    <div
                        v-if="approveTotal"
                        class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
                    >
                        <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">Deduction Preview</h4>
                        <div class="mt-2 flex flex-col gap-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Total Amount</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ formatCurrency(approveTotal.totalAmount) }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Monthly Deduction</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ formatCurrency(approveTotal.monthlyDeduction) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showApproveDialog = false">Cancel</Button>
                    <Button
                        :style="{ backgroundColor: primaryColor }"
                        :disabled="!approveInterestRate || !approveStartDate || isProcessing"
                        @click="executeApprove"
                    >
                        Approve
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Reject Dialog -->
        <Dialog v-model:open="showRejectDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject Loan Application</DialogTitle>
                    <DialogDescription>
                        Reject {{ rejectingApplication?.reference_number }} for
                        {{ rejectingApplication?.employee.full_name }}. Please provide a reason.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Textarea
                        v-model="rejectRemarks"
                        placeholder="Reason for rejection (required)"
                        rows="3"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showRejectDialog = false">Cancel</Button>
                    <Button
                        class="bg-red-600 hover:bg-red-700"
                        :disabled="!rejectRemarks.trim() || isProcessing"
                        @click="executeReject"
                    >
                        Reject
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
