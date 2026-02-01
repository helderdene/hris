<script setup lang="ts">
import EnumSelect from '@/components/EnumSelect.vue';
import PositionFormModal from '@/components/PositionFormModal.vue';
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
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface SalaryGrade {
    id: number;
    name: string;
    minimum_salary: string;
    midpoint_salary: string;
    maximum_salary: string;
    currency: string;
}

interface Position {
    id: number;
    title: string;
    code: string;
    description: string | null;
    salary_grade_id: number | null;
    salary_grade: SalaryGrade | null;
    job_level: string | null;
    job_level_label: string | null;
    employment_type: string | null;
    employment_type_label: string | null;
    status: string;
    created_at: string;
    updated_at: string;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    positions: Position[];
    salaryGrades: SalaryGrade[];
    jobLevels: EnumOption[];
    employmentTypes: EnumOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Positions', href: '/organization/positions' },
];

const isFormModalOpen = ref(false);
const editingPosition = ref<Position | null>(null);
const deletingPositionId = ref<number | null>(null);

// Filters
const statusFilter = ref<string>('');
const jobLevelFilter = ref<string>('');
const salaryGradeFilter = ref<string>('');

const statusOptions: EnumOption[] = [
    { value: '', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const salaryGradeOptions = computed<EnumOption[]>(() => {
    return [
        { value: '', label: 'All Salary Grades' },
        ...(props.salaryGrades || []).map((grade) => ({
            value: grade.id.toString(),
            label: grade.name,
        })),
    ];
});

const jobLevelOptions = computed<EnumOption[]>(() => {
    return [{ value: '', label: 'All Job Levels' }, ...props.jobLevels];
});

const filteredPositions = computed(() => {
    return props.positions.filter((position) => {
        if (statusFilter.value && position.status !== statusFilter.value) {
            return false;
        }
        if (
            jobLevelFilter.value &&
            position.job_level !== jobLevelFilter.value
        ) {
            return false;
        }
        if (
            salaryGradeFilter.value &&
            position.salary_grade_id?.toString() !== salaryGradeFilter.value
        ) {
            return false;
        }
        return true;
    });
});

function getStatusBadgeClasses(status: string): string {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'inactive':
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getJobLevelBadgeClasses(level: string | null): string {
    if (!level)
        return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';

    switch (level) {
        case 'executive':
        case 'director':
            return 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300';
        case 'manager':
        case 'lead':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 'senior':
            return 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300';
        case 'mid':
            return 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300';
        case 'junior':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function handleAddPosition() {
    editingPosition.value = null;
    isFormModalOpen.value = true;
}

function handleEditPosition(position: Position) {
    editingPosition.value = position;
    isFormModalOpen.value = true;
}

async function handleDeletePosition(position: Position) {
    if (
        !confirm(
            `Are you sure you want to delete the position "${position.title}"?`,
        )
    ) {
        return;
    }

    deletingPositionId.value = position.id;

    try {
        const response = await fetch(
            `/api/organization/positions/${position.id}`,
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
            router.reload({ only: ['positions'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete position');
        }
    } catch (error) {
        alert('An error occurred while deleting the position');
    } finally {
        deletingPositionId.value = null;
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingPosition.value = null;
    router.reload({ only: ['positions'] });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function formatCurrency(
    amount: string | null,
    currency: string | null,
): string {
    if (!amount) return '-';
    const num = parseFloat(amount);
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}
</script>

<template>
    <Head :title="`Positions - ${tenantName}`" />

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
                        Positions
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage job positions and their salary grade assignments.
                    </p>
                </div>
                <Button
                    @click="handleAddPosition"
                    class="shrink-0"
                    :style="{ backgroundColor: primaryColor }"
                    data-test="add-position-button"
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
                    Add Position
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="w-full sm:w-40">
                    <EnumSelect
                        v-model="statusFilter"
                        :options="statusOptions"
                        placeholder="All Statuses"
                    />
                </div>
                <div class="w-full sm:w-48">
                    <EnumSelect
                        v-model="jobLevelFilter"
                        :options="jobLevelOptions"
                        placeholder="All Job Levels"
                    />
                </div>
                <div class="w-full sm:w-48">
                    <EnumSelect
                        v-model="salaryGradeFilter"
                        :options="salaryGradeOptions"
                        placeholder="All Salary Grades"
                    />
                </div>
            </div>

            <!-- Positions Table -->
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
                                    Code
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Title
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Job Level
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Employment Type
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Salary Grade
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
                                v-for="position in filteredPositions"
                                :key="position.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                :data-test="`position-row-${position.id}`"
                            >
                                <td
                                    class="px-6 py-4 text-sm font-medium whitespace-nowrap text-slate-900 dark:text-slate-100"
                                >
                                    {{ position.code }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ position.title }}
                                    </div>
                                    <div
                                        v-if="position.description"
                                        class="max-w-xs truncate text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ position.description }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        v-if="position.job_level_label"
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            getJobLevelBadgeClasses(
                                                position.job_level,
                                            )
                                        "
                                    >
                                        {{ position.job_level_label }}
                                    </span>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400"
                                >
                                    {{ position.employment_type_label || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        v-if="position.salary_grade"
                                        class="text-sm"
                                    >
                                        <div
                                            class="font-medium text-slate-900 dark:text-slate-100"
                                        >
                                            {{ position.salary_grade.name }}
                                        </div>
                                        <div
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                formatCurrency(
                                                    position.salary_grade
                                                        .minimum_salary,
                                                    position.salary_grade
                                                        .currency,
                                                )
                                            }}
                                            -
                                            {{
                                                formatCurrency(
                                                    position.salary_grade
                                                        .maximum_salary,
                                                    position.salary_grade
                                                        .currency,
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                        :class="
                                            getStatusBadgeClasses(
                                                position.status,
                                            )
                                        "
                                    >
                                        {{ position.status }}
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
                                                    handleEditPosition(position)
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
                                                    deletingPositionId ===
                                                    position.id
                                                "
                                                @click="
                                                    handleDeletePosition(
                                                        position,
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
                        v-for="position in filteredPositions"
                        :key="position.id"
                        class="p-4"
                        :data-test="`position-card-${position.id}`"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ position.title }}
                                </div>
                                <div
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ position.code }}
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
                                        @click="handleEditPosition(position)"
                                    >
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                        @click="handleDeletePosition(position)"
                                    >
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span
                                v-if="position.job_level_label"
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="
                                    getJobLevelBadgeClasses(position.job_level)
                                "
                            >
                                {{ position.job_level_label }}
                            </span>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                :class="getStatusBadgeClasses(position.status)"
                            >
                                {{ position.status }}
                            </span>
                        </div>
                        <div
                            v-if="position.salary_grade"
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ position.salary_grade.name }}:
                            {{
                                formatCurrency(
                                    position.salary_grade.minimum_salary,
                                    position.salary_grade.currency,
                                )
                            }}
                            -
                            {{
                                formatCurrency(
                                    position.salary_grade.maximum_salary,
                                    position.salary_grade.currency,
                                )
                            }}
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="filteredPositions.length === 0"
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
                            d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No positions found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{
                            positions.length === 0
                                ? 'Get started by adding a new position.'
                                : 'Try adjusting your filters.'
                        }}
                    </p>
                    <div v-if="positions.length === 0" class="mt-6">
                        <Button
                            @click="handleAddPosition"
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
                            Add Position
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Position Form Modal -->
        <PositionFormModal
            v-model:open="isFormModalOpen"
            :position="editingPosition"
            :salary-grades="salaryGrades"
            :job-levels="jobLevels"
            :employment-types="employmentTypes"
            @success="handleFormSuccess"
        />
    </TenantLayout>
</template>
