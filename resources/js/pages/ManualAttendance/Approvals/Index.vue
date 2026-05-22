<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Employee {
    id: number;
    full_name: string;
}

interface Summary {
    pending_count: number;
    approved_today: number;
    rejected_today: number;
}

interface ExistingLog {
    id: number;
    logged_at: string | null;
    direction: string | null;
    source: string | null;
}

interface ManualAttendanceRequest {
    id: number;
    reference_number: string;
    employee: {
        id: number;
        full_name: string;
        employee_number: string;
        department?: string | null;
        position?: string | null;
    };
    attendance_date: string;
    time_in: string | null;
    time_out: string | null;
    reason: string;
    status: string;
    status_label: string;
    status_color: string;
    submitted_at: string | null;
    decided_at?: string | null;
    decided_by_role?: string | null;
    decision_remarks?: string | null;
    existing_logs?: ExistingLog[];
}

const props = defineProps<{
    employee: Employee | null;
    pendingRequests: ManualAttendanceRequest[];
    historyRequests: ManualAttendanceRequest[];
    summary: Summary;
    filters: {
        tab: string;
    };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Time & Attendance', href: '/attendance' },
    { title: 'Manual Attendance Approvals', href: '/manual-attendance/approvals' },
];

const selectedTab = ref(props.filters.tab || 'pending');

const showActionDialog = ref(false);
const actionType = ref<'approve' | 'reject'>('approve');
const selectedRequest = ref<ManualAttendanceRequest | null>(null);
const actionRemarks = ref('');
const isSubmitting = ref(false);
const actionError = ref('');

