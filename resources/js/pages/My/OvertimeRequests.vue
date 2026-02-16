<script setup lang="ts">
import OvertimeRequestFormModal from '@/components/OvertimeRequestFormModal.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Clock, FileText, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface OvertimeRequest {
    id: number;
    reference_number: string;
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
    submitted_at: string | null;
    created_at: string;
    can_be_edited: boolean;
    can_be_cancelled: boolean;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface OvertimeTypeOption {
    value: string;
    label: string;
    color: string;
}

const props = defineProps<{
    employee: { id: number; full_name: string; employee_number: string } | null;
    requests: OvertimeRequest[];
    statuses: StatusOption[];
    overtimeTypes: OvertimeTypeOption[];
    filters: {
        status: string | null;
        year: number;
    };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self-Service', href: '/my/dashboard' },
    { title: 'My Overtime', href: '/my/overtime-requests' },
];

const showFormModal = ref(false);
const editingRequest = ref<OvertimeRequest | null>(null);
const selectedStatus = ref(props.filters.status || 'all');
const selectedYear = ref(String(props.filters.year));

const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear();
    return [
        { value: String(currentYear), label: String(currentYear) },
        { value: String(currentYear - 1), label: String(currentYear - 1) },
        { value: String(currentYear - 2), label: String(currentYear - 2) },
    ];
});

