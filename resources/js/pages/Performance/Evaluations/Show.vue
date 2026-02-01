<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Reviewer {
    id: number;
    reviewer_type: string;
    reviewer_type_label: string;
    reviewer_type_color_class: string;
    status: string;
    status_label: string;
    status_color_class: string;
    can_view_kpis: boolean;
    can_edit: boolean;
    submitted_at: string | null;
    reviewer_employee: {
        id: number;
        full_name: string;
        employee_number: string;
        profile_photo_url: string | null;
        position?: { id: number; title: string };
        department?: { id: number; name: string };
    };
    evaluation_response?: {
        id: number;
        average_competency_rating: number | null;
        average_kpi_rating: number | null;
        completion_percentage: number;
    };
}

interface ReviewersByType {
    label: string;
    color_class: string;
    can_view_kpis: boolean;
    reviewers: Reviewer[];
    total: number;
    submitted: number;
}

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
        metric_unit: string;
    } | null;
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
    final_rating_label: string | null;
    is_calibrated: boolean;
    is_acknowledged: boolean;
    calibrated_at: string | null;
    calibration_notes: string | null;
    employee_acknowledged_at: string | null;
}

interface FinalRatingOption {
    value: string;
    label: string;
}

interface Participant {
    id: number;
    employee: {
        id: number;
        full_name: string;
        employee_number: string;
        profile_photo_url: string | null;
        position: { id: number; title: string } | null;
        department: { id: number; name: string } | null;
    };
    instance: {
        id: number;
        name: string;
        cycle_name: string | null;
        year: number;
    };
    evaluation_status: string;
    evaluation_status_label: string;
    evaluation_status_color_class: string;
    self_evaluation_due_date: string | null;
    peer_review_due_date: string | null;
    manager_review_due_date: string | null;
    min_peer_reviewers: number;
    max_peer_reviewers: number;
}

const props = defineProps<{
    participant: Participant;
    reviewers_by_type: Record<string, ReviewersByType>;
    competencies: Competency[];
    kpi_assignments: KpiAssignment[];
    summary: Summary | null;
    final_rating_options: FinalRatingOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Performance', href: '/performance/evaluations' },
    { title: '360 Evaluations', href: '/performance/evaluations' },
    { title: props.participant.employee.full_name, href: '#' },
];

const activeTab = ref('overview');

// Calibration form state
const calibrationForm = ref({
    final_competency_score: props.summary?.final_competency_score?.toString() || '',
    final_kpi_score: props.summary?.final_kpi_score?.toString() || '',
    final_overall_score: props.summary?.final_overall_score?.toString() || '',
    final_rating: props.summary?.final_rating || '',
    calibration_notes: props.summary?.calibration_notes || '',
});
const isSubmitting = ref(false);

const totalReviewers = computed(() => {
    return Object.values(props.reviewers_by_type).reduce((sum, type) => sum + type.total, 0);
});

const submittedReviewers = computed(() => {
    return Object.values(props.reviewers_by_type).reduce((sum, type) => sum + type.submitted, 0);
});

const progressPercentage = computed(() => {
    if (totalReviewers.value === 0) return 0;
    return Math.round((submittedReviewers.value / totalReviewers.value) * 100);
});

const canCalibrate = computed(() => {
    return ['calibration', 'completed'].includes(props.participant.evaluation_status);
});

