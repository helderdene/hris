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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { computed, ref } from 'vue';

interface CertificationFile {
    id: number;
    original_filename: string;
    mime_type: string;
    file_size_formatted: string;
}

interface Employee {
    id: number;
    employee_number: string;
    full_name: string;
    position?: { title: string } | null;
    department?: { name: string } | null;
}

interface CertificationType {
    id: number;
    name: string;
}

interface Certification {
    id: number;
    employee_id: number;
    employee: Employee;
    certification_type_id: number;
    certification_type: CertificationType;
    certificate_number: string | null;
    issuing_body: string | null;
    issued_date: string | null;
    expiry_date: string | null;
    description: string | null;
    status: string;
    status_label: string;
    status_color: string;
    can_be_edited: boolean;
    can_be_submitted: boolean;
    is_expiring_soon: boolean;
    days_until_expiry: number | null;
    expiry_status: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    revoked_at: string | null;
    rejection_reason: string | null;
    revocation_reason: string | null;
    files: CertificationFile[];
    files_count: number;
}

interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface Statistics {
    total_active: number;
    pending_approval: number;
    expiring_soon: number;
    expired: number;
}

interface Filters {
    status: string | null;
    certification_type_id: number | null;
    employee_id: number | null;
    search: string | null;
    expiry_from: string | null;
    expiry_to: string | null;
}

const props = defineProps<{
    certifications: { data: Certification[] };
    certificationTypes: CertificationType[];
    employees: EmployeeOption[];
    statuses: StatusOption[];
    statistics: Statistics;
    filters: Filters;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR Management', href: '/employees' },
    { title: 'Certifications', href: '/hr/certifications' },
];

const selectedStatus = ref(props.filters.status || 'all');
const selectedType = ref(
    props.filters.certification_type_id
        ? String(props.filters.certification_type_id)
        : 'all',
);
const searchQuery = ref(props.filters.search || '');
const expiryFrom = ref(props.filters.expiry_from || '');
const expiryTo = ref(props.filters.expiry_to || '');

// Modal states
const isRejectModalOpen = ref(false);
const isRevokeModalOpen = ref(false);
const isFilesModalOpen = ref(false);
const selectedCertification = ref<Certification | null>(null);
const actionReason = ref('');
const isProcessing = ref(false);

const certificationsData = computed(() => props.certifications?.data ?? []);

function applyFilters(): void {
    const params: Record<string, string | number | undefined> = {};

    if (selectedStatus.value !== 'all') {
        params.status = selectedStatus.value;
    }
    if (selectedType.value !== 'all') {
        params.certification_type_id = Number(selectedType.value);
    }
    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim();
    }
    if (expiryFrom.value) {
        params.expiry_from = expiryFrom.value;
    }
    if (expiryTo.value) {
        params.expiry_to = expiryTo.value;
    }

    router.get('/hr/certifications', params, { preserveState: true });
}

function handleStatusChange(value: string): void {
    selectedStatus.value = value;
    applyFilters();
}

function handleTypeChange(value: string): void {
    selectedType.value = value;
    applyFilters();
}

function handleSearch(): void {
    applyFilters();
}

function clearFilters(): void {
    selectedStatus.value = 'all';
    selectedType.value = 'all';
    searchQuery.value = '';
    expiryFrom.value = '';
    expiryTo.value = '';
    router.get('/hr/certifications', {}, { preserveState: true });
}

function getStatusBadgeClasses(color: string): string {
    switch (color) {
        case 'slate':
            return 'bg-slate-100 text-slate-800 dark:bg-slate-700/50 dark:text-slate-300';
        case 'amber':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'green':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'red':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleApprove(certification: Certification): Promise<void> {
    if (!confirm('Are you sure you want to approve this certification?')) {
        return;
    }

    isProcessing.value = true;

    try {
        const response = await fetch(
            `/api/certifications/${certification.id}/approve`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        const data = await response.json();

        if (response.ok) {
            router.reload({ only: ['certifications', 'statistics'] });
        } else {
            alert(data.message || 'Failed to approve certification');
        }
    } catch {
        alert('An error occurred while approving the certification');
    } finally {
        isProcessing.value = false;
    }
}

function openRejectModal(certification: Certification): void {
    selectedCertification.value = certification;
    actionReason.value = '';
    isRejectModalOpen.value = true;
}

async function handleReject(): Promise<void> {
    if (!selectedCertification.value) return;
    if (!actionReason.value.trim()) {
        alert('Please provide a rejection reason');
        return;
    }

    isProcessing.value = true;

    try {
        const response = await fetch(
            `/api/certifications/${selectedCertification.value.id}/reject`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ reason: actionReason.value }),
            },
        );

        const data = await response.json();

        if (response.ok) {
            isRejectModalOpen.value = false;
            router.reload({ only: ['certifications', 'statistics'] });
        } else {
            alert(data.message || 'Failed to reject certification');
        }
    } catch {
        alert('An error occurred while rejecting the certification');
    } finally {
        isProcessing.value = false;
    }
}