function reloadPage() {
    const params: Record<string, string> = {
        year: selectedYear.value,
    };
    if (selectedStatus.value !== 'all') {
        params.status = selectedStatus.value;
    }
    router.get(window.location.pathname, params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function handleStatusChange(value: string) {
    selectedStatus.value = value;
    reloadPage();
}

function handleYearChange(value: string) {
    selectedYear.value = value;
    reloadPage();
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

function openNewModal(): void {
    editingRequest.value = null;
    showFormModal.value = true;
}

function openEditModal(req: OvertimeRequest): void {
    editingRequest.value = req;
    showFormModal.value = true;
}

// Confirmation dialog state
const showConfirmDialog = ref(false);
const confirmDialogTitle = ref('');
const confirmDialogDescription = ref('');
const confirmDialogAction = ref<(() => void) | null>(null);
const confirmDialogDestructive = ref(false);

const showCancelDialog = ref(false);
const cancellingRequest = ref<OvertimeRequest | null>(null);
const cancelReason = ref('');
const isProcessing = ref(false);

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function handleSubmitRequest(req: OvertimeRequest) {
    confirmDialogTitle.value = 'Submit OT Request';
    confirmDialogDescription.value = `Submit ${req.reference_number} for approval?`;
    confirmDialogDestructive.value = false;
    confirmDialogAction.value = () => executeSubmit(req);
    showConfirmDialog.value = true;
}

async function executeSubmit(req: OvertimeRequest) {
    showConfirmDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/overtime-requests/${req.id}/submit`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });
        if (response.ok) {
            reloadPage();
        }
    } catch {
    } finally {
        isProcessing.value = false;
    }
}

function handleCancelRequest(req: OvertimeRequest) {
    cancellingRequest.value = req;
    cancelReason.value = '';
    showCancelDialog.value = true;
}

async function executeCancelRequest() {
    if (!cancellingRequest.value) return;
    showCancelDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/overtime-requests/${cancellingRequest.value.id}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ reason: cancelReason.value || null }),
        });
        if (response.ok) {
            reloadPage();
        }
    } catch {
    } finally {
        isProcessing.value = false;
        cancellingRequest.value = null;
    }
}

function handleDeleteRequest(req: OvertimeRequest) {
    confirmDialogTitle.value = 'Delete Draft';
    confirmDialogDescription.value = `Delete draft ${req.reference_number}? This cannot be undone.`;
    confirmDialogDestructive.value = true;
    confirmDialogAction.value = () => executeDelete(req);
    showConfirmDialog.value = true;
}

async function executeDelete(req: OvertimeRequest) {
    showConfirmDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/overtime-requests/${req.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });
        if (response.ok) {
            reloadPage();
        }
    } catch {
    } finally {
        isProcessing.value = false;
    }
}

function handleFormSuccess(): void {
    showFormModal.value = false;
    editingRequest.value = null;
    reloadPage();
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head :title="`My Overtime - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        My Overtime Requests
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        File and track your overtime requests.
                    </p>
                </div>
                <Button v-if="employee" @click="openNewModal" class="gap-2">
                    <Plus class="h-4 w-4" />
                    File OT Request
                </Button>
            </div>

            <!-- No Employee Profile -->
            <div
                v-if="!employee"
                class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
            >
                <Clock class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                    No employee profile
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    No employee profile is linked to your account.
                </p>
            </div>

            <template v-else>
                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3">
                    <Select :model-value="selectedStatus" @update:model-value="handleStatusChange">
                        <SelectTrigger class="w-40">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem
                                v-for="status in statuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select :model-value="selectedYear" @update:model-value="handleYearChange">
                        <SelectTrigger class="w-32">
                            <SelectValue placeholder="Year" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="year in yearOptions"
                                :key="year.value"
                                :value="year.value"
                            >
                                {{ year.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Requests Table -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                    <div v-if="requests.length > 0" class="hidden md:block">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-800/50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Reference
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Date
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Type
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Duration
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Status
                                    </th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                <tr
                                    v-for="req in requests"
                                    :key="req.id"
                                    class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                >
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="font-medium text-slate-900 dark:text-slate-100">
                                            {{ req.reference_number }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                        {{ formatDate(req.overtime_date) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                            :class="getTypeBadgeClasses(req.overtime_type_color)"
                                        >
                                            {{ req.overtime_type_label }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                        {{ req.expected_hours_formatted }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                            :class="getStatusBadgeClasses(req.status_color)"
                                        >
                                            {{ req.status_label }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                v-if="req.can_be_edited"
                                                variant="outline"
                                                size="sm"
                                                @click="openEditModal(req)"
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                v-if="req.status === 'draft'"
                                                size="sm"
                                                @click="handleSubmitRequest(req)"
                                            >
                                                Submit
                                            </Button>
                                            <Button
                                                v-if="req.can_be_cancelled"
                                                variant="outline"
                                                size="sm"
                                                class="text-amber-600 hover:text-amber-700"
                                                @click="handleCancelRequest(req)"
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                v-if="req.status === 'draft'"
                                                variant="outline"
                                                size="sm"
                                                class="text-red-600 hover:text-red-700"
                                                @click="handleDeleteRequest(req)"
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile View -->
                    <div v-else-if="requests.length > 0" class="divide-y divide-slate-200 md:hidden dark:divide-slate-700">
                        <div
                            v-for="req in requests"
                            :key="req.id"
                            class="space-y-2 p-4"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ req.reference_number }}
                                    </div>
                                    <div class="text-sm text-slate-500">
                                        {{ formatDate(req.overtime_date) }}
                                    </div>
                                </div>
                                <span
                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                    :class="getStatusBadgeClasses(req.status_color)"
                                >
                                    {{ req.status_label }}
                                </span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span
                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                    :class="getTypeBadgeClasses(req.overtime_type_color)"
                                >
                                    {{ req.overtime_type_label }}
                                </span>
                                <span class="font-medium">{{ req.expected_hours_formatted }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="px-6 py-12 text-center">
                        <FileText class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                        <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                            No overtime requests found
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            File your first overtime request to get started.
                        </p>
                        <div v-if="employee" class="mt-6">
                            <Button @click="openNewModal">File OT Request</Button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- OT Request Form Modal -->
        <OvertimeRequestFormModal
            v-model:open="showFormModal"
            :request="editingRequest"
            :employee="employee"
            :overtime-types="overtimeTypes"
            @success="handleFormSuccess"
        />

        <!-- Confirmation Dialog -->
        <Dialog v-model:open="showConfirmDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ confirmDialogTitle }}</DialogTitle>
                    <DialogDescription>{{ confirmDialogDescription }}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showConfirmDialog = false">Cancel</Button>
                    <Button
                        :class="confirmDialogDestructive ? 'bg-red-600 hover:bg-red-700' : ''"
                        @click="confirmDialogAction?.()"
                    >
                        Confirm
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Cancel Request Dialog -->
        <Dialog v-model:open="showCancelDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel OT Request</DialogTitle>
                    <DialogDescription>
                        Cancel {{ cancellingRequest?.reference_number }}? You may provide a reason.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Textarea
                        v-model="cancelReason"
                        placeholder="Reason for cancellation (optional)"
                        rows="3"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showCancelDialog = false">Keep Request</Button>
                    <Button class="bg-amber-600 hover:bg-amber-700" @click="executeCancelRequest">
                        Cancel Request
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
