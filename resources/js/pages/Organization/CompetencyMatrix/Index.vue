<script setup lang="ts">
import PositionCompetencyModal from '@/Components/PositionCompetencyModal.vue';
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
    Position,
    PositionCompetency,
    ProficiencyLevel,
    CategoryOption,
    JobLevelOption,
} from '@/types/competency';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    positions: Position[];
    competencies: Competency[];
    positionCompetencies: PositionCompetency[];
    proficiencyLevels: ProficiencyLevel[];
    categories: CategoryOption[];
    jobLevels: JobLevelOption[];
    filters: {
        position_id: number | null;
        job_level: string | null;
        category: string | null;
    };
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Competency Matrix', href: '/organization/competency-matrix' },
];

const isModalOpen = ref(false);
const editingAssignment = ref<PositionCompetency | null>(null);
const selectedPositionId = ref<number | null>(null);
const selectedJobLevel = ref<string | null>(null);
const deletingAssignmentId = ref<number | null>(null);

// Filters
const positionFilter = ref<string>(props.filters.position_id?.toString() || '');
const jobLevelFilter = ref<string>(props.filters.job_level || '');
const categoryFilter = ref<string>(props.filters.category || '');

const positionOptions = computed(() => {
    return [
        { value: '', label: 'All Positions' },
        ...props.positions.map((pos) => ({
            value: pos.id.toString(),
            label: `${pos.title} (${pos.code})`,
        })),
    ];
});

const jobLevelOptions = computed(() => {
    return [
        { value: '', label: 'All Job Levels' },
        ...props.jobLevels.map((level) => ({
            value: level.value,
            label: level.label,
        })),
    ];
});

const categoryOptions = computed(() => {
    return [
        { value: '', label: 'All Categories' },
        ...props.categories.map((cat) => ({
            value: cat.value,
            label: cat.label,
        })),
    ];
});

// Group position competencies by position and job level
const groupedCompetencies = computed(() => {
    const grouped: Record<
        string,
        {
            position: Position;
            jobLevel: string;
            jobLevelLabel: string;
            assignments: PositionCompetency[];
        }
    > = {};

    for (const pc of props.positionCompetencies) {
        const key = `${pc.position_id}-${pc.job_level}`;
        if (!grouped[key]) {
            grouped[key] = {
                position: pc.position!,
                jobLevel: pc.job_level,
                jobLevelLabel: pc.job_level_label,
                assignments: [],
            };
        }
        grouped[key].assignments.push(pc);
    }

    return Object.values(grouped);
});

function applyFilters() {
    const params: Record<string, string> = {};

    if (positionFilter.value) {
        params.position_id = positionFilter.value;
    }
    if (jobLevelFilter.value) {
        params.job_level = jobLevelFilter.value;
    }
    if (categoryFilter.value) {
        params.category = categoryFilter.value;
    }

    router.get('/organization/competency-matrix', params, {
        preserveState: true,
        replace: true,
    });
}

function handleAddAssignment() {
    editingAssignment.value = null;
    selectedPositionId.value = positionFilter.value
        ? parseInt(positionFilter.value)
        : null;
    selectedJobLevel.value = jobLevelFilter.value || null;
    isModalOpen.value = true;
}

function handleEditAssignment(assignment: PositionCompetency) {
    editingAssignment.value = assignment;
    selectedPositionId.value = assignment.position_id;
    selectedJobLevel.value = assignment.job_level;
    isModalOpen.value = true;
}

