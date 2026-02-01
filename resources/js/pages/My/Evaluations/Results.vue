<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface CompetencyScore {
    competency_id: number;
    competency_name: string;
    competency_code: string;
    category: string;
    self_rating: number | null;
    manager_rating: number | null;
    peer_avg_rating: number | null;
    direct_report_avg_rating: number | null;
    overall_avg: number | null;
}

interface Summary {
    id: number;
    self_competency_avg: number | null;
    manager_competency_avg: number | null;
    peer_competency_avg: number | null;
    direct_report_competency_avg: number | null;
    overall_competency_avg: number | null;
    kpi_achievement_score: number | null;
    manager_kpi_rating: number | null;
    final_competency_score: number | null;
    final_kpi_score: number | null;
    final_overall_score: number | null;
    final_rating: string | null;
    calibrated_at: string | null;
    employee_acknowledged_at: string | null;
    employee_comments: string | null;
}

interface AggregatedNarrative {
    type: string;
    type_label: string;
    strengths: string[];
    areas_for_improvement: string[];
    development_suggestions: string[];
}

interface Participant {
    id: number;
    employee: {
        id: number;
        full_name: string;
        position: string | null;
        department: string | null;
    };
    instance: {
        id: number;
        name: string;
        cycle_name: string | null;
        year: number;
    };
}

const props = defineProps<{
    participant: Participant;
    summary: Summary | null;
    competency_scores: CompetencyScore[];
    aggregated_narratives: AggregatedNarrative[];
    can_acknowledge: boolean;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Dashboard', href: '/my/dashboard' },
    { title: 'My Evaluations', href: '/my/evaluations' },
    { title: 'Results', href: '#' },
];

const showAcknowledgeDialog = ref(false);
const acknowledgementComments = ref('');
const isAcknowledging = ref(false);

const isAcknowledged = computed(() => !!props.summary?.employee_acknowledged_at);

const ratingLabels: Record<string, string> = {
    exceptional: 'Exceptional',
    exceeds_expectations: 'Exceeds Expectations',
    meets_expectations: 'Meets Expectations',
    below_expectations: 'Below Expectations',
    needs_improvement: 'Needs Improvement',
};

const ratingColors: Record<string, string> = {
    exceptional: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300',
    exceeds_expectations: 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
    meets_expectations: 'bg-slate-100 text-slate-800 dark:bg-slate-900/20 dark:text-slate-300',
    below_expectations: 'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300',
    needs_improvement: 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
};

// Group competencies by category
const competenciesByCategory = computed(() => {
    const grouped: Record<string, CompetencyScore[]> = {};
    for (const c of props.competency_scores) {
        const category = c.category || 'Other';
        if (!grouped[category]) {
            grouped[category] = [];
        }
        grouped[category].push(c);
    }
    return grouped;
});

function formatScore(score: number | null): string {
    if (score === null) return '-';
    return score.toFixed(2);
}

function getScoreBarWidth(score: number | null): string {
    if (score === null) return '0%';
    return `${(score / 5) * 100}%`;
}

function getScoreBarColor(score: number | null): string {
    if (score === null) return 'bg-slate-200';
    if (score >= 4.5) return 'bg-emerald-500';
    if (score >= 3.5) return 'bg-blue-500';
    if (score >= 2.5) return 'bg-slate-400';
    if (score >= 1.5) return 'bg-amber-500';
    return 'bg-red-500';
}

function handleAcknowledge() {
    showAcknowledgeDialog.value = true;
}

