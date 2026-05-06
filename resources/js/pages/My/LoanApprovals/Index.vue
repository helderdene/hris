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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, Clock } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Approval {
    id: number;
    approval_level: number;
    approver_type: string;
    decision: string;
    decision_label: string;
    remarks: string | null;
    decided_at: string | null;
    deadline_at: string | null;
    is_overdue: boolean;
}

interface LoanApp {
    id: number;
    reference_number: string;
    employee: {
        id: number;
        full_name: string;
        employee_number: string;
        department: string | null;
        position: string | null;
    };
    loan_type: string;
    loan_type_label: string;
    amount_requested: number;
    term_months: number;
    purpose: string | null;
    urgency_level: number | null;
    status: string;
    status_label: string;
    status_color: string;
    submitted_at: string | null;
    current_approval_level: number;
    total_approval_levels: number;
    sla_deadline_at: string | null;
    my_approval: Approval | null;
    is_final_step: boolean;
}

defineProps<{
    employee: { id: number; full_name: string } | null;
    pendingApplications: LoanApp[];
    historyApplications: LoanApp[];
    summary: {
        pending_count: number;
        approved_today: number;
        rejected_today: number;
    };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Loan Approvals', href: '/loan-approvals' },
];

const activeTab = ref<'pending' | 'history'>('pending');
const dialogOpen = ref(false);
const dialogMode = ref<'approve' | 'reject'>('approve');
const dialogApp = ref<LoanApp | null>(null);
const remarks = ref('');
const interestRate = ref('');
const startDate = ref('');
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);

const isFinalStep = computed(() => dialogApp.value?.is_final_step ?? false);
const showLoanFields = computed(
    () => isFinalStep.value && dialogMode.value === 'approve',
);

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function openDialog(app: LoanApp, mode: 'approve' | 'reject'): void {
    dialogApp.value = app;
    dialogMode.value = mode;
    remarks.value = '';
    interestRate.value = '';
    startDate.value = new Date().toISOString().split('T')[0];
    errorMessage.value = null;
    dialogOpen.value = true;
}

