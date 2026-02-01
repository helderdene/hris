<script setup lang="ts">
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface CertificationType {
    id: number;
    name: string;
    description: string | null;
    validity_period_months: number | null;
    validity_period_formatted: string | null;
    reminder_days_before_expiry: number[] | null;
    reminder_days_formatted: string | null;
    is_mandatory: boolean;
    is_active: boolean;
    certifications_count?: number;
}

defineProps<{
    certificationTypes: CertificationType[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Certification Types', href: '/organization/certification-types' },
];

const isFormModalOpen = ref(false);
const editingType = ref<CertificationType | null>(null);
const deletingTypeId = ref<number | null>(null);
const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref({
    name: '',
    description: '',
    validity_period_months: null as number | null,
    reminder_days_before_expiry: '' as string,
    is_mandatory: false,
    is_active: true,
});

const isEditing = computed(() => !!editingType.value);

watch(
    () => editingType.value,
    (newType) => {
        if (newType) {
            form.value = {
                name: newType.name,
                description: newType.description || '',
                validity_period_months: newType.validity_period_months,
                reminder_days_before_expiry: newType.reminder_days_before_expiry
                    ? newType.reminder_days_before_expiry.join(', ')
                    : '',
                is_mandatory: newType.is_mandatory,
                is_active: newType.is_active,
            };
        } else {
            resetForm();
        }
        errors.value = {};
    },
    { immediate: true },
);

function resetForm(): void {
    form.value = {
        name: '',
        description: '',
        validity_period_months: null,
        reminder_days_before_expiry: '',
        is_mandatory: false,
        is_active: true,
    };
    errors.value = {};
}

function getStatusBadgeClasses(isActive: boolean): string {
    if (isActive) {
        return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
    }
    return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
}

function getMandatoryBadgeClasses(isMandatory: boolean): string {
    if (isMandatory) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
    }
    return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
}

function handleAddType(): void {
    editingType.value = null;
    isFormModalOpen.value = true;
}

function handleEditType(type: CertificationType): void {
    editingType.value = type;
    isFormModalOpen.value = true;
}

