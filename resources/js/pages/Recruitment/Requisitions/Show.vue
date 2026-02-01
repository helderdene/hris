<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

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

interface Requisition {
    id: number;
    reference_number: string;
    position: { id: number; name: string };
    department: { id: number; name: string };
    requested_by: {
        id: number;
        full_name: string;
        employee_number: string;
        department: string | null;
        position: string | null;
    };
    headcount: number;
    employment_type: string;
    employment_type_label: string;
    salary_range_min: number | null;
    salary_range_max: number | null;
    justification: string;
    urgency: string;
    urgency_label: string;
    urgency_color: string;
    preferred_start_date: string | null;
    requirements: string[] | null;
    remarks: string | null;
    status: string;
    status_label: string;
    status_color: string;
    current_approval_level: number;
    total_approval_levels: number;
    approvals: Approval[];
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
    requisition: Requisition;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/requisitions' },
    { title: 'Job Requisitions', href: '/recruitment/requisitions' },
    { title: props.requisition.reference_number, href: `/recruitment/requisitions/${props.requisition.id}` },
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

function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatDateTime(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatCurrency(amount: number | null): string {
    if (amount === null) return '-';
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
}
</script>

<template>
    <Head :title="`${requisition.reference_number} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ requisition.reference_number }}
                        </h1>
                        <span
                            class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium"
                            :class="getStatusBadgeClasses(requisition.status_color)"
                        >
                            {{ requisition.status_label }}
                        </span>
                        <span
                            class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium"
                            :class="getStatusBadgeClasses(requisition.urgency_color)"
                        >
                            {{ requisition.urgency_label }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ requisition.position.name }} &middot; {{ requisition.department.name }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/recruitment/requisitions">Back to List</Link>
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Details -->
                <div class="lg:col-span-2">
                    <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Requisition Details</h2>
                        </div>
                        <div class="divide-y divide-slate-200 dark:divide-slate-700">
                            <div class="grid grid-cols-2 gap-4 px-6 py-4">
                                <div>
                                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Position</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ requisition.position.name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Department</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ requisition.department.name }}</dd>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 px-6 py-4">
                                <div>
                                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Headcount</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ requisition.headcount }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Employment Type</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ requisition.employment_type_label }}</dd>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 px-6 py-4">
                                <div>
                                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Salary Range</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">
                                        <template v-if="requisition.salary_range_min || requisition.salary_range_max">
                                            {{ formatCurrency(requisition.salary_range_min) }} - {{ formatCurrency(requisition.salary_range_max) }}
                                        </template>
                                        <template v-else>Not specified</template>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Preferred Start Date</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ formatDate(requisition.preferred_start_date) }}</dd>
                                </div>
                            </div>
                            <div class="px-6 py-4">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Justification</dt>
                                <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-900 dark:text-slate-100">{{ requisition.justification }}</dd>
                            </div>
                            <div v-if="requisition.requirements && requisition.requirements.length > 0" class="px-6 py-4">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Requirements</dt>
                                <dd class="mt-1">
                                    <ul class="list-inside list-disc space-y-1 text-sm text-slate-900 dark:text-slate-100">
                                        <li v-for="(req, i) in requisition.requirements" :key="i">{{ req }}</li>
                                    </ul>
                                </dd>
                            </div>
                            <div v-if="requisition.remarks" class="px-6 py-4">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Remarks</dt>
                                <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-900 dark:text-slate-100">{{ requisition.remarks }}</dd>
                            </div>
                            <div v-if="requisition.cancellation_reason" class="px-6 py-4">
                                <dt class="text-sm font-medium text-red-500">Cancellation Reason</dt>
                                <dd class="mt-1 text-sm text-red-600 dark:text-red-400">{{ requisition.cancellation_reason }}</dd>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar: Requester Info + Approval Chain -->
                <div class="flex flex-col gap-6">
                    <!-- Requester -->
                    <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Requested By</h2>
                        </div>
                        <div class="space-y-3 px-6 py-4">
                            <div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ requisition.requested_by.full_name }}</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">{{ requisition.requested_by.employee_number }}</div>
                            </div>
                            <div v-if="requisition.requested_by.position" class="text-sm text-slate-600 dark:text-slate-300">
                                {{ requisition.requested_by.position }}
                            </div>
                            <div v-if="requisition.requested_by.department" class="text-sm text-slate-500 dark:text-slate-400">
                                {{ requisition.requested_by.department }}
                            </div>
                        </div>
                    </div>

                    <!-- Approval Chain Timeline -->
                    <div v-if="requisition.approvals.length > 0" class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                Approval Chain
                                <span class="text-sm font-normal text-slate-500">
                                    ({{ requisition.current_approval_level }}/{{ requisition.total_approval_levels }})
                                </span>
                            </h2>
                        </div>
                        <div class="px-6 py-4">
                            <div class="space-y-4">
                                <div
                                    v-for="approval in requisition.approvals"
                                    :key="approval.id"
                                    class="relative flex gap-3 pb-4 last:pb-0"
                                >
                                    <!-- Timeline dot -->
                                    <div class="flex flex-col items-center">
                                        <div
                                            class="h-3 w-3 rounded-full border-2"
                                            :class="{
                                                'border-green-500 bg-green-500': approval.decision === 'approved',
                                                'border-red-500 bg-red-500': approval.decision === 'rejected',
                                                'border-amber-500 bg-amber-500': approval.decision === 'pending',
                                                'border-slate-300 bg-slate-300': approval.decision === 'skipped',
                                            }"
                                        />
                                        <div
                                            v-if="approval.approval_level < requisition.total_approval_levels"
                                            class="mt-1 h-full w-0.5 bg-slate-200 dark:bg-slate-700"
                                        />
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                                Level {{ approval.approval_level }}
                                            </span>
                                            <span
                                                class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium"
                                                :class="getStatusBadgeClasses(approval.decision_color)"
                                            >
                                                {{ approval.decision_label }}
                                            </span>
                                        </div>
                                        <div class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                            {{ approval.approver_name }}
                                            <span v-if="approval.approver_position" class="text-slate-400">
                                                &middot; {{ approval.approver_position }}
                                            </span>
                                        </div>
                                        <div v-if="approval.remarks" class="mt-1 text-sm italic text-slate-500 dark:text-slate-400">
                                            "{{ approval.remarks }}"
                                        </div>
                                        <div v-if="approval.decided_at" class="mt-1 text-xs text-slate-400">
                                            {{ formatDateTime(approval.decided_at) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timestamps -->
                    <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Timeline</h2>
                        </div>
                        <div class="space-y-3 px-6 py-4 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Created</span>
                                <span class="text-slate-900 dark:text-slate-100">{{ formatDateTime(requisition.created_at) }}</span>
                            </div>
                            <div v-if="requisition.submitted_at" class="flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Submitted</span>
                                <span class="text-slate-900 dark:text-slate-100">{{ formatDateTime(requisition.submitted_at) }}</span>
                            </div>
                            <div v-if="requisition.approved_at" class="flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Approved</span>
                                <span class="text-green-600 dark:text-green-400">{{ formatDateTime(requisition.approved_at) }}</span>
                            </div>
                            <div v-if="requisition.rejected_at" class="flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Rejected</span>
                                <span class="text-red-600 dark:text-red-400">{{ formatDateTime(requisition.rejected_at) }}</span>
                            </div>
                            <div v-if="requisition.cancelled_at" class="flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Cancelled</span>
                                <span class="text-slate-600 dark:text-slate-300">{{ formatDateTime(requisition.cancelled_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
