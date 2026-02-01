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
}

interface Reviewer {
    id: number;
    reviewer_type: string;
    reviewer_type_label: string;
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
    peer_review_due_date: string | null;
}

const props = defineProps<{
    reviewer: Reviewer;
    participant: Participant;
    competencies: Competency[];
    response: ExistingResponse | null;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Dashboard', href: '/my/dashboard' },
    { title: 'My Evaluations', href: '/my/evaluations' },
    { title: `Review: ${props.participant.employee.full_name}`, href: '#' },
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

const form = useForm({
    strengths: props.response?.strengths ?? '',
    areas_for_improvement: props.response?.areas_for_improvement ?? '',
    overall_comments: props.response?.overall_comments ?? '',
    development_suggestions: props.response?.development_suggestions ?? '',
    competency_ratings: buildInitialCompetencyRatings(),
    submit: false,
});

const activeSection = ref<'competency' | 'narrative'>('competency');
const isSaving = ref(false);
const lastSaved = ref<Date | null>(null);
const showConfirmSubmit = ref(false);
const showDeclineDialog = ref(false);
const declineReason = ref('');

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

function handleDecline() {
    showDeclineDialog.value = true;
}

function confirmDecline() {
    router.post(
        `/api/evaluation-reviewers/${props.reviewer.id}/decline`,
        { reason: declineReason.value },
        {
            onSuccess: () => {
                router.visit('/my/evaluations');
            },
        },
    );
    showDeclineDialog.value = false;
}

function setRating(competencyId: number, rating: number) {
    if (!props.reviewer.can_edit) return;
    form.competency_ratings[competencyId].rating = rating;
    triggerAutoSave();
}

const ratingLabels = ['', 'Needs Improvement', 'Below Expectations', 'Meets Expectations', 'Exceeds Expectations', 'Exceptional'];
</script>

<template>
    <Head :title="`Review ${participant.employee.full_name} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300"
                        >
                            {{ reviewer.reviewer_type_label }} Review
                        </span>
                    </div>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ participant.employee.full_name }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ participant.employee.position || 'No Position' }}
                        <span v-if="participant.employee.department">
                            · {{ participant.employee.department }}
                        </span>
                    </p>
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
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
                    <Button variant="ghost" :disabled="!reviewer.can_edit" @click="handleDecline">
                        Decline
                    </Button>
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

            <!-- Info Banner -->
            <div class="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    Your feedback will be kept confidential and aggregated with other reviewers' responses.
                    Individual responses are not shared directly with the employee.
                </p>
            </div>

            <!-- Section Navigation -->
            <div class="flex gap-2 border-b border-slate-200 dark:border-slate-700">
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

            <!-- Competency Section -->
            <div v-show="activeSection === 'competency'">
                <div v-for="(categoryCompetencies, category) in competenciesByCategory" :key="category" class="mb-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ category }}</CardTitle>
                            <CardDescription>
                                Rate {{ participant.employee.full_name }}'s proficiency in each competency.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-6">
                                <div
                                    v-for="competency in categoryCompetencies"
                                    :key="competency.id"
                                    class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                                >
                                    <div class="mb-4">
                                        <h4 class="font-medium text-slate-900 dark:text-slate-100">
                                            {{ competency.competency.name }}
                                        </h4>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                            {{ competency.competency.description }}
                                        </p>
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
                                                @click="setRating(competency.id, n)"
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
                            Provide detailed feedback about {{ participant.employee.full_name }}'s performance.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div>
                            <Label for="strengths" class="mb-2 block">Strengths</Label>
                            <Textarea
                                id="strengths"
                                v-model="form.strengths"
                                placeholder="What are their key strengths?"
                                rows="4"
                                :disabled="!reviewer.can_edit"
                            />
                        </div>

                        <div>
                            <Label for="areas_for_improvement" class="mb-2 block">Areas for Improvement</Label>
                            <Textarea
                                id="areas_for_improvement"
                                v-model="form.areas_for_improvement"
                                placeholder="What areas could they improve?"
                                rows="4"
                                :disabled="!reviewer.can_edit"
                            />
                        </div>

                        <div>
                            <Label for="overall_comments" class="mb-2 block">Overall Comments</Label>
                            <Textarea
                                id="overall_comments"
                                v-model="form.overall_comments"
                                placeholder="Any additional comments..."
                                rows="4"
                                :disabled="!reviewer.can_edit"
                            />
                        </div>

                        <div>
                            <Label for="development_suggestions" class="mb-2 block">Development Suggestions</Label>
                            <Textarea
                                id="development_suggestions"
                                v-model="form.development_suggestions"
                                placeholder="What training or development would help them grow?"
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
                        Submit Review
                    </h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Are you sure you want to submit your review for {{ participant.employee.full_name }}?
                        You will not be able to make changes after submission.
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

            <!-- Decline Dialog -->
            <div
                v-if="showDeclineDialog"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            >
                <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6 dark:bg-slate-900">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Decline Review
                    </h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Are you sure you want to decline this review? Please provide a reason (optional).
                    </p>
                    <Textarea
                        v-model="declineReason"
                        class="mt-4"
                        placeholder="Reason for declining (optional)..."
                        rows="3"
                    />
                    <div class="mt-6 flex justify-end gap-3">
                        <Button variant="outline" @click="showDeclineDialog = false">
                            Cancel
                        </Button>
                        <Button variant="destructive" @click="confirmDecline">
                            Decline Review
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