async function handleDeleteType(type: CertificationType): Promise<void> {
    if (
        !confirm(
            `Are you sure you want to delete the certification type "${type.name}"?`,
        )
    ) {
        return;
    }

    deletingTypeId.value = type.id;

    try {
        const response = await fetch(`/api/certification-types/${type.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            router.reload({ only: ['certificationTypes'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete certification type');
        }
    } catch {
        alert('An error occurred while deleting the certification type');
    } finally {
        deletingTypeId.value = null;
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit(): Promise<void> {
    errors.value = {};
    isSubmitting.value = true;

    const url = isEditing.value
        ? `/api/certification-types/${editingType.value!.id}`
        : '/api/certification-types';

    const method = isEditing.value ? 'PUT' : 'POST';

    // Parse reminder days
    let reminderDays: number[] | null = null;
    if (form.value.reminder_days_before_expiry.trim()) {
        reminderDays = form.value.reminder_days_before_expiry
            .split(',')
            .map((s) => parseInt(s.trim(), 10))
            .filter((n) => !isNaN(n) && n > 0);
        if (reminderDays.length === 0) {
            reminderDays = null;
        }
    }

    const payload = {
        name: form.value.name,
        description: form.value.description || null,
        validity_period_months: form.value.validity_period_months,
        reminder_days_before_expiry: reminderDays,
        is_mandatory: form.value.is_mandatory,
        is_active: form.value.is_active,
    };

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (response.ok) {
            isFormModalOpen.value = false;
            editingType.value = null;
            router.reload({ only: ['certificationTypes'] });
        } else if (response.status === 422 && data.errors) {
            errors.value = Object.fromEntries(
                Object.entries(data.errors).map(([key, value]) => [
                    key,
                    (value as string[])[0],
                ]),
            );
        } else {
            errors.value = { general: data.message || 'An error occurred' };
        }
    } catch {
        errors.value = {
            general: 'An error occurred while saving the certification type',
        };
    } finally {
        isSubmitting.value = false;
    }
}

function closeModal(): void {
    isFormModalOpen.value = false;
    editingType.value = null;
    errors.value = {};
}
</script>

<template>
    <Head :title="`Certification Types - ${tenantName}`" />

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
                        Certification Types
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Configure certification types for employee credentials
                        and licenses.
                    </p>
                </div>
                <Button
                    @click="handleAddType"
                    class="shrink-0"
                    :style="{ backgroundColor: primaryColor }"
                    data-test="add-certification-type-button"
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
                    Add Certification Type
                </Button>
            </div>

            <!-- Certification Types Table -->
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
                                    Name
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
                                    Validity
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Reminders
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Mandatory
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
                                v-for="type in certificationTypes"
                                :key="type.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                :data-test="`certification-type-row-${type.id}`"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ type.name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="max-w-xs truncate text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ type.description || '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="text-sm text-slate-900 dark:text-slate-100"
                                    >
                                        {{ type.validity_period_formatted }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ type.reminder_days_formatted || '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            getMandatoryBadgeClasses(
                                                type.is_mandatory,
                                            )
                                        "
                                    >
                                        {{
                                            type.is_mandatory
                                                ? 'Mandatory'
                                                : 'Optional'
                                        }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            getStatusBadgeClasses(type.is_active)
                                        "
                                    >
                                        {{
                                            type.is_active
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
                                                @click="handleEditType(type)"
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
                                                    deletingTypeId === type.id
                                                "
                                                @click="handleDeleteType(type)"
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
                        v-for="type in certificationTypes"
                        :key="type.id"
                        class="p-4"
                        :data-test="`certification-type-card-${type.id}`"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ type.name }}
                                </div>
                                <div
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ type.validity_period_formatted }}
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
                                        @click="handleEditType(type)"
                                    >
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                        @click="handleDeleteType(type)"
                                    >
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <div
                            v-if="type.description"
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ type.description }}
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    getMandatoryBadgeClasses(type.is_mandatory)
                                "
                            >
                                {{
                                    type.is_mandatory ? 'Mandatory' : 'Optional'
                                }}
                            </span>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClasses(type.is_active)"
                            >
                                {{ type.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="certificationTypes.length === 0"
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
                            d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.746 3.746 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No certification types found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Get started by adding a new certification type.
                    </p>
                    <div class="mt-6">
                        <Button
                            @click="handleAddType"
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
                            Add Certification Type
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certification Type Form Modal -->
        <Dialog v-model:open="isFormModalOpen" @update:open="(v) => !v && closeModal()">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            isEditing
                                ? 'Edit Certification Type'
                                : 'Add Certification Type'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            isEditing
                                ? 'Update the certification type configuration below.'
                                : 'Configure a new certification type for your organization.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleSubmit" class="space-y-4">
                    <!-- General Error -->
                    <div
                        v-if="errors.general"
                        class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
                    >
                        {{ errors.general }}
                    </div>

                    <!-- Name -->
                    <div class="space-y-2">
                        <Label for="name">Name *</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="e.g., CPR Certification"
                            :class="{ 'border-red-500': errors.name }"
                            data-test="certification-type-name-input"
                        />
                        <p v-if="errors.name" class="text-sm text-red-500">
                            {{ errors.name }}
                        </p>
                    </div>

                    <!-- Description -->
                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            placeholder="Optional description"
                            rows="2"
                            data-test="certification-type-description-input"
                        />
                    </div>

                    <!-- Validity Period -->
                    <div class="space-y-2">
                        <Label for="validity_period_months"
                            >Validity Period (months)</Label
                        >
                        <Input
                            id="validity_period_months"
                            v-model.number="form.validity_period_months"
                            type="number"
                            min="1"
                            placeholder="Leave empty for no expiry"
                            :class="{
                                'border-red-500': errors.validity_period_months,
                            }"
                        />
                        <p
                            v-if="errors.validity_period_months"
                            class="text-sm text-red-500"
                        >
                            {{ errors.validity_period_months }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            e.g., 12 for 1 year, 24 for 2 years
                        </p>
                    </div>

                    <!-- Reminder Days -->
                    <div class="space-y-2">
                        <Label for="reminder_days_before_expiry"
                            >Reminder Days Before Expiry</Label
                        >
                        <Input
                            id="reminder_days_before_expiry"
                            v-model="form.reminder_days_before_expiry"
                            type="text"
                            placeholder="e.g., 30, 14, 7"
                            :class="{
                                'border-red-500':
                                    errors.reminder_days_before_expiry,
                            }"
                        />
                        <p
                            v-if="errors.reminder_days_before_expiry"
                            class="text-sm text-red-500"
                        >
                            {{ errors.reminder_days_before_expiry }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Comma-separated list of days before expiry to send
                            reminders
                        </p>
                    </div>

                    <!-- Mandatory & Active -->
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <input
                                id="is_mandatory"
                                type="checkbox"
                                v-model="form.is_mandatory"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                            />
                            <div>
                                <Label for="is_mandatory" class="cursor-pointer"
                                    >Mandatory</Label
                                >
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    All employees must have this certification
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <input
                                id="is_active"
                                type="checkbox"
                                v-model="form.is_active"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                            />
                            <Label for="is_active" class="cursor-pointer"
                                >Active</Label
                            >
                        </div>
                    </div>

                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeModal"
                            :disabled="isSubmitting"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="isSubmitting"
                            data-test="submit-certification-type-button"
                        >
                            <svg
                                v-if="isSubmitting"
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
                            {{
                                isEditing
                                    ? 'Update Certification Type'
                                    : 'Create Certification Type'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
