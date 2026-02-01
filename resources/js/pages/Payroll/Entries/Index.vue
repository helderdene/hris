<script setup lang="ts">
import EnumSelect from '@/Components/EnumSelect.vue';
import PayrollComputeDialog from '@/components/Payroll/PayrollComputeDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Calculator, Download, Eye, FileText, MoreHorizontal, RefreshCw } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Department {
    id: number;
    name: string;
}

interface PayrollPeriod {
    id: number;
    name: string;
    year: number;
    period_number: number;
    cutoff_start: string;
    cutoff_end: string;
    date_range: string;
    pay_date: string;
    formatted_pay_date: string;
    status: string;
    status_label: string;
    payroll_cycle?: {
        id: number;
        name: string;
        cycle_type: string;
    };
}

interface PayrollEntry {
    id: number;
    employee_id: number;
    employee_number: string;
    employee_name: string;
    department_name: string | null;
    position_name: string | null;
    gross_pay: string;
    total_deductions: string;
    net_pay: string;
    status: string;
    status_label: string;
    status_color: string;
    computed_at: string | null;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedEntries {
    data: PayrollEntry[];
    links: PaginationLink[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

interface Summary {
    total_employees: number;
    total_gross: string;
    total_deductions: string;
    total_net: string;
    by_status: Record<string, number>;
}

interface Filters {
    status: string | null;
    department_id: string | null;
    search: string | null;
}

const props = defineProps<{
    period: PayrollPeriod;
    entries: PaginatedEntries;
    departments: Department[];
    statusOptions: StatusOption[];
    summary: Summary;
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll Periods', href: '/organization/payroll-periods' },
    { title: props.period.name, href: `/payroll/periods/${props.period.id}/entries` },
];

// Filter state
const searchQuery = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const departmentFilter = ref(props.filters?.department_id || '');
const showFilters = ref(true);

// Compute dialog state
const isComputeDialogOpen = ref(false);

// Options for filters
const statusFilterOptions = computed(() => [
    { value: '', label: 'All Statuses' },
    ...props.statusOptions.map((s) => ({ value: s.value, label: s.label })),
]);

const departmentOptions = computed(() => [
    { value: '', label: 'All Departments' },
    ...(props.departments || []).map((d) => ({
        value: d.id.toString(),
        label: d.name,
    })),
]);

// Computed
const recordCount = computed(() => props.entries?.data?.length || 0);
const totalCount = computed(() => props.entries?.meta?.total || recordCount.value);

function formatCurrency(value: string | number): string {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(num);
}

function getStatusBadgeClasses(status: string): string {
    switch (status) {
        case 'draft':
            return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
        case 'computed':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 'reviewed':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case 'approved':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
        case 'paid':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function applyFilters() {
    router.get(
        `/payroll/periods/${props.period.id}/entries`,
        {
            status: statusFilter.value || undefined,
            department_id: departmentFilter.value || undefined,
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function clearFilters() {
    searchQuery.value = '';
    statusFilter.value = '';
    departmentFilter.value = '';
    router.get(
        `/payroll/periods/${props.period.id}/entries`,
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

// Watch filter changes
watch([statusFilter, departmentFilter], () => {
    applyFilters();
});

function handleSearchKeyup(event: KeyboardEvent) {
    if (event.key === 'Enter') {
        applyFilters();
    }
}

function goToPage(url: string | null) {
    if (url) {
        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
        });
    }
}

function viewEntry(entryId: number) {
    router.visit(`/payroll/entries/${entryId}`);
}

function handleComputeSuccess() {
    isComputeDialogOpen.value = false;
    router.reload();
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

const isDownloadingBulk = ref(false);

async function handleBulkDownload(format: 'pdf' | 'zip' = 'pdf') {
    isDownloadingBulk.value = true;

    try {
        const response = await fetch(
            `/api/organization/payroll-periods/${props.period.id}/payslips/bulk-pdf`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/pdf, application/zip, application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ format }),
            },
        );

        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            const data = await response.json();
            if (data.queued) {
                alert(data.message);
            } else if (!response.ok) {
                alert(data.message || 'Failed to download payslips');
            }
        } else {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            const ext = format === 'zip' ? 'zip' : 'pdf';
            link.download = `payslips_${props.period.name}_${new Date().toISOString().split('T')[0]}.${ext}`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        }
    } catch {
        alert('An error occurred while downloading payslips');
    } finally {
        isDownloadingBulk.value = false;
    }
}

async function handleBulkStatusUpdate(newStatus: string) {
    const selectedEntries = props.entries.data.filter(
        (e) => e.status !== 'approved' && e.status !== 'paid',
    );

    if (selectedEntries.length === 0) {
        alert('No entries available for status update');
        return;
    }

    if (!confirm(`Are you sure you want to mark ${selectedEntries.length} entries as ${newStatus}?`)) {
        return;
    }

    try {
        const response = await fetch(
            `/api/payroll/entries/bulk-status`,
            {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    entry_ids: selectedEntries.map((e) => e.id),
                    status: newStatus,
                }),
            },
        );

        if (response.ok) {
            router.reload();
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to update status');
        }
    } catch {
        alert('An error occurred while updating status');
    }
}
</script>

<template>
    <Head :title="`Payroll Entries - ${period.name} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ period.name }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ period.date_range }} &middot; Pay Date: {{ period.formatted_pay_date }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <DropdownMenu v-if="entries.data && entries.data.length > 0">
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" :disabled="isDownloadingBulk">
                                <Download class="mr-2 h-4 w-4" />
                                {{ isDownloadingBulk ? 'Downloading...' : 'Download Payslips' }}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>Download Format</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleBulkDownload('pdf')">
                                Combined PDF
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="handleBulkDownload('zip')">
                                Individual PDFs (ZIP)
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button
                        variant="outline"
                        @click="isComputeDialogOpen = true"
                        :disabled="period.status === 'closed'"
                    >
                        <Calculator class="mr-2 h-4 w-4" />
                        Compute Payroll
                    </Button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Employees</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">
                        {{ summary.total_employees }}
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Gross</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">
                        {{ formatCurrency(summary.total_gross) }}
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Deductions</div>
                    <div class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">
                        {{ formatCurrency(summary.total_deductions) }}
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Net Pay</div>
                    <div class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">
                        {{ formatCurrency(summary.total_net) }}
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <Button variant="outline" @click="showFilters = !showFilters">
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
                            d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"
                        />
                    </svg>
                    Filters
                </Button>
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    {{ recordCount }} of {{ totalCount }} entries
                </div>
            </div>

            <!-- Filter Panel -->
            <div
                v-if="showFilters"
                class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
            >
                <div class="w-full sm:w-56">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Search
                    </label>
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Name or Employee #"
                        @keyup="handleSearchKeyup"
                    />
                </div>
                <div class="w-full sm:w-40">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Status
                    </label>
                    <EnumSelect v-model="statusFilter" :options="statusFilterOptions" placeholder="All Statuses" />
                </div>
                <div class="w-full sm:w-48">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Department
                    </label>
                    <EnumSelect v-model="departmentFilter" :options="departmentOptions" placeholder="All Departments" />
                </div>
                <Button variant="ghost" size="sm" @click="clearFilters" class="text-slate-600 dark:text-slate-400">
                    Clear filters
                </Button>
            </div>

            <!-- Entries Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <!-- Desktop Table -->
                <div class="hidden lg:block">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Employee
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Department
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Gross Pay
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Deductions
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Net Pay
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Status
                                </th>
                                <th scope="col" class="relative px-4 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="entry in entries.data"
                                :key="entry.id"
                                class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                @click="viewEntry(entry.id)"
                            >
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div>
                                        <div class="font-medium text-slate-900 dark:text-slate-100">
                                            {{ entry.employee_name }}
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ entry.employee_number }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-slate-100">
                                        {{ entry.department_name || '-' }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ entry.position_name || '' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(entry.gross_pay) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <span class="text-sm text-red-600 dark:text-red-400">
                                        {{ formatCurrency(entry.total_deductions) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <span class="text-sm font-semibold text-green-600 dark:text-green-400">
                                        {{ formatCurrency(entry.net_pay) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <Badge :class="getStatusBadgeClasses(entry.status)">
                                        {{ entry.status_label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap" @click.stop>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                                <span class="sr-only">Open menu</span>
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem @click="viewEntry(entry.id)">
                                                <Eye class="mr-2 h-4 w-4" />
                                                View Payslip
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List -->
                <div class="divide-y divide-slate-200 lg:hidden dark:divide-slate-700">
                    <div
                        v-for="entry in entries.data"
                        :key="entry.id"
                        class="cursor-pointer p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        @click="viewEntry(entry.id)"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ entry.employee_name }}
                                </div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ entry.employee_number }} &middot; {{ entry.department_name || 'No Department' }}
                                </div>
                            </div>
                            <Badge :class="getStatusBadgeClasses(entry.status)">
                                {{ entry.status_label }}
                            </Badge>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <div class="text-slate-500 dark:text-slate-400">Gross</div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ formatCurrency(entry.gross_pay) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-slate-500 dark:text-slate-400">Deductions</div>
                                <div class="font-medium text-red-600 dark:text-red-400">
                                    {{ formatCurrency(entry.total_deductions) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-slate-500 dark:text-slate-400">Net Pay</div>
                                <div class="font-semibold text-green-600 dark:text-green-400">
                                    {{ formatCurrency(entry.net_pay) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!entries.data || entries.data.length === 0" class="px-6 py-12 text-center">
                    <FileText class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No payroll entries
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{
                            searchQuery || statusFilter || departmentFilter
                                ? 'Try adjusting your filters.'
                                : 'Run payroll computation to generate entries.'
                        }}
                    </p>
                    <div class="mt-6">
                        <Button
                            @click="isComputeDialogOpen = true"
                            :style="{ backgroundColor: primaryColor }"
                            :disabled="period.status === 'closed'"
                        >
                            <Calculator class="mr-2 h-4 w-4" />
                            Compute Payroll
                        </Button>
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    v-if="entries.links && entries.links.length > 3"
                    class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50 sm:px-6"
                >
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-slate-700 dark:text-slate-300">
                                Showing page
                                <span class="font-medium">{{ entries.meta?.current_page || 1 }}</span>
                                of
                                <span class="font-medium">{{ entries.meta?.last_page || 1 }}</span>
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <button
                                    v-for="(link, index) in entries.links"
                                    :key="index"
                                    :disabled="!link.url"
                                    @click="goToPage(link.url)"
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium"
                                    :class="[
                                        link.active
                                            ? 'z-10 bg-blue-600 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                                            : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 dark:text-slate-300 dark:ring-slate-600 dark:hover:bg-slate-700',
                                        !link.url ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                                        index === 0 ? 'rounded-l-md' : '',
                                        index === entries.links.length - 1 ? 'rounded-r-md' : '',
                                    ]"
                                    v-html="link.label"
                                ></button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compute Dialog -->
        <PayrollComputeDialog
            v-model:open="isComputeDialogOpen"
            :period="period"
            @success="handleComputeSuccess"
        />
    </TenantLayout>
</template>
