<script setup lang="ts">
import {
    ActivityFeedCard,
    InlineApprovalDialog,
    NotificationsHubCard,
    PendingActionsCard,
    PriorityAlertsCard,
    QuickActionsCard,
    type ActivityItem,
    type ApprovalAction,
    type ApprovalTarget,
    type Notification,
    type PendingCounts,
    type PriorityItem,
} from '@/components/ActionCenter';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { useActionCenterLive } from '@/composables/useActionCenterLive';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Deferred, Head, router } from '@inertiajs/vue3';
import { onMounted, ref, computed } from 'vue';
import { toast } from 'vue-sonner';

interface LeaveApprovalDetail {
    id: number;
    leave_application_id: number;
    employee_name: string;
    leave_type: string;
    start_date: string;
    end_date: string;
    total_days: number;
    reason: string;
    is_overdue: boolean;
    is_approaching_deadline: boolean;
    priority_level: string | null;
    hours_remaining: number | null;
    hours_overdue: number;
    created_at: string;
}

interface RequisitionApprovalDetail {
    id: number;
    job_requisition_id: number;
    position_name: string;
    department_name: string;
    requested_by: string;
    number_of_positions: number;
    justification: string;
    is_overdue: boolean;
    is_approaching_deadline: boolean;
    priority_level: string | null;
    hours_remaining: number | null;
    hours_overdue: number;
    created_at: string;
}

const props = defineProps<{
    justCreated?: boolean;
    pendingActions: PendingCounts;
    priorityItems: PriorityItem[];
    notifications?: Notification[];
    unreadNotificationCount?: number;
    activityFeed?: ActivityItem[];
    pendingLeaveDetails?: LeaveApprovalDetail[];
    pendingRequisitionDetails?: RequisitionApprovalDetail[];
}>();

const { tenantName, primaryColor, isAdmin, userRole } = useTenant();
const showSuccessBanner = ref(props.justCreated ?? false);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// Real-time updates
const { isConnected, isPolling, refresh } = useActionCenterLive({
    onUpdate: (update) => {
        // Show toast notification for updates
        if (update.action.includes('approved')) {
            toast.success('Request approved successfully');
        } else if (update.action.includes('rejected')) {
            toast.info('Request rejected');
        }
        // Refresh data
        refresh();
    },
});

// Inline approval dialog state
const showApprovalDialog = ref(false);
const approvalAction = ref<ApprovalAction>('approve');
const approvalTarget = ref<ApprovalTarget | null>(null);
const isProcessing = ref(false);

// Track current item being processed for finding related data
const currentItemId = ref<number | null>(null);
const currentItemType = ref<string | null>(null);

onMounted(() => {
    // Clear the query param from URL without reload
    if (props.justCreated) {
        window.history.replaceState({}, '', window.location.pathname);
        // Auto-hide success banner after 8 seconds
        setTimeout(() => {
            showSuccessBanner.value = false;
        }, 8000);
    }
});

function handleApprove(item: PriorityItem) {
    currentItemId.value = item.id;
    currentItemType.value = item.type;
    approvalAction.value = 'approve';
    approvalTarget.value = {
        id: item.id,
        type: item.type as 'leave_approval' | 'requisition_approval',
        title: item.title,
        employee_name: item.employee_name,
        description: item.description,
    };
    showApprovalDialog.value = true;
}

function handleReject(item: PriorityItem) {
    currentItemId.value = item.id;
    currentItemType.value = item.type;
    approvalAction.value = 'reject';
    approvalTarget.value = {
        id: item.id,
        type: item.type as 'leave_approval' | 'requisition_approval',
        title: item.title,
        employee_name: item.employee_name,
        description: item.description,
    };
    showApprovalDialog.value = true;
}

async function handleApprovalSubmit(remarks: string) {
    if (!approvalTarget.value) return;

    isProcessing.value = true;

    try {
        // Determine the correct endpoint based on type
        let endpoint: string;
        let leaveApplicationId: number | null = null;
        let jobRequisitionId: number | null = null;

        if (approvalTarget.value.type === 'leave_approval') {
            // Find the leave application ID from the details
            const detail = props.pendingLeaveDetails?.find(d => d.id === approvalTarget.value!.id);
            leaveApplicationId = detail?.leave_application_id ?? null;

            if (!leaveApplicationId) {
                throw new Error('Leave application not found');
            }

            endpoint = approvalAction.value === 'approve'
                ? `/api/action-center/leave-approvals/${leaveApplicationId}/approve`
                : `/api/action-center/leave-approvals/${leaveApplicationId}/reject`;
        } else {
            // Find the job requisition ID from the details
            const detail = props.pendingRequisitionDetails?.find(d => d.id === approvalTarget.value!.id);
            jobRequisitionId = detail?.job_requisition_id ?? null;

            if (!jobRequisitionId) {
                throw new Error('Job requisition not found');
            }

            endpoint = approvalAction.value === 'approve'
                ? `/api/action-center/requisitions/${jobRequisitionId}/approve`
                : `/api/action-center/requisitions/${jobRequisitionId}/reject`;
        }

        const body = approvalAction.value === 'approve'
            ? { remarks: remarks || null }
            : { reason: remarks };

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''
                ),
            },
            body: JSON.stringify(body),
        });

        const result = await response.json();

        if (result.success) {
            toast.success(result.message);
            showApprovalDialog.value = false;
            // Refresh the page data
            router.reload({
                only: [
                    'pendingActions',
                    'priorityItems',
                    'pendingLeaveDetails',
                    'pendingRequisitionDetails',
                    'activityFeed',
                ],
            });
        } else {
            toast.error(result.message || 'Action failed');
        }
    } catch (error) {
        console.error('Approval error:', error);
        toast.error('An error occurred. Please try again.');
    } finally {
        isProcessing.value = false;
    }
}

