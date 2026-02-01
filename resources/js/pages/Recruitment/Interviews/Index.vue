<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ScheduleInterviewDialog from '@/Components/Recruitment/ScheduleInterviewDialog.vue';

interface Panelist {
    id: number;
    employee: { id: number; full_name: string };
    is_lead: boolean;
    feedback: string | null;
    rating: number | null;
    feedback_submitted_at: string | null;
}

interface Interview {
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
    panelists: Panelist[];
    cancelled_at: string | null;
}

interface EmployeeOption {
    id: number;
    full_name: string;
}

interface TypeOption {
    value: string;
    label: string;
    color: string;
}

const props = defineProps<{
    jobApplication: {
        id: number;
        candidate: { id: number; full_name: string };
        job_posting: { id: number; title: string };
    };
    interviews: Interview[];
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
];

const showScheduleDialog = ref(false);

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
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="`Interviews - ${jobApplication.candidate.full_name} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Interviews
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ jobApplication.candidate.full_name }} &middot; {{ jobApplication.job_posting.title }}
                    </p>
                </div>
                <Button @click="showScheduleDialog = true">Schedule Interview</Button>
            </div>

            <div v-if="interviews.length === 0" class="rounded-xl border border-slate-200 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">No interviews scheduled yet.</p>
                <Button class="mt-4" variant="outline" @click="showScheduleDialog = true">Schedule First Interview</Button>
            </div>

            <div v-else class="space-y-4">
                <Link
                    v-for="interview in interviews"
                    :key="interview.id"
                    :href="`/recruitment/interviews/${interview.id}`"
                    class="block rounded-xl border border-slate-200 bg-white p-5 transition-colors hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                                    {{ interview.title }}
                                </h3>
                                <span
                                    class="inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                    :class="getStatusBadgeClasses(interview.status_color)"
                                >
                                    {{ interview.status_label }}
                                </span>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                <span :class="getStatusBadgeClasses(interview.type_color)" class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium">
                                    {{ interview.type_label }}
                                </span>
                                <span>{{ formatDateTime(interview.scheduled_at) }}</span>
                                <span>{{ interview.duration_minutes }} min</span>
                                <span v-if="interview.location">{{ interview.location }}</span>
                            </div>
                            <div v-if="interview.panelists.length" class="mt-2 flex flex-wrap gap-1">
                                <span
                                    v-for="panelist in interview.panelists"
                                    :key="panelist.id"
                                    class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                                    :class="{ 'ring-1 ring-blue-400': panelist.is_lead }"
                                >
                                    {{ panelist.employee.full_name }}
                                    <span v-if="panelist.is_lead" class="ml-1 text-blue-500">*</span>
                                </span>
                            </div>
                        </div>
                        <div class="text-right text-xs text-slate-400">
                            <span v-if="interview.meeting_url" class="text-blue-500">Video</span>
                        </div>
                    </div>
                </Link>
            </div>
        </div>

        <ScheduleInterviewDialog
            v-model:open="showScheduleDialog"
            :job-application-id="jobApplication.id"
            :interview-types="interviewTypes"
            :employees="employees"
        />
    </TenantLayout>
</template>
