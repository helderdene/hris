<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Head } from '@inertiajs/vue3';
import {
    CalendarDays,
    Download,
    FileSpreadsheet,
    FileText,
    Loader2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ReportType {
    value: string;
    label: string;
    shortLabel: string;
    description: string;
    periodType: string;
    supportsDateRange: boolean;
}

interface Month {
    value: number;
    label: string;
}

interface Department {
    id: number;
    name: string;
}

interface PreviewRow {
    philhealth_number: string;
    last_name: string;
    first_name: string;
    middle_name?: string;
    philhealth_employee?: number;
    philhealth_employer?: number;
    total_contribution?: number;
    basic_salary?: number;
    hire_date?: string;
    position?: string;
    department?: string;
    [key: string]: unknown;
}

interface PreviewTotals {
    employee_count: number;
    philhealth_employee?: number;
    philhealth_employer?: number;
    total_contribution?: number;
    total_salary?: number;
    [key: string]: unknown;
}

const props = defineProps<{
    reportTypes: ReportType[];
    departments: Department[];
    years: number[];
    months: Month[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports/philhealth' },
    { title: 'PhilHealth Reports', href: '/reports/philhealth' },
];

// Form state
const selectedReportType = ref<string | null>(null);
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const selectedDepartments = ref<number[]>([]);
const useDateRange = ref(false);
const startDate = ref('');
const endDate = ref('');

// UI state
const isLoading = ref(false);
const isGenerating = ref(false);
const previewData = ref<PreviewRow[]>([]);
const previewTotals = ref<PreviewTotals | null>(null);
const errorMessage = ref<string | null>(null);

const currentReportType = computed(() => {
    return props.reportTypes.find((t) => t.value === selectedReportType.value);
});

const supportsDateRange = computed(() => {
    return currentReportType.value?.supportsDateRange ?? false;
});

const canPreview = computed(() => {
    if (!selectedReportType.value) return false;
    if (useDateRange.value && supportsDateRange.value) {
        return startDate.value && endDate.value;
    }
    return selectedYear.value && selectedMonth.value;
});

// Watch for report type changes and fetch preview
watch(
    [selectedReportType, selectedYear, selectedMonth, startDate, endDate],
    () => {
        if (canPreview.value) {
            fetchPreview();
        }
    },
);

// Reset date range when report type changes
watch(selectedReportType, () => {
    if (!supportsDateRange.value) {
        useDateRange.value = false;
        startDate.value = '';
        endDate.value = '';
    }
});

async function fetchPreview() {
    if (!canPreview.value) return;

    isLoading.value = true;
    errorMessage.value = null;

    try {
        const body: Record<string, unknown> = {
            report_type: selectedReportType.value,
            year: selectedYear.value,
            month: !useDateRange.value ? selectedMonth.value : null,
            department_ids:
                selectedDepartments.value.length > 0
                    ? selectedDepartments.value
                    : null,
        };

        if (useDateRange.value && supportsDateRange.value) {
            body.start_date = startDate.value;
            body.end_date = endDate.value;
        }

        const response = await fetch('/api/reports/philhealth/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.message || 'Failed to fetch preview');
        }

        const data = await response.json();
        previewData.value = data.data;
        previewTotals.value = data.totals;
    } catch (error) {
        errorMessage.value =
            error instanceof Error ? error.message : 'An error occurred';
        previewData.value = [];
        previewTotals.value = null;
    } finally {
        isLoading.value = false;
    }
}

async function generateReport(format: 'xlsx' | 'pdf') {
    if (!canPreview.value) return;

    isGenerating.value = true;

    try {
        const body: Record<string, unknown> = {
            report_type: selectedReportType.value,
            format,
            year: selectedYear.value,
            month: !useDateRange.value ? selectedMonth.value : null,
            department_ids:
                selectedDepartments.value.length > 0
                    ? selectedDepartments.value
                    : null,
        };

        if (useDateRange.value && supportsDateRange.value) {
            body.start_date = startDate.value;
            body.end_date = endDate.value;
        }

        const response = await fetch('/api/reports/philhealth/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/octet-stream',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            const text = await response.text();
            try {
                const data = JSON.parse(text);
                throw new Error(data.message || 'Failed to generate report');
            } catch {
                throw new Error('Failed to generate report');
            }
        }

        // Get filename from X-Filename header or Content-Disposition
        let filename = response.headers.get('X-Filename');
        if (!filename) {
            const contentDisposition = response.headers.get('Content-Disposition');
            if (contentDisposition) {
                const matches = contentDisposition.match(/filename="(.+)"/);
                if (matches) {
                    filename = matches[1];
                }
            }
        }
        if (!filename) {
            filename = `philhealth_${selectedReportType.value}_${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}.${format}`;
        }

        // Download the file
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    } catch (error) {
        errorMessage.value =
            error instanceof Error ? error.message : 'Failed to generate report';
    } finally {
        isGenerating.value = false;
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function formatCurrency(amount: number | undefined): string {
    if (amount === undefined) return '-';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
}

function getReportTypeIcon(type: string): string {
    switch (type) {
        case 'rf1':
            return 'Monthly contributions';
        case 'er2':
            return 'Employee details';
        case 'mdr':
            return 'New hire registration';
        default:
            return '';
    }
}

function getReportTypeColor(type: string): string {
    switch (type) {
        case 'rf1':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
        case 'er2':
            return 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300';
        case 'mdr':
            return 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
    }
}

function formatDate(date: string | undefined): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head :title="`PhilHealth Reports - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    PhilHealth Compliance Reports
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Generate PhilHealth compliance reports for regulatory
                    submission.
                </p>
            </div>

            <!-- Report Type Selection -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="reportType in reportTypes"
                    :key="reportType.value"
                    :class="[
                        'cursor-pointer transition-all',
                        selectedReportType === reportType.value
                            ? 'ring-2 ring-offset-2'
                            : 'hover:border-slate-300 dark:hover:border-slate-600',
                    ]"
                    :style="{
                        '--tw-ring-color':
                            selectedReportType === reportType.value
                                ? primaryColor
                                : 'transparent',
                    }"
                    @click="selectedReportType = reportType.value"
                >
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <span
                                class="rounded-md px-2 py-1 text-xs font-semibold"
                                :class="getReportTypeColor(reportType.value)"
                            >
                                {{ reportType.shortLabel }}
                            </span>
                            <span
                                v-if="reportType.supportsDateRange"
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Date Range
                            </span>
                            <span
                                v-else
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Monthly
                            </span>
                        </div>
                        <CardTitle class="mt-2 text-sm">
                            {{ getReportTypeIcon(reportType.value) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CardDescription class="text-xs">
                            {{ reportType.description }}
                        </CardDescription>
                    </CardContent>
                </Card>
            </div>

            <!-- Period Selection -->
            <Card v-if="selectedReportType">
                <CardHeader>
                    <CardTitle class="text-lg">Select Period</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap items-end gap-4">
                        <!-- Date Range Toggle (for MDR) -->
                        <div
                            v-if="supportsDateRange"
                            class="flex items-center gap-2"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                :class="{ 'bg-emerald-50': !useDateRange }"
                                @click="useDateRange = false"
                            >
                                Monthly
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :class="{ 'bg-emerald-50': useDateRange }"
                                @click="useDateRange = true"
                            >
                                <CalendarDays class="mr-1 h-4 w-4" />
                                Date Range
                            </Button>
                        </div>

                        <!-- Monthly Selection -->
                        <template v-if="!useDateRange">
                            <!-- Year -->
                            <div class="w-32">
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Year
                                </label>
                                <Select
                                    v-model="selectedYear"
                                    @update:model-value="
                                        (v) => (selectedYear = Number(v))
                                    "
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            :placeholder="String(selectedYear)"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="year in years"
                                            :key="year"
                                            :value="year"
                                        >
                                            {{ year }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <!-- Month -->
                            <div class="w-40">
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Month
                                </label>
                                <Select
                                    v-model="selectedMonth"
                                    @update:model-value="
                                        (v) => (selectedMonth = Number(v))
                                    "
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="month in months"
                                            :key="month.value"
                                            :value="month.value"
                                        >
                                            {{ month.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </template>

                        <!-- Date Range Selection -->
                        <template v-else>
                            <div class="w-40">
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Start Date
                                </label>
                                <input
                                    v-model="startDate"
                                    type="date"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"
                                />
                            </div>
                            <div class="w-40">
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    End Date
                                </label>
                                <input
                                    v-model="endDate"
                                    type="date"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"
                                />
                            </div>
                        </template>

                        <!-- Department Filter -->
                        <div v-if="departments.length > 0" class="w-48">
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Department
                            </label>
                            <Select
                                :model-value="
                                    selectedDepartments.length > 0
                                        ? String(selectedDepartments[0])
                                        : 'all'
                                "
                                @update:model-value="
                                    (v) =>
                                        (selectedDepartments =
                                            v === 'all' ? [] : [Number(v)])
                                "
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All Departments" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Departments
                                    </SelectItem>
                                    <SelectItem
                                        v-for="dept in departments"
                                        :key="dept.id"
                                        :value="String(dept.id)"
                                    >
                                        {{ dept.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Refresh Button -->
                        <Button
                            variant="outline"
                            :disabled="!canPreview || isLoading"
                            @click="fetchPreview"
                        >
                            <Loader2
                                v-if="isLoading"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            Refresh Preview
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Error Message -->
            <div
                v-if="errorMessage"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
            >
                {{ errorMessage }}
            </div>

            <!-- Preview Section -->
            <Card v-if="selectedReportType && canPreview">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-lg">Preview</CardTitle>
                            <CardDescription>
                                <span v-if="previewTotals">
                                    {{ previewTotals.employee_count }} employees
                                    <span v-if="previewData.length >= 50">
                                        (showing first 50)
                                    </span>
                                </span>
                            </CardDescription>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="
                                    isGenerating || previewData.length === 0
                                "
                                @click="generateReport('xlsx')"
                            >
                                <FileSpreadsheet class="mr-2 h-4 w-4" />
                                Excel
                            </Button>
                            <Button
                                size="sm"
                                :disabled="
                                    isGenerating || previewData.length === 0
                                "
                                :style="{ backgroundColor: primaryColor }"
                                @click="generateReport('pdf')"
                            >
                                <FileText class="mr-2 h-4 w-4" />
                                PDF
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Loading State -->
                    <div
                        v-if="isLoading"
                        class="flex items-center justify-center py-12"
                    >
                        <Loader2 class="h-8 w-8 animate-spin text-slate-400" />
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else-if="previewData.length === 0"
                        class="py-12 text-center"
                    >
                        <p class="text-slate-500 dark:text-slate-400">
                            No data found for the selected period.
                        </p>
                    </div>

                    <!-- Data Table -->
                    <div v-else class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <thead class="bg-slate-50 dark:bg-slate-800/50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        PIN
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Name
                                    </th>
                                    <!-- RF1 Columns -->
                                    <template v-if="selectedReportType === 'rf1'">
                                        <th
                                            class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            EE Share
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            ER Share
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Total
                                        </th>
                                    </template>
                                    <!-- ER2 Columns -->
                                    <template
                                        v-else-if="selectedReportType === 'er2'"
                                    >
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Position
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Salary
                                        </th>
                                    </template>
                                    <!-- MDR Columns -->
                                    <template
                                        v-else-if="selectedReportType === 'mdr'"
                                    >
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Position
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Date Hired
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Salary
                                        </th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900"
                            >
                                <tr
                                    v-for="(row, index) in previewData"
                                    :key="index"
                                    class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                >
                                    <td
                                        class="px-4 py-3 text-sm text-slate-900 whitespace-nowrap dark:text-slate-100"
                                    >
                                        {{ row.philhealth_number || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div
                                            class="font-medium text-slate-900 dark:text-slate-100"
                                        >
                                            {{ row.last_name }},
                                            {{ row.first_name }}
                                            {{
                                                row.middle_name
                                                    ? row.middle_name
                                                          .charAt(0)
                                                          .toUpperCase() + '.'
                                                    : ''
                                            }}
                                        </div>
                                    </td>
                                    <!-- RF1 Data -->
                                    <template v-if="selectedReportType === 'rf1'">
                                        <td
                                            class="px-4 py-3 text-right text-sm text-slate-600 whitespace-nowrap dark:text-slate-400"
                                        >
                                            {{
                                                formatCurrency(
                                                    row.philhealth_employee,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right text-sm text-slate-600 whitespace-nowrap dark:text-slate-400"
                                        >
                                            {{
                                                formatCurrency(
                                                    row.philhealth_employer,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right text-sm font-medium text-slate-900 whitespace-nowrap dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(
                                                    row.total_contribution,
                                                )
                                            }}
                                        </td>
                                    </template>
                                    <!-- ER2 Data -->
                                    <template
                                        v-else-if="selectedReportType === 'er2'"
                                    >
                                        <td
                                            class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap dark:text-slate-400"
                                        >
                                            {{ row.position || '-' }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right text-sm font-medium text-slate-900 whitespace-nowrap dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(row.basic_salary)
                                            }}
                                        </td>
                                    </template>
                                    <!-- MDR Data -->
                                    <template
                                        v-else-if="selectedReportType === 'mdr'"
                                    >
                                        <td
                                            class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap dark:text-slate-400"
                                        >
                                            {{ row.position || '-' }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap dark:text-slate-400"
                                        >
                                            {{ formatDate(row.hire_date) }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right text-sm font-medium text-slate-900 whitespace-nowrap dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(row.basic_salary)
                                            }}
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                            <tfoot
                                v-if="previewTotals"
                                class="bg-slate-100 dark:bg-slate-800"
                            >
                                <tr>
                                    <td
                                        colspan="2"
                                        class="px-4 py-3 text-sm font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        TOTAL ({{
                                            previewTotals.employee_count
                                        }}
                                        employees)
                                    </td>
                                    <!-- RF1 Totals -->
                                    <template v-if="selectedReportType === 'rf1'">
                                        <td
                                            class="px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(
                                                    previewTotals.philhealth_employee,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(
                                                    previewTotals.philhealth_employer,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right text-sm font-bold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(
                                                    previewTotals.total_contribution,
                                                )
                                            }}
                                        </td>
                                    </template>
                                    <!-- ER2/MDR Totals -->
                                    <template
                                        v-else-if="
                                            selectedReportType === 'er2' ||
                                            selectedReportType === 'mdr'
                                        "
                                    >
                                        <td
                                            class="px-4 py-3"
                                            :colspan="
                                                selectedReportType === 'mdr'
                                                    ? 2
                                                    : 1
                                            "
                                        ></td>
                                        <td
                                            class="px-4 py-3 text-right text-sm font-bold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(
                                                    previewTotals.total_salary,
                                                )
                                            }}
                                        </td>
                                    </template>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