async function handleDeleteAssignment(assignment: PositionCompetency) {
    if (
        !confirm(
            `Are you sure you want to remove "${assignment.competency?.name}" from this position?`,
        )
    ) {
        return;
    }

    deletingAssignmentId.value = assignment.id;

    try {
        const response = await fetch(
            `/api/performance/position-competencies/${assignment.id}`,
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
            router.reload({ only: ['positionCompetencies'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete assignment');
        }
    } catch (error) {
        alert('An error occurred while deleting the assignment');
    } finally {
        deletingAssignmentId.value = null;
    }
}

function handleFormSuccess() {
    isModalOpen.value = false;
    editingAssignment.value = null;
    router.reload({ only: ['positionCompetencies'] });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
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
</script>

<template>
    <Head :title="`Competency Matrix - ${tenantName}`" />

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
                        Competency Matrix
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Map competencies to positions with proficiency requirements by job level.
                    </p>
                </div>
                <Button
                    @click="handleAddAssignment"
                    class="shrink-0"
                    :style="{ backgroundColor: primaryColor }"
                    data-test="add-assignment-button"
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
                    Add Assignment
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="w-full sm:w-56">
                    <EnumSelect
                        v-model="positionFilter"
                        :options="positionOptions"
                        placeholder="All Positions"
                        @update:model-value="applyFilters"
                    />
                </div>
                <div class="w-full sm:w-44">
                    <EnumSelect
                        v-model="jobLevelFilter"
                        :options="jobLevelOptions"
                        placeholder="All Job Levels"
                        @update:model-value="applyFilters"
                    />
                </div>
                <div class="w-full sm:w-44">
                    <EnumSelect
                        v-model="categoryFilter"
                        :options="categoryOptions"
                        placeholder="All Categories"
                        @update:model-value="applyFilters"
                    />
                </div>
            </div>

            <!-- Matrix Content -->
            <div
                v-if="groupedCompetencies.length > 0"
                class="space-y-6"
            >
                <!-- Position Group -->
                <div
                    v-for="group in groupedCompetencies"
                    :key="`${group.position.id}-${group.jobLevel}`"
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                >
                    <!-- Group Header -->
                    <div
                        class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4 dark:border-slate-700 dark:bg-slate-800/50"
                    >
                        <div>
                            <h3
                                class="font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{ group.position.title }}
                            </h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ group.position.code }} &middot;
                                <span class="font-medium">{{ group.jobLevelLabel }}</span>
                            </p>
                        </div>
                        <div class="text-sm text-slate-500 dark:text-slate-400">
                            {{ group.assignments.length }} competenc{{
                                group.assignments.length === 1 ? 'y' : 'ies'
                            }}
                        </div>
                    </div>

                    <!-- Competency Cards -->
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div
                            v-for="assignment in group.assignments"
                            :key="assignment.id"
                            class="flex items-center justify-between px-6 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/30"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ assignment.competency?.name }}
                                    </span>
                                    <span
                                        v-if="assignment.competency?.category_label"
                                        class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium"
                                        :class="
                                            getCategoryBadgeClasses(
                                                assignment.competency?.category,
                                            )
                                        "
                                    >
                                        {{ assignment.competency?.category_label }}
                                    </span>
                                    <span
                                        v-if="!assignment.is_mandatory"
                                        class="text-xs text-slate-400"
                                    >
                                        (Optional)
                                    </span>
                                </div>
                                <p
                                    class="mt-0.5 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ assignment.competency?.code }}
                                </p>
                            </div>

                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        Required Level
                                    </div>
                                    <ProficiencyLevelBadge
                                        :level="assignment.required_proficiency_level"
                                        :name="assignment.proficiency_level?.name"
                                        show-level
                                        size="md"
                                    />
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
                                            @click="handleEditAssignment(assignment)"
                                        >
                                            Edit
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                            :disabled="deletingAssignmentId === assignment.id"
                                            @click="handleDeleteAssignment(assignment)"
                                        >
                                            Remove
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
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
                        d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"
                    />
                </svg>
                <h3
                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    No competency assignments found
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{
                        positionCompetencies.length === 0
                            ? 'Get started by assigning competencies to positions.'
                            : 'Try adjusting your filters.'
                    }}
                </p>
                <div v-if="positionCompetencies.length === 0" class="mt-6">
                    <Button
                        @click="handleAddAssignment"
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
                        Add Assignment
                    </Button>
                </div>
            </div>
        </div>

        <!-- Position Competency Modal -->
        <PositionCompetencyModal
            v-model:open="isModalOpen"
            :assignment="editingAssignment"
            :positions="positions"
            :competencies="competencies"
            :proficiency-levels="proficiencyLevels"
            :job-levels="jobLevels"
            :initial-position-id="selectedPositionId"
            :initial-job-level="selectedJobLevel"
            @success="handleFormSuccess"
        />
    </TenantLayout>
</template>
