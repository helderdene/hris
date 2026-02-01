<script setup lang="ts">
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download } from 'lucide-vue-next';

interface Earning {
    description: string;
    amount: number;
}

interface Deduction {
    description: string;
    amount: number;
}

interface PayslipEntry {
    id: number;
    employee_number: string;
    employee_name: string;
    department_name: string | null;
    position_name: string | null;
    period_name: string | null;
    period_start: string | null;
    period_end: string | null;
    status: string;
    status_label: string;
    status_color: string;
    basic_pay: number;
    overtime_pay: number;
    night_diff_pay: number;
    holiday_pay: number;
    allowances_total: number;
    bonuses_total: number;
    gross_pay: number;
    sss_employee: number;
    philhealth_employee: number;
    pagibig_employee: number;
    withholding_tax: number;
    other_deductions_total: number;
    total_deductions: number;
    net_pay: number;
    earnings: Earning[];
    deductions: Deduction[];
}

const props = defineProps<{
    entry: PayslipEntry;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self-Service', href: '/my/dashboard' },
    { title: 'Payslips', href: '/my/payslips' },
    { title: props.entry.period_name ?? 'Payslip', href: `/my/payslips/${props.entry.id}` },
];

function formatCurrency(value: number | null): string {
    if (value == null) return '---';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
}
</script>

<template>
    <Head :title="`Payslip - ${entry.period_name ?? 'Detail'} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <Link
                            href="/my/payslips"
                            class="text-slate-400 transition-colors hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300"
                        >
                            <ArrowLeft class="h-5 w-5" />
                        </Link>
                        <h1
                            class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                        >
                            {{ entry.period_name ?? 'Payslip' }}
                        </h1>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="entry.status_color"
                        >
                            {{ entry.status_label }}
                        </span>
                    </div>
                    <p
                        v-if="entry.period_start && entry.period_end"
                        class="mt-1 ml-8 text-sm text-slate-500 dark:text-slate-400"
                    >
                        {{ entry.period_start }} - {{ entry.period_end }}
                    </p>
                </div>
                <a
                    :href="`/my/payslips/${entry.id}/pdf`"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                >
                    <Download class="h-4 w-4" />
                    Download PDF
                </a>
            </div>

            <!-- Employee Info -->
            <Card class="dark:border-slate-700 dark:bg-slate-900">
                <CardHeader>
                    <CardTitle class="text-base text-slate-900 dark:text-slate-100">
                        Employee Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p class="text-xs text-slate-500 uppercase dark:text-slate-400">
                                Employee No.
                            </p>
                            <p class="mt-1 font-medium text-slate-900 dark:text-slate-100">
                                {{ entry.employee_number }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase dark:text-slate-400">
                                Name
                            </p>
                            <p class="mt-1 font-medium text-slate-900 dark:text-slate-100">
                                {{ entry.employee_name }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase dark:text-slate-400">
                                Department
                            </p>
                            <p class="mt-1 font-medium text-slate-900 dark:text-slate-100">
                                {{ entry.department_name ?? 'N/A' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase dark:text-slate-400">
                                Position
                            </p>
                            <p class="mt-1 font-medium text-slate-900 dark:text-slate-100">
                                {{ entry.position_name ?? 'N/A' }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Earnings -->
                <Card class="dark:border-slate-700 dark:bg-slate-900">
                    <CardHeader>
                        <CardTitle class="text-base text-slate-900 dark:text-slate-100">
                            Earnings
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-for="(earning, index) in entry.earnings"
                                :key="index"
                                class="flex items-center justify-between"
                            >
                                <span class="text-sm text-slate-700 dark:text-slate-300">
                                    {{ earning.description }}
                                </span>
                                <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ formatCurrency(earning.amount) }}
                                </span>
                            </div>
                            <div
                                v-if="entry.earnings.length === 0"
                                class="text-sm text-slate-400 dark:text-slate-500"
                            >
                                No detailed earnings breakdown available.
                            </div>
                            <div
                                class="border-t border-slate-200 pt-3 dark:border-slate-700"
                            >
                                <div class="flex items-center justify-between font-semibold">
                                    <span class="text-slate-900 dark:text-slate-100">
                                        Gross Pay
                                    </span>
                                    <span class="text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(entry.gross_pay) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Deductions -->
                <Card class="dark:border-slate-700 dark:bg-slate-900">
                    <CardHeader>
                        <CardTitle class="text-base text-slate-900 dark:text-slate-100">
                            Deductions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-700 dark:text-slate-300">SSS</span>
                                <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ formatCurrency(entry.sss_employee) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-700 dark:text-slate-300">PhilHealth</span>
                                <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ formatCurrency(entry.philhealth_employee) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-700 dark:text-slate-300">Pag-IBIG</span>
                                <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ formatCurrency(entry.pagibig_employee) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-700 dark:text-slate-300">Withholding Tax</span>
                                <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ formatCurrency(entry.withholding_tax) }}
                                </span>
                            </div>
                            <div
                                v-for="(deduction, index) in entry.deductions"
                                :key="index"
                                class="flex items-center justify-between"
                            >
                                <span class="text-sm text-slate-700 dark:text-slate-300">
                                    {{ deduction.description }}
                                </span>
                                <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ formatCurrency(deduction.amount) }}
                                </span>
                            </div>
                            <div
                                class="border-t border-slate-200 pt-3 dark:border-slate-700"
                            >
                                <div class="flex items-center justify-between font-semibold">
                                    <span class="text-slate-900 dark:text-slate-100">
                                        Total Deductions
                                    </span>
                                    <span class="text-red-600 dark:text-red-400">
                                        {{ formatCurrency(entry.total_deductions) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Net Pay Summary -->
            <Card class="dark:border-slate-700 dark:bg-slate-900">
                <CardContent class="py-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Net Pay
                            </p>
                            <p
                                class="text-3xl font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ formatCurrency(entry.net_pay) }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Gross Pay: {{ formatCurrency(entry.gross_pay) }}
                            </p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Deductions: {{ formatCurrency(entry.total_deductions) }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
