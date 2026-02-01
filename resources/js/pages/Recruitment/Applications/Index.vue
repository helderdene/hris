<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ApplicationItem {
    id: number;
    candidate: { id: number; full_name: string; email: string };
    status: string;
    status_label: string;
    status_color: string;
    source_label: string;
    applied_at: string | null;
    allowed_transitions: { value: string; label: string; color: string }[];
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

const props = defineProps<{
    jobPosting: { id: number; title: string };
    applications: { data: ApplicationItem[]; links: any; meta: any };
    statuses: StatusOption[];
    filters: { status: string | null };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/job-postings' },
    { title: 'Job Postings', href: '/recruitment/job-postings' },
    { title: props.jobPosting.title, href: `/recruitment/job-postings/${props.jobPosting.id}` },
    { title: 'Applications', href: `/recruitment/job-postings/${props.jobPosting.id}/applications` },
];

const isProcessing = ref(false);

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

async function quickTransition(applicationId: number, status: string) {
    if (isProcessing.value) {
        return;
    }
    isProcessing.value = true;

    try {
        const body: Record<string, string> = { status };
        if (status === 'rejected') {
            const reason = prompt('Please provide a rejection reason:');
            if (!reason) {
                isProcessing.value = false;
                return;
            }
            body.rejection_reason = reason;
        }

        const response = await fetch(`/api/applications/${applicationId}/status`, {
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

function filterByStatus(status: string | null) {
    router.get(
        `/recruitment/job-postings/${props.jobPosting.id}/applications`,
        { status: status || undefined },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="`Applications - ${jobPosting.title} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Applications</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ jobPosting.title }}</p>
            </div>

            <!-- Status Filters -->
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    @click="filterByStatus(null)"
                    class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                    :class="!filters.status ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'"
                >
                    All
                </button>
                <button
                    v-for="status in statuses"
                    :key="status.value"
                    @click="filterByStatus(status.value)"
                    class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                    :class="filters.status === status.value ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'"
                >
                    {{ status.label }}
                </button>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Candidate</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Status</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Source</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Applied</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="app in applications.data"
                            :key="app.id"
                            class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/recruitment/applications/${app.id}`"
                                    class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                >
                                    {{ app.candidate.full_name }}
                                </Link>
                                <p class="text-xs text-slate-500">{{ app.candidate.email }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium"
                                    :class="getStatusBadgeClasses(app.status_color)"
                                >
                                    {{ app.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ app.source_label }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ app.applied_at?.split(' ')[0] }}</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1">
                                    <button
                                        v-for="transition in app.allowed_transitions.slice(0, 2)"
                                        :key="transition.value"
                                        @click="quickTransition(app.id, transition.value)"
                                        :disabled="isProcessing"
                                        class="rounded px-2 py-1 text-xs font-medium transition-colors"
                                        :class="getStatusBadgeClasses(transition.color)"
                                    >
                                        {{ transition.label }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="applications.data.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                No applications found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </TenantLayout>
</template>
