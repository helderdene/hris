<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Banknote } from 'lucide-vue-next';

interface Payslip {
    id: number;
    period_name: string | null;
    period_start: string | null;
    period_end: string | null;
    gross_pay: number;
    total_deductions: number;
    net_pay: number;
    status: string;
    status_label: string;
    status_color: string;
}

interface PaginatedData<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    hasEmployeeProfile: boolean;
    payslips: PaginatedData<Payslip> | null;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self-Service', href: '/my/dashboard' },
    { title: 'Payslips', href: '/my/payslips' },
];

function formatCurrency(value: number | null): string {
    if (value == null) return '---';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
}

function handlePageChange(url: string | null): void {
    if (url) {
        router.visit(url);
    }
}
</script>

<template>
    <Head :title="`Payslips - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Payslips
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    View your payslip history and download payslip PDFs.
                </p>
            </div>

            <!-- No Employee Profile -->
            <div
                v-if="!hasEmployeeProfile"
                class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
            >
                <Banknote
                    class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                />
                <h3
                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    No employee profile
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    No employee profile is linked to your account.
                </p>
            </div>

            <!-- Payslip Table -->
            <div
                v-else-if="payslips && payslips.data.length > 0"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800"
                    >
                        <tr>
                            <th
                                class="px-6 py-3 font-medium text-slate-500 dark:text-slate-400"
                            >
                                Period
                            </th>
                            <th
                                class="px-6 py-3 text-right font-medium text-slate-500 dark:text-slate-400"
                            >
                                Gross Pay
                            </th>
                            <th
                                class="hidden px-6 py-3 text-right font-medium text-slate-500 md:table-cell dark:text-slate-400"
                            >
                                Deductions
                            </th>
                            <th
                                class="px-6 py-3 text-right font-medium text-slate-500 dark:text-slate-400"
                            >
                                Net Pay
                            </th>
                            <th
                                class="px-6 py-3 text-center font-medium text-slate-500 dark:text-slate-400"
                            >
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <tr
                            v-for="payslip in payslips.data"
                            :key="payslip.id"
                            class="cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            @click="router.visit(`/my/payslips/${payslip.id}`)"
                        >
                            <td class="px-6 py-4">
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ payslip.period_name ?? 'N/A' }}
                                </div>
                                <div
                                    v-if="payslip.period_start && payslip.period_end"
                                    class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ payslip.period_start }} -
                                    {{ payslip.period_end }}
                                </div>
                            </td>
                            <td
                                class="px-6 py-4 text-right text-slate-700 dark:text-slate-300"
                            >
                                {{ formatCurrency(payslip.gross_pay) }}
                            </td>
                            <td
                                class="hidden px-6 py-4 text-right text-slate-700 md:table-cell dark:text-slate-300"
                            >
                                {{ formatCurrency(payslip.total_deductions) }}
                            </td>
                            <td
                                class="px-6 py-4 text-right font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{ formatCurrency(payslip.net_pay) }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="payslip.status_color"
                                >
                                    {{ payslip.status_label }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
            >
                <Banknote
                    class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                />
                <h3
                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    No payslips
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    You don't have any payslips available yet.
                </p>
            </div>

            <!-- Pagination -->
            <div
                v-if="payslips && payslips.last_page > 1"
                class="flex items-center justify-center gap-2"
            >
                <Button
                    v-for="link in payslips.links"
                    :key="link.label"
                    variant="outline"
                    size="sm"
                    :disabled="!link.url || link.active"
                    :class="{ 'bg-blue-500 text-white': link.active }"
                    @click="handlePageChange(link.url)"
                    v-html="link.label"
                />
            </div>
        </div>
    </TenantLayout>
</template>
