<script setup lang="ts">
import CompetencyFormModal from '@/Components/CompetencyFormModal.vue';
import ProficiencyLevelBadge from '@/Components/ProficiencyLevelBadge.vue';
import EnumSelect from '@/Components/EnumSelect.vue';
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
import type {
    Competency,
    ProficiencyLevel,
    CategoryOption,
    JobLevelOption,
} from '@/types/competency';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    competencies: Competency[];
    proficiencyLevels: ProficiencyLevel[];
    categories: CategoryOption[];
    jobLevels: JobLevelOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Competencies', href: '/organization/competencies' },
];

const isFormModalOpen = ref(false);
const editingCompetency = ref<Competency | null>(null);
const deletingCompetencyId = ref<number | null>(null);

// Filters
const statusFilter = ref<string>('');
const categoryFilter = ref<string>('');
const searchQuery = ref('');

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const categoryOptions = computed(() => {
    return [
        { value: '', label: 'All Categories' },
        ...props.categories.map((cat) => ({
            value: cat.value,
            label: cat.label,
        })),
    ];
});

const filteredCompetencies = computed(() => {
    return props.competencies.filter((competency) => {
        // Status filter
        if (statusFilter.value === 'active' && !competency.is_active) {
            return false;
        }
        if (statusFilter.value === 'inactive' && competency.is_active) {
            return false;
        }

        // Category filter
        if (categoryFilter.value && competency.category !== categoryFilter.value) {
            return false;
        }

        // Search filter
        if (searchQuery.value) {
            const search = searchQuery.value.toLowerCase();
            if (
                !competency.name.toLowerCase().includes(search) &&
                !competency.code.toLowerCase().includes(search)
            ) {
                return false;
            }
        }

        return true;
    });
});

function getStatusBadgeClasses(isActive: boolean): string {
    return isActive
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        : 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
}

function getCategoryBadgeClasses(category: string | null): string {
    if (!category) {
        return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }

    switch (category) {
        case 'core':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 'technical':
            return 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300';
        case 'leadership':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case 'interpersonal':
            return 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300';
        case 'analytical':
            return 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function handleAddCompetency() {
    editingCompetency.value = null;
    isFormModalOpen.value = true;
}

function handleEditCompetency(competency: Competency) {
    editingCompetency.value = competency;
    isFormModalOpen.value = true;
}

async function handleDeleteCompetency(competency: Competency) {
    if (
        !confirm(
            `Are you sure you want to delete the competency "${competency.name}"?`,
        )
    ) {
        return;
    }

    deletingCompetencyId.value = competency.id;

    try {
        const response = await fetch(
            `/api/performance/competencies/${competency.id}`,
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
            router.reload({ only: ['competencies'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete competency');
        }
    } catch (error) {
        alert('An error occurred while deleting the competency');
    } finally {
        deletingCompetencyId.value = null;
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingCompetency.value = null;
    router.reload({ only: ['competencies'] });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Head :title="`Competencies - ${tenantName}`" />

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
                        Competencies
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage behavioral and technical competencies for performance evaluations.
                    </p>
                </div>
                <Button
                    @click="handleAddCompetency"
                    class="shrink-0"
                    :style="{ backgroundColor: primaryColor }"
                    data-test="add-competency-button"
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
                    Add Competency
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="w-full sm:w-64">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by name or code..."
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500"
                    />
                </div>
                <div class="w-full sm:w-40">
                    <EnumSelect
                        v-model="statusFilter"
                        :options="statusOptions"
                        placeholder="All Statuses"
                    />
                </div>
                <div class="w-full sm:w-48">
                    <EnumSelect
                        v-model="categoryFilter"
                        :options="categoryOptions"
                        placeholder="All Categories"
                    />
                </div>
            </div>

            <!-- Competencies Table -->
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
                                    Name
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
                                    Assignments
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
                                v-for="competency in filteredCompetencies"
                                :key="competency.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                :data-test="`competency-row-${competency.id}`"
                            >
                                <td
                                    class="px-6 py-4 text-sm font-medium whitespace-nowrap text-slate-900 dark:text-slate-100"
                                >
                                    {{ competency.code }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ competency.name }}
                                    </div>
                                    <div
                                        v-if="competency.description"
                                        class="max-w-xs truncate text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ competency.description }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        v-if="competency.category_label"
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getCategoryBadgeClasses(competency.category)"
                                    >
                                        {{ competency.category_label }}
                                    </span>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400"
                                >
                                    {{ competency.position_competencies_count ?? 0 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(competency.is_active)"
                                    >
                                        {{ competency.is_active ? 'Active' : 'Inactive' }}
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
                                                @click="handleEditCompetency(competency)"
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
                                                :disabled="deletingCompetencyId === competency.id"
                                                @click="handleDeleteCompetency(competency)"
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
                        v-for="competency in filteredCompetencies"
                        :key="competency.id"
                        class="p-4"
                        :data-test="`competency-card-${competency.id}`"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ competency.name }}
                                </div>
                                <div
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ competency.code }}
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
                                        @click="handleEditCompetency(competency)"
                                    >
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                        @click="handleDeleteCompetency(competency)"
                                    >
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span
                                v-if="competency.category_label"
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="getCategoryBadgeClasses(competency.category)"
                            >
                                {{ competency.category_label }}
                            </span>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClasses(competency.is_active)"
                            >
                                {{ competency.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div
                            v-if="competency.description"
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ competency.description }}
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="filteredCompetencies.length === 0"
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
                            d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No competencies found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{
                            competencies.length === 0
                                ? 'Get started by adding a new competency.'
                                : 'Try adjusting your filters.'
                        }}
                    </p>
                    <div v-if="competencies.length === 0" class="mt-6">
                        <Button
                            @click="handleAddCompetency"
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
                            Add Competency
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Competency Form Modal -->
        <CompetencyFormModal
            v-model:open="isFormModalOpen"
            :competency="editingCompetency"
            :categories="categories"
            @success="handleFormSuccess"
        />
    </TenantLayout>
</template>
