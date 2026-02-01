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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Employee {
    id: number;
    employee_number: string;
    full_name: string;
}

interface DocumentRequest {
    id: number;
    employee_id: number;
    employee?: {
        id: number;
        employee_number: string;
        full_name: string;
        department?: { name: string } | null;
        position?: { name: string } | null;
    };
    document_type: string;
    document_type_label: string;
    status: string;
    status_label: string;
    status_color: string;
    notes: string | null;
    admin_notes: string | null;
    processed_at: string | null;
    collected_at: string | null;
    created_at: string;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface DocumentTypeOption {
    value: string;
    label: string;
}

interface Filters {
    status: string | null;
    document_type: string | null;
    employee_id: number | null;
}

interface Summary {
    total_requests: number;
    pending: number;
    processing: number;
    ready: number;
}

const props = defineProps<{
    documentRequests: { data: DocumentRequest[] };
    employees: Employee[];
    statuses: StatusOption[];
    documentTypes: DocumentTypeOption[];
    filters: Filters;
    summary: Summary;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR Management', href: '/employees' },
    { title: 'Document Requests', href: '/hr/document-requests' },
];

const selectedStatus = ref(props.filters.status || 'all');
const selectedType = ref(props.filters.document_type || 'all');
const selectedEmployee = ref(
    props.filters.employee_id ? String(props.filters.employee_id) : 'all',
);

const requestsData = computed(() => props.documentRequests?.data ?? []);

const employeeOptions = computed(() => [
    { value: 'all', label: 'All Employees' },
    ...props.employees.map((emp) => ({
        value: String(emp.id),
        label: `${emp.full_name} (${emp.employee_number})`,
    })),
]);

// Update status dialog state
const isUpdateDialogOpen = ref(false);
const updatingRequest = ref<DocumentRequest | null>(null);
const newStatus = ref('');
const adminNotes = ref('');

function applyFilters(): void {
    const params: Record<string, string | number | undefined> = {};

    if (selectedStatus.value !== 'all') {
        params.status = selectedStatus.value;
    }
    if (selectedType.value !== 'all') {
        params.document_type = selectedType.value;
    }
    if (selectedEmployee.value !== 'all') {
        params.employee_id = Number(selectedEmployee.value);
    }

    router.get('/hr/document-requests', params, { preserveState: true });
}

function handleStatusChange(value: string): void {
    selectedStatus.value = value;
    applyFilters();
}

function handleTypeChange(value: string): void {
    selectedType.value = value;
    applyFilters();
}

function handleEmployeeChange(value: string): void {
    selectedEmployee.value = value;
    applyFilters();
}

function openUpdateDialog(request: DocumentRequest): void {
    updatingRequest.value = request;
    newStatus.value = request.status;
    adminNotes.value = request.admin_notes || '';
    isUpdateDialogOpen.value = true;
}

async function handleUpdateStatus(): Promise<void> {
    if (!updatingRequest.value) return;

    try {
        const response = await fetch(
            `/api/document-requests/${updatingRequest.value.id}`,
            {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    status: newStatus.value,
                    admin_notes: adminNotes.value,
                }),
            },
        );

