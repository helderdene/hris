<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Competency {
    id: number;
    required_proficiency_level: number;
    is_mandatory: boolean;
    weight: number;
    competency: {
        id: number;
        name: string;
        code: string;
        description: string;
        category: string;
        category_label: string;
    };
}

interface KpiAssignment {
    id: number;
    target_value: number;
    actual_value: number | null;
    weight: number;
    achievement_percentage: number | null;
    status: string;
    status_label: string;
    kpi_template: {
        id: number;
        name: string;
        code: string;
        description: string;
        metric_unit: string;
    } | null;
}

interface ExistingResponse {
    id: number;
    strengths: string | null;
    areas_for_improvement: string | null;
    overall_comments: string | null;
    development_suggestions: string | null;
    competency_ratings: Array<{
        id: number;
        position_competency_id: number;
        rating: number | null;
        comments: string | null;
    }>;
    kpi_ratings: Array<{
        id: number;
        kpi_assignment_id: number;
        rating: number | null;
        comments: string | null;
    }>;
}

interface Reviewer {
    id: number;
    status: string;
    status_label: string;
    can_edit: boolean;
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
    evaluation_status: string;
    self_evaluation_due_date: string | null;
}

const props = defineProps<{
    participant: Participant;
    reviewer: Reviewer;
    competencies: Competency[];
    kpi_assignments: KpiAssignment[];
    response: ExistingResponse | null;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Dashboard', href: '/my/dashboard' },
    { title: 'My Evaluations', href: '/my/evaluations' },
    { title: 'Self-Evaluation', href: '#' },
];

// Build initial form data from existing response
const buildInitialCompetencyRatings = () => {
    const ratings: Record<number, { rating: number | null; comments: string }> = {};
    for (const c of props.competencies) {
        const existing = props.response?.competency_ratings?.find(
            (r) => r.position_competency_id === c.id,
        );
        ratings[c.id] = {
            rating: existing?.rating ?? null,
            comments: existing?.comments ?? '',
        };
    }
    return ratings;
};

const buildInitialKpiRatings = () => {
    const ratings: Record<number, { rating: number | null; comments: string }> = {};
    for (const kpi of props.kpi_assignments) {
        const existing = props.response?.kpi_ratings?.find(
            (r) => r.kpi_assignment_id === kpi.id,
        );
        ratings[kpi.id] = {
            rating: existing?.rating ?? null,
            comments: existing?.comments ?? '',
        };
    }
    return ratings;
};

const form = useForm({
    strengths: props.response?.strengths ?? '',
    areas_for_improvement: props.response?.areas_for_improvement ?? '',
    overall_comments: props.response?.overall_comments ?? '',
    development_suggestions: props.response?.development_suggestions ?? '',
    competency_ratings: buildInitialCompetencyRatings(),
    kpi_ratings: buildInitialKpiRatings(),
    submit: false,
});

const activeSection = ref<'kpi' | 'competency' | 'narrative'>('kpi');
const isSaving = ref(false);
const lastSaved = ref<Date | null>(null);
const showConfirmSubmit = ref(false);

// Group competencies by category
const competenciesByCategory = computed(() => {
    const grouped: Record<string, Competency[]> = {};
    for (const c of props.competencies) {
        const category = c.competency.category_label || 'Other';
        if (!grouped[category]) {
            grouped[category] = [];
        }
        grouped[category].push(c);
    }
    return grouped;
});

// Auto-save timer
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;

function triggerAutoSave() {
    if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
    }
    autoSaveTimer = setTimeout(() => {
        saveDraft();
    }, 3000);
}

watch(
    () => [form.strengths, form.areas_for_improvement, form.overall_comments, form.development_suggestions],
    () => {
        if (props.reviewer.can_edit) {
            triggerAutoSave();
        }
    },
);

