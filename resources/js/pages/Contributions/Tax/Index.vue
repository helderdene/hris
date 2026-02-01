<script setup lang="ts">
import WithholdingTaxFormModal from '@/Components/WithholdingTaxFormModal.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const contributionTabs = [
    { name: 'SSS', href: '/organization/contributions/sss' },
    { name: 'PhilHealth', href: '/organization/contributions/philhealth' },
    { name: 'Pag-IBIG', href: '/organization/contributions/pagibig' },
    { name: 'Tax', href: '/organization/contributions/tax' },
    { name: 'Calculator', href: '/organization/contributions/calculator' },
];

interface TaxBracket {
    id: number;
    withholding_tax_table_id: number;
    min_compensation: number;
    max_compensation: number | null;
    base_tax: number;
    excess_rate: number;
    excess_rate_percentage: number;
    compensation_range: string;
}

interface TaxTable {
    id: number;
    pay_period: string;
    pay_period_label: string;
    effective_from: string;
    effective_from_formatted: string;
    description: string | null;
    is_active: boolean;
    brackets: TaxBracket[];
    brackets_count: number;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    taxTables: TaxTable[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Contributions', href: '/organization/contributions/sss' },
    { title: 'Tax', href: '/organization/contributions/tax' },
];

const isFormModalOpen = ref(false);
const editingTable = ref<TaxTable | null>(null);
const deletingTableId = ref<number | null>(null);

function getStatusBadgeClasses(isActive: boolean): string {
    if (isActive) {
        return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
    }
    return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
}

function getPayPeriodBadgeClasses(payPeriod: string): string {
    const colors: Record<string, string> = {
        monthly: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        semi_monthly: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        weekly: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        daily: 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
    };
    return colors[payPeriod] || 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
}

function handleAddTable() {
    editingTable.value = null;
    isFormModalOpen.value = true;
}

function handleEditTable(table: TaxTable) {
    editingTable.value = table;
    isFormModalOpen.value = true;
}

async function handleDeleteTable(table: TaxTable) {
    if (
        !confirm(
            `Are you sure you want to delete the withholding tax table effective ${table.effective_from_formatted}?`,
        )
    ) {
        return;
    }

    deletingTableId.value = table.id;

    try {
        const response = await fetch(
            `/api/organization/contributions/tax/${table.id}`,
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        if (response.ok) {
            router.reload({ only: ['taxTables'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete withholding tax table');
        }
    } catch (error) {
        alert('An error occurred while deleting the withholding tax table');
    } finally {
        deletingTableId.value = null;
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingTable.value = null;
    router.reload({ only: ['taxTables'] });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Head :title="`Withholding Tax - ${tenantName}`" />

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
                            tab.href === '/organization/contributions/tax'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                            'whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium',
                        ]"
                    >
                        {{ tab.name }}
                    </Link>
                </nav>
            </div>

            <!-- Page Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Withholding Tax Tables
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage BIR withholding tax brackets per TRAIN Law.
                    </p>
                </div>
                <Button
                    @click="handleAddTable"
                    class="shrink-0"
                    :style="{ backgroundColor: primaryColor }"
                    data-test="add-tax-table-button"
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
                            d="M12 4.5v15m7.5-7.5h-15"
                        />
                    </svg>
                    Add Tax Table
                </Button>
            </div>

            <!-- Tax Tables List -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Desktop Table -->
                <div class="hidden md:block">
                    <table
                        class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                    >
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Effective Date
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Pay Period
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Description
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Brackets
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
                                v-for="table in taxTables"
                                :key="table.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                :data-test="`tax-table-row-${table.id}`"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ table.effective_from_formatted }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getPayPeriodBadgeClasses(table.pay_period)"
                                    >
                                        {{ table.pay_period_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ table.description || '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                    >
                                        {{ table.brackets_count || table.brackets?.length || 0 }} brackets
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            getStatusBadgeClasses(
                                                table.is_active,
                                            )
                                        "
                                    >
                                        {{ table.is_active ? 'Active' : 'Inactive' }}
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
                                                @click="handleEditTable(table)"
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
                                                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                                    />
                                                </svg>
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                :disabled="
                                                    deletingTableId === table.id
                                                "
                                                @click="handleDeleteTable(table)"
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
                                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                                                    />
                                                </svg>
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List -->
                <div
                    class="divide-y divide-slate-200 md:hidden dark:divide-slate-700"
                >
                    <div
                        v-for="table in taxTables"
                        :key="table.id"
                        class="p-4"
                        :data-test="`tax-table-card-${table.id}`"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ table.effective_from_formatted }}
                                </div>
                                <div
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ table.description || 'Withholding Tax Table' }}
                                </div>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 w-8 p-0"
                                    >
                                        <span class="sr-only">Open menu</span>
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
                                    <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        @click="handleEditTable(table)"
                                    >
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                        @click="handleDeleteTable(table)"
                                    >
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="getPayPeriodBadgeClasses(table.pay_period)"
                            >
                                {{ table.pay_period_label }}
                            </span>
                            <span
                                class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                            >
                                {{ table.brackets_count || table.brackets?.length || 0 }} brackets
                            </span>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClasses(table.is_active)"
                            >
                                {{ table.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="taxTables.length === 0"
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
                            d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No withholding tax tables
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Get started by adding a withholding tax table with brackets.
                    </p>
                    <div class="mt-6">
                        <Button
                            @click="handleAddTable"
                            :style="{ backgroundColor: primaryColor }"
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
                                    d="M12 4.5v15m7.5-7.5h-15"
                                />
                            </svg>
                            Add Tax Table
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Withholding Tax Form Modal -->
        <WithholdingTaxFormModal
            v-model:open="isFormModalOpen"
            :tax-table="editingTable"
            @success="handleFormSuccess"
        />
    </TenantLayout>
</template>
