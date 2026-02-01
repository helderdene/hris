<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
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

interface Approval {
    id: number;
    approval_level: number;
    approver_type: string;
    approver_name: string;
    approver_position: string | null;
    decision: string;
    decision_label: string;
    decision_color: string;
    remarks: string | null;
    decided_at: string | null;
}

interface LeaveApplication {
    id: number;
    reference_number: string;
    employee: {
        id: number;
        full_name: string;
        employee_number: string;
        department?: string;
        position?: string;
    };
    leave_type: {
        id: number;
        name: string;
        code: string;
    };
    start_date: string;
    end_date: string;
    date_range: string;
    total_days: number;
    is_half_day_start: boolean;
    is_half_day_end: boolean;
    reason: string;
    status: string;
    status_label: string;
    status_color: string;
    current_approval_level: number;
    total_approval_levels: number;
    approvals: Approval[];
    balance: {
        available: number;
        used: number;
        pending: number;
    } | null;
    cancellation_reason: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    cancelled_at: string | null;
    created_at: string;
    can_be_edited: boolean;
    can_be_cancelled: boolean;
}

const props = defineProps<{
    application: LeaveApplication;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Leave', href: '/leave/applications' },
    { title: 'My Applications', href: '/leave/applications' },
    { title: props.application.reference_number, href: `/leave/applications/${props.application.id}` },
];

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
        const response = await fetch(`/api/leave-applications/${props.application.id}/submit`, {
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
        const response = await fetch(`/api/leave-applications/${props.application.id}/cancel`, {
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
    <Head :title="`${application.reference_number} - Leave Application - ${tenantName}`" />

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
                        Submit for Approval
                    </Button>
                    <Button
                        v-if="application.can_be_cancelled"
                        @click="handleCancel"
                        variant="outline"
                    >
                        Cancel Request
                    </Button>
                    <Link href="/leave/applications">
                        <Button variant="ghost">Back to List</Button>
                    </Link>
                </div>
            </div>

            <!-- Main Content -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column - Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Leave Details Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Leave Details</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Leave Type
                                    </label>
                                    <p class="mt-1 text-slate-900 dark:text-slate-100">
                                        {{ application.leave_type.name }}
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Duration
                                    </label>
                                    <p class="mt-1 text-slate-900 dark:text-slate-100">
                                        {{ application.total_days }} day(s)
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Start Date
                                    </label>
                                    <p class="mt-1 text-slate-900 dark:text-slate-100">
                                        {{ formatDate(application.start_date) }}
                                        <span v-if="application.is_half_day_start" class="text-sm text-slate-500">(Half day)</span>
                                    </p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        End Date
                                    </label>
                                    <p class="mt-1 text-slate-900 dark:text-slate-100">
                                        {{ formatDate(application.end_date) }}
                                        <span v-if="application.is_half_day_end" class="text-sm text-slate-500">(Half day)</span>
                                    </p>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Reason
                                </label>
                                <p class="mt-1 whitespace-pre-wrap text-slate-900 dark:text-slate-100">
                                    {{ application.reason }}
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

                    <!-- Approval Timeline -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Approval Timeline</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div
                                    v-for="approval in application.approvals"
                                    :key="approval.id"
                                    class="relative flex gap-4 pb-4"
                                    :class="{ 'border-b border-slate-200 dark:border-slate-700': approval.approval_level < application.total_approval_levels }"
                                >
                                    <!-- Status indicator -->
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                        :class="{
                                            'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400': approval.decision === 'approved',
                                            'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400': approval.decision === 'rejected',
                                            'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400': approval.decision === 'pending',
                                            'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400': approval.decision === 'skipped',
                                        }"
                                    >
                                        <svg v-if="approval.decision === 'approved'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <svg v-else-if="approval.decision === 'rejected'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ approval.approver_name }}
                                                </p>
                                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                                    Level {{ approval.approval_level }} - {{ approval.approver_type }}
                                                    <span v-if="approval.approver_position"> ({{ approval.approver_position }})</span>
                                                </p>
                                            </div>
                                            <span
                                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                                :class="getStatusBadgeClasses(approval.decision_color)"
                                            >
                                                {{ approval.decision_label }}
                                            </span>
                                        </div>
                                        <p v-if="approval.remarks" class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                            "{{ approval.remarks }}"
                                        </p>
                                        <p v-if="approval.decided_at" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            {{ formatDateTime(approval.decided_at) }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="application.approvals.length === 0" class="text-center py-4 text-slate-500 dark:text-slate-400">
                                    No approvers assigned yet. Submit the application to start the approval process.
                                </div>
                            </div>
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

                    <!-- Balance Summary -->
                    <Card v-if="application.balance">
                        <CardHeader>
                            <CardTitle>Leave Balance</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Available</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ application.balance.available }} days
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Used</span>
                                    <span class="text-slate-600 dark:text-slate-300">
                                        {{ application.balance.used }} days
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Pending</span>
                                    <span class="text-slate-600 dark:text-slate-300">
                                        {{ application.balance.pending }} days
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Timeline -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Timeline</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Created</span>
                                    <span class="text-slate-600 dark:text-slate-300">
                                        {{ formatDateTime(application.created_at) }}
                                    </span>
                                </div>
                                <div v-if="application.submitted_at" class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Submitted</span>
                                    <span class="text-slate-600 dark:text-slate-300">
                                        {{ formatDateTime(application.submitted_at) }}
                                    </span>
                                </div>
                                <div v-if="application.approved_at" class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Approved</span>
                                    <span class="text-green-600 dark:text-green-400">
                                        {{ formatDateTime(application.approved_at) }}
                                    </span>
                                </div>
                                <div v-if="application.rejected_at" class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Rejected</span>
                                    <span class="text-red-600 dark:text-red-400">
                                        {{ formatDateTime(application.rejected_at) }}
                                    </span>
                                </div>
                                <div v-if="application.cancelled_at" class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Cancelled</span>
                                    <span class="text-slate-600 dark:text-slate-300">
                                        {{ formatDateTime(application.cancelled_at) }}
                                    </span>
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
                        Submit {{ application.reference_number }} for approval?
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
