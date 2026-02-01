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
    BookOpen,
    Calendar,
    CheckCircle2,
    ClipboardCheck,
    Clock,
    MapPin,
    Users,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface TrainingEnrollmentRequest {
    id: number;
    reference_number: string;
    employee: {
        id: number;
        full_name: string;
        employee_number: string;
        department: string | null;
        position: string | null;
        avatar_url: string | null;
    };
    session: {
        id: number;
        title: string;
        start_date: string;
        end_date: string;
        date_range: string;
        time_range: string | null;
        location: string | null;
        enrolled_count: number;
        max_participants: number | null;
        is_full: boolean;
        course: {
            id: number;
            title: string;
            code: string | null;
        } | null;
    };
    request_reason: string | null;
    status: string;
    status_label: string;
    status_color: string;
    submitted_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    rejection_reason: string | null;
    can_approve: boolean;
    can_reject: boolean;
}

const props = defineProps<{
    pendingEnrollments: { data: TrainingEnrollmentRequest[] };
    recentEnrollments: { data: TrainingEnrollmentRequest[] };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Training', href: '/training/courses' },
    { title: 'Approvals', href: '/training/approvals' },
];

const activeTab = ref('pending');
const showRemarksDialog = ref(false);
const remarksAction = ref<'approve' | 'reject'>('approve');
const remarksEnrollmentId = ref<number | null>(null);
const remarks = ref('');
const isProcessing = ref(false);

const pendingCount = computed(() => props.pendingEnrollments.data?.length ?? 0);