function saveDraft() {
    if (!props.reviewer.can_edit || isSaving.value) return;

    isSaving.value = true;
    form.submit = false;

    router.post(
        `/api/evaluation-reviewers/${props.reviewer.id}/response`,
        {
            ...form.data(),
            submit: false,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                lastSaved.value = new Date();
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
}

function handleSubmit() {
    showConfirmSubmit.value = true;
}

function confirmSubmit() {
    form.submit = true;
    router.post(
        `/api/evaluation-reviewers/${props.reviewer.id}/response`,
        {
            ...form.data(),
            submit: true,
        },
        {
            onSuccess: () => {
                router.visit('/my/evaluations');
            },
        },
    );
    showConfirmSubmit.value = false;
}

function setRating(type: 'competency' | 'kpi', id: number, rating: number) {
    if (!props.reviewer.can_edit) return;

    if (type === 'competency') {
        form.competency_ratings[id].rating = rating;
    } else {
        form.kpi_ratings[id].rating = rating;
    }
    triggerAutoSave();
}

const ratingLabels = ['', 'Needs Improvement', 'Below Expectations', 'Meets Expectations', 'Exceeds Expectations', 'Exceptional'];
</script>

<template>
    <Head :title="`Self-Evaluation - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Self-Evaluation
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ participant.instance.name }} · {{ participant.instance.year }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span v-if="lastSaved" class="text-xs text-slate-500">
                        Saved {{ lastSaved.toLocaleTimeString() }}
                    </span>
                    <span v-if="isSaving" class="text-xs text-blue-600">
                        Saving...
                    </span>
                    <Button variant="outline" :disabled="!reviewer.can_edit || isSaving" @click="saveDraft">
                        Save Draft
                    </Button>
                    <Button
                        :style="{ backgroundColor: primaryColor }"
                        :disabled="!reviewer.can_edit || form.processing"
                        @click="handleSubmit"
                    >
                        Submit
                    </Button>
                </div>
            </div>

            <!-- Section Navigation -->
            <div class="flex gap-2 border-b border-slate-200 dark:border-slate-700">
                <button
                    class="px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeSection === 'kpi' ? 'border-b-2 text-blue-600' : 'text-slate-500 hover:text-slate-700'"
                    :style="activeSection === 'kpi' ? { borderColor: primaryColor } : {}"
                    @click="activeSection = 'kpi'"
                >
                    KPI Achievement
                </button>
                <button
                    class="px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeSection === 'competency' ? 'border-b-2 text-blue-600' : 'text-slate-500 hover:text-slate-700'"
                    :style="activeSection === 'competency' ? { borderColor: primaryColor } : {}"
                    @click="activeSection = 'competency'"
                >
                    Competencies
                </button>
                <button
                    class="px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeSection === 'narrative' ? 'border-b-2 text-blue-600' : 'text-slate-500 hover:text-slate-700'"
                    :style="activeSection === 'narrative' ? { borderColor: primaryColor } : {}"
                    @click="activeSection = 'narrative'"
                >
                    Narrative Feedback
                </button>
            </div>

            <!-- KPI Achievement Section -->
            <div v-show="activeSection === 'kpi'">
                <Card>
                    <CardHeader>
                        <CardTitle>KPI Achievement</CardTitle>
                        <CardDescription>
                            Review your KPI performance and provide a self-assessment rating.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="kpi_assignments.length > 0" class="space-y-6">
                            <div
                                v-for="kpi in kpi_assignments"
                                :key="kpi.id"
                                class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <div class="mb-4 flex items-start justify-between">
                                    <div>
                                        <h4 class="font-medium text-slate-900 dark:text-slate-100">
                                            {{ kpi.kpi_template?.name || 'Unknown KPI' }}
                                        </h4>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                            {{ kpi.kpi_template?.description }}
                                        </p>
                                    </div>
                                    <span
                                        class="text-lg font-bold"
                                        :class="(kpi.achievement_percentage ?? 0) >= 100 ? 'text-emerald-600' : 'text-amber-600'"
                                    >
                                        {{ kpi.achievement_percentage?.toFixed(0) ?? '-' }}%
                                    </span>
                                </div>

                                <div class="mb-4 grid grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <p class="text-slate-500 dark:text-slate-400">Target</p>
                                        <p class="font-medium text-slate-900 dark:text-slate-100">
                                            {{ kpi.target_value }} {{ kpi.kpi_template?.metric_unit }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 dark:text-slate-400">Actual</p>
                                        <p class="font-medium text-slate-900 dark:text-slate-100">
                                            {{ kpi.actual_value ?? '-' }} {{ kpi.kpi_template?.metric_unit }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 dark:text-slate-400">Weight</p>
                                        <p class="font-medium text-slate-900 dark:text-slate-100">
                                            {{ kpi.weight }}%
                                        </p>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <Label class="mb-2 block text-sm">Your Rating</Label>
                                    <div class="flex gap-2">
                                        <button
                                            v-for="n in 5"
                                            :key="n"
                                            type="button"
                                            class="flex h-10 w-10 items-center justify-center rounded-lg border-2 text-sm font-medium transition-colors"
                                            :class="form.kpi_ratings[kpi.id]?.rating === n
                                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300'
                                                : 'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-400'"
                                            :disabled="!reviewer.can_edit"
                                            @click="setRating('kpi', kpi.id, n)"
                                        >
                                            {{ n }}
                                        </button>
                                    </div>
                                    <p v-if="form.kpi_ratings[kpi.id]?.rating" class="mt-1 text-xs text-slate-500">
                                        {{ ratingLabels[form.kpi_ratings[kpi.id].rating!] }}
                                    </p>
                                </div>

                                <div>
                                    <Label class="mb-2 block text-sm">Comments (Optional)</Label>
                                    <Textarea
                                        v-model="form.kpi_ratings[kpi.id].comments"
                                        placeholder="Add any comments about your KPI performance..."
                                        rows="2"
                                        :disabled="!reviewer.can_edit"
                                        @input="triggerAutoSave"
                                    />
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-8 text-center text-sm text-slate-500">
                            No KPIs assigned for this evaluation period.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Competency Section -->
            <div v-show="activeSection === 'competency'">
                <div v-for="(categoryCompetencies, category) in competenciesByCategory" :key="category" class="mb-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ category }}</CardTitle>
                            <CardDescription>
                                Rate your proficiency in each competency.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-6">
                                <div
                                    v-for="competency in categoryCompetencies"
                                    :key="competency.id"
                                    class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                                >
                                    <div class="mb-4 flex items-start justify-between">
                                        <div>
                                            <h4 class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ competency.competency.name }}
                                                <span
                                                    v-if="competency.is_mandatory"
                                                    class="ml-2 text-xs font-normal text-red-600"
                                                >
                                                    Required
                                                </span>
                                            </h4>
                                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                                {{ competency.competency.description }}
                                            </p>
                                        </div>
                                        <span class="text-sm text-slate-500">
                                            Required: Level {{ competency.required_proficiency_level }}
                                        </span>
                                    </div>

                                    <div class="mb-3">
                                        <Label class="mb-2 block text-sm">Your Rating</Label>
                                        <div class="flex gap-2">
                                            <button
                                                v-for="n in 5"
                                                :key="n"
                                                type="button"
                                                class="flex h-10 w-10 items-center justify-center rounded-lg border-2 text-sm font-medium transition-colors"
                                                :class="form.competency_ratings[competency.id]?.rating === n
                                                    ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300'
                                                    : 'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-400'"
                                                :disabled="!reviewer.can_edit"
                                                @click="setRating('competency', competency.id, n)"
                                            >
                                                {{ n }}
                                            </button>
                                        </div>
                                        <p v-if="form.competency_ratings[competency.id]?.rating" class="mt-1 text-xs text-slate-500">
                                            {{ ratingLabels[form.competency_ratings[competency.id].rating!] }}
                                        </p>
                                    </div>

                                    <div>
                                        <Label class="mb-2 block text-sm">Comments (Optional)</Label>
                                        <Textarea
                                            v-model="form.competency_ratings[competency.id].comments"
                                            placeholder="Add any comments or examples..."
                                            rows="2"
                                            :disabled="!reviewer.can_edit"
                                            @input="triggerAutoSave"
                                        />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Narrative Feedback Section -->
            <div v-show="activeSection === 'narrative'">
                <Card>
                    <CardHeader>
                        <CardTitle>Narrative Feedback</CardTitle>
                        <CardDescription>
                            Provide detailed feedback about your performance.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div>
                            <Label for="strengths" class="mb-2 block">Strengths</Label>
                            <Textarea
                                id="strengths"
                                v-model="form.strengths"
                                placeholder="Describe your key strengths and accomplishments this period..."
                                rows="4"
                                :disabled="!reviewer.can_edit"
                            />
                        </div>

                        <div>
                            <Label for="areas_for_improvement" class="mb-2 block">Areas for Improvement</Label>
                            <Textarea
                                id="areas_for_improvement"
                                v-model="form.areas_for_improvement"
                                placeholder="Identify areas where you can improve..."
                                rows="4"
                                :disabled="!reviewer.can_edit"
                            />
                        </div>

                        <div>
                            <Label for="overall_comments" class="mb-2 block">Overall Comments</Label>
                            <Textarea
                                id="overall_comments"
                                v-model="form.overall_comments"
                                placeholder="Any additional comments about your overall performance..."
                                rows="4"
                                :disabled="!reviewer.can_edit"
                            />
                        </div>

                        <div>
                            <Label for="development_suggestions" class="mb-2 block">Development Goals</Label>
                            <Textarea
                                id="development_suggestions"
                                v-model="form.development_suggestions"
                                placeholder="What development goals or training would help you grow..."
                                rows="4"
                                :disabled="!reviewer.can_edit"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Submit Confirmation Dialog -->
            <div
                v-if="showConfirmSubmit"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            >
                <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6 dark:bg-slate-900">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Submit Self-Evaluation
                    </h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Are you sure you want to submit your self-evaluation? You will not be able to make changes after submission.
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <Button variant="outline" @click="showConfirmSubmit = false">
                            Cancel
                        </Button>
                        <Button :style="{ backgroundColor: primaryColor }" @click="confirmSubmit">
                            Submit
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
