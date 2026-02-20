<script setup lang="ts">
import VisitorApprovalModal from '@/components/VisitorApprovalModal.vue';
import VisitorCheckInModal from '@/components/VisitorCheckInModal.vue';
import VisitorPreRegisterModal from '@/components/VisitorPreRegisterModal.vue';
import VisitorStatusBadge from '@/components/VisitorStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import type { BreadcrumbItem, VisitorVisitData } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CheckCircle,
    Clock,
    Download,
    Filter,
    Loader2,
    LogIn,
    LogOut,
    Mail,
    Plus,
    Search,
    UserRoundCheck,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface LocationOption {
    id: number;
    name: string;
}

const props = defineProps<{
    locations: LocationOption[];
    pendingCount: number;
    todayCount: number;
    checkedInCount: number;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Visitors', href: '/visitors' },
];

const activeTab = ref<'pending' | 'today' | 'checked_in' | 'all'>('pending');
const visits = ref<VisitorVisitData[]>([]);
const loading = ref(false);
const searchQuery = ref('');
const locationFilter = ref('');

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

// Modals
const showPreRegisterModal = ref(false);
const showApprovalModal = ref(false);
const showCheckInModal = ref(false);
const approvalMode = ref<'approve' | 'reject'>('approve');
const selectedVisit = ref<VisitorVisitData | null>(null);
const resendingQrId = ref<number | null>(null);

const tabs = computed(() => [
    { key: 'pending' as const, label: 'Pending Approval', count: props.pendingCount },
    { key: 'today' as const, label: 'Expected Today', count: props.todayCount },
    { key: 'checked_in' as const, label: 'Checked In', count: props.checkedInCount },
    { key: 'all' as const, label: 'All Visits', count: null },
]);

async function fetchVisits() {
    loading.value = true;
    try {
        const params: Record<string, string> = {};
        if (activeTab.value === 'pending') params.status = 'pending_approval';
        if (activeTab.value === 'today') {
            const now = new Date();
            const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
            params.date_from = today;
            params.date_to = today;
        }
        if (activeTab.value === 'checked_in') params.status = 'checked_in';
        if (searchQuery.value) params.search = searchQuery.value;
        if (locationFilter.value) params.work_location_id = locationFilter.value;

        const response = await axios.get('/api/visitor-visits', { params });
        visits.value = response.data.data;

        // Refresh tab counts from server
        router.reload({ only: ['pendingCount', 'todayCount', 'checkedInCount'] });
    } finally {
        loading.value = false;
    }
}

function switchTab(tab: typeof activeTab.value) {
    activeTab.value = tab;
    fetchVisits();
}

function openApproval(visit: VisitorVisitData, mode: 'approve' | 'reject') {
    selectedVisit.value = visit;
    approvalMode.value = mode;
    showApprovalModal.value = true;
}

function openCheckIn(visit: VisitorVisitData) {
    selectedVisit.value = visit;
    showCheckInModal.value = true;
}

async function handleApprove(visitId: number) {
    try {
        await axios.post(`/api/visitor-visits/${visitId}/approve`);
        showApprovalModal.value = false;
        showNotification('Visit approved (admin). Awaiting host approval to finalize.', 'success');
        fetchVisits();
    } catch {
        showApprovalModal.value = false;
        showNotification('This visit can no longer be approved. It may have already been processed.', 'error');
        fetchVisits();
    }
}

async function handleReject(visitId: number, reason: string) {
    try {
        await axios.post(`/api/visitor-visits/${visitId}/reject`, { reason });
        showApprovalModal.value = false;
        showNotification('Visit rejected.', 'success');
        fetchVisits();
    } catch {
        showApprovalModal.value = false;
        showNotification('This visit can no longer be rejected. It may have already been processed.', 'error');
        fetchVisits();
    }
}

