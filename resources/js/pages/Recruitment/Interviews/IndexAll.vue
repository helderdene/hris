<script setup lang="ts">
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Panelist {
    id: number;
    employee: { id: number; full_name: string };
    is_lead: boolean;
}

interface Interview {
    id: number;
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
    candidate: { id: number; full_name: string };
    job_posting: { id: number; title: string };
    job_application_id: number;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface PaginatedData {
    data: Interview[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    interviews: PaginatedData;
    interviewTypes: StatusOption[];
    interviewStatuses: StatusOption[];
    filters: { status: string | null };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/job-postings' },
    { title: 'Interviews', href: '/recruitment/interviews' },
];

const statusFilter = ref(props.filters.status ?? '');

function applyFilters() {
    router.get('/recruitment/interviews', {
        status: statusFilter.value || undefined,
    }, { preserveState: true });
}

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
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}
</script>

<template>
    <Head :title="`Interviews - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-5xl">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Interviews</h1>
                <div class="flex items-center gap-3">
                    <select v-model="statusFilter" @change="applyFilters" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">All Statuses</option>
                        <option v-for="s in interviewStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
            </div>

            <div v-if="interviews.data.length === 0" class="rounded-xl border border-slate-200 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">No interviews found.</p>
            </div>

            <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-400">Interview</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-400">Candidate</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-400">Type</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-400">Date</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-400">Status</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-400">Panelists</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <tr v-for="interview in interviews.data" :key="interview.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3">
                                <Link :href="`/recruitment/interviews/${interview.id}`" class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    {{ interview.title }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                <Link :href="`/recruitment/applications/${interview.job_application_id}`" class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ interview.candidate.full_name }}
                                </Link>
                                <div class="text-xs text-slate-400">{{ interview.job_posting.title }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="getStatusBadgeClasses(interview.type_color)" class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium">
                                    {{ interview.type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                {{ formatDateTime(interview.scheduled_at) }}
                                <div class="text-xs text-slate-400">{{ interview.duration_minutes }} min</div>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="getStatusBadgeClasses(interview.status_color)" class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium">
                                    {{ interview.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="p in interview.panelists" :key="p.id" class="text-xs">
                                        {{ p.employee.full_name }}<span v-if="p.is_lead" class="text-blue-500">*</span><span v-if="interview.panelists.indexOf(p) < interview.panelists.length - 1">,</span>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="interviews.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-4 py-3 dark:border-slate-700">
                    <p class="text-sm text-slate-500">
                        Showing {{ (interviews.current_page - 1) * interviews.per_page + 1 }} to {{ Math.min(interviews.current_page * interviews.per_page, interviews.total) }} of {{ interviews.total }}
                    </p>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in interviews.links"
                            :key="link.label"
                            :href="link.url ?? ''"
                            class="rounded px-3 py-1 text-sm"
                            :class="link.active ? 'bg-blue-600 text-white' : link.url ? 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' : 'text-slate-300 dark:text-slate-600'"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
