<script setup lang="ts">

interface Summary {
    self_competency_avg: number | null;
    manager_competency_avg: number | null;
    peer_competency_avg: number | null;
    direct_report_competency_avg: number | null;
    overall_competency_avg: number | null;
    kpi_achievement_score: number | null;
    final_competency_score: number | null;
    final_kpi_score: number | null;
    final_overall_score: number | null;
    final_rating: string | null;
}

const props = defineProps<{
    summary: Summary | null;
    compact?: boolean;
}>();

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

function formatScore(score: number | null): string {
    if (score === null) return '-';
    return score.toFixed(2);
}

function getScoreBarWidth(score: number | null, max: number = 5): string {
    if (score === null) return '0%';
    return `${(score / max) * 100}%`;
}

function getScoreBarColor(score: number | null): string {
    if (score === null) return 'bg-slate-200';
    if (score >= 4.5) return 'bg-emerald-500';
    if (score >= 3.5) return 'bg-blue-500';
    if (score >= 2.5) return 'bg-slate-400';
    if (score >= 1.5) return 'bg-amber-500';
    return 'bg-red-500';
}
</script>

<template>
    <div v-if="!summary" class="py-4 text-center text-sm text-slate-500">
        No summary data available.
    </div>

    <div v-else>
        <!-- Compact View -->
        <div v-if="compact" class="flex items-center gap-4">
            <div
                v-if="summary.final_rating"
                class="rounded px-3 py-1.5 text-sm font-medium"
                :class="ratingColors[summary.final_rating] || 'bg-slate-100 text-slate-800'"
            >
                {{ ratingLabels[summary.final_rating] || summary.final_rating }}
            </div>
            <div class="text-sm">
                <span class="text-slate-500">Score:</span>
                <span class="ml-1 font-medium text-slate-900 dark:text-slate-100">
                    {{ formatScore(summary.final_overall_score) }}
                </span>
            </div>
        </div>

        <!-- Full View -->
        <div v-else class="space-y-6">
            <!-- Final Rating -->
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Final Rating</p>
                    <div
                        v-if="summary.final_rating"
                        class="mt-1 inline-flex rounded-lg px-4 py-2 text-lg font-semibold"
                        :class="ratingColors[summary.final_rating] || 'bg-slate-100 text-slate-800'"
                    >
                        {{ ratingLabels[summary.final_rating] || summary.final_rating }}
                    </div>
                    <p v-else class="mt-1 text-slate-400">Not yet calibrated</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Overall Score</p>
                    <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-slate-100">
                        {{ formatScore(summary.final_overall_score) }}
                    </p>
                </div>
            </div>

            <!-- Score Breakdown -->
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Competency Score</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">
                        {{ formatScore(summary.final_competency_score) }}
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <p class="text-sm text-slate-500 dark:text-slate-400">KPI Achievement</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">
                        {{ summary.kpi_achievement_score !== null ? `${summary.kpi_achievement_score}%` : '-' }}
                    </p>
                </div>
            </div>

            <!-- Competency Scores by Source -->
            <div>
                <p class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                    Competency Scores by Source
                </p>
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="w-24 text-sm text-slate-500">Self</span>
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="getScoreBarColor(summary.self_competency_avg)"
                                :style="{ width: getScoreBarWidth(summary.self_competency_avg) }"
                            />
                        </div>
                        <span class="w-10 text-right text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ formatScore(summary.self_competency_avg) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="w-24 text-sm text-slate-500">Manager</span>
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="getScoreBarColor(summary.manager_competency_avg)"
                                :style="{ width: getScoreBarWidth(summary.manager_competency_avg) }"
                            />
                        </div>
                        <span class="w-10 text-right text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ formatScore(summary.manager_competency_avg) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="w-24 text-sm text-slate-500">Peers</span>
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="getScoreBarColor(summary.peer_competency_avg)"
                                :style="{ width: getScoreBarWidth(summary.peer_competency_avg) }"
                            />
                        </div>
                        <span class="w-10 text-right text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ formatScore(summary.peer_competency_avg) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="w-24 text-sm text-slate-500">Direct Reports</span>
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="getScoreBarColor(summary.direct_report_competency_avg)"
                                :style="{ width: getScoreBarWidth(summary.direct_report_competency_avg) }"
                            />
                        </div>
                        <span class="w-10 text-right text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ formatScore(summary.direct_report_competency_avg) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