function openRevokeModal(certification: Certification): void {
    selectedCertification.value = certification;
    actionReason.value = '';
    isRevokeModalOpen.value = true;
}

async function handleRevoke(): Promise<void> {
    if (!selectedCertification.value) return;
    if (!actionReason.value.trim()) {
        alert('Please provide a revocation reason');
        return;
    }

    isProcessing.value = true;

    try {
        const response = await fetch(
            `/api/certifications/${selectedCertification.value.id}/revoke`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ reason: actionReason.value }),
            },
        );

        const data = await response.json();

        if (response.ok) {
            isRevokeModalOpen.value = false;
            router.reload({ only: ['certifications', 'statistics'] });
        } else {
            alert(data.message || 'Failed to revoke certification');
        }
    } catch {
        alert('An error occurred while revoking the certification');
    } finally {
        isProcessing.value = false;
    }
}

function openFilesModal(certification: Certification): void {
    selectedCertification.value = certification;
    isFilesModalOpen.value = true;
}

function downloadFile(certificationId: number, fileId: number): void {
    window.open(
        `/api/certifications/${certificationId}/files/${fileId}/download`,
        '_blank',
    );
}
</script>

<template>
    <Head :title="`Certifications - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Certifications
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Review and manage employee certifications and credentials.
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
                        Total Active
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400"
                    >
                        {{ statistics.total_active }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Pending Approval
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-amber-600 dark:text-amber-400"
                    >
                        {{ statistics.pending_approval }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Expiring Soon (30 days)
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-orange-600 dark:text-orange-400"
                    >
                        {{ statistics.expiring_soon }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Expired
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400"
                    >
                        {{ statistics.expired }}
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-full sm:w-48">
                    <Label class="mb-1.5 block text-xs">Status</Label>
                    <Select
                        :model-value="selectedStatus"
                        @update:model-value="handleStatusChange"
                    >
                        <SelectTrigger>
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
                </div>

                <div class="w-full sm:w-48">
                    <Label class="mb-1.5 block text-xs">Type</Label>
                    <Select
                        :model-value="selectedType"
                        @update:model-value="handleTypeChange"
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            <SelectItem
                                v-for="type in certificationTypes"
                                :key="type.id"
                                :value="String(type.id)"
                            >
                                {{ type.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-full sm:w-64">
                    <Label class="mb-1.5 block text-xs">Search Employee</Label>
                    <div class="flex gap-2">
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Name or employee number"
                            @keyup.enter="handleSearch"
                        />
                        <Button variant="outline" size="icon" @click="handleSearch">
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
                                    d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
                                />
                            </svg>
                        </Button>
                    </div>
                </div>

                <div class="w-full sm:w-auto">
                    <Label class="mb-1.5 block text-xs">Expiry Date Range</Label>
                    <div class="flex items-center gap-2">
                        <Input
                            v-model="expiryFrom"
                            type="date"
                            class="w-36"
                            @change="applyFilters"
                        />
                        <span class="text-slate-500">to</span>
                        <Input
                            v-model="expiryTo"
                            type="date"
                            class="w-36"
                            @change="applyFilters"
                        />
                    </div>
                </div>

                <Button variant="outline" size="sm" @click="clearFilters">
                    Clear Filters
                </Button>
            </div>

            <!-- Certifications Table -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Desktop Table -->
                <div class="hidden lg:block">
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
                                    Certification
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Certificate #
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Issuing Body
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Issue Date
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Expiry Date
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Status
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
                                v-for="certification in certificationsData"
                                :key="certification.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ certification.employee.full_name }}
                                    </div>
                                    <div
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            certification.employee
                                                .employee_number
                                        }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            certification.certification_type
                                                .name
                                        }}
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300"
                                >
                                    {{
                                        certification.certificate_number || '-'
                                    }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300"
                                >
                                    {{ certification.issuing_body || '-' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-slate-700 whitespace-nowrap dark:text-slate-300"
                                >
                                    {{ formatDate(certification.issued_date) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="text-sm text-slate-700 dark:text-slate-300"
                                    >
                                        {{
                                            formatDate(certification.expiry_date)
                                        }}
                                    </div>
                                    <div
                                        v-if="certification.is_expiring_soon"
                                        class="text-xs text-orange-600 dark:text-orange-400"
                                    >
                                        Expires in
                                        {{ certification.days_until_expiry }}
                                        days
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            getStatusBadgeClasses(
                                                certification.status_color,
                                            )
                                        "
                                    >
                                        {{ certification.status_label }}
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
                                                v-if="
                                                    certification.files_count >
                                                    0
                                                "
                                                @click="
                                                    openFilesModal(
                                                        certification,
                                                    )
                                                "
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                                                    />
                                                </svg>
                                                View Files ({{
                                                    certification.files_count
                                                }})
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    certification.status ===
                                                    'pending_approval'
                                                "
                                                @click="
                                                    handleApprove(certification)
                                                "
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4 text-green-600"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="m4.5 12.75 6 6 9-13.5"
                                                    />
                                                </svg>
                                                Approve
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    certification.status ===
                                                    'pending_approval'
                                                "
                                                class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                @click="
                                                    openRejectModal(
                                                        certification,
                                                    )
                                                "
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M6 18 18 6M6 6l12 12"
                                                    />
                                                </svg>
                                                Reject
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    certification.status ===
                                                    'active'
                                                "
                                                class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                @click="
                                                    openRevokeModal(
                                                        certification,
                                                    )
                                                "
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"
                                                    />
                                                </svg>
                                                Revoke
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card Layout -->
                <div class="lg:hidden">
                    <div
                        v-for="certification in certificationsData"
                        :key="certification.id"
                        class="border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-700"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ certification.employee.full_name }}
                                </div>
                                <div
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        certification.certification_type.name
                                    }}
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    getStatusBadgeClasses(
                                        certification.status_color,
                                    )
                                "
                            >
                                {{ certification.status_label }}
                            </span>
                        </div>
                        <div
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            <div v-if="certification.certificate_number">
                                Certificate #:
                                {{ certification.certificate_number }}
                            </div>
                            <div v-if="certification.expiry_date">
                                Expires:
                                {{ formatDate(certification.expiry_date) }}
                                <span
                                    v-if="certification.is_expiring_soon"
                                    class="text-orange-600 dark:text-orange-400"
                                >
                                    ({{ certification.days_until_expiry }}
                                    days)
                                </span>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Button
                                v-if="certification.files_count > 0"
                                variant="outline"
                                size="sm"
                                @click="openFilesModal(certification)"
                            >
                                Files ({{ certification.files_count }})
                            </Button>
                            <Button
                                v-if="
                                    certification.status === 'pending_approval'
                                "
                                size="sm"
                                @click="handleApprove(certification)"
                            >
                                Approve
                            </Button>
                            <Button
                                v-if="
                                    certification.status === 'pending_approval'
                                "
                                variant="outline"
                                size="sm"
                                class="text-red-600"
                                @click="openRejectModal(certification)"
                            >
                                Reject
                            </Button>
                            <Button
                                v-if="certification.status === 'active'"
                                variant="outline"
                                size="sm"
                                class="text-red-600"
                                @click="openRevokeModal(certification)"
                            >
                                Revoke
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="certificationsData.length === 0"
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
                            d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.746 3.746 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No certifications found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        No certifications match your current filters.
                    </p>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <Dialog v-model:open="isRejectModalOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reject Certification</DialogTitle>
                    <DialogDescription>
                        Please provide a reason for rejecting this
                        certification. The employee will be notified.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="reject-reason">Rejection Reason *</Label>
                        <Textarea
                            id="reject-reason"
                            v-model="actionReason"
                            placeholder="Enter the reason for rejection..."
                            rows="3"
                        />
                    </div>
                </div>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isRejectModalOpen = false"
                        :disabled="isProcessing"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="handleReject"
                        :disabled="isProcessing || !actionReason.trim()"
                    >
                        <svg
                            v-if="isProcessing"
                            class="mr-2 h-4 w-4 animate-spin"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        Reject Certification
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Revoke Modal -->
        <Dialog v-model:open="isRevokeModalOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Revoke Certification</DialogTitle>
                    <DialogDescription>
                        This will permanently revoke the certification. Please
                        provide a reason.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="revoke-reason">Revocation Reason *</Label>
                        <Textarea
                            id="revoke-reason"
                            v-model="actionReason"
                            placeholder="Enter the reason for revocation..."
                            rows="3"
                        />
                    </div>
                </div>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isRevokeModalOpen = false"
                        :disabled="isProcessing"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="handleRevoke"
                        :disabled="isProcessing || !actionReason.trim()"
                    >
                        <svg
                            v-if="isProcessing"
                            class="mr-2 h-4 w-4 animate-spin"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        Revoke Certification
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Files Modal -->
        <Dialog v-model:open="isFilesModalOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Certificate Files</DialogTitle>
                    <DialogDescription>
                        Files attached to this certification.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-2">
                    <div
                        v-for="file in selectedCertification?.files"
                        :key="file.id"
                        class="flex items-center justify-between rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                    >
                        <div class="flex items-center gap-3">
                            <svg
                                class="h-8 w-8 text-slate-400"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                                />
                            </svg>
                            <div>
                                <div
                                    class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ file.original_filename }}
                                </div>
                                <div
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ file.file_size_formatted }}
                                </div>
                            </div>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="
                                downloadFile(
                                    selectedCertification!.id,
                                    file.id,
                                )
                            "
                        >
                            Download
                        </Button>
                    </div>
                    <div
                        v-if="
                            !selectedCertification?.files ||
                            selectedCertification.files.length === 0
                        "
                        class="py-4 text-center text-sm text-slate-500 dark:text-slate-400"
                    >
                        No files attached.
                    </div>
                </div>
                <DialogFooter>
                    <Button @click="isFilesModalOpen = false"> Close </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
