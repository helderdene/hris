<script setup lang="ts">
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Landmark, ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';

interface Loan {
    id: number;
    loan_type: string;
    loan_type_label: string;
    loan_type_category: string;
    loan_code: string;
    reference_number: string | null;
    principal_amount: number;
    total_amount: number;
    total_paid: number;
    remaining_balance: number;
    monthly_deduction: number;
    interest_rate: number;
    term_months: number;
    status: string;
    status_label: string;
    status_color: string;
    start_date: string | null;
    expected_end_date: string | null;
    progress_percentage: number;
}

const props = defineProps<{
    employee: { id: number; full_name: string } | null;
    loans: Loan[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self-Service', href: '/my/dashboard' },
    { title: 'My Loans', href: '/my/loans' },
];

const activeLoans = computed(() => props.loans.filter((l) => l.status === 'active'));
const otherLoans = computed(() => props.loans.filter((l) => l.status !== 'active'));

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
</script>

<template>
    <Head :title="`My Loans - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    My Loans
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    View your loan balances and payment progress.
                </p>
            </div>

            <!-- No Employee Profile -->
            <div
                v-if="!employee"
                class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
            >
                <Landmark class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                    No employee profile
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    No employee profile is linked to your account.
                </p>
            </div>

            <!-- Empty State -->
            <div
                v-else-if="loans.length === 0"
                class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
            >
                <Landmark class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                    No loans
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    You don't have any loans on record.
                </p>
            </div>

            <!-- Active Loans -->
            <template v-else>
                <div v-if="activeLoans.length > 0" class="flex flex-col gap-4">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Active Loans
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="loan in activeLoans"
                            :key="loan.id"
                            class="cursor-pointer rounded-xl border border-slate-200 bg-white p-5 transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
                            @click="router.visit(`/my/loans/${loan.id}`)"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        {{ loan.loan_type_label }}
                                    </p>
                                    <p class="text-xs text-slate-400 dark:text-slate-500">
                                        {{ loan.loan_code }}
                                    </p>
                                </div>
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusBadgeClasses(loan.status_color)"
                                >
                                    {{ loan.status_label }}
                                </span>
                            </div>

                            <div class="mt-4">
                                <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                                    {{ formatCurrency(loan.remaining_balance) }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    remaining balance
                                </p>
                            </div>

                            <!-- Progress Bar -->
                            <div class="mt-3">
                                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                    <span>{{ loan.progress_percentage.toFixed(1) }}% paid</span>
                                    <span>{{ formatCurrency(loan.total_amount) }} total</span>
                                </div>
                                <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div
                                        class="h-full rounded-full bg-green-500 transition-all"
                                        :style="{ width: `${loan.progress_percentage}%` }"
                                    />
                                </div>
                            </div>

                            <div class="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span>{{ formatCurrency(loan.monthly_deduction) }}/mo</span>
                                <ArrowRight class="h-4 w-4" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Other Loans -->
                <div v-if="otherLoans.length > 0" class="flex flex-col gap-4">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Other Loans
                    </h2>
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                                <tr>
                                    <th class="px-6 py-3 font-medium text-slate-500 dark:text-slate-400">Loan</th>
                                    <th class="hidden px-6 py-3 text-right font-medium text-slate-500 md:table-cell dark:text-slate-400">Total</th>
                                    <th class="px-6 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Paid</th>
                                    <th class="px-6 py-3 text-center font-medium text-slate-500 dark:text-slate-400">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                <tr
                                    v-for="loan in otherLoans"
                                    :key="loan.id"
                                    class="cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                    @click="router.visit(`/my/loans/${loan.id}`)"
                                >
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-slate-900 dark:text-slate-100">
                                            {{ loan.loan_type_label }}
                                        </div>
                                        <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                            {{ loan.loan_code }}
                                        </div>
                                    </td>
                                    <td class="hidden px-6 py-4 text-right text-slate-700 md:table-cell dark:text-slate-300">
                                        {{ formatCurrency(loan.total_amount) }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">
                                        {{ formatCurrency(loan.total_paid) }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="statusBadgeClasses(loan.status_color)"
                                        >
                                            {{ loan.status_label }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </TenantLayout>
</template>
