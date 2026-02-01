<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Document {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    download_url: string;
}

interface Reviewer {
    id: number;
    full_name: string;
    position: string | null;
}

interface EmployeeLoan {
    id: number;
    total_amount: number;
    monthly_deduction: number;
    interest_rate: number;
    start_date: string;
    end_date: string | null;
    remaining_balance: number | null;
}

interface LoanApplication {
    id: number;
    reference_number: string;
    employee: {
        id: number;
        full_name: string;
        employee_number: string;
        department?: string;
        position?: string;
    };
    loan_type: string;
    loan_type_label: string;
    loan_type_category: string;
    amount_requested: number;
    term_months: number;
    purpose: string;
    status: string;
    status_label: string;
    status_color: string;
    reviewer: Reviewer | null;
    reviewer_remarks: string | null;
    employee_loan: EmployeeLoan | null;
    documents: Document[];
    submitted_at: string | null;
    reviewed_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    created_at: string;
    can_be_edited: boolean;
    can_be_cancelled: boolean;
}

const props = defineProps<{
    application: LoanApplication;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'My Loan Applications', href: '/my/loan-applications' },
    { title: props.application.reference_number, href: `/my/loan-applications/${props.application.id}` },
];

function getStatusBadgeClasses(color: string): string {
    switch (color) {
        case 'green':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'red':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        case 'amber':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'blue':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
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
        month: 'long',
        day: 'numeric',
    });
}

