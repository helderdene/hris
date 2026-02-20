<script setup lang="ts">
import VisitorStatusBadge from '@/components/VisitorStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import type { BreadcrumbItem, VisitorVisitData } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CheckCircle,
    Loader2,
    UserRoundCheck,
    XCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    pending: VisitorVisitData[];
    history: VisitorVisitData[];
    pendingCount: number;
    hasEmployeeProfile: boolean;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self-Service', href: '/my/dashboard' },
    { title: 'My Visitors', href: '/my/visitors' },
];

const activeTab = ref<'pending' | 'history'>('pending');

// Notification
const notification = ref<{ message: string; type: 'success' | 'error' } | null>(null);
let notificationTimeout: ReturnType<typeof setTimeout> | null = null;

function showNotification(message: string, type: 'success' | 'error') {
    if (notificationTimeout) {
        clearTimeout(notificationTimeout);
    }
    notification.value = { message, type };
    notificationTimeout = setTimeout(() => {
        notification.value = null;
    }, 4000);
}

// Reject modal
const showRejectModal = ref(false);
const rejectVisitId = ref<number | null>(null);
const rejectReason = ref('');
const processingId = ref<number | null>(null);

function openRejectModal(visitId: number) {
    rejectVisitId.value = visitId;
    rejectReason.value = '';
    showRejectModal.value = true;
}

async function handleApprove(visitId: number) {
    processingId.value = visitId;
    try {
        await axios.post(`/api/my/visitor-visits/${visitId}/approve`);
        showNotification('Visit approved successfully.', 'success');
        router.reload();
    } catch {
        showNotification('Failed to approve visit.', 'error');
    } finally {
        processingId.value = null;
    }
}

async function handleReject() {
    if (!rejectVisitId.value) return;
    processingId.value = rejectVisitId.value;
    try {
        await axios.post(`/api/my/visitor-visits/${rejectVisitId.value}/reject`, {
            reason: rejectReason.value || undefined,
        });
        showRejectModal.value = false;
        showNotification('Visit rejected.', 'success');
        router.reload();
    } catch {
        showNotification('Failed to reject visit.', 'error');
    } finally {
        processingId.value = null;
    }
}

