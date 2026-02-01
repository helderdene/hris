<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    employment_type_label: string;
    urgency: string;
    urgency_label: string;
    urgency_color: string;
    justification: string;
    status: string;
    status_label: string;
    status_color: string;
    submitted_at: string | null;
    current_approval_level: number;
    total_approval_levels: number;
}

interface Summary {
    pending_count: number;
    approved_today: number;
    rejected_today: number;
}

const props = defineProps<{
    employee: { id: number; full_name: string } | null;
    pendingRequisitions: Requisition[];
    historyRequisitions: { data: Requisition[]; links: any; meta: any } | Requisition[];
    summary: Summary;
    filters: { tab: string };
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/requisitions' },
    { title: 'Approvals', href: '/recruitment/approvals' },
];

const activeTab = ref(props.filters.tab || 'pending');

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

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function reloadPage() {
    router.get(window.location.pathname, { tab: activeTab.value }, {
        preserveState: true,
        preserveScroll: true,
    });
}

// Approve dialog
const showApproveDialog = ref(false);
const approvingRequisition = ref<Requisition | null>(null);
const approveRemarks = ref('');
const isProcessing = ref(false);

// Reject dialog
const showRejectDialog = ref(false);
const rejectingRequisition = ref<Requisition | null>(null);
const rejectReason = ref('');

function handleApprove(requisition: Requisition) {
    approvingRequisition.value = requisition;
    approveRemarks.value = '';
    showApproveDialog.value = true;
}

async function executeApprove() {
    if (!approvingRequisition.value) return;
    showApproveDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/job-requisition-approvals/${approvingRequisition.value.id}/approve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ remarks: approveRemarks.value || null }),
        });

        if (response.ok) {
            reloadPage();
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isProcessing.value = false;
        approvingRequisition.value = null;
    }
}

function handleReject(requisition: Requisition) {
    rejectingRequisition.value = requisition;
    rejectReason.value = '';
    showRejectDialog.value = true;
}

async function executeReject() {
    if (!rejectingRequisition.value || !rejectReason.value.trim()) {
        return;
    }
    showRejectDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/job-requisition-approvals/${rejectingRequisition.value.id}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ reason: rejectReason.value }),
        });

        if (response.ok) {
            reloadPage();
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isProcessing.value = false;
        rejectingRequisition.value = null;
    }
}

const historyData = Array.isArray(props.historyRequisitions)
    ? props.historyRequisitions
    : props.historyRequisitions.data;
</script>

<template>
    <Head :title="`Requisition Approvals - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Requisition Approvals
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Review and action pending job requisition requests.
                </p>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Pending</div>
                    <div class="mt-1 text-2xl font-semibold text-amber-600">{{ summary.pending_count }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Approved Today</div>
                    <div class="mt-1 text-2xl font-semibold text-green-600">{{ summary.approved_today }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Rejected Today</div>
                    <div class="mt-1 text-2xl font-semibold text-red-600">{{ summary.rejected_today }}</div>
                </div>
            </div>

            <!-- Tabs -->
            <Tabs v-model="activeTab">
                <TabsList>
                    <TabsTrigger value="pending">
                        Pending ({{ pendingRequisitions.length }})
                    </TabsTrigger>
                    <TabsTrigger value="history">History</TabsTrigger>
                </TabsList>

                <TabsContent value="pending">
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                        <div v-if="pendingRequisitions.length > 0" class="divide-y divide-slate-200 dark:divide-slate-700">
                            <div
                                v-for="req in pendingRequisitions"
                                :key="req.id"
                                class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex-1 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/recruitment/requisitions/${req.id}`"
                                            class="font-medium text-slate-900 hover:underline dark:text-slate-100"
                                        >
                                            {{ req.reference_number }}
                                        </Link>
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                            :class="getStatusBadgeClasses(req.urgency_color)"
                                        >
                                            {{ req.urgency_label }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-slate-600 dark:text-slate-300">
                                        {{ req.position.name }} &middot; {{ req.department.name }} &middot; {{ req.headcount }} position(s)
                                    </div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">
                                        Requested by {{ req.requested_by.full_name }}
                                    </div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">
                                        Level {{ req.current_approval_level }}/{{ req.total_approval_levels }}
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <Button
                                        class="bg-green-600 hover:bg-green-700"
                                        size="sm"
                                        @click="handleApprove(req)"
                                        :disabled="isProcessing"
                                    >
                                        Approve
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        @click="handleReject(req)"
                                        :disabled="isProcessing"
                                    >
                                        Reject
                                    </Button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="px-6 py-12 text-center">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                No pending requisitions
                            </h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                All caught up! There are no requisitions awaiting your approval.
                            </p>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="history">
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                        <div v-if="historyData.length > 0" class="divide-y divide-slate-200 dark:divide-slate-700">
                            <div
                                v-for="req in historyData"
                                :key="req.id"
                                class="flex items-center justify-between p-4"
                            >
                                <div class="space-y-1">
                                    <Link
                                        :href="`/recruitment/requisitions/${req.id}`"
                                        class="font-medium text-slate-900 hover:underline dark:text-slate-100"
                                    >
                                        {{ req.reference_number }}
                                    </Link>
                                    <div class="text-sm text-slate-500">
                                        {{ req.position.name }} &middot; {{ req.requested_by.full_name }}
                                    </div>
                                </div>
                                <span
                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                    :class="getStatusBadgeClasses(req.status_color)"
                                >
                                    {{ req.status_label }}
                                </span>
                            </div>
                        </div>
                        <div v-else class="px-6 py-12 text-center">
                            <p class="text-sm text-slate-500 dark:text-slate-400">No approval history yet.</p>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>

        <!-- Approve Dialog -->
        <Dialog v-model:open="showApproveDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Approve Requisition</DialogTitle>
                    <DialogDescription>
                        Approve {{ approvingRequisition?.reference_number }}?
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Textarea
                        v-model="approveRemarks"
                        placeholder="Remarks (optional)"
                        rows="3"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showApproveDialog = false">Cancel</Button>
                    <Button class="bg-green-600 hover:bg-green-700" @click="executeApprove">
                        Approve
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Reject Dialog -->
        <Dialog v-model:open="showRejectDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject Requisition</DialogTitle>
                    <DialogDescription>
                        Reject {{ rejectingRequisition?.reference_number }}? A reason is required.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Textarea
                        v-model="rejectReason"
                        placeholder="Reason for rejection (required)"
                        rows="3"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showRejectDialog = false">Cancel</Button>
                    <Button variant="destructive" @click="executeReject">
                        Reject
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