function formatDateTime(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

const showSubmitDialog = ref(false);
const showCancelDialog = ref(false);
const cancelReason = ref('');
const isProcessing = ref(false);

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function handleSubmit() {
    showSubmitDialog.value = true;
}

async function executeSubmit() {
    showSubmitDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/loan-applications/${props.application.id}/submit`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            router.reload();
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isProcessing.value = false;
    }
}

function handleCancel() {
    cancelReason.value = '';
    showCancelDialog.value = true;
}

async function executeCancelApplication() {
    showCancelDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/loan-applications/${props.application.id}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ reason: cancelReason.value || null }),
        });

        if (response.ok) {
            router.reload();
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isProcessing.value = false;
    }
}
</script>

<template>
    <Head :title="`${application.reference_number} - Loan Application - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ application.reference_number }}
                        </h1>
                        <span
                            class="inline-flex items-center rounded-md px-2.5 py-0.5 text-sm font-medium"
                            :class="getStatusBadgeClasses(application.status_color)"
                        >
                            {{ application.status_label }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Created {{ formatDateTime(application.created_at) }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="application.status === 'draft'"
                        @click="handleSubmit"
                        variant="default"
                    >
                        Submit for Review
                    </Button>
                    <Button
                        v-if="application.can_be_cancelled"
                        @click="handleCancel"
                        variant="outline"
                    >
                        Cancel Request
                    </Button>
                    <Link href="/my/loan-applications">
                        <Button variant="ghost">Back to List</Button>
                    </Link>
                </div>
            </div>

            <!-- Main Content -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column - Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Loan Details Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Loan Details</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Loan Type
                                    </label>
                                    <p class="mt-1 text-slate-900 dark:text-slate-100">
                                        {{ application.loan_type_label }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ application.loan_type_category }}
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Amount Requested
                                    </label>
                                    <p class="mt-1 text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(application.amount_requested) }}
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Term
                                    </label>
                                    <p class="mt-1 text-slate-900 dark:text-slate-100">
                                        {{ application.term_months }} months
                                    </p>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Purpose
                                </label>
                                <p class="mt-1 whitespace-pre-wrap text-slate-900 dark:text-slate-100">
                                    {{ application.purpose }}
                                </p>
                            </div>
                            <div v-if="application.cancellation_reason">
                                <label class="text-sm font-medium text-red-500">
                                    Cancellation Reason
                                </label>
                                <p class="mt-1 text-slate-900 dark:text-slate-100">
                                    {{ application.cancellation_reason }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Reviewer Remarks (if rejected) -->
                    <Card v-if="application.reviewer_remarks">
                        <CardHeader>
                            <CardTitle>Reviewer Remarks</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="whitespace-pre-wrap text-slate-900 dark:text-slate-100">
                                {{ application.reviewer_remarks }}
                            </p>
                            <div v-if="application.reviewer" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                                Reviewed by {{ application.reviewer.full_name }}
                                <span v-if="application.reviewer.position"> ({{ application.reviewer.position }})</span>
                                <span v-if="application.reviewed_at"> on {{ formatDateTime(application.reviewed_at) }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Approved Loan Details -->
                    <Card v-if="application.employee_loan">
                        <CardHeader>
                            <CardTitle>Loan Details (Approved)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Total Amount
                                    </label>
                                    <p class="mt-1 text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(application.employee_loan.total_amount) }}
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Monthly Deduction
                                    </label>
                                    <p class="mt-1 text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(application.employee_loan.monthly_deduction) }}
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Interest Rate
                                    </label>
                                    <p class="mt-1 text-slate-900 dark:text-slate-100">
                                        {{ application.employee_loan.interest_rate }}%
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Start Date
                                    </label>
                                    <p class="mt-1 text-slate-900 dark:text-slate-100">
                                        {{ formatDate(application.employee_loan.start_date) }}
                                    </p>
                                </div>
                                <div v-if="application.employee_loan.remaining_balance !== null">
                                    <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Remaining Balance
                                    </label>
                                    <p class="mt-1 text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(application.employee_loan.remaining_balance) }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Documents -->
                    <Card v-if="application.documents && application.documents.length > 0">
                        <CardHeader>
                            <CardTitle>Documents</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                                <li
                                    v-for="doc in application.documents"
                                    :key="doc.id"
                                    class="flex items-center justify-between py-3"
                                >
                                    <div class="flex items-center gap-3">
                                        <svg class="h-5 w-5 text-slate-400 dark:text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        <div>
                                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                                {{ doc.name || doc.file_name }}
                                            </p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ formatFileSize(doc.size) }}
                                            </p>
                                        </div>
                                    </div>
                                    <a
                                        :href="doc.download_url"
                                        target="_blank"
                                        class="text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        Download
                                    </a>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column - Summary -->
                <div class="space-y-6">
                    <!-- Employee Info -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Applicant</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ application.employee.full_name }}
                                    </p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ application.employee.employee_number }}
                                    </p>
                                </div>
                                <div v-if="application.employee.position || application.employee.department">
                                    <p v-if="application.employee.position" class="text-sm text-slate-600 dark:text-slate-300">
                                        {{ application.employee.position }}
                                    </p>
                                    <p v-if="application.employee.department" class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ application.employee.department }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Status Timeline -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Timeline</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <!-- Created -->
                                <div class="relative flex gap-3">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Created</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ formatDateTime(application.created_at) }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Submitted -->
                                <div v-if="application.submitted_at" class="relative flex gap-3">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Submitted</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ formatDateTime(application.submitted_at) }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Approved -->
                                <div v-if="application.approved_at" class="relative flex gap-3">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-green-600 dark:text-green-400">Approved</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ formatDateTime(application.approved_at) }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Rejected -->
                                <div v-if="application.rejected_at" class="relative flex gap-3">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-red-600 dark:text-red-400">Rejected</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ formatDateTime(application.rejected_at) }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Cancelled -->
                                <div v-if="application.cancelled_at" class="relative flex gap-3">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Cancelled</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ formatDateTime(application.cancelled_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Submit Confirmation Dialog -->
        <Dialog v-model:open="showSubmitDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Submit Application</DialogTitle>
                    <DialogDescription>
                        Submit {{ application.reference_number }} for review?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showSubmitDialog = false">Cancel</Button>
                    <Button @click="executeSubmit">Submit</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Cancel Application Dialog -->
        <Dialog v-model:open="showCancelDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel Application</DialogTitle>
                    <DialogDescription>
                        Cancel {{ application.reference_number }}? You may provide a reason.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Textarea
                        v-model="cancelReason"
                        placeholder="Reason for cancellation (optional)"
                        rows="3"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showCancelDialog = false">Keep Application</Button>
                    <Button class="bg-amber-600 hover:bg-amber-700" @click="executeCancelApplication">
                        Cancel Application
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
