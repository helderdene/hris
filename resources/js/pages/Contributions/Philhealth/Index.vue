<script setup lang="ts">
import PhilhealthContributionFormModal from '@/Components/PhilhealthContributionFormModal.vue';
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

interface PhilhealthTable {
    id: number;
    effective_from: string;
    effective_from_formatted: string;
    description: string | null;
    contribution_rate: number;
    contribution_rate_percent: number;
    employee_share_rate: number;
    employer_share_rate: number;
    salary_floor: number;
    salary_ceiling: number;
    min_contribution: number;
    max_contribution: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    philhealthTables: PhilhealthTable[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Contributions', href: '/organization/contributions/sss' },
    { title: 'PhilHealth', href: '/organization/contributions/philhealth' },
];

const isFormModalOpen = ref(false);
const editingTable = ref<PhilhealthTable | null>(null);
const deletingTableId = ref<number | null>(null);

function getStatusBadgeClasses(isActive: boolean): string {
    if (isActive) {
        return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
    }
    return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

function handleAddTable() {
    editingTable.value = null;
    isFormModalOpen.value = true;
}

function handleEditTable(table: PhilhealthTable) {
    editingTable.value = table;
    isFormModalOpen.value = true;
}

async function handleDeleteTable(table: PhilhealthTable) {
    if (
        !confirm(
            `Are you sure you want to delete the PhilHealth contribution table effective ${table.effective_from_formatted}?`,
        )
    ) {
        return;
    }

    deletingTableId.value = table.id;

    try {
        const response = await fetch(
            `/api/organization/contributions/philhealth/${table.id}`,
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
            router.reload({ only: ['philhealthTables'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete PhilHealth contribution table');
        }
    } catch (error) {
        alert('An error occurred while deleting the PhilHealth contribution table');
    } finally {
        deletingTableId.value = null;
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingTable.value = null;
    router.reload({ only: ['philhealthTables'] });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Head :title="`PhilHealth Contributions - ${tenantName}`" />

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
                            tab.href === '/organization/contributions/philhealth'
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
                        PhilHealth Contribution Tables
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage Philippine Health Insurance Corporation contribution rates.
                    </p>
                </div>
                <Button
                    @click="handleAddTable"
                    class="shrink-0"
                    :style="{ backgroundColor: primaryColor }"
                    data-test="add-philhealth-table-button"
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
                    Add PhilHealth Table
                </Button>
            </div>

            <!-- PhilHealth Tables List -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="hidden md:block">
                    <table
                        class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                    >
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Effective Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Rate
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Salary Range
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Contribution Range
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    Status
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="table in philhealthTables"
                                :key="table.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ table.effective_from_formatted }}
                                    </div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ table.description || '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {{ table.contribution_rate_percent }}%
                                </td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {{ formatCurrency(table.salary_floor) }} - {{ formatCurrency(table.salary_ceiling) }}
                                </td>
                                <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {{ formatCurrency(table.min_contribution) }} - {{ formatCurrency(table.max_contribution) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(table.is_active)"
                                    >
                                        {{ table.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                                <span class="sr-only">Open menu</span>
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                                </svg>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem @click="handleEditTable(table)">Edit</DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                :disabled="deletingTableId === table.id"
                                                @click="handleDeleteTable(table)"
                                            >
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-if="philhealthTables.length === 0" class="px-6 py-12 text-center">
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No PhilHealth contribution tables
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Get started by adding a PhilHealth contribution table.
                    </p>
                    <div class="mt-6">
                        <Button @click="handleAddTable" :style="{ backgroundColor: primaryColor }">
                            Add PhilHealth Table
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <PhilhealthContributionFormModal
            v-model:open="isFormModalOpen"
            :philhealth-table="editingTable"
            @success="handleFormSuccess"
        />
    </TenantLayout>
</template>
