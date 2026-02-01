<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface Payment {
    id: number;
    amount: number;
    balance_before: number;
    balance_after: number;
    payment_date: string | null;
    payment_source: string | null;
}

interface LoanDetail {
    id: number;
    loan_type: string;
    loan_type_label: string;
    loan_type_category: string;
    loan_code: string;
    reference_number: string | null;
    principal_amount: number;
    interest_rate: number;
    monthly_deduction: number;
    term_months: number;
    total_amount: number;
    total_paid: number;
    remaining_balance: number;
    status: string;
    status_label: string;
    status_color: string;
    start_date: string | null;
    expected_end_date: string | null;
    actual_end_date: string | null;
    progress_percentage: number;
    notes: string | null;
    payments: Payment[];
}

const props = defineProps<{
    loan: LoanDetail;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self-Service', href: '/my/dashboard' },
    { title: 'My Loans', href: '/my/loans' },
    { title: props.loan.loan_type_label, href: `/my/loans/${props.loan.id}` },
];

function formatCurrency(value: number | null): string {
    if (value == null) return '---';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
}

function statusBadgeClasses(color: string): string {
    const map: Record<string, string> = {
        green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        slate: 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
    };
    return map[color] ?? map.slate;
}

function formatRate(rate: number): string {
    return (rate * 100).toFixed(2) + '%';
}
</script>

<template>
    <Head :title="`${loan.loan_type_label} - My Loans - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Back Button & Header -->
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="router.visit('/my/loans')">
                    <ArrowLeft class="mr-1 h-4 w-4" />
                    Back
                </Button>
            </div>

            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ loan.loan_type_label }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ loan.loan_code }}
                        <span v-if="loan.reference_number"> &middot; Ref: {{ loan.reference_number }}</span>
                    </p>
                </div>
                <span
                    class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                    :class="statusBadgeClasses(loan.status_color)"
                >
                    {{ loan.status_label }}
                </span>
            </div>

            <!-- Progress -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between text-sm text-slate-500 dark:text-slate-400">
                    <span>{{ loan.progress_percentage.toFixed(1) }}% paid</span>
                    <span>{{ formatCurrency(loan.total_paid) }} of {{ formatCurrency(loan.total_amount) }}</span>
                </div>
                <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                    <div
                        class="h-full rounded-full bg-green-500 transition-all"
                        :style="{ width: `${loan.progress_percentage}%` }"
                    />
                </div>
                <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ formatCurrency(loan.remaining_balance) }}
                    <span class="font-normal text-slate-500 dark:text-slate-400">remaining</span>
                </p>
            </div>

            <!-- Loan Details Grid -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Principal Amount</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {{ formatCurrency(loan.principal_amount) }}
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Monthly Deduction</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {{ formatCurrency(loan.monthly_deduction) }}
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Interest Rate</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {{ formatRate(loan.interest_rate) }}
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Term</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {{ loan.term_months }} months
                    </p>
                </div>
            </div>

            <!-- Dates -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Dates</h2>
                <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Start Date</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ loan.start_date ?? '---' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Expected End Date</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ loan.expected_end_date ?? '---' }}
                        </dd>
                    </div>
                    <div v-if="loan.actual_end_date">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Actual End Date</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ loan.actual_end_date }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Notes -->
            <div
                v-if="loan.notes"
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
            >
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Notes</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ loan.notes }}</p>
            </div>

            <!-- Payment History -->
            <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Payment History</h2>
                </div>

                <div v-if="loan.payments.length === 0" class="px-6 py-12 text-center">
                    <p class="text-sm text-slate-500 dark:text-slate-400">No payments recorded yet.</p>
                </div>

                <table v-else class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                        <tr>
                            <th class="px-6 py-3 font-medium text-slate-500 dark:text-slate-400">Date</th>
                            <th class="px-6 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Amount</th>
                            <th class="hidden px-6 py-3 text-right font-medium text-slate-500 md:table-cell dark:text-slate-400">Balance Before</th>
                            <th class="px-6 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Balance After</th>
                            <th class="hidden px-6 py-3 font-medium text-slate-500 md:table-cell dark:text-slate-400">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <tr v-for="payment in loan.payments" :key="payment.id">
                            <td class="px-6 py-4 text-slate-900 dark:text-slate-100">
                                {{ payment.payment_date ?? '---' }}
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-slate-900 dark:text-slate-100">
                                {{ formatCurrency(payment.amount) }}
                            </td>
                            <td class="hidden px-6 py-4 text-right text-slate-700 md:table-cell dark:text-slate-300">
                                {{ formatCurrency(payment.balance_before) }}
                            </td>
                            <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">
                                {{ formatCurrency(payment.balance_after) }}
                            </td>
                            <td class="hidden px-6 py-4 text-slate-500 md:table-cell dark:text-slate-400">
                                {{ payment.payment_source ?? '---' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </TenantLayout>
</template>
