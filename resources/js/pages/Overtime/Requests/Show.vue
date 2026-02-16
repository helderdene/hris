<script setup lang="ts">
import OvertimeApprovalTimeline from '@/components/OvertimeApprovalTimeline.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, Clock, FileText, User } from 'lucide-vue-next';

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
    is_pending: boolean;
    is_decided: boolean;
}

interface OvertimeRequest {
    id: number;
    reference_number: string;
    employee: {
        id: number;
        full_name: string;
        employee_number: string;
        department: string | null;
        position: string | null;
    };
    overtime_date: string;
    expected_minutes: number;
    expected_hours_formatted: string;
    expected_start_time: string | null;
    expected_end_time: string | null;
    overtime_type: string;
    overtime_type_label: string;
    overtime_type_color: string;
    reason: string;
    status: string;
    status_label: string;
    status_color: string;
    current_approval_level: number;
    total_approval_levels: number;
    submitted_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    created_at: string;
    approvals: Approval[];
}

const props = defineProps<{
    request: OvertimeRequest;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'OT Requests', href: '/overtime/requests' },
    { title: props.request.reference_number, href: `/overtime/requests/${props.request.id}` },
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

function getTypeBadgeClasses(color: string): string {
    switch (color) {
        case 'blue':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'orange':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300';
        case 'purple':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
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
</script>

<template>
    <Head :title="`${request.reference_number} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Back link + Header -->
            <div>
                <Link href="/overtime/requests" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                    <ArrowLeft class="h-4 w-4" />
                    Back to OT Requests
                </Link>
                <div class="flex items-center gap-4">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ request.reference_number }}
                    </h1>
                    <span
                        class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium"
                        :class="getStatusBadgeClasses(request.status_color)"
                    >
                        {{ request.status_label }}
                    </span>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Details -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Employee Info -->
                    <Card class="dark:border-slate-700 dark:bg-slate-900">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <User class="h-4 w-4" />
                                Employee
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Name</span>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ request.employee.full_name }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Employee No.</span>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ request.employee.employee_number }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Department</span>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ request.employee.department || '-' }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Position</span>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ request.employee.position || '-' }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Overtime Details -->
                    <Card class="dark:border-slate-700 dark:bg-slate-900">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Clock class="h-4 w-4" />
                                Overtime Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Date</span>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatDate(request.overtime_date) }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">OT Type</span>
                                    <p>
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                            :class="getTypeBadgeClasses(request.overtime_type_color)"
                                        >
                                            {{ request.overtime_type_label }}
                                        </span>
                                    </p>
                                </div>
                                <div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Duration</span>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ request.expected_hours_formatted }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Time</span>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        <template v-if="request.expected_start_time && request.expected_end_time">
                                            {{ request.expected_start_time }} - {{ request.expected_end_time }}
                                        </template>
                                        <template v-else>-</template>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <span class="text-xs text-slate-500 dark:text-slate-400">Reason</span>
                                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                                    {{ request.reason }}
                                </p>
                            </div>
                            <div v-if="request.cancellation_reason" class="mt-4 rounded-md bg-red-50 p-3 dark:bg-red-900/20">
                                <span class="text-xs font-medium text-red-700 dark:text-red-400">Cancellation Reason</span>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-300">
                                    {{ request.cancellation_reason }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar: Timeline + Dates -->
                <div class="space-y-6">
                    <!-- Approval Timeline -->
                    <Card class="dark:border-slate-700 dark:bg-slate-900">
                        <CardHeader>
                            <CardTitle class="text-base">Approval Timeline</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <OvertimeApprovalTimeline :approvals="request.approvals" />
                        </CardContent>
                    </Card>

                    <!-- Key Dates -->
                    <Card class="dark:border-slate-700 dark:bg-slate-900">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Calendar class="h-4 w-4" />
                                Key Dates
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-xs text-slate-500 dark:text-slate-400">Created</dt>
                                    <dd class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatDateTime(request.created_at) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-slate-500 dark:text-slate-400">Submitted</dt>
                                    <dd class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatDateTime(request.submitted_at) }}
                                    </dd>
                                </div>
                                <div v-if="request.approved_at">
                                    <dt class="text-xs text-slate-500 dark:text-slate-400">Approved</dt>
                                    <dd class="text-sm font-medium text-green-600 dark:text-green-400">
                                        {{ formatDateTime(request.approved_at) }}
                                    </dd>
                                </div>
                                <div v-if="request.rejected_at">
                                    <dt class="text-xs text-slate-500 dark:text-slate-400">Rejected</dt>
                                    <dd class="text-sm font-medium text-red-600 dark:text-red-400">
                                        {{ formatDateTime(request.rejected_at) }}
                                    </dd>
                                </div>
                                <div v-if="request.cancelled_at">
                                    <dt class="text-xs text-slate-500 dark:text-slate-400">Cancelled</dt>
                                    <dd class="text-sm font-medium text-slate-600 dark:text-slate-400">
                                        {{ formatDateTime(request.cancelled_at) }}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
