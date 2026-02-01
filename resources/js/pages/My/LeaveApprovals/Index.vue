<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Head } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ClipboardCheck,
    Clock,
    XCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface ApprovalApplication {
    id: number;
    reference_number: string;
    employee: {
        id: number;
        full_name: string;
        employee_number: string;
        department: string | null;
        position: string | null;
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
    employee: { id: number; full_name: string } | null;
    pendingApplications: ApprovalApplication[];
    historyApplications: {
        data: ApprovalApplication[];
        links: unknown;
    };
    summary: {
        pending_count: number;
        approved_today: number;
        rejected_today: number;
    };
    filters: {
        tab: string;
    };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self-Service', href: '/my/dashboard' },
    { title: 'Leave Approvals', href: '/my/leave-approvals' },
];

const activeTab = ref(props.filters.tab);
const showRemarksDialog = ref(false);
const remarksAction = ref<'approve' | 'reject'>('approve');
const remarksApplicationId = ref<number | null>(null);
const remarks = ref('');
const isProcessing = ref(false);

function badgeClasses(color: string): string {
    const map: Record<string, string> = {
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
    return map[color] ?? map.slate;
}

function openRemarksDialog(
    action: 'approve' | 'reject',
    applicationId: number,
): void {
    remarksAction.value = action;
    remarksApplicationId.value = applicationId;
    remarks.value = '';
    showRemarksDialog.value = true;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function submitDecision(): Promise<void> {
    if (!remarksApplicationId.value) return;

    isProcessing.value = true;

    const endpoint =
        remarksAction.value === 'approve' ? 'approve' : 'reject';
    const bodyKey =
        remarksAction.value === 'approve' ? 'remarks' : 'reason';

    try {
        await fetch(
            `/api/leave-approvals/${remarksApplicationId.value}/${endpoint}`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ [bodyKey]: remarks.value }),
            },
        );
        showRemarksDialog.value = false;
        window.location.reload();
    } finally {
        isProcessing.value = false;
    }
}
</script>

