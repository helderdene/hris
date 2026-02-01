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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
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

interface Filters {
    tab: string;
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
    reason: string;
    status: string;
    status_label: string;
    status_color: string;
    submitted_at: string | null;
    current_approval_level: number;
    total_approval_levels: number;
}

const props = defineProps<{
    employee: Employee | null;
    pendingApplications: LeaveApplication[];
    historyApplications: LeaveApplication[];
    summary: Summary;
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Leave', href: '/leave/applications' },
    { title: 'Approvals', href: '/leave/approvals' },
];

const selectedTab = ref(props.filters.tab || 'pending');

// Action dialog state
const showActionDialog = ref(false);
const actionType = ref<'approve' | 'reject'>('approve');
const selectedApplication = ref<LeaveApplication | null>(null);
const actionRemarks = ref('');
const isSubmitting = ref(false);

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

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function openApproveDialog(application: LeaveApplication) {
    selectedApplication.value = application;
    actionType.value = 'approve';
    actionRemarks.value = '';
    showActionDialog.value = true;
}

function openRejectDialog(application: LeaveApplication) {
    selectedApplication.value = application;
    actionType.value = 'reject';
    actionRemarks.value = '';
    showActionDialog.value = true;
}

async function handleAction() {
    if (!selectedApplication.value) return;

    if (actionType.value === 'reject' && !actionRemarks.value.trim()) {
        return;
    }

    isSubmitting.value = true;
    try {
        const endpoint = actionType.value === 'approve'
            ? `/api/leave-approvals/${selectedApplication.value.id}/approve`
            : `/api/leave-approvals/${selectedApplication.value.id}/reject`;

        const body = actionType.value === 'approve'
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
            const data = await response.json();
        }
    } catch {
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Head :title="`Leave Approvals - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Leave Approvals
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Review and process leave requests from your team.
                </p>
            </div>

            <!-- Summary Cards -->
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

            <!-- Tabs -->
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
                    <TabsTrigger value="history">History</TabsTrigger>
                </TabsList>

                <!-- Pending Tab -->
                <TabsContent value="pending">
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                        <!-- Applications List -->
                        <div v-if="props.pendingApplications.length > 0" class="divide-y divide-slate-200 dark:divide-slate-700">
                            <div
                                v-for="application in props.pendingApplications"
                                :key="application.id"
                                class="p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <Link
                                                :href="`/leave/applications/${application.id}`"
                                                class="font-medium text-slate-900 hover:underline dark:text-slate-100"
                                            >
                                                {{ application.employee?.full_name }}
                                            </Link>
                                            <span class="text-sm text-slate-500">
                                                ({{ application.employee?.employee_number }})
                                            </span>
                                        </div>
                                        <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                            {{ application.employee?.position }}
                                            <span v-if="application.employee?.department">
                                                - {{ application.employee.department }}
                                            </span>
                                        </div>
                                        <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                            <div>
                                                <span class="text-xs text-slate-500 dark:text-slate-400">Leave Type</span>
                                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ application.leave_type?.name }}
                                                </p>
                                            </div>
                                            <div>
                                                <span class="text-xs text-slate-500 dark:text-slate-400">Dates</span>
                                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ application.date_range }}
                                                </p>
                                            </div>
                                            <div>
                                                <span class="text-xs text-slate-500 dark:text-slate-400">Duration</span>
                                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ application.total_days }} day(s)
                                                </p>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <span class="text-xs text-slate-500 dark:text-slate-400">Reason:</span>
                                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 line-clamp-2">
                                                {{ application.reason }}
                                            </p>
                                        </div>
                                        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                            Submitted {{ formatDateTime(application.submitted_at) }}
                                            <span class="mx-1">|</span>
                                            Approval {{ application.current_approval_level }}/{{ application.total_approval_levels }}
                                        </div>
                                    </div>
                                    <div class="flex gap-2 sm:flex-col">
                                        <Button
                                            @click="openApproveDialog(application)"
                                            size="sm"
                                            class="bg-green-600 hover:bg-green-700"
                                        >
                                            Approve
                                        </Button>
                                        <Button
                                            @click="openRejectDialog(application)"
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
                                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                />
                            </svg>
                            <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                No pending approvals
                            </h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                You're all caught up! Check back later for new requests.
                            </p>
                        </div>
                    </div>
                </TabsContent>

                <!-- History Tab -->
                <TabsContent value="history">
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                        <div v-if="props.historyApplications.length > 0" class="divide-y divide-slate-200 dark:divide-slate-700">
                            <div
                                v-for="application in props.historyApplications"
                                :key="application.id"
                                class="flex items-center justify-between p-4"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/leave/applications/${application.id}`"
                                            class="font-medium text-slate-900 hover:underline dark:text-slate-100"
                                        >
                                            {{ application.employee?.full_name }}
                                        </Link>
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                            :class="getStatusBadgeClasses(application.status_color)"
                                        >
                                            {{ application.status_label }}
                                        </span>
                                    </div>
                                    <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        {{ application.leave_type?.name }} | {{ application.date_range }} ({{ application.total_days }} days)
                                    </div>
                                </div>
                                <Link :href="`/leave/applications/${application.id}`">
                                    <Button variant="ghost" size="sm">View</Button>
                                </Link>
                            </div>
                        </div>
                        <div v-else class="px-6 py-12 text-center">
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                No approval history found.
                            </p>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>

        <!-- Action Dialog -->
        <Dialog v-model:open="showActionDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{ actionType === 'approve' ? 'Approve Leave Request' : 'Reject Leave Request' }}
                    </DialogTitle>
                    <DialogDescription v-if="selectedApplication">
                        {{ selectedApplication.employee?.full_name }} - {{ selectedApplication.leave_type?.name }}
                        ({{ selectedApplication.total_days }} days)
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label :for="actionType === 'approve' ? 'remarks' : 'reason'">
                            {{ actionType === 'approve' ? 'Remarks (Optional)' : 'Reason for Rejection' }}
                            <span v-if="actionType === 'reject'" class="text-red-500">*</span>
                        </Label>
                        <Textarea
                            :id="actionType === 'approve' ? 'remarks' : 'reason'"
                            v-model="actionRemarks"
                            :placeholder="actionType === 'approve' ? 'Add any comments...' : 'Please provide a reason for rejection...'"
                            rows="3"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showActionDialog = false" :disabled="isSubmitting">
                        Cancel
                    </Button>
                    <Button
                        @click="handleAction"
                        :disabled="isSubmitting || (actionType === 'reject' && !actionRemarks.trim())"
                        :class="actionType === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
                    >
                        {{ isSubmitting ? 'Processing...' : (actionType === 'approve' ? 'Approve' : 'Reject') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
