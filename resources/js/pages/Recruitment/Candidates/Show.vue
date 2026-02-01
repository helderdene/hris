<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Education {
    id: number;
    education_level: string;
    education_level_label: string;
    institution: string;
    field_of_study: string | null;
    start_date: string | null;
    end_date: string | null;
    is_current: boolean;
}

interface WorkExperience {
    id: number;
    company: string;
    job_title: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    is_current: boolean;
}

interface Application {
    id: number;
    job_posting: { id: number; title: string };
    status: string;
    status_label: string;
    status_color: string;
    source_label: string;
    applied_at: string | null;
}

interface CandidateDetail {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string;
    phone: string | null;
    date_of_birth: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    zip_code: string | null;
    country: string | null;
    linkedin_url: string | null;
    portfolio_url: string | null;
    resume_file_name: string | null;
    skills: string[] | null;
    notes: string | null;
    education: Education[];
    work_experiences: WorkExperience[];
    job_applications: Application[];
    created_at: string;
    assessments_count: number;
    background_checks_count: number;
    reference_checks_count: number;
}

const props = defineProps<{
    candidate: CandidateDetail;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/candidates' },
    { title: 'Candidates', href: '/recruitment/candidates' },
    { title: props.candidate.full_name, href: `/recruitment/candidates/${props.candidate.id}` },
];

const activeTab = ref<'profile' | 'education' | 'experience' | 'applications'>('profile');

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
</script>

<template>
    <Head :title="`${candidate.full_name} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <!-- Header -->
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ candidate.full_name }}</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ candidate.email }}</p>
                </div>
                <Link :href="`/recruitment/candidates/${candidate.id}/edit`">
                    <Button variant="outline" size="sm">Edit</Button>
                </Link>
            </div>

            <!-- Tabs -->
            <div class="mb-6 border-b border-slate-200 dark:border-slate-700">
                <nav class="-mb-px flex gap-6">
                    <button
                        v-for="tab in (['profile', 'education', 'experience', 'applications'] as const)"
                        :key="tab"
                        @click="activeTab = tab"
                        class="border-b-2 pb-3 text-sm font-medium capitalize transition-colors"
                        :class="activeTab === tab ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    >
                        {{ tab }}
                        <span v-if="tab === 'applications'" class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-700">
                            {{ candidate.job_applications.length }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Profile Tab -->
            <div v-if="activeTab === 'profile'" class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Contact Information</h2>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Phone</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ candidate.phone || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Date of Birth</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ candidate.date_of_birth || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Location</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                                {{ [candidate.city, candidate.state, candidate.country].filter(Boolean).join(', ') || '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Resume</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ candidate.resume_file_name || 'Not uploaded' }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="candidate.skills?.length" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Skills</h2>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="skill in candidate.skills"
                            :key="skill"
                            class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
                        >
                            {{ skill }}
                        </span>
                    </div>
                </div>

                <!-- Recruitment Activity Summary -->
                <div v-if="candidate.assessments_count || candidate.background_checks_count || candidate.reference_checks_count" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Recruitment Activity</h2>
                    <dl class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Assessments</dt>
                            <dd class="mt-0.5 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ candidate.assessments_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Background Checks</dt>
                            <dd class="mt-0.5 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ candidate.background_checks_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Reference Checks</dt>
                            <dd class="mt-0.5 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ candidate.reference_checks_count }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="candidate.notes" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Notes</h2>
                    <p class="whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ candidate.notes }}</p>
                </div>
            </div>

            <!-- Education Tab -->
            <div v-if="activeTab === 'education'" class="space-y-4">
                <div v-for="edu in candidate.education" :key="edu.id" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="font-semibold text-slate-900 dark:text-slate-100">{{ edu.institution }}</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ edu.education_level_label }} — {{ edu.field_of_study || 'N/A' }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ edu.start_date || '?' }} — {{ edu.is_current ? 'Present' : (edu.end_date || '?') }}
                    </p>
                </div>
                <p v-if="!candidate.education.length" class="text-sm text-slate-500 dark:text-slate-400">No education records.</p>
            </div>

            <!-- Experience Tab -->
            <div v-if="activeTab === 'experience'" class="space-y-4">
                <div v-for="exp in candidate.work_experiences" :key="exp.id" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="font-semibold text-slate-900 dark:text-slate-100">{{ exp.job_title }}</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ exp.company }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ exp.start_date || '?' }} — {{ exp.is_current ? 'Present' : (exp.end_date || '?') }}
                    </p>
                    <p v-if="exp.description" class="mt-2 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ exp.description }}</p>
                </div>
                <p v-if="!candidate.work_experiences.length" class="text-sm text-slate-500 dark:text-slate-400">No work experience records.</p>
            </div>

            <!-- Applications Tab -->
            <div v-if="activeTab === 'applications'" class="space-y-4">
                <div v-for="app in candidate.job_applications" :key="app.id" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div>
                        <Link
                            :href="`/recruitment/applications/${app.id}`"
                            class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400"
                        >
                            {{ app.job_posting.title }}
                        </Link>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ app.source_label }} — {{ app.applied_at?.split(' ')[0] }}</p>
                    </div>
                    <span
                        class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium"
                        :class="getStatusBadgeClasses(app.status_color)"
                    >
                        {{ app.status_label }}
                    </span>
                </div>
                <p v-if="!candidate.job_applications.length" class="text-sm text-slate-500 dark:text-slate-400">No applications yet.</p>
            </div>
        </div>
    </TenantLayout>
</template>