async function handleCheckIn(visitId: number, badgeNumber: string) {
    await axios.post(`/api/visitor-visits/${visitId}/check-in`, { badge_number: badgeNumber || undefined });
    showCheckInModal.value = false;
    fetchVisits();
}

async function handleCheckOut(visit: VisitorVisitData) {
    await axios.post(`/api/visitor-visits/${visit.id}/check-out`);
    fetchVisits();
}

async function handleResendQr(visit: VisitorVisitData) {
    resendingQrId.value = visit.id;
    try {
        await axios.post(`/api/visitor-visits/${visit.id}/resend-qr`);
        showNotification('QR code email sent successfully.', 'success');
    } catch {
        showNotification('Failed to send QR code email.', 'error');
    } finally {
        resendingQrId.value = null;
    }
}

function handlePreRegisterSaved() {
    showPreRegisterModal.value = false;
    activeTab.value = 'all';
    fetchVisits();
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

onMounted(() => {
    fetchVisits();
});
</script>

<template>
    <Head :title="`Visitors - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Visitor Management
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage visitor registrations, approvals, and check-ins
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" as="a" href="/visitors/log">
                        <Clock class="mr-2 h-4 w-4" />
                        View Log
                    </Button>
                    <Button @click="showPreRegisterModal = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Pre-Register
                    </Button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 overflow-x-auto rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800/50">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="switchTab(tab.key)"
                    class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                    :class="[
                        activeTab === tab.key
                            ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-slate-100'
                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200',
                    ]"
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.count !== null"
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="[
                            activeTab === tab.key
                                ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                : 'bg-slate-200 text-slate-600 dark:bg-slate-600 dark:text-slate-300',
                        ]"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <!-- Search / Filter Bar -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search visitors..."
                        class="pl-10"
                        @keyup.enter="fetchVisits"
                    />
                </div>
                <select
                    v-model="locationFilter"
                    @change="fetchVisits"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"
                >
                    <option value="">All Locations</option>
                    <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                        {{ loc.name }}
                    </option>
                </select>
            </div>

            <!-- Visits Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <!-- Loading Skeleton -->
                <div v-if="loading" class="divide-y divide-slate-200 dark:divide-slate-700">
                    <div v-for="i in 5" :key="i" class="flex items-center gap-4 px-6 py-4">
                        <div class="h-4 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
                        <div class="h-4 w-24 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
                        <div class="h-4 w-20 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
                        <div class="ml-auto h-4 w-16 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
                    </div>
                </div>

                <template v-else-if="visits.length > 0">
                    <!-- Desktop Table -->
                    <div class="hidden md:block">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Visitor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Purpose</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Expected</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                <tr v-for="visit in visits" :key="visit.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ visit.visitor?.full_name }}
                                            </div>
                                            <div class="text-sm text-slate-500 dark:text-slate-400">
                                                {{ visit.visitor?.company || visit.visitor?.email || '' }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="max-w-[200px] truncate px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                                        {{ visit.purpose }}
                                    </td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300">
                                        {{ visit.work_location?.name || '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300">
                                        {{ formatDate(visit.expected_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col gap-1">
                                            <VisitorStatusBadge :status="visit.status" :label="visit.status_label" />
                                            <div v-if="visit.status === 'pending_approval'" class="flex gap-2 text-xs">
                                                <span :class="visit.is_admin_approved ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-slate-500'">
                                                    Admin: {{ visit.is_admin_approved ? 'Approved' : 'Pending' }}
                                                </span>
                                                <span :class="visit.is_host_approved ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-slate-500'">
                                                    Host: {{ visit.is_host_approved ? 'Approved' : 'Pending' }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1">
                                            <!-- Pending Approval Actions -->
                                            <template v-if="visit.status === 'pending_approval'">
                                                <Button size="sm" variant="ghost" @click="openApproval(visit, 'approve')" title="Approve">
                                                    <CheckCircle class="h-4 w-4 text-green-600" />
                                                </Button>
                                                <Button size="sm" variant="ghost" @click="openApproval(visit, 'reject')" title="Reject">
                                                    <XCircle class="h-4 w-4 text-red-600" />
                                                </Button>
                                            </template>
                                            <!-- Approved / Pre-Registered Actions -->
                                            <template v-if="visit.status === 'approved' || visit.status === 'pre_registered'">
                                                <Button size="sm" variant="ghost" @click="openCheckIn(visit)" title="Check In">
                                                    <LogIn class="h-4 w-4 text-blue-600" />
                                                </Button>
                                                <Button size="sm" variant="ghost" @click="handleResendQr(visit)" :disabled="resendingQrId === visit.id" title="Resend QR">
                                                    <Loader2 v-if="resendingQrId === visit.id" class="h-4 w-4 animate-spin text-slate-600" />
                                                    <Mail v-else class="h-4 w-4 text-slate-600" />
                                                </Button>
                                            </template>
                                            <!-- Checked In Actions -->
                                            <template v-if="visit.status === 'checked_in'">
                                                <Button size="sm" variant="ghost" @click="handleCheckOut(visit)" title="Check Out">
                                                    <LogOut class="h-4 w-4 text-orange-600" />
                                                </Button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card List -->
                    <div class="divide-y divide-slate-200 md:hidden dark:divide-slate-700">
                        <div v-for="visit in visits" :key="visit.id" class="p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ visit.visitor?.full_name }}
                                    </div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ visit.visitor?.company || '' }}
                                    </div>
                                </div>
                                <VisitorStatusBadge :status="visit.status" :label="visit.status_label" />
                            </div>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ visit.purpose }}</p>
                            <div class="mt-3 flex items-center gap-2">
                                <template v-if="visit.status === 'pending_approval'">
                                    <Button size="sm" @click="openApproval(visit, 'approve')">Approve</Button>
                                    <Button size="sm" variant="destructive" @click="openApproval(visit, 'reject')">Reject</Button>
                                </template>
                                <template v-if="visit.status === 'approved' || visit.status === 'pre_registered'">
                                    <Button size="sm" @click="openCheckIn(visit)">Check In</Button>
                                </template>
                                <template v-if="visit.status === 'checked_in'">
                                    <Button size="sm" variant="outline" @click="handleCheckOut(visit)">Check Out</Button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div v-if="!loading && visits.length === 0" class="px-6 py-12 text-center">
                    <UserRoundCheck class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No visitors found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ activeTab === 'pending' ? 'No pending visitor requests.' :
                           activeTab === 'today' ? 'No visitors expected today.' :
                           activeTab === 'checked_in' ? 'No visitors currently checked in.' :
                           'No visitor records yet.' }}
                    </p>
                    <Button v-if="activeTab === 'all'" class="mt-4" @click="showPreRegisterModal = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Pre-Register a Visitor
                    </Button>
                </div>
            </div>
        </div>

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
                <CheckCircle
                    v-if="notification.type === 'success'"
                    class="h-4 w-4 shrink-0 text-green-500"
                />
                <XCircle
                    v-else
                    class="h-4 w-4 shrink-0 text-red-500"
                />
                <span class="text-sm font-medium">
                    {{ notification.message }}
                </span>
            </div>
        </Transition>

        <!-- Modals -->
        <VisitorPreRegisterModal
            :open="showPreRegisterModal"
            :locations="locations"
            @update:open="showPreRegisterModal = $event"
            @saved="handlePreRegisterSaved"
        />

        <VisitorApprovalModal
            :open="showApprovalModal"
            :visit="selectedVisit"
            :mode="approvalMode"
            @update:open="showApprovalModal = $event"
            @approve="handleApprove"
            @reject="handleReject"
        />

        <VisitorCheckInModal
            :open="showCheckInModal"
            :visit="selectedVisit"
            @update:open="showCheckInModal = $event"
            @check-in="handleCheckIn"
        />
    </TenantLayout>
</template>
