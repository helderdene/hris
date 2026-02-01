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
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ScheduleInterviewDialog from '@/components/Recruitment/ScheduleInterviewDialog.vue';
import InterviewFeedbackForm from '@/components/Recruitment/InterviewFeedbackForm.vue';
import InterviewPanelistList from '@/components/Recruitment/InterviewPanelistList.vue';

interface Panelist {
    id: number;
    employee: { id: number; full_name: string };
    is_lead: boolean;
    invitation_sent_at: string | null;
    feedback: string | null;
    rating: number | null;
    feedback_submitted_at: string | null;
}

interface InterviewDetail {
    id: number;
    type: string;
    type_label: string;
    type_color: string;
    status: string;
    status_label: string;
    status_color: string;
    title: string;
    scheduled_at: string;
    duration_minutes: number;
    location: string | null;
    meeting_url: string | null;
    notes: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    created_by: { id: number; full_name: string } | null;
    panelists: Panelist[];
    created_at: string | null;
}

interface TypeOption {
    value: string;
    label: string;
    color: string;
}

interface EmployeeOption {
    id: number;
    full_name: string;
}

const props = defineProps<{
    interview: InterviewDetail;
    jobApplication: {
        id: number;
        candidate: { id: number; full_name: string; email: string };
        job_posting: { id: number; title: string };
    };
    interviewTypes: TypeOption[];
    interviewStatuses: TypeOption[];
    employees: EmployeeOption[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/job-postings' },
    { title: props.jobApplication.job_posting.title, href: `/recruitment/job-postings/${props.jobApplication.job_posting.id}` },
    { title: props.jobApplication.candidate.full_name, href: `/recruitment/applications/${props.jobApplication.id}` },
    { title: 'Interviews', href: `/recruitment/applications/${props.jobApplication.id}/interviews` },
    { title: props.interview.title, href: `/recruitment/interviews/${props.interview.id}` },
];

const isProcessing = ref(false);
const showCancelDialog = ref(false);
const showEditDialog = ref(false);
const cancellationReason = ref('');

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

function formatDateTime(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function cancelInterview() {
    if (isProcessing.value || !cancellationReason.value) {
        return;
    }

    isProcessing.value = true;
    showCancelDialog.value = false;

    try {
        const response = await fetch(`/api/interviews/${props.interview.id}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ cancellation_reason: cancellationReason.value }),
        });

        if (response.ok) {
            router.reload();
        } else {
            const data = await response.json().catch(() => null);
            console.error('Cancel interview failed:', response.status, data);
            alert(data?.message || `Failed to cancel interview (${response.status})`);
        }
    } catch (error) {
        console.error('Cancel interview error:', error);
        alert('Failed to cancel interview. Please try again.');
    } finally {
        isProcessing.value = false;
    }
}

async function sendInvitations() {
    if (isProcessing.value) {
        return;
    }

    isProcessing.value = true;

    try {
        const response = await fetch(`/api/interviews/${props.interview.id}/send-invitations`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            router.reload();
        } else {
            const data = await response.json().catch(() => null);
            console.error('Send invitations failed:', response.status, data);
            alert(data?.message || `Failed to send invitations (${response.status})`);
        }
    } catch (error) {
        console.error('Send invitations error:', error);
        alert('Failed to send invitations. Please try again.');
    } finally {
        isProcessing.value = false;
    }
}

const isTerminal = ['completed', 'cancelled', 'no_show'].includes(props.interview.status);
</script>

<template>
    <Head :title="`${interview.title} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <!-- Header -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ interview.title }}
                        </h1>
                        <span
                            class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium"
                            :class="getStatusBadgeClasses(interview.status_color)"
                        >
                            {{ interview.status_label }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ jobApplication.candidate.full_name }} &middot; {{ jobApplication.job_posting.title }}
                    </p>
                </div>
                <div v-if="!isTerminal" class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="showEditDialog = true" :disabled="isProcessing">Edit</Button>
                    <Button variant="outline" @click="sendInvitations" :disabled="isProcessing">Send Invitations</Button>
                    <a :href="`/api/interviews/${interview.id}/calendar.ics`" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                        Download .ics
                    </a>
                    <Button variant="destructive" @click="showCancelDialog = true" :disabled="isProcessing">Cancel</Button>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Interview Details -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Details</h2>
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Type</dt>
                            <dd class="mt-0.5">
                                <span :class="getStatusBadgeClasses(interview.type_color)" class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium">
                                    {{ interview.type_label }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Date & Time</dt>
                            <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ formatDateTime(interview.scheduled_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Duration</dt>
                            <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ interview.duration_minutes }} minutes</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Location</dt>
                            <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ interview.location || '-' }}</dd>
                        </div>
                        <div v-if="interview.meeting_url" class="sm:col-span-2">
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Meeting URL</dt>
                            <dd class="mt-0.5 text-sm">
                                <a :href="interview.meeting_url" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    {{ interview.meeting_url }}
                                </a>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Candidate</dt>
                            <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ jobApplication.candidate.full_name }} ({{ jobApplication.candidate.email }})</dd>
                        </div>
                        <div v-if="interview.created_by">
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Scheduled By</dt>
                            <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ interview.created_by.full_name }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Notes -->
                <div v-if="interview.notes" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Notes</h2>
                    <div class="whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ interview.notes }}</div>
                </div>

                <!-- Cancellation -->
                <div v-if="interview.cancellation_reason" class="rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-800 dark:bg-red-900/20">
                    <h2 class="mb-3 text-lg font-semibold text-red-900 dark:text-red-100">Cancellation Reason</h2>
                    <div class="text-sm text-red-700 dark:text-red-300">{{ interview.cancellation_reason }}</div>
                    <p v-if="interview.cancelled_at" class="mt-2 text-xs text-red-500">Cancelled on {{ formatDateTime(interview.cancelled_at) }}</p>
                </div>

                <!-- Panelists -->
                <InterviewPanelistList :panelists="interview.panelists" />

                <!-- Feedback Form (for panelists) -->
                <InterviewFeedbackForm :interview-id="interview.id" />
            </div>
        </div>

        <!-- Cancel Dialog -->
        <Dialog v-model:open="showCancelDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel Interview</DialogTitle>
                    <DialogDescription>This will cancel the interview and notify panelists.</DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Reason *</label>
                    <textarea v-model="cancellationReason" rows="3" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" placeholder="Why is this interview being cancelled?"></textarea>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showCancelDialog = false">Keep Interview</Button>
                    <Button variant="destructive" @click="cancelInterview" :disabled="!cancellationReason">Cancel Interview</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Dialog -->
        <ScheduleInterviewDialog
            v-model:open="showEditDialog"
            :job-application-id="jobApplication.id"
            :interview-types="interviewTypes"
            :employees="employees"
            :interview="interview"
        />
    </TenantLayout>
</template>
