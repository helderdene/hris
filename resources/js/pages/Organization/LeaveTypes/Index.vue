<script setup lang="ts">
import LeaveTypeFormModal from '@/Components/LeaveTypeFormModal.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface LeaveType {
    id: number;
    name: string;
    code: string;
    description: string | null;
    leave_category: string;
    leave_category_label: string;
    accrual_method: string;
    accrual_method_label: string;
    accrual_method_short_label: string;
    default_days_per_year: number;
    monthly_accrual_rate: number | null;
    tenure_brackets: { years: number; days: number }[] | null;
    allow_carry_over: boolean;
    max_carry_over_days: number | null;
    carry_over_expiry_months: number | null;
    is_convertible_to_cash: boolean;
    cash_conversion_rate: number | null;
    max_convertible_days: number | null;
    min_tenure_months: number | null;
    eligible_employment_types: string[] | null;
    gender_restriction: string | null;
    gender_restriction_label: string | null;
    requires_attachment: boolean;
    requires_approval: boolean;
    max_consecutive_days: number | null;
    min_days_advance_notice: number | null;
    is_statutory: boolean;
    statutory_reference: string | null;
    is_active: boolean;
    formatted_days: string;
    formatted_eligibility: string;
}

interface EnumOption {
    value: string;
    label: string;
    shortLabel?: string;
}

const props = defineProps<{
    leaveTypes: LeaveType[];
    leaveCategories: EnumOption[];
    accrualMethods: EnumOption[];
    genderRestrictions: EnumOption[];
    employmentTypes: EnumOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Leave Types', href: '/organization/leave-types' },
];

const isFormModalOpen = ref(false);
const editingLeaveType = ref<LeaveType | null>(null);
const deletingLeaveTypeId = ref<number | null>(null);

// Seed statutory dialog state
const isSeedDialogOpen = ref(false);
const isSeeding = ref(false);
const seedResult = ref<{ message: string; count: number } | null>(null);

