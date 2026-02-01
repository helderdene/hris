<script setup lang="ts">
import HolidayFormModal from '@/components/HolidayFormModal.vue';
import TenantHolidaySettings from '@/components/TenantHolidaySettings.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { computed, ref } from 'vue';

interface WorkLocation {
    id: number;
    name: string;
    code: string;
}

interface Holiday {
    id: number;
    name: string;
    date: string;
    formatted_date: string;
    holiday_type: string;
    holiday_type_label: string;
    description: string | null;
    is_national: boolean;
    year: number;
    work_location_id: number | null;
    work_location: WorkLocation | null;
    scope_label: string;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    holidays: Holiday[];
    holidayTypes: EnumOption[];
    workLocations: WorkLocation[];
}>();

const { primaryColor, tenantName, isAdmin } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Holidays', href: '/organization/holidays' },
];

const isFormModalOpen = ref(false);
const editingHoliday = ref<Holiday | null>(null);
const deletingHolidayId = ref<number | null>(null);

// Copy to next year state
const isCopyDialogOpen = ref(false);
const isCopying = ref(false);
const copyResult = ref<{ message: string; copied_count: number } | null>(null);

// Settings collapsible state
const isSettingsOpen = ref(false);

function getHolidayTypeBadgeClasses(type: string): string {
    switch (type) {
        case 'regular':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        case 'special_non_working':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300';
        case 'special_working':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'double':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getScopeBadgeClasses(isNational: boolean): string {
    if (isNational) {
        return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
    }
    return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
}

function handleAddHoliday() {
    editingHoliday.value = null;
    isFormModalOpen.value = true;
}

function handleEditHoliday(holiday: Holiday) {
    editingHoliday.value = holiday;
    isFormModalOpen.value = true;
}

async function handleDeleteHoliday(holiday: Holiday) {
    if (
        !confirm(
            `Are you sure you want to delete the holiday "${holiday.name}"?`,
        )
    ) {
        return;
    }

    deletingHolidayId.value = holiday.id;

    try {
        const response = await fetch(
            `/api/organization/holidays/${holiday.id}`,
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
            router.reload({ only: ['holidays'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete holiday');
        }
    } catch (error) {
        alert('An error occurred while deleting the holiday');
    } finally {
        deletingHolidayId.value = null;
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingHoliday.value = null;
    router.reload({ only: ['holidays'] });
}

function openCopyDialog() {
    copyResult.value = null;
    isCopyDialogOpen.value = true;
}

async function handleCopyToNextYear() {
    isCopying.value = true;
    copyResult.value = null;

    const currentYear = new Date().getFullYear();
    const targetYear = currentYear + 1;

    try {
        const response = await fetch(
            '/api/organization/holidays/copy-to-year',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ target_year: targetYear }),
            },
        );

        const data = await response.json();

        if (response.ok) {
            copyResult.value = {
                message: data.message,
                copied_count: data.copied_count,
            };
            router.reload({ only: ['holidays'] });
        } else {
            alert(data.message || 'Failed to copy holidays');
            isCopyDialogOpen.value = false;
        }
    } catch (error) {
        alert('An error occurred while copying holidays');
        isCopyDialogOpen.value = false;
    } finally {
        isCopying.value = false;
    }
}

function closeCopyDialog() {
    isCopyDialogOpen.value = false;
    copyResult.value = null;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

const currentYear = computed(() => new Date().getFullYear());
const nextYear = computed(() => currentYear.value + 1);
</script>

<template>
    <Head :title="`Holidays - ${tenantName}`" />

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
                        Holidays
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage Philippine holidays for payroll and attendance.
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Button
                        variant="outline"
                        @click="openCopyDialog"
                        class="shrink-0"
                        data-test="copy-to-next-year-button"
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
                                d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"
                            />
                        </svg>
                        Copy to {{ nextYear }}
                    </Button>
                    <Button
                        @click="handleAddHoliday"
                        class="shrink-0"
                        :style="{ backgroundColor: primaryColor }"
                        data-test="add-holiday-button"
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
                        Add Holiday
                    </Button>
                </div>
            </div>

            <!-- Holidays Table -->
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
                                    Date
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Name
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Type
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Scope
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
                                v-for="holiday in holidays"
                                :key="holiday.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                :data-test="`holiday-row-${holiday.id}`"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ holiday.formatted_date }}
                                    </div>
                                    <div
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ holiday.year }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ holiday.name }}
                                    </div>
                                    <div
                                        v-if="holiday.description"
                                        class="max-w-xs truncate text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ holiday.description }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            getHolidayTypeBadgeClasses(
                                                holiday.holiday_type,
                                            )
                                        "
                                        data-test="holiday-type-badge"
                                    >
                                        {{ holiday.holiday_type_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            getScopeBadgeClasses(
                                                holiday.is_national,
                                            )
                                        "
                                        data-test="holiday-scope-badge"
                                    >
                                        {{ holiday.scope_label }}
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
                                                    handleEditHoliday(holiday)
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
                                                    deletingHolidayId ===
                                                    holiday.id
                                                "
                                                @click="
                                                    handleDeleteHoliday(holiday)
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
                        v-for="holiday in holidays"
                        :key="holiday.id"
                        class="p-4"
                        :data-test="`holiday-card-${holiday.id}`"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ holiday.name }}
                                </div>
                                <div
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ holiday.formatted_date }}
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
                                        @click="handleEditHoliday(holiday)"
                                    >
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                        @click="handleDeleteHoliday(holiday)"
                                    >
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <div
                            v-if="holiday.description"
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ holiday.description }}
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="
                                    getHolidayTypeBadgeClasses(
                                        holiday.holiday_type,
                                    )
                                "
                            >
                                {{ holiday.holiday_type_label }}
                            </span>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    getScopeBadgeClasses(holiday.is_national)
                                "
                            >
                                {{ holiday.scope_label }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="holidays.length === 0"
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
                        No holidays found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Get started by adding a new holiday.
                    </p>
                    <div class="mt-6">
                        <Button
                            @click="handleAddHoliday"
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
                            Add Holiday
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Holiday Pay Settings Section (Admin only) -->
            <Collapsible
                v-if="isAdmin"
                v-model:open="isSettingsOpen"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                data-test="holiday-settings-section"
            >
                <CollapsibleTrigger
                    class="flex w-full items-center justify-between px-6 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/50"
                >
                    <div class="flex items-center gap-3">
                        <svg
                            class="h-5 w-5 text-slate-500 dark:text-slate-400"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                            />
                        </svg>
                        <span
                            class="font-medium text-slate-900 dark:text-slate-100"
                        >
                            Holiday Pay Settings
                        </span>
                    </div>
                    <svg
                        class="h-5 w-5 text-slate-400 transition-transform duration-200"
                        :class="{ 'rotate-180': isSettingsOpen }"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19.5 8.25l-7.5 7.5-7.5-7.5"
                        />
                    </svg>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <div
                        class="border-t border-slate-200 px-6 py-6 dark:border-slate-700"
                    >
                        <TenantHolidaySettings />
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </div>

        <!-- Holiday Form Modal -->
        <HolidayFormModal
            v-model:open="isFormModalOpen"
            :holiday="editingHoliday"
            :holiday-types="holidayTypes"
            :work-locations="workLocations"
            @success="handleFormSuccess"
        />

        <!-- Copy to Next Year Confirmation Dialog -->
        <Dialog v-model:open="isCopyDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            copyResult
                                ? 'Holidays Copied'
                                : 'Copy Holidays to Next Year'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="copyResult">
                            {{ copyResult.message }}
                        </template>
                        <template v-else>
                            This will copy all holidays from
                            {{ currentYear }} to {{ nextYear }}. You can adjust
                            the dates after copying if needed.
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <template v-if="copyResult">
                        <Button @click="closeCopyDialog"> Done </Button>
                    </template>
                    <template v-else>
                        <Button
                            variant="outline"
                            @click="closeCopyDialog"
                            :disabled="isCopying"
                        >
                            Cancel
                        </Button>
                        <Button
                            @click="handleCopyToNextYear"
                            :disabled="isCopying"
                            data-test="confirm-copy-button"
                        >
                            <svg
                                v-if="isCopying"
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
                            {{ isCopying ? 'Copying...' : 'Copy Holidays' }}
                        </Button>
                    </template>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