function reloadPage() {
    router.reload();
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

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatDateTime(dateString: string | null | undefined): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function punchSummary(req: ManualAttendanceRequest): string {
    const parts: string[] = [];
    if (req.time_in) parts.push(`In ${req.time_in}`);
    if (req.time_out) parts.push(`Out ${req.time_out}`);
    return parts.join(' • ') || '—';
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function openApproveDialog(req: ManualAttendanceRequest) {
    selectedRequest.value = req;
    actionType.value = 'approve';
    actionRemarks.value = '';
    actionError.value = '';
    showActionDialog.value = true;
}

function openRejectDialog(req: ManualAttendanceRequest) {
    selectedRequest.value = req;
    actionType.value = 'reject';
    actionRemarks.value = '';
    actionError.value = '';
    showActionDialog.value = true;
}

async function handleAction() {
    if (!selectedRequest.value) return;
    if (actionType.value === 'reject' && !actionRemarks.value.trim()) return;

    actionError.value = '';
    isSubmitting.value = true;
    try {
        const endpoint = `/api/manual-attendance-requests/${selectedRequest.value.id}/${actionType.value}`;

        const body =
            actionType.value === 'approve'
                ? { remarks: actionRemarks.value || null }
                : { reason: actionRemarks.value };

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        if (response.ok) {
            showActionDialog.value = false;
            reloadPage();
        } else {
            const data = await response.json().catch(() => ({}));
            actionError.value =
                data.message ||
                data.errors?.approver?.[0] ||
                'Could not complete the action. The request may have already been decided.';
        }
    } catch {
        actionError.value = 'Network error. Please try again.';
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Head :title="`Manual Attendance Approvals - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Manual Attendance Approvals
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Review missing-punch requests from your team. Either the Department Head or HR may approve.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Pending Approval
                        </div>
                        <div class="mt-1 text-3xl font-semibold text-amber-600 dark:text-amber-400">
                            {{ summary.pending_count }}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Approved Today
                        </div>
                        <div class="mt-1 text-3xl font-semibold text-green-600 dark:text-green-400">
                            {{ summary.approved_today }}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Rejected Today
                        </div>
                        <div class="mt-1 text-3xl font-semibold text-red-600 dark:text-red-400">
                            {{ summary.rejected_today }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Tabs v-model="selectedTab" class="space-y-4">
                <TabsList>
                    <TabsTrigger value="pending">
                        Pending
                        <span
                            v-if="summary.pending_count > 0"
                            class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                        >
                            {{ summary.pending_count }}
                        </span>
                    </TabsTrigger>
                    <TabsTrigger value="history">My Decisions</TabsTrigger>
                </TabsList>

                <TabsContent value="pending">
                    <div
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div
                            v-if="pendingRequests.length > 0"
                            class="divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <div
                                v-for="req in pendingRequests"
                                :key="req.id"
                                class="p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ req.employee.full_name }}
                                            </span>
                                            <span class="text-sm text-slate-500">
                                                ({{ req.employee.employee_number }})
                                            </span>
                                            <span class="text-xs text-slate-400">{{ req.reference_number }}</span>
                                        </div>
                                        <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                            <span v-if="req.employee.position">{{ req.employee.position }}</span>
                                            <span v-if="req.employee.department">
                                                {{ req.employee.position ? '·' : '' }} {{ req.employee.department }}
                                            </span>
                                        </div>

                                        <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                            <div>
                                                <span class="text-xs text-slate-500 dark:text-slate-400">Date</span>
                                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ formatDate(req.attendance_date) }}
                                                </p>
                                            </div>
                                            <div>
                                                <span class="text-xs text-slate-500 dark:text-slate-400">Time In</span>
                                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ req.time_in || '—' }}
                                                </p>
                                            </div>
                                            <div>
                                                <span class="text-xs text-slate-500 dark:text-slate-400">Time Out</span>
                                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ req.time_out || '—' }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="mt-2">
                                            <span class="text-xs text-slate-500 dark:text-slate-400">Reason:</span>
                                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                                {{ req.reason }}
                                            </p>
                                        </div>

                                        <div
                                            v-if="req.existing_logs && req.existing_logs.length > 0"
                                            class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-2 dark:border-amber-800 dark:bg-amber-900/20"
                                        >
                                            <p class="text-xs font-medium text-amber-700 dark:text-amber-300">
                                                Existing punches on this date:
                                            </p>
                                            <ul class="mt-1 space-y-0.5 text-xs text-amber-800 dark:text-amber-200">
                                                <li v-for="log in req.existing_logs" :key="log.id">
                                                    {{ formatDateTime(log.logged_at) }} — {{ log.direction || 'unknown' }}
                                                    ({{ log.source || 'biometric' }})
                                                </li>
                                            </ul>
                                        </div>

                                        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                            Submitted {{ formatDateTime(req.submitted_at) }}
                                        </div>
                                    </div>
                                    <div class="flex gap-2 sm:flex-col">
                                        <Button
                                            @click="openApproveDialog(req)"
                                            size="sm"
                                            class="bg-green-600 hover:bg-green-700"
                                        >
                                            Approve
                                        </Button>
                                        <Button
                                            @click="openRejectDialog(req)"
                                            size="sm"
                                            variant="outline"
                                            class="border-red-300 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                                        >
                                            Reject
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

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
                                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                />
                            </svg>
                            <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                No pending approvals
                            </h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                You're all caught up.
                            </p>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="history">
                    <div
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div
                            v-if="historyRequests.length > 0"
                            class="divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <div
                                v-for="req in historyRequests"
                                :key="req.id"
                                class="p-4"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ req.employee.full_name }}
                                            </span>
                                            <span
                                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                                :class="getStatusBadgeClasses(req.status_color)"
                                            >
                                                {{ req.status_label }}
                                            </span>
                                        </div>
                                        <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                            {{ formatDate(req.attendance_date) }} · {{ punchSummary(req) }}
                                        </div>
                                        <div
                                            v-if="req.decision_remarks"
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            "{{ req.decision_remarks }}"
                                        </div>
                                    </div>
                                    <div class="text-right text-xs text-slate-500 dark:text-slate-400">
                                        <div>{{ formatDateTime(req.decided_at) }}</div>
                                        <div v-if="req.decided_by_role" class="mt-0.5 capitalize">
                                            as {{ req.decided_by_role.replace('_', ' ') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="px-6 py-12 text-center">
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                No approval history yet.
                            </p>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>

        <Dialog v-model:open="showActionDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{ actionType === 'approve' ? 'Approve Request' : 'Reject Request' }}
                    </DialogTitle>
                    <DialogDescription v-if="selectedRequest">
                        {{ selectedRequest.employee.full_name }} —
                        {{ formatDate(selectedRequest.attendance_date) }} ({{ punchSummary(selectedRequest) }})
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div
                        v-if="actionError"
                        class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"
                    >
                        {{ actionError }}
                    </div>
                    <div class="space-y-2">
                        <Label :for="actionType === 'approve' ? 'remarks' : 'reason'">
                            {{ actionType === 'approve' ? 'Remarks (Optional)' : 'Reason for Rejection' }}
                            <span v-if="actionType === 'reject'" class="text-red-500">*</span>
                        </Label>
                        <Textarea
                            :id="actionType === 'approve' ? 'remarks' : 'reason'"
                            v-model="actionRemarks"
                            :placeholder="
                                actionType === 'approve'
                                    ? 'Add any comments...'
                                    : 'Please provide a reason for rejection...'
                            "
                            rows="3"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showActionDialog = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleAction"
                        :disabled="isSubmitting || (actionType === 'reject' && !actionRemarks.trim())"
                        :class="actionType === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
                    >
                        {{ isSubmitting ? 'Processing...' : actionType === 'approve' ? 'Approve' : 'Reject' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