function getCategoryBadgeClasses(category: string): string {
    switch (category) {
        case 'statutory':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'company':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'special':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getStatusBadgeClasses(isActive: boolean): string {
    if (isActive) {
        return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
    }
    return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
}

function handleAddLeaveType() {
    editingLeaveType.value = null;
    isFormModalOpen.value = true;
}

function handleEditLeaveType(leaveType: LeaveType) {
    editingLeaveType.value = leaveType;
    isFormModalOpen.value = true;
}

async function handleDeleteLeaveType(leaveType: LeaveType) {
    if (
        !confirm(
            `Are you sure you want to delete the leave type "${leaveType.name}"?`,
        )
    ) {
        return;
    }

    deletingLeaveTypeId.value = leaveType.id;

    try {
        const response = await fetch(
            `/api/organization/leave-types/${leaveType.id}`,
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
            router.reload({ only: ['leaveTypes'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete leave type');
        }
    } catch (error) {
        alert('An error occurred while deleting the leave type');
    } finally {
        deletingLeaveTypeId.value = null;
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingLeaveType.value = null;
    router.reload({ only: ['leaveTypes'] });
}

function openSeedDialog() {
    seedResult.value = null;
    isSeedDialogOpen.value = true;
}

async function handleSeedStatutory() {
    isSeeding.value = true;
    seedResult.value = null;

    try {
        const response = await fetch(
            '/api/organization/leave-types/seed-statutory',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        const data = await response.json();

        if (response.ok) {
            seedResult.value = {
                message: data.message,
                count: data.count,
            };
            router.reload({ only: ['leaveTypes'] });
        } else {
            alert(data.message || 'Failed to seed statutory leaves');
            isSeedDialogOpen.value = false;
        }
    } catch (error) {
        alert('An error occurred while seeding statutory leaves');
        isSeedDialogOpen.value = false;
    } finally {
        isSeeding.value = false;
    }
}

function closeSeedDialog() {
    isSeedDialogOpen.value = false;
    seedResult.value = null;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Head :title="`Leave Types - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Leave Types
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Configure leave types including statutory and company
                        leaves.
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Button
                        variant="outline"
                        @click="openSeedDialog"
                        class="shrink-0"
                        data-test="seed-statutory-button"
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
                                d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z"
                            />
                        </svg>
                        Seed PH Statutory
                    </Button>
                    <Button
                        @click="handleAddLeaveType"
                        class="shrink-0"
                        :style="{ backgroundColor: primaryColor }"
                        data-test="add-leave-type-button"
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
                        Add Leave Type
                    </Button>
                </div>
            </div>

            <!-- Leave Types Table -->
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
                                    Leave Type
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Category
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Entitlement
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Accrual
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
                                v-for="leaveType in leaveTypes"
                                :key="leaveType.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                :data-test="`leave-type-row-${leaveType.id}`"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ leaveType.name }}
                                    </div>
                                    <div
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ leaveType.code }}
                                        <span
                                            v-if="leaveType.statutory_reference"
                                            class="ml-1"
                                        >
                                            ({{ leaveType.statutory_reference }})
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            getCategoryBadgeClasses(
                                                leaveType.leave_category,
                                            )
                                        "
                                        data-test="leave-category-badge"
                                    >
                                        {{ leaveType.leave_category_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ leaveType.formatted_days }}
                                    </div>
                                    <div
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ leaveType.formatted_eligibility }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="text-sm text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            leaveType.accrual_method_short_label
                                        }}
                                    </div>
                                    <div
                                        v-if="leaveType.allow_carry_over"
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Carry-over allowed
                                    </div>
                                    <div
                                        v-if="leaveType.is_convertible_to_cash"
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Cash convertible
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            getStatusBadgeClasses(
                                                leaveType.is_active,
                                            )
                                        "
                                        data-test="leave-status-badge"
                                    >
                                        {{
                                            leaveType.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
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
                                                @click="
                                                    handleEditLeaveType(
                                                        leaveType,
                                                    )
                                                "
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
                                                    deletingLeaveTypeId ===
                                                    leaveType.id
                                                "
                                                @click="
                                                    handleDeleteLeaveType(
                                                        leaveType,
                                                    )
                                                "
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
                        v-for="leaveType in leaveTypes"
                        :key="leaveType.id"
                        class="p-4"
                        :data-test="`leave-type-card-${leaveType.id}`"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ leaveType.name }}
                                </div>
                                <div
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ leaveType.code }} -
                                    {{ leaveType.formatted_days }}
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
                                    <DropdownMenuLabel
                                        >Actions</DropdownMenuLabel
                                    >
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        @click="handleEditLeaveType(leaveType)"
                                    >
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                        @click="
                                            handleDeleteLeaveType(leaveType)
                                        "
                                    >
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <div
                            v-if="leaveType.description"
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ leaveType.description }}
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="
                                    getCategoryBadgeClasses(
                                        leaveType.leave_category,
                                    )
                                "
                            >
                                {{ leaveType.leave_category_label }}
                            </span>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    getStatusBadgeClasses(leaveType.is_active)
                                "
                            >
                                {{
                                    leaveType.is_active ? 'Active' : 'Inactive'
                                }}
                            </span>
                            <span
                                v-if="leaveType.allow_carry_over"
                                class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700/50 dark:text-slate-300"
                            >
                                Carry-over
                            </span>
                            <span
                                v-if="leaveType.is_convertible_to_cash"
                                class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700/50 dark:text-slate-300"
                            >
                                Cash
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="leaveTypes.length === 0"
                    class="px-6 py-12 text-center"
                    data-test="empty-state"
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
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No leave types found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Get started by adding a new leave type or seeding
                        Philippine statutory leaves.
                    </p>
                    <div class="mt-6 flex justify-center gap-3">
                        <Button variant="outline" @click="openSeedDialog">
                            Seed PH Statutory
                        </Button>
                        <Button
                            @click="handleAddLeaveType"
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
                            Add Leave Type
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leave Type Form Modal -->
        <LeaveTypeFormModal
            v-model:open="isFormModalOpen"
            :leave-type="editingLeaveType"
            :leave-categories="leaveCategories"
            :accrual-methods="accrualMethods"
            :gender-restrictions="genderRestrictions"
            :employment-types="employmentTypes"
            @success="handleFormSuccess"
        />

        <!-- Seed Statutory Confirmation Dialog -->
        <Dialog v-model:open="isSeedDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            seedResult
                                ? 'Statutory Leaves Seeded'
                                : 'Seed Philippine Statutory Leaves'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="seedResult">
                            {{ seedResult.message }}
                        </template>
                        <template v-else>
                            This will create the standard Philippine statutory
                            leave types: SIL, Maternity, Paternity, Solo Parent,
                            VAWC, and Special Leave for Women.
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <template v-if="seedResult">
                        <Button @click="closeSeedDialog"> Done </Button>
                    </template>
                    <template v-else>
                        <Button
                            variant="outline"
                            @click="closeSeedDialog"
                            :disabled="isSeeding"
                        >
                            Cancel
                        </Button>
                        <Button
                            @click="handleSeedStatutory"
                            :disabled="isSeeding"
                            data-test="confirm-seed-button"
                        >
                            <svg
                                v-if="isSeeding"
                                class="mr-2 h-4 w-4 animate-spin"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                />
                            </svg>
                            {{ isSeeding ? 'Seeding...' : 'Seed Leaves' }}
                        </Button>
                    </template>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
