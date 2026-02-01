<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const contributionTabs = [
    { name: 'SSS', href: '/organization/contributions/sss' },
    { name: 'PhilHealth', href: '/organization/contributions/philhealth' },
    { name: 'Pag-IBIG', href: '/organization/contributions/pagibig' },
    { name: 'Tax', href: '/organization/contributions/tax' },
    { name: 'Calculator', href: '/organization/contributions/calculator' },
];

interface ContributionResult {
    employee_share: number;
    employer_share: number;
    total: number;
    table_id: number | null;
    error: string | null;
    monthly_salary_credit?: number;
    ec_contribution?: number;
    basis_salary?: number;
}

interface TaxResult {
    tax_due: number;
    taxable_income: number;
    table_id: number | null;
    pay_period: string;
    error: string | null;
}

interface CalculationResult {
    salary: number;
    effective_date: string;
    contributions: {
        sss: ContributionResult;
        philhealth: ContributionResult;
        pagibig: ContributionResult;
        tax: TaxResult;
        totals: {
            employee_share: number;
            employer_share: number;
            total: number;
            tax_due: number;
            total_employee_deductions: number;
            net_pay: number;
        };
    };
}

const props = defineProps<{
    hasAllTables: boolean;
    tableStatus: {
        sss: boolean;
        philhealth: boolean;
        pagibig: boolean;
        tax: boolean;
    };
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Contributions', href: '/organization/contributions/sss' },
    { title: 'Calculator', href: '/organization/contributions/calculator' },
];

const salary = ref<number>(25000);
const effectiveDate = ref<string>(new Date().toISOString().split('T')[0]);
const isCalculating = ref(false);
const result = ref<CalculationResult | null>(null);
const error = ref<string | null>(null);

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

