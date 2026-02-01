<script setup lang="ts">
import ProficiencyLevelBadge from '@/components/ProficiencyLevelBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { CompetencyEvaluation, ProficiencyLevel } from '@/types/competency';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface PerformanceInstance {
    id: number;
    name: string;
    cycle_name: string | null;
    year: number;
}

interface Participant {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_code: string | null;
    position_name: string | null;
    status: string;
}

interface Filters {
    instance_id: number | null;
    participant_id: number | null;
}

const props = defineProps<{
    instances: PerformanceInstance[];
    participants: Participant[];
    evaluations: CompetencyEvaluation[];
    proficiencyLevels: ProficiencyLevel[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Performance', href: '/performance/kpis' },
    { title: 'Competency Evaluations', href: '/performance/competency-evaluations' },
];

const selectedInstanceId = ref(
    props.filters.instance_id ? String(props.filters.instance_id) : '',
);
const selectedParticipantId = ref(
    props.filters.participant_id ? String(props.filters.participant_id) : '',
);

const instancesData = computed(() => props.instances ?? []);
const participantsData = computed(() => props.participants ?? []);
const evaluationsData = computed(() => props.evaluations ?? []);

const instanceOptions = computed(() => {
    return instancesData.value.map((instance) => ({
        value: String(instance.id),
        label: `${instance.name} (${instance.year})`,
    }));
});

const participantOptions = computed(() => {
    return participantsData.value.map((participant) => ({
        value: String(participant.id),
        label: `${participant.employee_name} - ${participant.position_name || 'No Position'}`,
        status: participant.status,
    }));
});

const selectedParticipant = computed(() => {
    if (!selectedParticipantId.value) return null;
    return participantsData.value.find(
        (p) => String(p.id) === selectedParticipantId.value,
    );
});

const evaluationSummary = computed(() => {
    const evals = evaluationsData.value;
    return {
        total: evals.length,
        withSelfRating: evals.filter((e) => e.self_rating !== null).length,
        withManagerRating: evals.filter((e) => e.manager_rating !== null).length,
        completed: evals.filter((e) => e.is_complete).length,
    };
});

function handleInstanceChange(instanceId: string) {
    selectedInstanceId.value = instanceId;
    selectedParticipantId.value = '';
    router.get(
        '/performance/competency-evaluations',
        { instance_id: instanceId },
        { preserveState: true },
    );
}

function handleParticipantChange(participantId: string) {
    selectedParticipantId.value = participantId;
    router.get(
        '/performance/competency-evaluations',
        {
            instance_id: selectedInstanceId.value,
            participant_id: participantId,
        },
        { preserveState: true },
    );
}

function getStatusBadgeClasses(status: string): string {
    switch (status) {
        case 'pending':
            return 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300';
        case 'in_progress':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'completed':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getCompletionPercentage(evaluation: CompetencyEvaluation): number {
    let completed = 0;
    if (evaluation.self_rating) completed++;
    if (evaluation.manager_rating) completed++;
    if (evaluation.final_rating) completed++;
    return Math.round((completed / 3) * 100);
}
</script>

<template>
    <Head :title="`Competency Evaluations - ${tenantName}`" />

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
                        Competency Evaluations
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Evaluate employee competencies based on their position requirements.
                    </p>
                </div>
            </div>

            <!-- Filters -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            Performance Cycle Instance
                        </label>
                        <Select
                            :model-value="selectedInstanceId"
                            @update:model-value="handleInstanceChange"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select an instance..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in instanceOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="participantOptions.length > 0" class="flex-1">
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            Employee
                        </label>
                        <Select
                            :model-value="selectedParticipantId"
                            @update:model-value="handleParticipantChange"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select an employee..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in participantOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>{{ option.label }}</span>
                                        <span
                                            class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium"
                                            :class="getStatusBadgeClasses(option.status)"
                                        >
                                            {{ option.status }}
                                        </span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="selectedParticipant">
                        <Link
                            :href="`/performance/competency-evaluations/participants/${selectedParticipant.id}`"
                        >
                            <Button :style="{ backgroundColor: primaryColor }">
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
                                        d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"
                                    />
                                </svg>
                                Start Evaluation
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Empty State - No Instance Selected -->
            <div
                v-if="!selectedInstanceId"
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
                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
                    />
                </svg>
                <h3
                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    Select a Performance Cycle Instance
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Choose a performance cycle instance to view and evaluate employee competencies.
                </p>
            </div>

            <!-- Empty State - No Participants -->
            <div
                v-else-if="participantOptions.length === 0"
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
                        d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                    />
                </svg>
                <h3
                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    No Participants Found
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    This performance cycle instance has no active participants.
                </p>
            </div>

            <!-- Participant Summary and Evaluations Preview -->
            <div
                v-else-if="selectedParticipant && evaluationsData.length > 0"
                class="flex flex-col gap-6"
            >
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Total Competencies
                        </p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">
                            {{ evaluationSummary.total }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Self Ratings
                        </p>
                        <p class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">
                            {{ evaluationSummary.withSelfRating }} / {{ evaluationSummary.total }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Manager Ratings
                        </p>
                        <p class="mt-1 text-2xl font-semibold text-violet-600 dark:text-violet-400">
                            {{ evaluationSummary.withManagerRating }} / {{ evaluationSummary.total }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Completed
                        </p>
                        <p class="mt-1 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">
                            {{ evaluationSummary.completed }} / {{ evaluationSummary.total }}
                        </p>
                    </div>
                </div>

                <!-- Evaluations Preview Table -->
                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Competency Evaluations
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <thead class="bg-slate-50 dark:bg-slate-800/50">
                                <tr>
                                    <th
                                        scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Competency
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-6 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Required
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-6 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Self
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-6 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Manager
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-6 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Final
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-6 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Progress
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-200 dark:divide-slate-700"
                            >
                                <tr
                                    v-for="evaluation in evaluationsData"
                                    :key="evaluation.id"
                                    class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                >
                                    <td class="px-6 py-4">
                                        <div
                                            class="font-medium text-slate-900 dark:text-slate-100"
                                        >
                                            {{ evaluation.position_competency?.competency?.name || 'Unknown' }}
                                        </div>
                                        <div
                                            class="text-sm text-slate-500 dark:text-slate-400"
                                        >
                                            {{ evaluation.position_competency?.competency?.category_label }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <ProficiencyLevelBadge
                                            v-if="evaluation.position_competency?.required_proficiency_level"
                                            :level="evaluation.position_competency.required_proficiency_level"
                                            show-level
                                        />
                                        <span v-else class="text-sm text-slate-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <ProficiencyLevelBadge
                                            v-if="evaluation.self_rating"
                                            :level="evaluation.self_rating"
                                            show-level
                                        />
                                        <span v-else class="text-sm text-slate-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <ProficiencyLevelBadge
                                            v-if="evaluation.manager_rating"
                                            :level="evaluation.manager_rating"
                                            show-level
                                        />
                                        <span v-else class="text-sm text-slate-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <ProficiencyLevelBadge
                                            v-if="evaluation.final_rating"
                                            :level="evaluation.final_rating"
                                            show-level
                                        />
                                        <span v-else class="text-sm text-slate-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <div
                                                class="h-2 w-16 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
                                            >
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    :class="
                                                        evaluation.is_complete
                                                            ? 'bg-emerald-500'
                                                            : 'bg-blue-500'
                                                    "
                                                    :style="{
                                                        width: `${getCompletionPercentage(evaluation)}%`,
                                                    }"
                                                />
                                            </div>
                                            <span
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{ getCompletionPercentage(evaluation) }}%
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="evaluationsData.length === 0"
                        class="px-6 py-12 text-center"
                    >
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            No competency evaluations found for this participant.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Participants List -->
            <div
                v-else-if="!selectedParticipant && participantOptions.length > 0"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Participants ({{ participantsData.length }})
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Select an employee to view or perform competency evaluations.
                    </p>
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-700">
                    <div
                        v-for="participant in participantsData"
                        :key="participant.id"
                        class="flex items-center justify-between px-6 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/50"
                    >
                        <div>
                            <p
                                class="font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ participant.employee_name }}
                            </p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ participant.position_name || 'No Position' }}
                                <span v-if="participant.employee_code" class="ml-2">
                                    ({{ participant.employee_code }})
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                :class="getStatusBadgeClasses(participant.status)"
                            >
                                {{ participant.status }}
                            </span>
                            <Link
                                :href="`/performance/competency-evaluations/participants/${participant.id}`"
                            >
                                <Button variant="outline" size="sm">
                                    Evaluate
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