const connectionStatusClass = computed(() => {
    if (isConnected.value) return 'bg-green-500';
    if (isPolling.value) return 'bg-amber-500';
    return 'bg-slate-400';
});

const connectionStatusText = computed(() => {
    if (isConnected.value) return 'Live';
    if (isPolling.value) return 'Polling';
    return 'Offline';
});
</script>

<template>
    <Head :title="`Dashboard - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Success Banner for newly created organization -->
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="showSuccessBanner"
                    class="flex items-center justify-between gap-4 rounded-xl bg-emerald-50 px-4 py-3 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200"
                >
                    <div class="flex items-center gap-3">
                        <svg
                            class="h-5 w-5 shrink-0"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <span class="font-medium">
                            Organization created successfully! Welcome to
                            {{ tenantName }}.
                        </span>
                    </div>
                    <button
                        type="button"
                        class="rounded p-1 hover:bg-emerald-100 dark:hover:bg-emerald-800/50"
                        @click="showSuccessBanner = false"
                    >
                        <svg
                            class="h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                            />
                        </svg>
                    </button>
                </div>
            </Transition>

            <!-- Welcome Banner with tenant branding and connection status -->
            <div
                class="relative overflow-hidden rounded-xl p-6 text-white"
                :style="{ backgroundColor: primaryColor }"
            >
                <div class="relative z-10 flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">
                            Welcome to {{ tenantName }}
                        </h1>
                        <p class="mt-1 text-white/80">
                            You are logged in as
                            <span class="font-medium">{{
                                userRole === 'admin' ? 'Administrator' : 'HR Staff'
                            }}</span>
                        </p>
                    </div>
                    <!-- Connection status indicator -->
                    <div
                        class="flex items-center gap-2 rounded-full bg-white/20 px-3 py-1"
                    >
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="connectionStatusClass"
                        />
                        <span class="text-xs font-medium text-white/90">
                            {{ connectionStatusText }}
                        </span>
                    </div>
                </div>
                <!-- Decorative pattern -->
                <div
                    class="absolute inset-0 opacity-10"
                    style="
                        background-image: url(&quot;data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E&quot;);
                    "
                />
            </div>

            <!-- Priority Alerts Section (Full Width at Top) -->
            <PriorityAlertsCard
                :items="priorityItems"
                @approve="handleApprove"
                @reject="handleReject"
            />

            <!-- Main Dashboard Grid -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column: Notifications + Activity -->
                <div class="space-y-6 lg:col-span-1">
                    <!-- Notifications Hub -->
                    <Deferred data="notifications,unreadNotificationCount">
                        <template #fallback>
                            <NotificationsHubCard
                                :notifications="null"
                                :unread-count="null"
                                :loading="true"
                            />
                        </template>
                        <NotificationsHubCard
                            :notifications="notifications ?? []"
                            :unread-count="unreadNotificationCount ?? 0"
                        />
                    </Deferred>

                    <!-- Activity Feed -->
                    <Deferred data="activityFeed">
                        <template #fallback>
                            <ActivityFeedCard :activities="null" :loading="true" />
                        </template>
                        <ActivityFeedCard :activities="activityFeed ?? []" />
                    </Deferred>
                </div>

                <!-- Right Column: Pending Actions + Quick Actions -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Pending Actions -->
                    <PendingActionsCard :counts="pendingActions" />

                    <!-- Quick Actions -->
                    <QuickActionsCard />
                </div>
            </div>
        </div>

        <!-- Inline Approval Dialog -->
        <InlineApprovalDialog
            v-model:open="showApprovalDialog"
            :action="approvalAction"
            :target="approvalTarget"
            :processing="isProcessing"
            @submit="handleApprovalSubmit"
        />
    </TenantLayout>
</template>