async function handleCalculate() {
    isCalculating.value = true;
    error.value = null;
    result.value = null;

    try {
        const response = await fetch('/api/organization/contributions/calculate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                salary: salary.value,
                effective_date: effectiveDate.value,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            result.value = data.data;
        } else {
            error.value = data.message || 'Failed to calculate contributions';
        }
    } catch (e) {
        error.value = 'An error occurred while calculating contributions';
    } finally {
        isCalculating.value = false;
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Head :title="`Contribution Calculator - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Contribution Type Tabs -->
            <div class="border-b border-slate-200 dark:border-slate-700">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <Link
                        v-for="tab in contributionTabs"
                        :key="tab.name"
                        :href="tab.href"
                        :class="[
                            tab.href === '/organization/contributions/calculator'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                            'whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium',
                        ]"
                    >
                        {{ tab.name }}
                    </Link>
                </nav>
            </div>

            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Contribution Calculator
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Calculate government contributions for a given salary.
                </p>
            </div>

            <!-- Warning if tables not configured -->
            <Alert v-if="!hasAllTables" class="border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950">
                <AlertTitle class="text-amber-800 dark:text-amber-200">Missing Contribution Tables</AlertTitle>
                <AlertDescription class="text-amber-700 dark:text-amber-300">
                    <p>Some contribution tables are not configured:</p>
                    <ul class="mt-2 list-disc pl-5">
                        <li v-if="!tableStatus.sss">SSS contribution table is missing</li>
                        <li v-if="!tableStatus.philhealth">PhilHealth contribution table is missing</li>
                        <li v-if="!tableStatus.pagibig">Pag-IBIG contribution table is missing</li>
                    </ul>
                </AlertDescription>
            </Alert>

            <!-- Info if tax table not configured -->
            <Alert v-if="hasAllTables && !tableStatus.tax" class="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950">
                <AlertTitle class="text-blue-800 dark:text-blue-200">Tax Table Not Configured</AlertTitle>
                <AlertDescription class="text-blue-700 dark:text-blue-300">
                    Withholding tax table is not configured. Tax calculation will be skipped.
                    <Link href="/organization/contributions/tax" class="underline">Configure tax tables</Link>
                </AlertDescription>
            </Alert>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Input Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Calculate Contributions</CardTitle>
                        <CardDescription>
                            Enter a monthly salary to see the contribution breakdown.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="handleCalculate" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="salary">Monthly Salary (PHP)</Label>
                                <Input
                                    id="salary"
                                    v-model.number="salary"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Enter monthly salary"
                                    required
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="effective_date">Effective Date</Label>
                                <Input
                                    id="effective_date"
                                    v-model="effectiveDate"
                                    type="date"
                                />
                            </div>
                            <Button
                                type="submit"
                                :disabled="isCalculating || !hasAllTables"
                                :style="{ backgroundColor: primaryColor }"
                                class="w-full"
                            >
                                {{ isCalculating ? 'Calculating...' : 'Calculate' }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- Results Card -->
                <Card v-if="result">
                    <CardHeader>
                        <CardTitle>Contribution Breakdown</CardTitle>
                        <CardDescription>
                            For {{ formatCurrency(result.salary) }} monthly salary
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- SSS -->
                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                            <h4 class="font-medium text-slate-900 dark:text-slate-100">SSS</h4>
                            <div v-if="result.contributions.sss.error" class="mt-2 text-sm text-red-500">
                                {{ result.contributions.sss.error }}
                            </div>
                            <div v-else class="mt-2 grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Employee</div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.sss.employee_share) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Employer</div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.sss.employer_share) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Total</div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.sss.total) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PhilHealth -->
                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                            <h4 class="font-medium text-slate-900 dark:text-slate-100">PhilHealth</h4>
                            <div v-if="result.contributions.philhealth.error" class="mt-2 text-sm text-red-500">
                                {{ result.contributions.philhealth.error }}
                            </div>
                            <div v-else class="mt-2 grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Employee</div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.philhealth.employee_share) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Employer</div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.philhealth.employer_share) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Total</div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.philhealth.total) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pag-IBIG -->
                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                            <h4 class="font-medium text-slate-900 dark:text-slate-100">Pag-IBIG</h4>
                            <div v-if="result.contributions.pagibig.error" class="mt-2 text-sm text-red-500">
                                {{ result.contributions.pagibig.error }}
                            </div>
                            <div v-else class="mt-2 grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Employee</div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.pagibig.employee_share) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Employer</div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.pagibig.employer_share) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Total</div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.pagibig.total) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Withholding Tax -->
                        <div class="rounded-lg bg-amber-50 p-4 dark:bg-amber-900/20">
                            <h4 class="font-medium text-slate-900 dark:text-slate-100">Withholding Tax</h4>
                            <div v-if="result.contributions.tax.error" class="mt-2 text-sm text-amber-600 dark:text-amber-400">
                                {{ result.contributions.tax.error }}
                            </div>
                            <div v-else class="mt-2 grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Taxable Income</div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.tax.taxable_income) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Tax Due</div>
                                    <div class="font-medium text-red-600 dark:text-red-400">
                                        {{ formatCurrency(result.contributions.tax.tax_due) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Pay Period</div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100 capitalize">
                                        {{ result.contributions.tax.pay_period.replace('_', '-') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="rounded-lg border-2 border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                            <h4 class="font-medium text-slate-900 dark:text-slate-100">Total Contributions</h4>
                            <div class="mt-2 grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Employee (SSS+PH+PI)</div>
                                    <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.totals.employee_share) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Employer</div>
                                    <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.totals.employer_share) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Contributions Total</div>
                                    <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(result.contributions.totals.total) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Net Pay Summary -->
                        <div class="rounded-lg border-2 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20" :style="{ borderColor: primaryColor }">
                            <h4 class="font-medium text-slate-900 dark:text-slate-100">Net Pay Summary</h4>
                            <div class="mt-2 grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Total Deductions (incl. Tax)</div>
                                    <div class="text-lg font-bold text-red-600 dark:text-red-400">
                                        {{ formatCurrency(result.contributions.totals.total_employee_deductions) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-slate-500 dark:text-slate-400">Estimated Net Pay</div>
                                    <div class="text-lg font-bold" :style="{ color: primaryColor }">
                                        {{ formatCurrency(result.contributions.totals.net_pay) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Error State -->
                <Alert v-if="error" variant="destructive">
                    <AlertTitle>Error</AlertTitle>
                    <AlertDescription>{{ error }}</AlertDescription>
                </Alert>
            </div>
        </div>
    </TenantLayout>
</template>