function submitCalibration() {
    isSubmitting.value = true;
    router.post(
        `/api/participants/${props.participant.id}/summary/calibrate`,
        {
            final_competency_score: calibrationForm.value.final_competency_score
                ? parseFloat(calibrationForm.value.final_competency_score)
                : null,
            final_kpi_score: calibrationForm.value.final_kpi_score
                ? parseFloat(calibrationForm.value.final_kpi_score)
                : null,
            final_overall_score: calibrationForm.value.final_overall_score
                ? parseFloat(calibrationForm.value.final_overall_score)
                : null,
            final_rating: calibrationForm.value.final_rating || null,
            calibration_notes: calibrationForm.value.calibration_notes || null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}

function recalculateSummary() {
    router.post(
        `/api/participants/${props.participant.id}/summary/recalculate`,
        {},
        { preserveScroll: true },
    );
}

function formatScore(score: number | null): string {
    if (score === null) return '-';
    return score.toFixed(2);
}
</script>

<template>
    <Head :title="`${participant.employee.full_name} - Evaluation - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-200 text-xl font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                    >
                        {{ participant.employee.full_name.split(' ').map((n) => n[0]).join('').slice(0, 2) }}
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ participant.employee.full_name }}
                        </h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ participant.employee.position?.title || 'No Position' }}
                            <span v-if="participant.employee.department">
                                · {{ participant.employee.department.name }}
                            </span>
                        </p>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                            {{ participant.instance.name }} ({{ participant.instance.year }})
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span
                        class="inline-flex items-center rounded-md px-3 py-1 text-sm font-medium"
                        :class="participant.evaluation_status_color_class"
                    >
                        {{ participant.evaluation_status_label }}
                    </span>
                </div>
            </div>

            <!-- Progress Summary -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-slate-500">Total Reviewers</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                            {{ totalReviewers }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-slate-500">Submitted</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold text-emerald-600">
                            {{ submittedReviewers }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-slate-500">Completion</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div
                                    class="h-full rounded-full bg-blue-500 transition-all"
                                    :style="{ width: `${progressPercentage}%` }"
                                />
                            </div>
                            <span class="text-sm font-medium text-slate-600 dark:text-slate-400">
                                {{ progressPercentage }}%
                            </span>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-slate-500">Final Rating</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-lg font-bold" :class="summary?.final_rating ? 'text-purple-600' : 'text-slate-400'">
                            {{ summary?.final_rating_label || 'Not Set' }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs -->
            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="grid w-full grid-cols-4">
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="reviewers">Reviewers</TabsTrigger>
                    <TabsTrigger value="scores">Scores</TabsTrigger>
                    <TabsTrigger value="calibration">Calibration</TabsTrigger>
                </TabsList>

                <!-- Overview Tab -->
                <TabsContent value="overview" class="mt-6">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <!-- Competencies Summary -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Competencies</CardTitle>
                                <CardDescription>
                                    {{ competencies.length }} competencies for this position
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div
                                        v-for="competency in competencies.slice(0, 5)"
                                        :key="competency.id"
                                        class="flex items-center justify-between"
                                    >
                                        <div>
                                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                                {{ competency.competency.name }}
                                            </p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ competency.competency.category_label }}
                                            </p>
                                        </div>
                                        <span
                                            class="text-xs font-medium"
                                            :class="competency.is_mandatory ? 'text-red-600' : 'text-slate-400'"
                                        >
                                            {{ competency.is_mandatory ? 'Required' : 'Optional' }}
                                        </span>
                                    </div>
                                    <p v-if="competencies.length > 5" class="text-sm text-slate-500">
                                        +{{ competencies.length - 5 }} more competencies
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- KPIs Summary -->
                        <Card>
                            <CardHeader>
                                <CardTitle>KPI Assignments</CardTitle>
                                <CardDescription>
                                    {{ kpi_assignments.length }} KPIs assigned
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div
                                        v-for="kpi in kpi_assignments.slice(0, 5)"
                                        :key="kpi.id"
                                        class="flex items-center justify-between"
                                    >
                                        <div>
                                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                                {{ kpi.kpi_template?.name || 'Unknown KPI' }}
                                            </p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                Target: {{ kpi.target_value }} {{ kpi.kpi_template?.metric_unit }}
                                            </p>
                                        </div>
                                        <span
                                            class="text-sm font-medium"
                                            :class="(kpi.achievement_percentage ?? 0) >= 100 ? 'text-emerald-600' : 'text-amber-600'"
                                        >
                                            {{ kpi.achievement_percentage?.toFixed(0) ?? '-' }}%
                                        </span>
                                    </div>
                                    <p v-if="kpi_assignments.length > 5" class="text-sm text-slate-500">
                                        +{{ kpi_assignments.length - 5 }} more KPIs
                                    </p>
                                    <p v-if="kpi_assignments.length === 0" class="text-sm text-slate-500">
                                        No KPIs assigned to this participant.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Reviewers Tab -->
                <TabsContent value="reviewers" class="mt-6">
                    <div class="space-y-6">
                        <div
                            v-for="(typeData, typeKey) in reviewers_by_type"
                            :key="typeKey"
                            class="rounded-lg border border-slate-200 dark:border-slate-700"
                        >
                            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                        :class="typeData.color_class"
                                    >
                                        {{ typeData.label }}
                                    </span>
                                    <span class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ typeData.submitted }}/{{ typeData.total }} submitted
                                    </span>
                                </div>
                                <span v-if="typeData.can_view_kpis" class="text-xs text-slate-400">
                                    Can view KPIs
                                </span>
                            </div>
                            <div class="divide-y divide-slate-200 dark:divide-slate-700">
                                <div
                                    v-for="reviewer in typeData.reviewers"
                                    :key="reviewer.id"
                                    class="flex items-center justify-between px-4 py-3"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                        >
                                            {{ reviewer.reviewer_employee.full_name.split(' ').map((n: string) => n[0]).join('').slice(0, 2) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                                {{ reviewer.reviewer_employee.full_name }}
                                            </p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ reviewer.reviewer_employee.position?.title || 'No Position' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span
                                            v-if="reviewer.evaluation_response?.average_competency_rating"
                                            class="text-sm font-medium text-slate-600 dark:text-slate-400"
                                        >
                                            Avg: {{ reviewer.evaluation_response.average_competency_rating.toFixed(1) }}
                                        </span>
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                            :class="reviewer.status_color_class"
                                        >
                                            {{ reviewer.status_label }}
                                        </span>
                                    </div>
                                </div>
                                <div
                                    v-if="typeData.reviewers.length === 0"
                                    class="px-4 py-6 text-center text-sm text-slate-500"
                                >
                                    No {{ typeData.label.toLowerCase() }} reviewers assigned.
                                </div>
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- Scores Tab -->
                <TabsContent value="scores" class="mt-6">
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle>Aggregated Scores</CardTitle>
                                    <CardDescription>
                                        Competency averages by reviewer type
                                    </CardDescription>
                                </div>
                                <Button variant="outline" size="sm" @click="recalculateSummary">
                                    Recalculate
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="summary" class="space-y-6">
                                <!-- Competency Scores -->
                                <div>
                                    <h4 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                        Competency Ratings
                                    </h4>
                                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                                        <div class="rounded-lg bg-blue-50 p-3 dark:bg-blue-900/20">
                                            <p class="text-xs font-medium text-blue-600 dark:text-blue-400">Self</p>
                                            <p class="text-xl font-bold text-blue-700 dark:text-blue-300">
                                                {{ formatScore(summary.self_competency_avg) }}
                                            </p>
                                        </div>
                                        <div class="rounded-lg bg-purple-50 p-3 dark:bg-purple-900/20">
                                            <p class="text-xs font-medium text-purple-600 dark:text-purple-400">Manager</p>
                                            <p class="text-xl font-bold text-purple-700 dark:text-purple-300">
                                                {{ formatScore(summary.manager_competency_avg) }}
                                            </p>
                                        </div>
                                        <div class="rounded-lg bg-green-50 p-3 dark:bg-green-900/20">
                                            <p class="text-xs font-medium text-green-600 dark:text-green-400">Peers</p>
                                            <p class="text-xl font-bold text-green-700 dark:text-green-300">
                                                {{ formatScore(summary.peer_competency_avg) }}
                                            </p>
                                        </div>
                                        <div class="rounded-lg bg-amber-50 p-3 dark:bg-amber-900/20">
                                            <p class="text-xs font-medium text-amber-600 dark:text-amber-400">Direct Reports</p>
                                            <p class="text-xl font-bold text-amber-700 dark:text-amber-300">
                                                {{ formatScore(summary.direct_report_competency_avg) }}
                                            </p>
                                        </div>
                                        <div class="rounded-lg bg-slate-100 p-3 dark:bg-slate-800">
                                            <p class="text-xs font-medium text-slate-600 dark:text-slate-400">Overall</p>
                                            <p class="text-xl font-bold text-slate-900 dark:text-slate-100">
                                                {{ formatScore(summary.overall_competency_avg) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- KPI Scores -->
                                <div>
                                    <h4 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                        KPI Achievement
                                    </h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="rounded-lg bg-emerald-50 p-3 dark:bg-emerald-900/20">
                                            <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                Achievement Score
                                            </p>
                                            <p class="text-xl font-bold text-emerald-700 dark:text-emerald-300">
                                                {{ summary.kpi_achievement_score?.toFixed(1) ?? '-' }}%
                                            </p>
                                        </div>
                                        <div class="rounded-lg bg-indigo-50 p-3 dark:bg-indigo-900/20">
                                            <p class="text-xs font-medium text-indigo-600 dark:text-indigo-400">
                                                Manager KPI Rating
                                            </p>
                                            <p class="text-xl font-bold text-indigo-700 dark:text-indigo-300">
                                                {{ summary.manager_kpi_rating ?? '-' }}/5
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="py-8 text-center text-sm text-slate-500">
                                No summary data available yet. Wait for reviewers to submit their evaluations.
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Calibration Tab -->
                <TabsContent value="calibration" class="mt-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Final Calibration</CardTitle>
                            <CardDescription>
                                Set the final performance scores and rating for this employee.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="canCalibrate" class="space-y-6">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div class="space-y-2">
                                        <Label for="final_competency_score">Final Competency Score (1-5)</Label>
                                        <Input
                                            id="final_competency_score"
                                            v-model="calibrationForm.final_competency_score"
                                            type="number"
                                            step="0.1"
                                            min="1"
                                            max="5"
                                            :placeholder="summary?.overall_competency_avg?.toFixed(2) || ''"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="final_kpi_score">Final KPI Score (%)</Label>
                                        <Input
                                            id="final_kpi_score"
                                            v-model="calibrationForm.final_kpi_score"
                                            type="number"
                                            step="0.1"
                                            min="0"
                                            max="200"
                                            :placeholder="summary?.kpi_achievement_score?.toFixed(2) || ''"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="final_overall_score">Final Overall Score (0-100)</Label>
                                        <Input
                                            id="final_overall_score"
                                            v-model="calibrationForm.final_overall_score"
                                            type="number"
                                            step="0.1"
                                            min="0"
                                            max="100"
                                        />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="final_rating">Final Rating</Label>
                                    <Select v-model="calibrationForm.final_rating">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a rating..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="option in final_rating_options"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2">
                                    <Label for="calibration_notes">Calibration Notes</Label>
                                    <Textarea
                                        id="calibration_notes"
                                        v-model="calibrationForm.calibration_notes"
                                        placeholder="Add any notes about the calibration decisions..."
                                        rows="4"
                                    />
                                </div>

                                <div class="flex items-center justify-between border-t border-slate-200 pt-4 dark:border-slate-700">
                                    <div v-if="summary?.is_calibrated" class="text-sm text-slate-500">
                                        Last calibrated: {{ new Date(summary.calibrated_at!).toLocaleDateString() }}
                                    </div>
                                    <Button
                                        :style="{ backgroundColor: primaryColor }"
                                        :disabled="isSubmitting"
                                        @click="submitCalibration"
                                    >
                                        {{ isSubmitting ? 'Saving...' : 'Save Calibration' }}
                                    </Button>
                                </div>
                            </div>
                            <div v-else class="py-8 text-center">
                                <p class="text-sm text-slate-500">
                                    Calibration is not available yet. The evaluation must be in the "Calibration" status.
                                </p>
                                <p class="mt-2 text-xs text-slate-400">
                                    Current status: {{ participant.evaluation_status_label }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </TenantLayout>
</template>
