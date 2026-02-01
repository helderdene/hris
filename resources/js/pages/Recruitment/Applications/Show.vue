<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AssessmentSection from '@/Components/Recruitment/AssessmentSection.vue';
import BackgroundCheckSection from '@/Components/Recruitment/BackgroundCheckSection.vue';
import InterviewCard from '@/Components/Recruitment/InterviewCard.vue';
import ReferenceCheckSection from '@/Components/Recruitment/ReferenceCheckSection.vue';

interface InterviewSummary {
    id: number;
    type_label: string;
    type_color: string;
    status_label: string;
    status_color: string;
    title: string;
    scheduled_at: string;
    duration_minutes: number;
    meeting_url: string | null;
    panelists: { id: number; employee: { id: number; full_name: string }; is_lead: boolean }[];
}

interface StatusHistory {
    id: number;
    from_status: string | null;
    from_status_label: string | null;
    to_status: string;
    to_status_label: string;
    to_status_color: string;
    notes: string | null;
    created_at: string | null;
}

interface ApplicationDetail {
    id: number;
    candidate: {
        id: number;
        full_name: string;
        email: string;
        phone: string | null;
        resume_file_name: string | null;
        skills: string[] | null;
    };
    job_posting: { id: number; title: string; slug: string };
    assigned_to_employee: { id: number; full_name: string } | null;
    status: string;
    status_label: string;
    status_color: string;
    source_label: string;
    cover_letter: string | null;
    rejection_reason: string | null;
    notes: string | null;
    allowed_transitions: { value: string; label: string; color: string }[];
    status_histories: StatusHistory[];
    applied_at: string | null;
    screening_at: string | null;
    interview_at: string | null;
    assessment_at: string | null;
    offer_at: string | null;
    hired_at: string | null;
    rejected_at: string | null;
    withdrawn_at: string | null;
    created_at: string | null;
}

interface AssessmentSummary {
    id: number;
    test_name: string;
    type: string;
    type_label: string;
    type_color: string;
    score: number | null;
    max_score: number | null;
    passed: boolean | null;
    assessed_at: string | null;
    notes: string | null;
}

interface BackgroundCheckDocument {
    id: number;
    file_name: string;
    file_size: number;
    mime_type: string;
}

interface BackgroundCheckSummary {
    id: number;
    check_type: string;
    status: string;
    status_label: string;
    status_color: string;
    provider: string | null;
    notes: string | null;
    started_at: string | null;
    completed_at: string | null;
    documents: BackgroundCheckDocument[];
}

interface ReferenceCheckSummary {
    id: number;
    referee_name: string;
    referee_email: string | null;
    referee_phone: string | null;
    referee_company: string | null;
    relationship: string | null;
    contacted: boolean;
    contacted_at: string | null;
    feedback: string | null;
    recommendation: string | null;
    recommendation_label: string | null;
    recommendation_color: string | null;
    notes: string | null;
}

interface EnumOption {
    value: string;
    label: string;
    color: string;
}

interface OfferSummary {
    id: number;
    status: string;
    status_label: string;
    status_color: string;
    position_title: string | null;
    salary: number | null;
    salary_currency: string | null;
    sent_at: string | null;
    accepted_at: string | null;
    declined_at: string | null;
}

const props = defineProps<{
    application: ApplicationDetail;
    interviews: InterviewSummary[];
    assessments: AssessmentSummary[];
    backgroundChecks: BackgroundCheckSummary[];
    referenceChecks: ReferenceCheckSummary[];
    assessmentTypes: EnumOption[];
    backgroundCheckStatuses: EnumOption[];
    referenceRecommendations: EnumOption[];
    offer: OfferSummary | null;
}>();

const isAssessmentStage = computed(() => ['assessment', 'offer', 'hired'].includes(props.application.status));
const isOfferStage = computed(() => ['offer', 'hired'].includes(props.application.status));

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/job-postings' },
    { title: props.application.job_posting.title, href: `/recruitment/job-postings/${props.application.job_posting.id}` },
    { title: props.application.candidate.full_name, href: `/recruitment/applications/${props.application.id}` },
];

const isProcessing = ref(false);
const showTransitionDialog = ref(false);
const transitionTarget = ref<{ value: string; label: string } | null>(null);
const transitionNotes = ref('');
const rejectionReason = ref('');