function confirmAcknowledge() {
    isAcknowledging.value = true;
    router.post(
        `/api/performance/participants/${props.participant.id}/summary/acknowledge`,
        { comments: acknowledgementComments.value },
        {
            onSuccess: () => {
                showAcknowledgeDialog.value = false;
            },
            onFinish: () => {
                isAcknowledging.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="`Evaluation Results - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Evaluation Results
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ participant.instance.name }} · {{ participant.instance.year }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Button
                        v-if="can_acknowledge && !isAcknowledged"
                        :style="{ backgroundColor: primaryColor }"
                        @click="handleAcknowledge"
                    >
                        Acknowledge Results
                    </Button>
                    <Button
                        v-if="summary"
                        variant="outline"
                        as="a"
                        :href="`/my/development-plans/create?from_evaluation=${participant.id}`"
                    >
                        Create Development Plan
                    </Button>
                </div>
                <div v-if="isAcknowledged && !(can_acknowledge && !isAcknowledged)" class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="text-sm text-emerald-600 dark:text-emerald-400">
                        Acknowledged on {{ new Date(summary!.employee_acknowledged_at!).toLocaleDateString() }}
                    </span>
                </div>
            </div>

            <!-- No Results State -->
            <div v-if="!summary" class="rounded-lg border border-slate-200 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-slate-900 dark:text-slate-100">
                    Results Not Available Yet
                </h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Your evaluation results are still being processed. Please check back later.
                </p>
            </div>

            <template v-else>
                <!-- Overall Score Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Final Evaluation Score</CardTitle>
                        <CardDescription>
                            Your overall performance rating for this evaluation period.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-start">
                            <!-- Final Rating Badge -->
                            <div class="flex flex-col items-center">
                                <div
                                    v-if="summary.final_rating"
                                    class="rounded-lg px-6 py-3 text-lg font-semibold"
                                    :class="ratingColors[summary.final_rating] || 'bg-slate-100 text-slate-800'"
                                >
                                    {{ ratingLabels[summary.final_rating] || summary.final_rating }}
                                </div>
                                <div v-else class="rounded-lg bg-slate-100 px-6 py-3 text-lg font-semibold text-slate-500">
                                    Pending
                                </div>
                                <p class="mt-2 text-sm text-slate-500">Final Rating</p>
                            </div>

                            <!-- Score Breakdown -->
                            <div class="flex-1 space-y-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                                        <p class="text-sm text-slate-500 dark:text-slate-400">Overall Score</p>
                                        <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                            {{ formatScore(summary.final_overall_score) }}
                                        </p>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                                        <p class="text-sm text-slate-500 dark:text-slate-400">Competency Score</p>
                                        <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                            {{ formatScore(summary.final_competency_score) }}
                                        </p>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                                        <p class="text-sm text-slate-500 dark:text-slate-400">KPI Achievement</p>
                                        <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                            {{ summary.kpi_achievement_score !== null ? `${summary.kpi_achievement_score}%` : '-' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Competency Scores by Source -->
                <Card>
                    <CardHeader>
                        <CardTitle>Competency Scores by Source</CardTitle>
                        <CardDescription>
                            Average competency ratings from each feedback source.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Self</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="getScoreBarColor(summary.self_competency_avg)"
                                                :style="{ width: getScoreBarWidth(summary.self_competency_avg) }"
                                            />
                                        </div>
                                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                            {{ formatScore(summary.self_competency_avg) }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Manager</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="getScoreBarColor(summary.manager_competency_avg)"
                                                :style="{ width: getScoreBarWidth(summary.manager_competency_avg) }"
                                            />
                                        </div>
                                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                            {{ formatScore(summary.manager_competency_avg) }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Peers</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="getScoreBarColor(summary.peer_competency_avg)"
                                                :style="{ width: getScoreBarWidth(summary.peer_competency_avg) }"
                                            />
                                        </div>
                                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                            {{ formatScore(summary.peer_competency_avg) }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Direct Reports</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="getScoreBarColor(summary.direct_report_competency_avg)"
                                                :style="{ width: getScoreBarWidth(summary.direct_report_competency_avg) }"
                                            />
                                        </div>
                                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                            {{ formatScore(summary.direct_report_competency_avg) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Detailed Competency Scores -->
                <Card v-if="competency_scores.length > 0">
                    <CardHeader>
                        <CardTitle>Competency Details</CardTitle>
                        <CardDescription>
                            Detailed breakdown of ratings for each competency.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-for="(categoryCompetencies, category) in competenciesByCategory" :key="category" class="mb-6 last:mb-0">
                            <h4 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">
                                {{ category }}
                            </h4>
                            <div class="space-y-3">
                                <div
                                    v-for="competency in categoryCompetencies"
                                    :key="competency.competency_id"
                                    class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                                >
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ competency.competency_name }}
                                            </p>
                                            <p class="text-xs text-slate-500">{{ competency.competency_code }}</p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-4">
                                            <div class="text-center">
                                                <p class="text-xs text-slate-500">Self</p>
                                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ formatScore(competency.self_rating) }}
                                                </p>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-xs text-slate-500">Manager</p>
                                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ formatScore(competency.manager_rating) }}
                                                </p>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-xs text-slate-500">Peers</p>
                                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ formatScore(competency.peer_avg_rating) }}
                                                </p>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-xs text-slate-500">Reports</p>
                                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ formatScore(competency.direct_report_avg_rating) }}
                                                </p>
                                            </div>
                                            <div class="rounded bg-slate-100 px-3 py-1 text-center dark:bg-slate-800">
                                                <p class="text-xs text-slate-500">Overall</p>
                                                <p class="font-semibold text-slate-900 dark:text-slate-100">
                                                    {{ formatScore(competency.overall_avg) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Aggregated Narrative Feedback -->
                <Card v-if="aggregated_narratives.length > 0">
                    <CardHeader>
                        <CardTitle>Feedback Summary</CardTitle>
                        <CardDescription>
                            Aggregated feedback from all reviewers. Individual peer responses are anonymized.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-6">
                            <div v-for="narrative in aggregated_narratives" :key="narrative.type">
                                <h4 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">
                                    {{ narrative.type_label }} Feedback
                                </h4>
                                <div class="space-y-4 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                                    <div v-if="narrative.strengths.length > 0">
                                        <p class="mb-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                            Strengths
                                        </p>
                                        <ul class="list-inside list-disc space-y-1 text-sm text-slate-600 dark:text-slate-400">
                                            <li v-for="(strength, index) in narrative.strengths" :key="index">
                                                {{ strength }}
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-if="narrative.areas_for_improvement.length > 0">
                                        <p class="mb-2 text-sm font-medium text-amber-700 dark:text-amber-400">
                                            Areas for Improvement
                                        </p>
                                        <ul class="list-inside list-disc space-y-1 text-sm text-slate-600 dark:text-slate-400">
                                            <li v-for="(area, index) in narrative.areas_for_improvement" :key="index">
                                                {{ area }}
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-if="narrative.development_suggestions.length > 0">
                                        <p class="mb-2 text-sm font-medium text-blue-700 dark:text-blue-400">
                                            Development Suggestions
                                        </p>
                                        <ul class="list-inside list-disc space-y-1 text-sm text-slate-600 dark:text-slate-400">
                                            <li v-for="(suggestion, index) in narrative.development_suggestions" :key="index">
                                                {{ suggestion }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Employee Comments (if acknowledged) -->
                <Card v-if="summary.employee_comments">
                    <CardHeader>
                        <CardTitle>Your Comments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            {{ summary.employee_comments }}
                        </p>
                    </CardContent>
                </Card>
            </template>

            <!-- Acknowledge Dialog -->
            <div
                v-if="showAcknowledgeDialog"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            >
                <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6 dark:bg-slate-900">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Acknowledge Evaluation Results
                    </h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        By acknowledging, you confirm that you have reviewed your evaluation results.
                        You may add any comments or feedback below (optional).
                    </p>
                    <Textarea
                        v-model="acknowledgementComments"
                        class="mt-4"
                        placeholder="Add any comments or feedback (optional)..."
                        rows="4"
                    />
                    <div class="mt-6 flex justify-end gap-3">
                        <Button variant="outline" @click="showAcknowledgeDialog = false">
                            Cancel
                        </Button>
                        <Button
                            :style="{ backgroundColor: primaryColor }"
                            :disabled="isAcknowledging"
                            @click="confirmAcknowledge"
                        >
                            {{ isAcknowledging ? 'Acknowledging...' : 'Acknowledge' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
