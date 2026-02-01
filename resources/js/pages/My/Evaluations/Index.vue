<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Participation {
    id: number;
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
    self_reviewer_id: number | null;
    self_reviewer_status: string | null;
    self_reviewer_status_label: string | null;
    has_results: boolean;
    is_acknowledged: boolean;
}

interface PendingReview {
    id: number;
    reviewer_type: string;
    reviewer_type_label: string;
    reviewer_type_color_class: string;
    status: string;
    status_label: string;
    participant: {
        id: number;
        employee: {
            id: number;
            full_name: string;
            position: string | null;
            department: string | null;
        };
    };
    instance: {
        id: number;
        name: string;
        year: number;
    };
    invited_at: string | null;
    due_date: string | null;
}

interface CompletedReview {
    id: number;
    reviewer_type: string;
    reviewer_type_label: string;
    participant_name: string;
    instance_name: string;
    submitted_at: string | null;
}

const props = defineProps<{
    my_participations: Participation[];
    pending_reviews: PendingReview[];
    completed_reviews: CompletedReview[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Dashboard', href: '/my/dashboard' },
    { title: 'My Evaluations', href: '/my/evaluations' },
];

const activeParticipations = computed(() => {
    return props.my_participations.filter(
        (p) => p.evaluation_status !== 'completed' || !p.is_acknowledged,
    );
});

const completedParticipations = computed(() => {
    return props.my_participations.filter(
        (p) => p.evaluation_status === 'completed' && p.is_acknowledged,
    );
});

function formatDate(dateString: string | null): string {
    if (!dateString) return 'Not set';
    return new Date(dateString).toLocaleDateString();
}

function getDueDateClass(dueDate: string | null): string {
    if (!dueDate) return 'text-slate-500';
    const due = new Date(dueDate);
    const now = new Date();
    const diffDays = Math.ceil((due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    if (diffDays < 0) return 'text-red-600';
    if (diffDays <= 3) return 'text-amber-600';
    return 'text-slate-500';
}
</script>

<template>
    <Head :title="`My Evaluations - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    My Evaluations
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Complete your self-evaluation and provide feedback for your colleagues.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- My Self-Evaluations -->
                <Card>
                    <CardHeader>
                        <CardTitle>My Self-Evaluations</CardTitle>
                        <CardDescription>
                            Complete your performance evaluation for each cycle.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="activeParticipations.length > 0" class="space-y-4">
                            <div
                                v-for="participation in activeParticipations"
                                :key="participation.id"
                                class="flex items-center justify-between rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ participation.instance.name }}
                                    </p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ participation.instance.cycle_name }} · {{ participation.instance.year }}
                                    </p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                            :class="participation.evaluation_status_color_class"
                                        >
                                            {{ participation.evaluation_status_label }}
                                        </span>
                                        <span
                                            v-if="participation.self_evaluation_due_date"
                                            class="text-xs"
                                            :class="getDueDateClass(participation.self_evaluation_due_date)"
                                        >
                                            Due: {{ formatDate(participation.self_evaluation_due_date) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Link
                                        v-if="participation.self_reviewer_id && participation.self_reviewer_status !== 'submitted'"
                                        :href="`/my/evaluations/${participation.id}/self`"
                                    >
                                        <Button :style="{ backgroundColor: primaryColor }" size="sm">
                                            {{ participation.self_reviewer_status === 'in_progress' ? 'Continue' : 'Start' }}
                                        </Button>
                                    </Link>
                                    <Link
                                        v-if="participation.has_results"
                                        :href="`/my/evaluations/${participation.id}/results`"
                                    >
                                        <Button variant="outline" size="sm">
                                            {{ participation.is_acknowledged ? 'View Results' : 'View & Acknowledge' }}
                                        </Button>
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-8 text-center text-sm text-slate-500">
                            No active self-evaluations at this time.
                        </div>
                    </CardContent>
                </Card>

                <!-- Pending Peer Reviews -->
                <Card>
                    <CardHeader>
                        <CardTitle>Pending Reviews</CardTitle>
                        <CardDescription>
                            Provide feedback for your colleagues.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="pending_reviews.length > 0" class="space-y-4">
                            <div
                                v-for="review in pending_reviews"
                                :key="review.id"
                                class="flex items-center justify-between rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ review.participant.employee.full_name }}
                                    </p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ review.participant.employee.position || 'No Position' }}
                                        <span v-if="review.participant.employee.department">
                                            · {{ review.participant.employee.department }}
                                        </span>
                                    </p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                            :class="review.reviewer_type_color_class"
                                        >
                                            {{ review.reviewer_type_label }}
                                        </span>
                                        <span
                                            v-if="review.due_date"
                                            class="text-xs"
                                            :class="getDueDateClass(review.due_date)"
                                        >
                                            Due: {{ formatDate(review.due_date) }}
                                        </span>
                                    </div>
                                </div>
                                <Link :href="`/my/evaluations/review/${review.id}`">
                                    <Button :style="{ backgroundColor: primaryColor }" size="sm">
                                        {{ review.status === 'in_progress' ? 'Continue' : 'Start' }}
                                    </Button>
                                </Link>
                            </div>
                        </div>
                        <div v-else class="py-8 text-center text-sm text-slate-500">
                            No pending reviews at this time.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Completed Reviews -->
            <Card v-if="completed_reviews.length > 0">
                <CardHeader>
                    <CardTitle>Recently Completed Reviews</CardTitle>
                    <CardDescription>
                        Reviews you have submitted.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        <div
                            v-for="review in completed_reviews"
                            :key="review.id"
                            class="flex items-center justify-between py-3"
                        >
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ review.participant_name }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ review.instance_name }} · {{ review.reviewer_type_label }}
                                </p>
                            </div>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ review.submitted_at ? new Date(review.submitted_at).toLocaleDateString() : '' }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Completed Self-Evaluations -->
            <Card v-if="completedParticipations.length > 0">
                <CardHeader>
                    <CardTitle>Past Evaluations</CardTitle>
                    <CardDescription>
                        View your completed performance evaluations.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        <div
                            v-for="participation in completedParticipations"
                            :key="participation.id"
                            class="flex items-center justify-between py-3"
                        >
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ participation.instance.name }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ participation.instance.cycle_name }} · {{ participation.instance.year }}
                                </p>
                            </div>
                            <Link :href="`/my/evaluations/${participation.id}/results`">
                                <Button variant="outline" size="sm">
                                    View Results
                                </Button>
                            </Link>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
