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
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface Filters {
    status: string | null;
}

interface LoanApplication {
    id: number;
    reference_number: string;
    loan_type: string;
    loan_type_label: string;
    loan_type_category: string;
    amount_requested: number;
    term_months: number;
    purpose: string;
    status: string;
    status_label: string;
    status_color: string;
    submitted_at: string | null;
    reviewed_at: string | null;
    created_at: string;
    can_be_edited: boolean;
    can_be_cancelled: boolean;
}

interface LoanTypeOption {
    value: string;
    label: string;
}

const props = defineProps<{
    employee: Employee | null;
    applications: LoanApplication[];
    loanTypes: Record<string, LoanTypeOption[]>;
    statuses: StatusOption[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'My Loan Applications', href: '/my/loan-applications' },
];

const selectedStatus = ref(props.filters.status || 'all');

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

function handleStatusChange(value: string) {
    selectedStatus.value = value;
    reloadPage();
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

// Confirmation dialog state
const showConfirmDialog = ref(false);
const confirmDialogTitle = ref('');
const confirmDialogDescription = ref('');
const confirmDialogAction = ref<(() => void) | null>(null);
const confirmDialogDestructive = ref(false);

// Cancel dialog state
const showCancelDialog = ref(false);
const cancellingApplication = ref<LoanApplication | null>(null);
const cancelReason = ref('');

// Loading state
const isProcessing = ref(false);

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function handleSubmitApplication(application: LoanApplication) {
    confirmDialogTitle.value = 'Submit Application';
    confirmDialogDescription.value = `Submit ${application.reference_number} for review?`;
    confirmDialogDestructive.value = false;
    confirmDialogAction.value = () => executeSubmit(application);
    showConfirmDialog.value = true;
}

async function executeSubmit(application: LoanApplication) {
    showConfirmDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/loan-applications/${application.id}/submit`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            reloadPage();
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isProcessing.value = false;
    }
}

function handleCancelApplication(application: LoanApplication) {
    cancellingApplication.value = application;
    cancelReason.value = '';
    showCancelDialog.value = true;
}

async function executeCancelApplication() {
    if (!cancellingApplication.value) return;
    showCancelDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/loan-applications/${cancellingApplication.value.id}/cancel`, {
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
            reloadPage();
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isProcessing.value = false;
        cancellingApplication.value = null;
    }
}

function handleDeleteApplication(application: LoanApplication) {
    confirmDialogTitle.value = 'Delete Draft';
    confirmDialogDescription.value = `Delete draft ${application.reference_number}? This cannot be undone.`;
    confirmDialogDestructive.value = true;
    confirmDialogAction.value = () => executeDelete(application);
    showConfirmDialog.value = true;
}

async function executeDelete(application: LoanApplication) {
    showConfirmDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/loan-applications/${application.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            reloadPage();
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
    <Head :title="`Loan Applications - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        My Loan Applications
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Submit and track your loan requests.
                    </p>
                </div>
                <Link v-if="employee" href="/my/loan-applications/create">
                    <Button :style="{ backgroundColor: primaryColor }">
                        <svg
                            class="mr-2 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        New Application
                    </Button>
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <Select :model-value="selectedStatus" @update:model-value="handleStatusChange">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

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
                                    Loan Type
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Amount
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Term
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
                                <td class="whitespace-nowrap px-6 py-4">
                                    <Link
                                        :href="`/my/loan-applications/${application.id}`"
                                        class="font-medium text-slate-900 hover:underline dark:text-slate-100"
                                    >
                                        {{ application.reference_number }}
                                    </Link>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ application.loan_type_label }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ formatCurrency(application.amount_requested) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ application.term_months }} months
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
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                                <span class="sr-only">Open menu</span>
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                                </svg>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem as-child>
                                                <Link :href="`/my/loan-applications/${application.id}`">
                                                    View Details
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="application.status === 'draft'"
                                                @click="handleSubmitApplication(application)"
                                            >
                                                Submit for Review
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="application.can_be_cancelled"
                                                class="text-amber-600 focus:text-amber-600"
                                                @click="handleCancelApplication(application)"
                                            >
                                                Cancel
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="application.status === 'draft'"
                                                class="text-red-600 focus:text-red-600"
                                                @click="handleDeleteApplication(application)"
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
                <div v-if="props.applications.length > 0" class="divide-y divide-slate-200 md:hidden dark:divide-slate-700">
                    <div
                        v-for="application in props.applications"
                        :key="application.id"
                        class="space-y-2 p-4"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <Link
                                    :href="`/my/loan-applications/${application.id}`"
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.reference_number }}
                                </Link>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ application.loan_type_label }}
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
                            <span class="text-slate-500 dark:text-slate-400">{{ formatCurrency(application.amount_requested) }}</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ application.term_months }} months</span>
                        </div>
                        <div class="flex items-center gap-2 pt-1">
                            <Link
                                :href="`/my/loan-applications/${application.id}`"
                                class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                            >
                                View Details
                            </Link>
                            <button
                                v-if="application.status === 'draft'"
                                class="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400"
                                @click="handleSubmitApplication(application)"
                            >
                                Submit for Review
                            </button>
                            <button
                                v-if="application.can_be_cancelled"
                                class="text-sm font-medium text-amber-600 hover:underline dark:text-amber-400"
                                @click="handleCancelApplication(application)"
                            >
                                Cancel
                            </button>
                            <button
                                v-if="application.status === 'draft'"
                                class="text-sm font-medium text-red-600 hover:underline dark:text-red-400"
                                @click="handleDeleteApplication(application)"
                            >
                                Delete
                            </button>
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
                        Submit your first loan request to get started.
                    </p>
                    <div v-if="employee" class="mt-6">
                        <Link href="/my/loan-applications/create">
                            <Button :style="{ backgroundColor: primaryColor }">
                                New Application
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Dialog -->
        <Dialog v-model:open="showConfirmDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ confirmDialogTitle }}</DialogTitle>
                    <DialogDescription>{{ confirmDialogDescription }}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showConfirmDialog = false">Cancel</Button>
                    <Button
                        :class="confirmDialogDestructive ? 'bg-red-600 hover:bg-red-700' : ''"
                        @click="confirmDialogAction?.()"
                    >
                        Confirm
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Cancel Application Dialog -->
        <Dialog v-model:open="showCancelDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel Application</DialogTitle>
                    <DialogDescription>
                        Cancel {{ cancellingApplication?.reference_number }}? You may provide a reason.
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
