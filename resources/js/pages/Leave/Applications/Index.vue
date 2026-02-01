<script setup lang="ts">
import LeaveApplicationFormModal from '@/components/LeaveApplicationFormModal.vue';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
}

interface LeaveType {
    id: number;
    name: string;
    code: string;
    requires_attachment: boolean;
    min_days_advance_notice: number;
}

interface Balance {
    leave_type_id: number;
    leave_type_name: string;
    available: number;
    used: number;
    pending: number;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface Filters {
    status: string | null;
    year: number;
}

interface LeaveApplication {
    id: number;
    reference_number: string;
    leave_type: {
        id: number;
        name: string;
        code: string;
    };
    start_date: string;
    end_date: string;
    date_range: string;
    total_days: number;
    reason: string;
    status: string;
    status_label: string;
    status_color: string;
    submitted_at: string | null;
    created_at: string;
    can_be_edited: boolean;
    can_be_cancelled: boolean;
}

const props = defineProps<{
    employee: Employee | null;
    leaveTypes: LeaveType[];
    balances: Balance[];
    applications: LeaveApplication[];
    statuses: StatusOption[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Leave', href: '/leave/applications' },
    { title: 'My Applications', href: '/leave/applications' },
];

const selectedStatus = ref(props.filters.status || 'all');
const selectedYear = ref(String(props.filters.year));

// Modal states
const isFormModalOpen = ref(false);
const editingApplication = ref<LeaveApplication | null>(null);

function reloadPage() {
    const params: Record<string, string> = {
        year: selectedYear.value,
    };
    if (selectedStatus.value !== 'all') {
        params.status = selectedStatus.value;
    }
    router.get(window.location.pathname, params, {
        preserveState: true,
        preserveScroll: true,
    });
}

const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear();
    return [
        { value: String(currentYear), label: String(currentYear) },
        { value: String(currentYear - 1), label: String(currentYear - 1) },
        { value: String(currentYear - 2), label: String(currentYear - 2) },
    ];
});

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

function handleStatusChange(value: string) {
    selectedStatus.value = value;
    reloadPage();
}

function handleYearChange(value: string) {
    selectedYear.value = value;
    reloadPage();
}

function handleAddApplication() {
    editingApplication.value = null;
    isFormModalOpen.value = true;
}

function handleEditApplication(application: LeaveApplication) {
    editingApplication.value = application;
    isFormModalOpen.value = true;
}

// Confirmation dialog state
const showConfirmDialog = ref(false);
const confirmDialogTitle = ref('');
const confirmDialogDescription = ref('');
const confirmDialogAction = ref<(() => void) | null>(null);
const confirmDialogDestructive = ref(false);

// Cancel dialog state
const showCancelDialog = ref(false);
const cancellingApplication = ref<LeaveApplication | null>(null);
const cancelReason = ref('');

// Loading state
const isProcessing = ref(false);

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function handleSubmitApplication(application: LeaveApplication) {
    confirmDialogTitle.value = 'Submit Application';
    confirmDialogDescription.value = `Submit ${application.reference_number} for approval?`;
    confirmDialogDestructive.value = false;
    confirmDialogAction.value = () => executeSubmit(application);
    showConfirmDialog.value = true;
}

async function executeSubmit(application: LeaveApplication) {
    showConfirmDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/leave-applications/${application.id}/submit`, {
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

function handleCancelApplication(application: LeaveApplication) {
    cancellingApplication.value = application;
    cancelReason.value = '';
    showCancelDialog.value = true;
}

async function executeCancelApplication() {
    if (!cancellingApplication.value) return;
    showCancelDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/leave-applications/${cancellingApplication.value.id}/cancel`, {
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

function handleDeleteApplication(application: LeaveApplication) {
    confirmDialogTitle.value = 'Delete Draft';
    confirmDialogDescription.value = `Delete draft ${application.reference_number}? This cannot be undone.`;
    confirmDialogDestructive.value = true;
    confirmDialogAction.value = () => executeDelete(application);
    showConfirmDialog.value = true;
}

async function executeDelete(application: LeaveApplication) {
    showConfirmDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/leave-applications/${application.id}`, {
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

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingApplication.value = null;
    reloadPage();
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head :title="`Leave Applications - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        My Leave Applications
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Submit and track your leave requests.
                    </p>
                </div>
                <Button
                    v-if="employee"
                    @click="handleAddApplication"
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
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    New Application
                </Button>
            </div>

            <!-- Balance Cards -->
            <div v-if="balances.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="balance in balances"
                    :key="balance.leave_type_id"
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        {{ balance.leave_type_name }}
                    </div>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-2xl font-semibold text-slate-900 dark:text-slate-100">
                            {{ balance.available }}
                        </span>
                        <span class="text-sm text-slate-500 dark:text-slate-400">days available</span>
                    </div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Used: {{ balance.used }} | Pending: {{ balance.pending }}
                    </div>
                </div>
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

                <Select :model-value="selectedYear" @update:model-value="handleYearChange">
                    <SelectTrigger class="w-32">
                        <SelectValue placeholder="Year" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="year in yearOptions"
                            :key="year.value"
                            :value="year.value"
                        >
                            {{ year.label }}
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
                                    Leave Type
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Dates
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Days
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
                                        :href="`/leave/applications/${application.id}`"
                                        class="font-medium text-slate-900 hover:underline dark:text-slate-100"
                                    >
                                        {{ application.reference_number }}
                                    </Link>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ application.leave_type?.name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ application.date_range }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ application.total_days }}
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
                                                <Link :href="`/leave/applications/${application.id}`">
                                                    View Details
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="application.can_be_edited"
                                                @click="handleEditApplication(application)"
                                            >
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="application.status === 'draft'"
                                                @click="handleSubmitApplication(application)"
                                            >
                                                Submit for Approval
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
                <div v-else-if="props.applications.length > 0" class="divide-y divide-slate-200 md:hidden dark:divide-slate-700">
                    <div
                        v-for="application in props.applications"
                        :key="application.id"
                        class="space-y-2 p-4"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <Link
                                    :href="`/leave/applications/${application.id}`"
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.reference_number }}
                                </Link>
                                <div class="text-sm text-slate-500">
                                    {{ application.leave_type?.name }}
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
                            <span class="text-slate-500">{{ application.date_range }}</span>
                            <span class="font-medium">{{ application.total_days }} day(s)</span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="px-6 py-12 text-center">
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
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No leave applications found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Submit your first leave request to get started.
                    </p>
                    <div v-if="employee" class="mt-6">
                        <Button @click="handleAddApplication" :style="{ backgroundColor: primaryColor }">
                            New Application
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Modal -->
        <LeaveApplicationFormModal
            v-model:open="isFormModalOpen"
            :application="editingApplication"
            :employee="employee"
            :leave-types="leaveTypes"
            :balances="balances"
            @success="handleFormSuccess"
        />

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