function badgeClasses(color: string): string {
    const map: Record<string, string> = {
        yellow: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
        gray: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
    return map[color] ?? map.gray;
}

function formatDate(dateString: string | null): string {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function openRemarksDialog(
    action: 'approve' | 'reject',
    enrollmentId: number,
): void {
    remarksAction.value = action;
    remarksEnrollmentId.value = enrollmentId;
    remarks.value = '';
    showRemarksDialog.value = true;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function submitDecision(): Promise<void> {
    if (!remarksEnrollmentId.value) return;

    if (remarksAction.value === 'reject' && !remarks.value.trim()) {
        return;
    }

    isProcessing.value = true;

    const endpoint =
        remarksAction.value === 'approve' ? 'approve' : 'reject';
    const bodyKey =
        remarksAction.value === 'approve' ? 'remarks' : 'reason';

    try {
        await fetch(
            `/api/training/enrollment-approvals/${remarksEnrollmentId.value}/${endpoint}`,
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
    <Head :title="`Training Enrollment Approvals - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Training Enrollment Approvals
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Review and manage training enrollment requests from your
                    team.
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
                            >Pending Requests</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ pendingCount }}
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
                            >Approved (Recent)</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{
                                recentEnrollments.data?.filter(
                                    (e) => e.status === 'confirmed',
                                ).length ?? 0
                            }}
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
                            >Rejected (Recent)</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{
                                recentEnrollments.data?.filter(
                                    (e) => e.status === 'rejected',
                                ).length ?? 0
                            }}
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
                    <span
                        v-if="pendingCount > 0"
                        class="ml-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs font-medium text-white"
                    >
                        {{ pendingCount }}
                    </span>
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

            <!-- Pending Enrollments -->
            <div v-if="activeTab === 'pending'">
                <div
                    v-if="!pendingEnrollments.data || pendingEnrollments.data.length === 0"
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
                        No pending training enrollment requests to review.
                    </p>
                </div>

                <div v-else class="flex flex-col gap-4">
                    <div
                        v-for="enrollment in pendingEnrollments.data"
                        :key="enrollment.id"
                        class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="min-w-0 flex-1">
                                <!-- Employee Info -->
                                <div class="flex items-center gap-3">
                                    <h3
                                        class="font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{ enrollment.employee.full_name }}
                                    </h3>
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            badgeClasses(
                                                enrollment.status_color,
                                            )
                                        "
                                    >
                                        {{ enrollment.status_label }}
                                    </span>
                                </div>
                                <p
                                    class="mt-0.5 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        [
                                            enrollment.employee.department,
                                            enrollment.employee.position,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </p>

                                <!-- Training Session Info -->
                                <div
                                    class="mt-4 rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
                                >
                                    <div class="flex items-start gap-3">
                                        <BookOpen
                                            class="mt-0.5 h-5 w-5 shrink-0 text-blue-500"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <h4
                                                class="font-medium text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    enrollment.session.title
                                                }}
                                            </h4>
                                            <div
                                                class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600 dark:text-slate-400"
                                            >
                                                <span
                                                    class="flex items-center gap-1"
                                                >
                                                    <Calendar
                                                        class="h-4 w-4"
                                                    />
                                                    {{
                                                        enrollment.session
                                                            .date_range
                                                    }}
                                                </span>
                                                <span
                                                    v-if="
                                                        enrollment.session
                                                            .time_range
                                                    "
                                                    class="flex items-center gap-1"
                                                >
                                                    <Clock class="h-4 w-4" />
                                                    {{
                                                        enrollment.session
                                                            .time_range
                                                    }}
                                                </span>
                                                <span
                                                    v-if="
                                                        enrollment.session
                                                            .location
                                                    "
                                                    class="flex items-center gap-1"
                                                >
                                                    <MapPin class="h-4 w-4" />
                                                    {{
                                                        enrollment.session
                                                            .location
                                                    }}
                                                </span>
                                                <span
                                                    class="flex items-center gap-1"
                                                >
                                                    <Users class="h-4 w-4" />
                                                    {{
                                                        enrollment.session
                                                            .enrolled_count
                                                    }}{{
                                                        enrollment.session
                                                            .max_participants
                                                            ? ` / ${enrollment.session.max_participants}`
                                                            : ''
                                                    }}
                                                    enrolled
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Request Reason -->
                                <div
                                    v-if="enrollment.request_reason"
                                    class="mt-3"
                                >
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        <span class="font-medium"
                                            >Request Reason:</span
                                        >
                                    </p>
                                    <p
                                        class="mt-1 text-sm text-slate-700 dark:text-slate-300"
                                    >
                                        {{ enrollment.request_reason }}
                                    </p>
                                </div>

                                <!-- Submitted Date -->
                                <p
                                    v-if="enrollment.submitted_at"
                                    class="mt-2 text-xs text-slate-400 dark:text-slate-500"
                                >
                                    Submitted:
                                    {{ formatDate(enrollment.submitted_at) }}
                                </p>
                            </div>

                            <!-- Action Buttons -->
                            <div
                                class="flex shrink-0 gap-2 lg:flex-col lg:items-end"
                            >
                                <Button
                                    v-if="enrollment.can_reject"
                                    variant="outline"
                                    size="sm"
                                    class="text-red-600 hover:text-red-700"
                                    @click="
                                        openRemarksDialog(
                                            'reject',
                                            enrollment.id,
                                        )
                                    "
                                >
                                    Reject
                                </Button>
                                <Button
                                    v-if="enrollment.can_approve"
                                    size="sm"
                                    @click="
                                        openRemarksDialog(
                                            'approve',
                                            enrollment.id,
                                        )
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
                        !recentEnrollments.data ||
                        recentEnrollments.data.length === 0
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
                                    Training
                                </th>
                                <th
                                    class="hidden px-6 py-3 font-medium text-slate-500 lg:table-cell dark:text-slate-400"
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
                                v-for="enrollment in recentEnrollments.data"
                                :key="enrollment.id"
                                class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ enrollment.employee.full_name }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ enrollment.reference_number }}
                                    </div>
                                </td>
                                <td
                                    class="hidden px-6 py-4 text-slate-700 md:table-cell dark:text-slate-300"
                                >
                                    {{ enrollment.session.title }}
                                </td>
                                <td
                                    class="hidden px-6 py-4 text-slate-700 lg:table-cell dark:text-slate-300"
                                >
                                    {{ enrollment.session.date_range }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            badgeClasses(
                                                enrollment.status_color,
                                            )
                                        "
                                    >
                                        {{ enrollment.status_label }}
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
                                ? 'Approve Enrollment'
                                : 'Reject Enrollment'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            remarksAction === 'approve'
                                ? 'Add optional remarks for this approval.'
                                : 'Please provide a reason for rejecting this enrollment request.'
                        }}
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Textarea
                        v-model="remarks"
                        :placeholder="
                            remarksAction === 'approve'
                                ? 'Optional remarks...'
                                : 'Reason for rejection (required)...'
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
                        :disabled="
                            isProcessing ||
                            (remarksAction === 'reject' && !remarks.trim())
                        "
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