async function submitDecision(): Promise<void> {
    if (!dialogApp.value) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = null;

    const url = `/api/loan-applications/${dialogApp.value.id}/${dialogMode.value === 'approve' ? 'approve' : 'reject'}`;

    const body: Record<string, unknown> = {
        remarks: remarks.value || null,
    };
    if (showLoanFields.value) {
        body.interest_rate = Number(interestRate.value);
        body.start_date = startDate.value;
    }

    try {
        const response = await fetch(url, {
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
            dialogOpen.value = false;
            router.reload({ only: ['pendingApplications', 'historyApplications', 'summary'] });
        } else {
            const data = await response.json();
            const firstError = Object.values(data.errors ?? {}).flat()[0] as
                | string
                | undefined;
            errorMessage.value = firstError ?? data.message ?? 'Failed to submit decision.';
        }
    } catch {
        errorMessage.value = 'An unexpected error occurred. Please try again.';
    } finally {
        isSubmitting.value = false;
    }
}

function urgencyBadgeClass(level: number | null): string {
    if (level === 5) {
        return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
    }
    if (level === 4) {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
    }
    return 'bg-slate-100 text-slate-700 dark:bg-slate-700/40 dark:text-slate-300';
}

function urgencyLabel(level: number | null): string {
    return level === 5
        ? 'High (5)'
        : level === 4
          ? 'Somewhat High (4)'
          : level === 3
            ? 'Medium (3)'
            : level === 2
              ? 'Somewhat Low (2)'
              : level === 1
                ? 'Low (1)'
                : '—';
}

function approverTypeLabel(type: string): string {
    return {
        cfo: 'CFO',
        admin_manager: 'Admin Manager',
        releasing_officer: 'Releasing',
    }[type] ?? type;
}

function formatDeadline(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="`Loan Approvals - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Loan Approvals
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Review loan applications waiting on your decision.
                </p>
            </div>

            <!-- Summary -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Pending Your Decision
                    </p>
                    <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                        {{ summary.pending_count }}
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Approved Today
                    </p>
                    <p class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ summary.approved_today }}
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Rejected Today
                    </p>
                    <p class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">
                        {{ summary.rejected_today }}
                    </p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-2 border-b border-slate-200 dark:border-slate-700">
                <button
                    type="button"
                    class="border-b-2 px-4 py-2 text-sm font-medium"
                    :class="
                        activeTab === 'pending'
                            ? 'border-slate-900 text-slate-900 dark:border-slate-100 dark:text-slate-100'
                            : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                    "
                    @click="activeTab = 'pending'"
                >
                    Pending ({{ summary.pending_count }})
                </button>
                <button
                    type="button"
                    class="border-b-2 px-4 py-2 text-sm font-medium"
                    :class="
                        activeTab === 'history'
                            ? 'border-slate-900 text-slate-900 dark:border-slate-100 dark:text-slate-100'
                            : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                    "
                    @click="activeTab = 'history'"
                >
                    My History
                </button>
            </div>

            <!-- Pending list -->
            <div v-if="activeTab === 'pending'" class="space-y-3">
                <div
                    v-if="pendingApplications.length === 0"
                    class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"
                >
                    No applications waiting on your decision.
                </div>

                <div
                    v-for="app in pendingApplications"
                    :key="app.id"
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                    :class="app.my_approval?.is_overdue ? 'ring-2 ring-red-300 dark:ring-red-900' : ''"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-slate-900 dark:text-slate-100">
                                    {{ app.employee.full_name }}
                                </span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    ({{ app.employee.employee_number }})
                                </span>
                                <span
                                    class="rounded px-1.5 py-0.5 text-[11px] font-medium uppercase"
                                    :class="urgencyBadgeClass(app.urgency_level)"
                                >
                                    Urgency: {{ urgencyLabel(app.urgency_level) }}
                                </span>
                                <span
                                    v-if="app.my_approval?.is_overdue"
                                    class="inline-flex items-center gap-1 rounded bg-red-100 px-1.5 py-0.5 text-[11px] font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                >
                                    <AlertTriangle class="h-3 w-3" />
                                    Overdue
                                </span>
                            </div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ app.reference_number }} ·
                                {{ app.loan_type_label }} ·
                                Step {{ app.current_approval_level }}/{{ app.total_approval_levels }}
                                ({{ approverTypeLabel(app.my_approval?.approver_type ?? '') }})
                            </div>
                        </div>

                        <div class="text-right">
                            <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                ₱{{ app.amount_requested.toLocaleString() }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ app.term_months }} months
                            </p>
                        </div>
                    </div>

                    <p
                        v-if="app.purpose"
                        class="mt-3 text-sm text-slate-700 dark:text-slate-300"
                    >
                        <span class="font-medium">Purpose:</span> {{ app.purpose }}
                    </p>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                        <p class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                            <Clock class="h-3 w-3" />
                            Deadline: {{ formatDeadline(app.my_approval?.deadline_at ?? null) }}
                        </p>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                @click="openDialog(app, 'reject')"
                            >
                                Reject
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                @click="openDialog(app, 'approve')"
                            >
                                {{ app.is_final_step ? 'Approve & Release' : 'Approve' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History list -->
            <div v-else class="space-y-3">
                <div
                    v-if="historyApplications.length === 0"
                    class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"
                >
                    No decision history yet.
                </div>

                <div
                    v-for="app in historyApplications"
                    :key="app.id"
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100">
                                {{ app.employee.full_name }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ app.reference_number }} · {{ app.loan_type_label }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                ₱{{ app.amount_requested.toLocaleString() }}
                            </p>
                            <p
                                class="mt-1 inline-block rounded px-1.5 py-0.5 text-[11px] font-medium uppercase"
                                :class="
                                    app.my_approval?.decision === 'approved'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                        : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                "
                            >
                                {{ app.my_approval?.decision_label }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="app.my_approval?.remarks"
                        class="mt-2 text-sm text-slate-600 dark:text-slate-400"
                    >
                        {{ app.my_approval.remarks }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Decision dialog -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ dialogMode === 'approve' ? 'Approve' : 'Reject' }}
                        Loan Application
                    </DialogTitle>
                    <DialogDescription>
                        {{ dialogApp?.reference_number }} —
                        {{ dialogApp?.employee.full_name }} (₱{{ dialogApp?.amount_requested.toLocaleString() }})
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div
                        v-if="errorMessage"
                        class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"
                    >
                        {{ errorMessage }}
                    </div>

                    <div v-if="showLoanFields" class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="interest_rate">
                                Interest Rate (decimal) <span class="text-red-500">*</span>
                            </Label>
                            <Input
                                id="interest_rate"
                                v-model="interestRate"
                                type="number"
                                step="0.0001"
                                min="0"
                                max="1"
                                placeholder="0.10 = 10%"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="start_date">
                                Start Date <span class="text-red-500">*</span>
                            </Label>
                            <Input
                                id="start_date"
                                v-model="startDate"
                                type="date"
                            />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="remarks">
                            Remarks
                            <span v-if="dialogMode === 'reject'" class="text-red-500">*</span>
                        </Label>
                        <Textarea
                            id="remarks"
                            v-model="remarks"
                            rows="3"
                            :placeholder="
                                dialogMode === 'approve'
                                    ? 'Optional notes for the next approver or applicant.'
                                    : 'Why is this being rejected?'
                            "
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogOpen = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="isSubmitting || (dialogMode === 'reject' && !remarks)"
                        @click="submitDecision"
                    >
                        {{
                            isSubmitting
                                ? 'Submitting…'
                                : dialogMode === 'approve'
                                  ? (isFinalStep ? 'Approve & Release' : 'Approve')
                                  : 'Reject'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