<template>
    <Head :title="`Leave Approvals - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Leave Approvals
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Review and manage leave requests from your team.
                </p>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <Card class="dark:border-slate-700 dark:bg-slate-900">
                    <CardHeader
                        class="flex flex-row items-center gap-3 pb-2"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400"
                        >
                            <Clock class="h-5 w-5" />
                        </div>
                        <CardTitle
                            class="text-sm font-medium text-slate-500 dark:text-slate-400"
                            >Pending</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ summary.pending_count }}
                        </p>
                    </CardContent>
                </Card>

                <Card class="dark:border-slate-700 dark:bg-slate-900">
                    <CardHeader
                        class="flex flex-row items-center gap-3 pb-2"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-100 text-green-600 dark:bg-green-900/40 dark:text-green-400"
                        >
                            <CheckCircle2 class="h-5 w-5" />
                        </div>
                        <CardTitle
                            class="text-sm font-medium text-slate-500 dark:text-slate-400"
                            >Approved Today</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ summary.approved_today }}
                        </p>
                    </CardContent>
                </Card>

                <Card class="dark:border-slate-700 dark:bg-slate-900">
                    <CardHeader
                        class="flex flex-row items-center gap-3 pb-2"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400"
                        >
                            <XCircle class="h-5 w-5" />
                        </div>
                        <CardTitle
                            class="text-sm font-medium text-slate-500 dark:text-slate-400"
                            >Rejected Today</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ summary.rejected_today }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs -->
            <div
                class="flex gap-2 border-b border-slate-200 pb-2 dark:border-slate-700"
            >
                <button
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'pending'
                            ? 'bg-blue-500 text-white'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                    "
                    @click="activeTab = 'pending'"
                >
                    Pending
                </button>
                <button
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'history'
                            ? 'bg-blue-500 text-white'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                    "
                    @click="activeTab = 'history'"
                >
                    History
                </button>
            </div>

            <!-- Pending Applications -->
            <div v-if="activeTab === 'pending'">
                <div
                    v-if="pendingApplications.length === 0"
                    class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
                >
                    <ClipboardCheck
                        class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                    />
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        All caught up
                    </h3>
                    <p
                        class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                    >
                        No pending leave requests to review.
                    </p>
                </div>

                <div v-else class="flex flex-col gap-4">
                    <div
                        v-for="app in pendingApplications"
                        :key="app.id"
                        class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-3">
                                    <h3
                                        class="font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{ app.employee.full_name }}
                                    </h3>
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            badgeClasses(app.status_color)
                                        "
                                    >
                                        {{ app.status_label }}
                                    </span>
                                </div>
                                <p
                                    class="mt-0.5 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        [
                                            app.employee.department,
                                            app.employee.position,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-4 text-sm">
                                    <div>
                                        <span
                                            class="text-slate-500 dark:text-slate-400"
                                            >Type:</span
                                        >
                                        <span
                                            class="ml-1 font-medium text-slate-900 dark:text-slate-100"
                                            >{{
                                                app.leave_type.name
                                            }}</span
                                        >
                                    </div>
                                    <div>
                                        <span
                                            class="text-slate-500 dark:text-slate-400"
                                            >Dates:</span
                                        >
                                        <span
                                            class="ml-1 font-medium text-slate-900 dark:text-slate-100"
                                            >{{ app.date_range }}</span
                                        >
                                    </div>
                                    <div>
                                        <span
                                            class="text-slate-500 dark:text-slate-400"
                                            >Days:</span
                                        >
                                        <span
                                            class="ml-1 font-medium text-slate-900 dark:text-slate-100"
                                            >{{ app.total_days }}</span
                                        >
                                    </div>
                                </div>
                                <p
                                    v-if="app.reason"
                                    class="mt-2 text-sm text-slate-600 dark:text-slate-300"
                                >
                                    {{ app.reason }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="text-red-600 hover:text-red-700"
                                    @click="
                                        openRemarksDialog('reject', app.id)
                                    "
                                >
                                    Reject
                                </Button>
                                <Button
                                    size="sm"
                                    @click="
                                        openRemarksDialog('approve', app.id)
                                    "
                                >
                                    Approve
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History -->
            <div v-if="activeTab === 'history'">
                <div
                    v-if="
                        !historyApplications.data ||
                        historyApplications.data.length === 0
                    "
                    class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
                >
                    <ClipboardCheck
                        class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                    />
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No history
                    </h3>
                    <p
                        class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                    >
                        No past approval decisions found.
                    </p>
                </div>

                <div
                    v-else
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                >
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800"
                        >
                            <tr>
                                <th
                                    class="px-6 py-3 font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Employee
                                </th>
                                <th
                                    class="hidden px-6 py-3 font-medium text-slate-500 md:table-cell dark:text-slate-400"
                                >
                                    Leave Type
                                </th>
                                <th
                                    class="hidden px-6 py-3 font-medium text-slate-500 md:table-cell dark:text-slate-400"
                                >
                                    Dates
                                </th>
                                <th
                                    class="px-6 py-3 text-center font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <tr
                                v-for="app in historyApplications.data"
                                :key="app.id"
                                class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ app.employee.full_name }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ app.reference_number }}
                                    </div>
                                </td>
                                <td
                                    class="hidden px-6 py-4 text-slate-700 md:table-cell dark:text-slate-300"
                                >
                                    {{ app.leave_type.name }}
                                </td>
                                <td
                                    class="hidden px-6 py-4 text-slate-700 md:table-cell dark:text-slate-300"
                                >
                                    {{ app.date_range }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            badgeClasses(app.status_color)
                                        "
                                    >
                                        {{ app.status_label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Remarks Dialog -->
        <Dialog v-model:open="showRemarksDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            remarksAction === 'approve'
                                ? 'Approve Leave'
                                : 'Reject Leave'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            remarksAction === 'approve'
                                ? 'Add optional remarks for this approval.'
                                : 'Please provide a reason for rejecting this leave request.'
                        }}
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Textarea
                        v-model="remarks"
                        :placeholder="
                            remarksAction === 'approve'
                                ? 'Optional remarks...'
                                : 'Reason for rejection...'
                        "
                        rows="3"
                    />
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showRemarksDialog = false"
                        :disabled="isProcessing"
                    >
                        Cancel
                    </Button>
                    <Button
                        :variant="
                            remarksAction === 'approve'
                                ? 'default'
                                : 'destructive'
                        "
                        @click="submitDecision"
                        :disabled="isProcessing"
                    >
                        {{
                            isProcessing
                                ? 'Processing...'
                                : remarksAction === 'approve'
                                  ? 'Approve'
                                  : 'Reject'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
