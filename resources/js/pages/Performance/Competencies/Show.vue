<script setup lang="ts">
import CompetencyRatingCard from '@/components/CompetencyRatingCard.vue';
import ProficiencyLevelBadge from '@/components/ProficiencyLevelBadge.vue';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { ProficiencyLevel } from '@/types/competency';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Competency {
    id: number;
    name: string;
    code: string;
    description: string | null;
    category: string | null;
    category_label: string | null;
}

interface EvaluationItem {
    id: number | null;
    position_competency_id: number;
    performance_cycle_participant_id: number;
    competency: Competency;
    required_proficiency_level: number;
    required_proficiency_name: string | null;
    job_level: string;
    job_level_label: string;
    is_mandatory: boolean;
    weight: number;
    self_rating: number | null;
    self_comments: string | null;
    manager_rating: number | null;
    manager_comments: string | null;
    final_rating: number | null;
    evidence: string[];
    evaluated_at: string | null;
    is_complete: boolean;
}

interface Participant {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_code: string | null;
    position_name: string | null;
    job_level: string | null;
    job_level_label: string | null;
    status: string;
    instance_name: string | null;
    cycle_name: string | null;
    year: number | null;
}

interface Summary {
    total: number;
    completed: number;
    with_self_rating: number;
    with_manager_rating: number;
}

const props = defineProps<{
    participant: Participant;
    evaluationData: EvaluationItem[];
    proficiencyLevels: ProficiencyLevel[];
    summary: Summary;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Performance', href: '/performance/kpis' },
    { title: 'Competency Evaluations', href: '/performance/competency-evaluations' },
    { title: props.participant.employee_name, href: '#' },
];

const evaluations = computed(() => props.evaluationData ?? []);

const groupedEvaluations = computed(() => {
    const groups: Record<string, EvaluationItem[]> = {};
    for (const evaluation of evaluations.value) {
        const category = evaluation.competency.category_label || 'Uncategorized';
        if (!groups[category]) {
            groups[category] = [];
        }
        groups[category].push(evaluation);
    }
    return groups;
});

const completionPercentage = computed(() => {
    if (props.summary.total === 0) return 0;
    return Math.round((props.summary.completed / props.summary.total) * 100);
});

const selfRatingPercentage = computed(() => {
    if (props.summary.total === 0) return 0;
    return Math.round((props.summary.with_self_rating / props.summary.total) * 100);
});

const managerRatingPercentage = computed(() => {
    if (props.summary.total === 0) return 0;
    return Math.round((props.summary.with_manager_rating / props.summary.total) * 100);
});

const expandedCategories = ref<Set<string>>(new Set(Object.keys(groupedEvaluations.value)));

function toggleCategory(category: string) {
    if (expandedCategories.value.has(category)) {
        expandedCategories.value.delete(category);
    } else {
        expandedCategories.value.add(category);
    }
}

function handleEvaluationUpdated() {
    router.reload({ only: ['evaluationData', 'summary'] });
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
</script>

<template>
    <Head :title="`${participant.employee_name} - Competency Evaluation - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Header with Back Button -->
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <Link :href="`//performance/competency-evaluations`">
                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
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
                                        d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"
                                    />
                                </svg>
                            </Button>
                        </Link>
                        <div>
                            <h1
                                class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                            >
                                {{ participant.employee_name }}
                            </h1>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400"
                            >
                                <span>{{ participant.position_name || 'No Position' }}</span>
                                <span v-if="participant.job_level_label" class="text-slate-300 dark:text-slate-600">
                                    |
                                </span>
                                <span v-if="participant.job_level_label">
                                    {{ participant.job_level_label }}
                                </span>
                                <span v-if="participant.employee_code" class="text-slate-300 dark:text-slate-600">
                                    |
                                </span>
                                <span v-if="participant.employee_code">
                                    {{ participant.employee_code }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <span
                    class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium"
                    :class="getStatusBadgeClasses(participant.status)"
                >
                    {{ participant.status }}
                </span>
            </div>

            <!-- Performance Cycle Info -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="flex flex-wrap items-center gap-6">
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Performance Cycle
                        </p>
                        <p class="font-medium text-slate-900 dark:text-slate-100">
                            {{ participant.cycle_name || 'Unknown Cycle' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Instance
                        </p>
                        <p class="font-medium text-slate-900 dark:text-slate-100">
                            {{ participant.instance_name }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Year
                        </p>
                        <p class="font-medium text-slate-900 dark:text-slate-100">
                            {{ participant.year }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Progress Summary -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                Self Rating Progress
                            </p>
                            <p class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">
                                {{ summary.with_self_rating }} / {{ summary.total }}
                            </p>
                        </div>
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30"
                        >
                            <svg
                                class="h-6 w-6 text-blue-600 dark:text-blue-400"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                                />
                            </svg>
                        </div>
                    </div>
                    <Progress :model-value="selfRatingPercentage" class="mt-3 h-2" />
                </div>

                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                Manager Rating Progress
                            </p>
                            <p class="mt-1 text-2xl font-semibold text-violet-600 dark:text-violet-400">
                                {{ summary.with_manager_rating }} / {{ summary.total }}
                            </p>
                        </div>
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-violet-100 dark:bg-violet-900/30"
                        >
                            <svg
                                class="h-6 w-6 text-violet-600 dark:text-violet-400"
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
                        </div>
                    </div>
                    <Progress :model-value="managerRatingPercentage" class="mt-3 h-2" />
                </div>

                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                Completed Evaluations
                            </p>
                            <p class="mt-1 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ summary.completed }} / {{ summary.total }}
                            </p>
                        </div>
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30"
                        >
                            <svg
                                class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                />
                            </svg>
                        </div>
                    </div>
                    <Progress :model-value="completionPercentage" class="mt-3 h-2" />
                </div>
            </div>

            <!-- Competency Evaluations by Category -->
            <div v-if="evaluations.length > 0" class="flex flex-col gap-4">
                <div
                    v-for="(categoryEvaluations, category) in groupedEvaluations"
                    :key="category"
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                >
                    <!-- Category Header -->
                    <button
                        type="button"
                        class="flex w-full items-center justify-between border-b border-slate-200 px-6 py-4 text-left hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/50"
                        @click="toggleCategory(category)"
                    >
                        <div class="flex items-center gap-3">
                            <h3
                                class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{ category }}
                            </h3>
                            <span
                                class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                            >
                                {{ categoryEvaluations.length }}
                            </span>
                        </div>
                        <svg
                            class="h-5 w-5 text-slate-400 transition-transform"
                            :class="{ 'rotate-180': expandedCategories.has(category) }"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m19.5 8.25-7.5 7.5-7.5-7.5"
                            />
                        </svg>
                    </button>

                    <!-- Category Content -->
                    <div
                        v-if="expandedCategories.has(category)"
                        class="divide-y divide-slate-200 dark:divide-slate-700"
                    >
                        <CompetencyRatingCard
                            v-for="evaluation in categoryEvaluations"
                            :key="evaluation.position_competency_id"
                            :evaluation="evaluation"
                            :proficiency-levels="proficiencyLevels"
                            :participant-id="participant.id"
                            @updated="handleEvaluationUpdated"
                        />
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
                        d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"
                    />
                </svg>
                <h3
                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    No Competencies Assigned
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    This employee's position has no competency requirements defined.
                    Please assign competencies to the position in the Competency Matrix.
                </p>
                <div class="mt-6">
                    <Link :href="`//organization/competency-matrix`">
                        <Button :style="{ backgroundColor: primaryColor }">
                            Go to Competency Matrix
                        </Button>
                    </Link>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
