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
import { Download, FileSpreadsheet, FileText, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ReportType {
    value: string;
    label: string;
    shortLabel: string;
    description: string;
    periodType: 'monthly' | 'quarterly';
}

interface Month {
    value: number;
    label: string;
}

interface Quarter {
    value: number;
    label: string;
}

interface Department {
    id: number;
    name: string;
}

interface PreviewRow {
    sss_number: string;
    last_name: string;
    first_name: string;
    middle_name?: string;
    sss_employee?: number;
    sss_employer?: number;
    total_contribution?: number;
    total_payments?: number;
    loan_type_label?: string;
    ss_contribution?: number;
    gross_pay?: number;
    [key: string]: unknown;
}

interface PreviewTotals {
    employee_count: number;
    sss_employee?: number;
    sss_employer?: number;
    total_contribution?: number;
    total_payments?: number;
    ss_contribution?: number;
    [key: string]: unknown;
}

const props = defineProps<{
    reportTypes: ReportType[];
    departments: Department[];
    years: number[];
    months: Month[];
    quarters: Quarter[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports/sss' },
    { title: 'SSS Reports', href: '/reports/sss' },
];

// Form state
const selectedReportType = ref<string | null>(null);
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const selectedQuarter = ref(Math.ceil((new Date().getMonth() + 1) / 3));
const selectedDepartments = ref<number[]>([]);

// UI state
const isLoading = ref(false);
const isGenerating = ref(false);
const previewData = ref<PreviewRow[]>([]);
const previewTotals = ref<PreviewTotals | null>(null);
const errorMessage = ref<string | null>(null);

const currentReportType = computed(() => {
    return props.reportTypes.find((t) => t.value === selectedReportType.value);
});

const isMonthlyReport = computed(() => {
    return currentReportType.value?.periodType === 'monthly';
});

const canPreview = computed(() => {
    if (!selectedReportType.value) return false;
    if (isMonthlyReport.value) {
        return selectedYear.value && selectedMonth.value;
    }
    return selectedYear.value && selectedQuarter.value;
});

// Watch for report type changes and fetch preview
watch([selectedReportType, selectedYear, selectedMonth, selectedQuarter], () => {
    if (canPreview.value) {
        fetchPreview();
    }
});

async function fetchPreview() {
    if (!canPreview.value) return;

    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await fetch('/api/reports/sss/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                report_type: selectedReportType.value,
                year: selectedYear.value,
                month: isMonthlyReport.value ? selectedMonth.value : null,
                quarter: !isMonthlyReport.value ? selectedQuarter.value : null,
                department_ids:
                    selectedDepartments.value.length > 0
                        ? selectedDepartments.value
                        : null,
            }),
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

async function generateReport(format: 'xlsx' | 'pdf' | 'csv') {
    if (!canPreview.value) return;

    isGenerating.value = true;

    try {
        const response = await fetch('/api/reports/sss/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/octet-stream',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                report_type: selectedReportType.value,
                format,
                year: selectedYear.value,
                month: isMonthlyReport.value ? selectedMonth.value : null,
                quarter: !isMonthlyReport.value ? selectedQuarter.value : null,
                department_ids:
                    selectedDepartments.value.length > 0
                        ? selectedDepartments.value
                        : null,
            }),
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
            filename = `sss_${selectedReportType.value}_${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}.${format}`;
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
        case 'r3':
            return 'Monthly contributions';
        case 'r5':
            return 'Loan payments';
        case 'sbr':
            return 'Remittance proof';
        case 'ecl':
            return 'Bank file';
        default:
            return '';
    }
}

function getReportTypeColor(type: string): string {
    switch (type) {
        case 'r3':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 'r5':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300';
        case 'sbr':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
        case 'ecl':
            return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
    }
}
</script>

<template>
    <Head :title="`SSS Reports - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    SSS Compliance Reports
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Generate SSS compliance reports for regulatory submission.
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
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    reportType.periodType === 'monthly'
                                        ? 'Monthly'
                                        : 'Quarterly'
                                }}
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

                        <!-- Month (for monthly reports) -->
                        <div v-if="isMonthlyReport" class="w-40">
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

                        <!-- Quarter (for quarterly reports) -->
                        <div v-else class="w-48">
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Quarter
                            </label>
                            <Select
                                v-model="selectedQuarter"
                                @update:model-value="
                                    (v) => (selectedQuarter = Number(v))
                                "
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="quarter in quarters"
                                        :key="quarter.value"
                                        :value="quarter.value"
                                    >
                                        {{ quarter.label }}
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
                                size="sm"
                                :disabled="
                                    isGenerating || previewData.length === 0
                                "
                                :style="{ backgroundColor: primaryColor }"
                                @click="generateReport('csv')"
                            >
                                <Download class="mr-2 h-4 w-4" />
                                {{
                                    selectedReportType === 'ecl'
                                        ? 'Text (ECL)'
                                        : 'CSV'
                                }}
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
                                        SSS Number
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Name
                                    </th>
                                    <template
                                        v-if="
                                            selectedReportType === 'r3' ||
                                            selectedReportType === 'sbr'
                                        "
                                    >
                                        <th
                                            class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Employee Share
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Employer Share
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Total
                                        </th>
                                    </template>
                                    <template
                                        v-else-if="selectedReportType === 'r5'"
                                    >
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Loan Type
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Quarterly Payment
                                        </th>
                                    </template>
                                    <template
                                        v-else-if="selectedReportType === 'ecl'"
                                    >
                                        <th
                                            class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            SS Contribution
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
                                        {{ row.sss_number }}
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
                                    <template
                                        v-if="
                                            selectedReportType === 'r3' ||
                                            selectedReportType === 'sbr'
                                        "
                                    >
                                        <td
                                            class="px-4 py-3 text-right text-sm text-slate-600 whitespace-nowrap dark:text-slate-400"
                                        >
                                            {{
                                                formatCurrency(row.sss_employee)
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right text-sm text-slate-600 whitespace-nowrap dark:text-slate-400"
                                        >
                                            {{
                                                formatCurrency(row.sss_employer)
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
                                    <template
                                        v-else-if="selectedReportType === 'r5'"
                                    >
                                        <td
                                            class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap dark:text-slate-400"
                                        >
                                            {{ row.loan_type_label }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right text-sm font-medium text-slate-900 whitespace-nowrap dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(
                                                    row.total_payments,
                                                )
                                            }}
                                        </td>
                                    </template>
                                    <template
                                        v-else-if="selectedReportType === 'ecl'"
                                    >
                                        <td
                                            class="px-4 py-3 text-right text-sm font-medium text-slate-900 whitespace-nowrap dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(
                                                    row.ss_contribution,
                                                )
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
                                    <template
                                        v-if="
                                            selectedReportType === 'r3' ||
                                            selectedReportType === 'sbr'
                                        "
                                    >
                                        <td
                                            class="px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(
                                                    previewTotals.sss_employee,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(
                                                    previewTotals.sss_employer,
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
                                    <template
                                        v-else-if="selectedReportType === 'r5'"
                                    >
                                        <td class="px-4 py-3"></td>
                                        <td
                                            class="px-4 py-3 text-right text-sm font-bold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(
                                                    previewTotals.total_payments,
                                                )
                                            }}
                                        </td>
                                    </template>
                                    <template
                                        v-else-if="selectedReportType === 'ecl'"
                                    >
                                        <td
                                            class="px-4 py-3 text-right text-sm font-bold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                formatCurrency(
                                                    previewTotals.ss_contribution,
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