        if (response.ok) {
            isUpdateDialogOpen.value = false;
            updatingRequest.value = null;
            router.reload({ only: ['documentRequests', 'summary'] });
            // Notification sent to employee's bell via backend
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to update status');
        }
    } catch {
        alert('An error occurred while updating the status');
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function getStatusBadgeClasses(color: string): string {
    switch (color) {
        case 'amber':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'blue':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'green':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'slate':
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function formatDate(dateString: string): string {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function truncateText(text: string | null, maxLength: number): string {
    if (!text) return '';
    return text.length > maxLength
        ? text.substring(0, maxLength) + '...'
        : text;
}
</script>

<template>
    <Head :title="`Document Requests - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Document Requests
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Manage employee document requests and track their status.
                </p>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Total Requests
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ summary.total_requests }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Pending
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-amber-600 dark:text-amber-400"
                    >
                        {{ summary.pending }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Processing
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400"
                    >
                        {{ summary.processing }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Ready for Pickup
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400"
                    >
                        {{ summary.ready }}
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <Select
                    :model-value="selectedStatus"
                    @update:model-value="handleStatusChange"
                >
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

                <Select
                    :model-value="selectedType"
                    @update:model-value="handleTypeChange"
                >
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Document Type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Types</SelectItem>
                        <SelectItem
                            v-for="docType in documentTypes"
                            :key="docType.value"
                            :value="docType.value"
                        >
                            {{ docType.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="selectedEmployee"
                    @update:model-value="handleEmployeeChange"
                >
                    <SelectTrigger class="w-56">
                        <SelectValue placeholder="Employee" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in employeeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Table -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Desktop Table -->
                <div class="hidden md:block">
                    <table
                        class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                    >
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Employee
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Document Type
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Notes
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Submitted
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <tr
                                v-for="request in requestsData"
                                :key="request.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ request.employee?.full_name }}
                                    </div>
                                    <div
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            request.employee?.employee_number
                                        }}
                                        <span
                                            v-if="
                                                request.employee?.department
                                            "
                                        >
                                            &middot;
                                            {{
                                                request.employee.department
                                                    .name
                                            }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ request.document_type_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            getStatusBadgeClasses(
                                                request.status_color,
                                            )
                                        "
                                    >
                                        {{ request.status_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="text-sm text-slate-600 dark:text-slate-400"
                                    >
                                        {{
                                            truncateText(request.notes, 50)
                                        }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="text-sm text-slate-600 dark:text-slate-400"
                                    >
                                        {{ formatDate(request.created_at) }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 w-8 p-0"
                                            >
                                                <span class="sr-only"
                                                    >Open menu</span
                                                >
                                                <svg
                                                    class="h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"
                                                    />
                                                </svg>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel
                                                >Actions</DropdownMenuLabel
                                            >
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                @click="
                                                    openUpdateDialog(request)
                                                "
                                            >
                                                Update Status
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card Layout -->
                <div class="md:hidden">
                    <div
                        v-for="request in requestsData"
                        :key="request.id"
                        class="border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-700"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ request.employee?.full_name }}
                                </div>
                                <div
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ request.employee?.employee_number }}
                                    <span
                                        v-if="request.employee?.department"
                                    >
                                        &middot;
                                        {{
                                            request.employee.department.name
                                        }}
                                    </span>
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="
                                    getStatusBadgeClasses(
                                        request.status_color,
                                    )
                                "
                            >
                                {{ request.status_label }}
                            </span>
                        </div>
                        <div class="mt-2 flex items-center gap-4 text-sm">
                            <span
                                class="text-slate-600 dark:text-slate-400"
                            >
                                {{ request.document_type_label }}
                            </span>
                            <span
                                class="text-slate-400 dark:text-slate-500"
                            >
                                {{ formatDate(request.created_at) }}
                            </span>
                        </div>
                        <div
                            v-if="request.notes"
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ truncateText(request.notes, 50) }}
                        </div>
                        <div class="mt-3">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openUpdateDialog(request)"
                            >
                                Update Status
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="requestsData.length === 0"
                    class="px-6 py-12 text-center"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No document requests found
                    </h3>
                    <p
                        class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                    >
                        Document requests from employees will appear here.
                    </p>
                </div>
            </div>
        </div>

        <!-- Update Status Dialog -->
        <Dialog v-model:open="isUpdateDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Update Request Status</DialogTitle>
                    <DialogDescription>
                        Update the status of this document request
                        <span v-if="updatingRequest?.employee">
                            for
                            {{ updatingRequest.employee.full_name }}.
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <div class="flex flex-col gap-4 py-4">
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            Status
                        </label>
                        <Select v-model="newStatus">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in statuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            Admin Notes
                        </label>
                        <textarea
                            v-model="adminNotes"
                            rows="3"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            placeholder="Add notes about this status change..."
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="isUpdateDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button @click="handleUpdateStatus"> Save </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