function getStatusBadgeClasses(color: string): string {
    const colorMap: Record<string, string> = {
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        purple: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
        emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300',
    };
    return colorMap[color] || colorMap.slate;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function openTransitionDialog(transition: { value: string; label: string }) {
    transitionTarget.value = transition;
    transitionNotes.value = '';
    rejectionReason.value = '';
    showTransitionDialog.value = true;
}

async function executeTransition() {
    if (!transitionTarget.value || isProcessing.value) {
        return;
    }

    isProcessing.value = true;
    showTransitionDialog.value = false;

    try {
        const body: Record<string, string> = {
            status: transitionTarget.value.value,
        };
        if (transitionNotes.value) {
            body.notes = transitionNotes.value;
        }
        if (transitionTarget.value.value === 'rejected' && rejectionReason.value) {
            body.rejection_reason = rejectionReason.value;
        }

        const response = await fetch(`/api/applications/${props.application.id}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        if (response.ok) {
            router.reload();
        }
    } catch {
    } finally {
        isProcessing.value = false;
    }
}

const pipelineSteps = [
    { key: 'applied', label: 'Applied', timestamp: props.application.applied_at },
    { key: 'screening', label: 'Screening', timestamp: props.application.screening_at },
    { key: 'interview', label: 'Interview', timestamp: props.application.interview_at },
    { key: 'assessment', label: 'Assessment', timestamp: props.application.assessment_at },
    { key: 'offer', label: 'Offer', timestamp: props.application.offer_at },
    { key: 'hired', label: 'Hired', timestamp: props.application.hired_at },
];
</script>

<template>
    <Head :title="`${application.candidate.full_name} - Application - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <!-- Header -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ application.candidate.full_name }}
                        </h1>
                        <span
                            class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium"
                            :class="getStatusBadgeClasses(application.status_color)"
                        >
                            {{ application.status_label }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Application for {{ application.job_posting.title }} &middot; {{ application.source_label }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="transition in application.allowed_transitions"
                        :key="transition.value"
                        @click="openTransitionDialog(transition)"
                        :disabled="isProcessing"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="getStatusBadgeClasses(transition.color)"
                    >
                        {{ transition.label }}
                    </button>
                </div>
            </div>

            <!-- Pipeline -->
            <div class="mb-6 overflow-x-auto rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                <div class="flex min-w-max gap-1">
                    <div
                        v-for="(step, i) in pipelineSteps"
                        :key="step.key"
                        class="flex flex-1 flex-col items-center px-2 py-2 text-center"
                    >
                        <div
                            class="mb-1 flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold"
                            :class="step.timestamp ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400'"
                        >
                            {{ i + 1 }}
                        </div>
                        <span class="text-xs font-medium" :class="step.timestamp ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500'">
                            {{ step.label }}
                        </span>
                        <span v-if="step.timestamp" class="text-[10px] text-slate-400">{{ step.timestamp.split(' ')[0] }}</span>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Candidate Summary -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Candidate</h2>
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Name</dt>
                            <dd class="mt-0.5 text-sm">
                                <Link :href="`/recruitment/candidates/${application.candidate.id}`" class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    {{ application.candidate.full_name }}
                                </Link>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Email</dt>
                            <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ application.candidate.email }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Phone</dt>
                            <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ application.candidate.phone || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Resume</dt>
                            <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ application.candidate.resume_file_name || 'Not uploaded' }}</dd>
                        </div>
                    </dl>
                    <div v-if="application.candidate.skills?.length" class="mt-3 flex flex-wrap gap-1">
                        <span
                            v-for="skill in application.candidate.skills"
                            :key="skill"
                            class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                        >
                            {{ skill }}
                        </span>
                    </div>
                </div>

                <!-- Cover Letter -->
                <div v-if="application.cover_letter" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Cover Letter</h2>
                    <div class="whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ application.cover_letter }}</div>
                </div>

                <!-- Rejection Reason -->
                <div v-if="application.rejection_reason" class="rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-800 dark:bg-red-900/20">
                    <h2 class="mb-3 text-lg font-semibold text-red-900 dark:text-red-100">Rejection Reason</h2>
                    <div class="text-sm text-red-700 dark:text-red-300">{{ application.rejection_reason }}</div>
                </div>

                <!-- Interviews -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Interviews</h2>
                        <Link :href="`/recruitment/applications/${application.id}/interviews`" class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400">
                            View All
                        </Link>
                    </div>
                    <div v-if="interviews && interviews.length" class="space-y-3">
                        <InterviewCard v-for="interview in interviews.slice(0, 3)" :key="interview.id" :interview="interview" />
                    </div>
                    <p v-else class="text-sm text-slate-500 dark:text-slate-400">No interviews scheduled.</p>
                </div>

                <!-- Assessments (visible from Assessment stage) -->
                <AssessmentSection
                    v-if="isAssessmentStage"
                    :assessments="assessments"
                    :application-id="application.id"
                    :assessment-types="assessmentTypes"
                />

                <!-- Reference Checks (visible from Assessment stage) -->
                <ReferenceCheckSection
                    v-if="isAssessmentStage"
                    :reference-checks="referenceChecks"
                    :application-id="application.id"
                    :reference-recommendations="referenceRecommendations"
                />

                <!-- Background Checks (visible from Offer stage) -->
                <BackgroundCheckSection
                    v-if="isOfferStage"
                    :background-checks="backgroundChecks"
                    :application-id="application.id"
                    :background-check-statuses="backgroundCheckStatuses"
                />

                <!-- Offer (visible at Offer stage) -->
                <div v-if="isOfferStage" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Offer</h2>
                        <Link v-if="!offer" :href="`/recruitment/offers/create?job_application_id=${application.id}`">
                            <Button size="sm">Create Offer</Button>
                        </Link>
                        <Link v-else :href="`/recruitment/offers/${offer.id}`">
                            <Button variant="outline" size="sm">View Offer</Button>
                        </Link>
                    </div>
                    <p v-if="!offer" class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Create and send an offer letter to this candidate.
                    </p>
                    <div v-else class="mt-3 space-y-2">
                        <div class="flex items-center gap-3">
                            <span :class="getStatusBadgeClasses(offer.status_color)" class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium">
                                {{ offer.status_label }}
                            </span>
                            <span v-if="offer.position_title" class="text-sm text-slate-600 dark:text-slate-400">
                                {{ offer.position_title }}
                            </span>
                        </div>
                        <p v-if="offer.salary" class="text-sm text-slate-600 dark:text-slate-400">
                            Salary: {{ Number(offer.salary).toLocaleString() }} {{ offer.salary_currency }}
                        </p>
                        <p v-if="offer.accepted_at" class="text-sm text-green-600 dark:text-green-400">
                            Accepted on {{ offer.accepted_at }}
                        </p>
                        <p v-else-if="offer.declined_at" class="text-sm text-red-600 dark:text-red-400">
                            Declined on {{ offer.declined_at }}
                        </p>
                        <p v-else-if="offer.sent_at" class="text-sm text-slate-500 dark:text-slate-400">
                            Sent on {{ offer.sent_at }}
                        </p>
                    </div>
                </div>

                <!-- Status Timeline -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Status History</h2>
                    <div v-if="application.status_histories.length" class="space-y-3">
                        <div v-for="history in application.status_histories" :key="history.id" class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <div class="h-2.5 w-2.5 rounded-full bg-blue-500"></div>
                                <div class="w-px flex-1 bg-slate-200 dark:bg-slate-700"></div>
                            </div>
                            <div class="pb-4">
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    <span v-if="history.from_status_label" class="text-slate-500">{{ history.from_status_label }}</span>
                                    <span v-if="history.from_status_label" class="mx-1 text-slate-400">&rarr;</span>
                                    <span :class="getStatusBadgeClasses(history.to_status_color)" class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium">{{ history.to_status_label }}</span>
                                </p>
                                <p v-if="history.notes" class="mt-1 text-xs text-slate-500">{{ history.notes }}</p>
                                <p class="mt-0.5 text-xs text-slate-400">{{ history.created_at }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-500 dark:text-slate-400">No status changes yet.</p>
                </div>
            </div>
        </div>

        <!-- Transition Dialog -->
        <Dialog v-model:open="showTransitionDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Move to {{ transitionTarget?.label }}</DialogTitle>
                    <DialogDescription>Update the application status.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div v-if="transitionTarget?.value === 'rejected'">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Rejection Reason *</label>
                        <textarea v-model="rejectionReason" rows="3" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Notes</label>
                        <textarea v-model="transitionNotes" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showTransitionDialog = false">Cancel</Button>
                    <Button @click="executeTransition" :disabled="transitionTarget?.value === 'rejected' && !rejectionReason">Confirm</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