function formatDate(dateStr?: string): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="`My Visitors - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    My Visitors
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Review and approve visitors assigned to you as host
                </p>
            </div>

            <!-- No employee profile -->
            <div v-if="!hasEmployeeProfile" class="rounded-xl border border-slate-200 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
                <UserRoundCheck class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">No employee profile</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Your account is not linked to an employee profile.
                </p>
            </div>

            <template v-else>
                <!-- Tabs -->
                <div class="flex gap-1 overflow-x-auto rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800/50">
                    <button
                        @click="activeTab = 'pending'"
                        class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                        :class="[
                            activeTab === 'pending'
                                ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-slate-100'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200',
                        ]"
                    >
                        Pending Approval
                        <span
                            v-if="pendingCount > 0"
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="[
                                activeTab === 'pending'
                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                    : 'bg-slate-200 text-slate-600 dark:bg-slate-600 dark:text-slate-300',
                            ]"
                        >
                            {{ pendingCount }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'history'"
                        class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                        :class="[
                            activeTab === 'history'
                                ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-slate-100'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200',
                        ]"
                    >
                        History
                    </button>
                </div>

                <!-- Pending Tab -->
                <div v-if="activeTab === 'pending'" class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                    <template v-if="pending.length > 0">
                        <!-- Desktop Table -->
                        <div class="hidden md:block">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-800/50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Visitor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Purpose</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Expected</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                    <tr v-for="visit in pending" :key="visit.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ visit.visitor?.full_name }}</div>
                                            <div class="text-sm text-slate-500 dark:text-slate-400">{{ visit.visitor?.company || visit.visitor?.email || '' }}</div>
                                        </td>
                                        <td class="max-w-[200px] truncate px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ visit.purpose }}</td>
                                        <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300">{{ visit.work_location?.name || '-' }}</td>
                                        <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300">{{ formatDate(visit.expected_at) }}</td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    @click="handleApprove(visit.id)"
                                                    :disabled="processingId === visit.id"
                                                >
                                                    <Loader2 v-if="processingId === visit.id" class="mr-1 h-4 w-4 animate-spin" />
                                                    <CheckCircle v-else class="mr-1 h-4 w-4" />
                                                    Approve
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    @click="openRejectModal(visit.id)"
                                                    :disabled="processingId === visit.id"
                                                >
                                                    <XCircle class="mr-1 h-4 w-4" />
                                                    Reject
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Card List -->
                        <div class="divide-y divide-slate-200 md:hidden dark:divide-slate-700">
                            <div v-for="visit in pending" :key="visit.id" class="p-4">
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ visit.visitor?.full_name }}</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">{{ visit.visitor?.company || '' }}</div>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ visit.purpose }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ formatDate(visit.expected_at) }} &middot; {{ visit.work_location?.name || '' }}</p>
                                <div class="mt-3 flex items-center gap-2">
                                    <Button size="sm" @click="handleApprove(visit.id)" :disabled="processingId === visit.id">Approve</Button>
                                    <Button size="sm" variant="destructive" @click="openRejectModal(visit.id)" :disabled="processingId === visit.id">Reject</Button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <div v-else class="px-6 py-12 text-center">
                        <UserRoundCheck class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                        <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">No pending visitors</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            You have no visitor requests awaiting your approval.
                        </p>
                    </div>
                </div>

                <!-- History Tab -->
                <div v-if="activeTab === 'history'" class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                    <template v-if="history.length > 0">
                        <div class="hidden md:block">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-800/50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Visitor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Purpose</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Expected</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                    <tr v-for="visit in history" :key="visit.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ visit.visitor?.full_name }}</div>
                                            <div class="text-sm text-slate-500 dark:text-slate-400">{{ visit.visitor?.company || '' }}</div>
                                        </td>
                                        <td class="max-w-[200px] truncate px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ visit.purpose }}</td>
                                        <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300">{{ formatDate(visit.expected_at) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <VisitorStatusBadge :status="visit.status" :label="visit.status_label" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Card List -->
                        <div class="divide-y divide-slate-200 md:hidden dark:divide-slate-700">
                            <div v-for="visit in history" :key="visit.id" class="p-4">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="font-medium text-slate-900 dark:text-slate-100">{{ visit.visitor?.full_name }}</div>
                                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ visit.visitor?.company || '' }}</div>
                                    </div>
                                    <VisitorStatusBadge :status="visit.status" :label="visit.status_label" />
                                </div>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ visit.purpose }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ formatDate(visit.expected_at) }}</p>
                            </div>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <div v-else class="px-6 py-12 text-center">
                        <UserRoundCheck class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                        <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">No history</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            No past visitor records found.
                        </p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Reject Reason Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Reject Visit</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Optionally provide a reason for rejecting this visit.</p>
                        <textarea
                            v-model="rejectReason"
                            rows="3"
                            class="mt-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            placeholder="Reason for rejection (optional)"
                        ></textarea>
                        <div class="mt-4 flex justify-end gap-3">
                            <Button variant="outline" @click="showRejectModal = false" :disabled="processingId !== null">Cancel</Button>
                            <Button variant="destructive" @click="handleReject" :disabled="processingId !== null">
                                <Loader2 v-if="processingId !== null" class="mr-1 h-4 w-4 animate-spin" />
                                Reject
                            </Button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Notification popup -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-if="notification"
                class="fixed right-6 bottom-6 z-50 flex items-center gap-2 rounded-lg border px-4 py-3 shadow-lg"
                :class="
                    notification.type === 'success'
                        ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/50 dark:text-green-300'
                        : 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-900/50 dark:text-red-300'
                "
            >
                <CheckCircle v-if="notification.type === 'success'" class="h-4 w-4 shrink-0 text-green-500" />
                <XCircle v-else class="h-4 w-4 shrink-0 text-red-500" />
                <span class="text-sm font-medium">{{ notification.message }}</span>
            </div>
        </Transition>
    </TenantLayout>
</template>
