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
    Users,
    FileArchive,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ReportType {
    value: string;
    label: string;
    shortLabel: string;
    description: string;
    periodType: 'monthly' | 'quarterly' | 'annual';
    isEmployeeCertificate?: boolean;
    supportsDataExport?: boolean;
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
    tin: string;
    last_name: string;
    first_name: string;
    middle_name?: string;
    gross_compensation?: number;
    non_taxable_compensation?: number;
    taxable_compensation?: number;
    withholding_tax?: number;
    termination_date?: string;
    [key: string]: unknown;
}

interface PreviewTotals {
    employee_count: number;
    gross_compensation?: number;
    non_taxable_compensation?: number;
    taxable_compensation?: number;
    withholding_tax?: number;
    [key: string]: unknown;
}

const props = defineProps<{
    reportTypes: ReportType[];
    departments: Department[];
    years: number[];
    months: Month[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports/bir' },
    { title: 'BIR Reports', href: '/reports/bir' },
];

// BIR color theme
const birPrimaryColor = '#8B0000';

// Form state
const selectedReportType = ref<string | null>(null);
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const selectedDepartments = ref<number[]>([]);
const selectedSchedule = ref<string>('7.1');

// UI state
const isLoading = ref(false);
const isGenerating = ref(false);
const isGeneratingBulk = ref(false);
const previewData = ref<PreviewRow[]>([]);
const previewTotals = ref<PreviewTotals | null>(null);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

// Alphalist schedule options
const alphalistSchedules = [
    { value: '7.1', label: 'Schedule 7.1 - Employees with Tax Withheld' },
    { value: '7.2', label: 'Schedule 7.2 - Minimum Wage Earners' },
    { value: '7.3', label: 'Schedule 7.3 - Separated Employees' },
];

const currentReportType = computed(() => {
    return props.reportTypes.find((t) => t.value === selectedReportType.value);
});

const isAnnualReport = computed(() => {
    return currentReportType.value?.periodType === 'annual';
});

const isAlphalistReport = computed(() => {
    return selectedReportType.value === 'alphalist';
});

const is2316Report = computed(() => {
    return selectedReportType.value === '2316';
});

const canPreview = computed(() => {
    if (!selectedReportType.value) return false;
    if (isAnnualReport.value) {
        return !!selectedYear.value;
    }
    return selectedYear.value && selectedMonth.value;
});

// Watch for report type changes and fetch preview
watch(
    [selectedReportType, selectedYear, selectedMonth, selectedSchedule],
    () => {
        if (canPreview.value) {
            fetchPreview();
        }
    },
);

async function fetchPreview() {
    if (!canPreview.value) return;

    isLoading.value = true;
    errorMessage.value = null;

    try {
        const body: Record<string, unknown> = {
            report_type: selectedReportType.value,
            year: selectedYear.value,
            department_ids:
                selectedDepartments.value.length > 0
                    ? selectedDepartments.value
                    : null,
        };

        // Add month only for non-annual reports
        if (!isAnnualReport.value) {
            body.month = selectedMonth.value;
        }

        // Add schedule for Alphalist
        if (isAlphalistReport.value) {
            body.schedule = selectedSchedule.value;
        }

        const response = await fetch('/api/reports/bir/preview', {
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

async function generateReport(format: 'xlsx' | 'pdf' | 'csv' | 'dat' | 'xlsx-template' | 'pdf-template') {
    if (!canPreview.value) return;

    isGenerating.value = true;
    errorMessage.value = null;

    try {
        const body: Record<string, unknown> = {
            report_type: selectedReportType.value,
            format,
            year: selectedYear.value,
            department_ids:
                selectedDepartments.value.length > 0
                    ? selectedDepartments.value
                    : null,
        };

        // Add month only for non-annual reports
        if (!isAnnualReport.value) {
            body.month = selectedMonth.value;
        }

        // Add schedule for Alphalist
        if (isAlphalistReport.value) {
            body.schedule = selectedSchedule.value;
        }

        const response = await fetch('/api/reports/bir/generate', {
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
            const contentDisposition = response.headers.get(
                'Content-Disposition',
            );
            if (contentDisposition) {
                const matches = contentDisposition.match(/filename="(.+)"/);
                if (matches) {
                    filename = matches[1];
                }
            }
        }
        if (!filename) {
            const periodPart = isAnnualReport.value
                ? selectedYear.value
                : `${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}`;
            filename = `bir_${selectedReportType.value}_${periodPart}.${format}`;
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

async function generateBulk2316() {
    isGeneratingBulk.value = true;
    errorMessage.value = null;
    successMessage.value = null;

    try {
        const response = await fetch('/api/reports/bir/2316/generate-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                year: selectedYear.value,
                department_ids:
                    selectedDepartments.value.length > 0
                        ? selectedDepartments.value
                        : null,
            }),
        });

        if (!response.ok) {
            const data = await response.json();
            throw new Error(
                data.message || 'Failed to generate bulk certificates',
            );
        }

        const data = await response.json();
        successMessage.value = data.message;
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Failed to generate certificates';
    } finally {
        isGeneratingBulk.value = false;
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
        case '1601c':
            return 'Withholding tax (Monthly)';
        case '1604cf':
            return 'Annual Information Return';
        case '2316':
            return 'Employee Certificates';
        case 'alphalist':
            return 'Year-End Employee Listing';
        default:
            return '';
    }
}

function getReportTypeColor(type: string): string {
    switch (type) {
        case '1601c':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300';
        case '1604cf':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case '2316':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
        case 'alphalist':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
    }
}

function getPeriodTypeLabel(type: string): string {
    switch (type) {
        case 'monthly':
            return 'Monthly';
        case 'quarterly':
            return 'Quarterly';
        case 'annual':
            return 'Annual';
        default:
            return '';
    }
}
</script>

<template>
    <Head :title="`BIR Reports - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    BIR Compliance Reports
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Generate BIR compliance reports for regulatory submission.
                </p>
            </div>

            <!-- Report Type Selection -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                                ? birPrimaryColor
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
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ getPeriodTypeLabel(reportType.periodType) }}
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

                        <!-- Month (only for non-annual reports) -->
                        <div v-if="!isAnnualReport" class="w-40">
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

                        <!-- Schedule (for Alphalist) -->
                        <div v-if="isAlphalistReport" class="w-64">
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Schedule
                            </label>
                            <Select
                                v-model="selectedSchedule"
                                @update:model-value="
                                    (v) => (selectedSchedule = String(v))
                                "
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="schedule in alphalistSchedules"
                                        :key="schedule.value"
                                        :value="schedule.value"
                                    >
                                        {{ schedule.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

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

                        <!-- Bulk Generate 2316 Button -->
                        <Button
                            v-if="is2316Report"
                            variant="default"
                            :disabled="isGeneratingBulk || !selectedYear"
                            :style="{ backgroundColor: birPrimaryColor }"
                            @click="generateBulk2316"
                        >
                            <Loader2
                                v-if="isGeneratingBulk"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <Users v-else class="mr-2 h-4 w-4" />
                            Generate All 2316
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Success Message -->
            <div
                v-if="successMessage"
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400"
            >
                {{ successMessage }}
            </div>

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
                                variant="outline"
                                size="sm"
                                :disabled="
                                    isGenerating || previewData.length === 0
                                "
                                @click="generateReport('pdf')"
                            >
                                <FileText class="mr-2 h-4 w-4" />
                                PDF
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="
                                    isGenerating || previewData.length === 0
                                "
                                @click="generateReport('csv')"
                            >
                                <Download class="mr-2 h-4 w-4" />
                                CSV
                            </Button>
                            <!-- DAT Export for annual reports -->
                            <Button
                                v-if="
                                    currentReportType?.supportsDataExport ||
                                    isAnnualReport
                                "
                                size="sm"
                                :disabled="
                                    isGenerating || previewData.length === 0
                                "
                                :style="{ backgroundColor: birPrimaryColor }"
                                @click="generateReport('dat')"
                            >
                                <FileArchive class="mr-2 h-4 w-4" />
                                DAT (eFiling)
                            </Button>
                            <!-- Official BIR 2316 Template Export -->
                            <Button
                                v-if="is2316Report"
                                size="sm"
                                :disabled="
                                    isGenerating || previewData.length === 0
                                "
                                class="bg-emerald-600 hover:bg-emerald-700"
                                @click="generateReport('xlsx-template')"
                            >
                                <FileSpreadsheet class="mr-2 h-4 w-4" />
                                Official Form (Excel)
                            </Button>
                            <Button
                                v-if="is2316Report"
                                size="sm"
                                :disabled="
                                    isGenerating || previewData.length === 0
                                "
                                class="bg-emerald-600 hover:bg-emerald-700"
                                @click="generateReport('pdf-template')"
                            >
                                <FileText class="mr-2 h-4 w-4" />
                                Official Form (PDF)
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
                        <CalendarDays
                            class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600"
                        />
                        <p
                            class="mt-4 text-slate-500 dark:text-slate-400"
                        >
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
                                        TIN
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Name
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Gross Compensation
                                    </th>
                                    <th
                                        v-if="isAnnualReport"
                                        class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Non-Taxable
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Taxable Compensation
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Tax Withheld
                                    </th>
                                    <th
                                        v-if="
                                            isAlphalistReport &&
                                            selectedSchedule === '7.3'
                                        "
                                        class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Separation Date
                                    </th>
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
                                        {{ row.tin }}
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
                                    <td
                                        class="px-4 py-3 text-right text-sm text-slate-600 whitespace-nowrap dark:text-slate-400"
                                    >
                                        {{
                                            formatCurrency(row.gross_compensation)
                                        }}
                                    </td>
                                    <td
                                        v-if="isAnnualReport"
                                        class="px-4 py-3 text-right text-sm text-slate-600 whitespace-nowrap dark:text-slate-400"
                                    >
                                        {{
                                            formatCurrency(
                                                row.non_taxable_compensation,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-sm text-slate-600 whitespace-nowrap dark:text-slate-400"
                                    >
                                        {{
                                            formatCurrency(
                                                row.taxable_compensation,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-sm font-medium text-slate-900 whitespace-nowrap dark:text-slate-100"
                                    >
                                        {{
                                            formatCurrency(row.withholding_tax)
                                        }}
                                    </td>
                                    <td
                                        v-if="
                                            isAlphalistReport &&
                                            selectedSchedule === '7.3'
                                        "
                                        class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap dark:text-slate-400"
                                    >
                                        {{ row.termination_date || '-' }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot
                                v-if="previewTotals"
                                class="bg-red-50 dark:bg-red-900/20"
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
                                    <td
                                        class="px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            formatCurrency(
                                                previewTotals.gross_compensation,
                                            )
                                        }}
                                    </td>
                                    <td
                                        v-if="isAnnualReport"
                                        class="px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            formatCurrency(
                                                previewTotals.non_taxable_compensation,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            formatCurrency(
                                                previewTotals.taxable_compensation,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-sm font-bold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            formatCurrency(
                                                previewTotals.withholding_tax,
                                            )
                                        }}
                                    </td>
                                    <td
                                        v-if="
                                            isAlphalistReport &&
                                            selectedSchedule === '7.3'
                                        "
                                    ></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
