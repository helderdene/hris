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
import { ref } from 'vue';

interface JobPostingDetail {
    id: number;
    slug: string;
    title: string;
    job_requisition: { id: number; reference_number: string } | null;
    department: { id: number; name: string };
    position: { id: number; name: string } | null;
    created_by_employee: { id: number; full_name: string };
    description: string;
    requirements: string | null;
    benefits: string | null;
    employment_type: string;
    employment_type_label: string;
    location: string;
    salary_display_option: string | null;
    salary_display_option_label: string | null;
    salary_range_min: number | null;
    salary_range_max: number | null;
    application_instructions: string | null;
    status: string;
    status_label: string;
    status_color: string;
    published_at: string | null;
    closed_at: string | null;
    created_at: string;
    can_be_edited: boolean;
    can_be_published: boolean;
    can_be_closed: boolean;
}

const props = defineProps<{
    posting: JobPostingDetail;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/job-postings' },
    { title: 'Job Postings', href: '/recruitment/job-postings' },
    { title: props.posting.title, href: `/recruitment/job-postings/${props.posting.id}` },
];

const isProcessing = ref(false);
const showConfirmDialog = ref(false);
const confirmTitle = ref('');
const confirmDescription = ref('');
const confirmAction = ref<(() => void) | null>(null);

function getStatusBadgeClasses(color: string): string {
    switch (color) {
        case 'green':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'red':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        case 'slate':
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function confirmPublish() {
    confirmTitle.value = 'Publish Job Posting';
    confirmDescription.value = 'This will make the job posting visible on the public careers page.';
    confirmAction.value = () => executeAction('publish');
    showConfirmDialog.value = true;
}

function confirmClose() {
    confirmTitle.value = 'Close Job Posting';
    confirmDescription.value = 'This will remove the job posting from the public careers page.';
    confirmAction.value = () => executeAction('close');
    showConfirmDialog.value = true;
}

function confirmArchive() {
    confirmTitle.value = 'Archive Job Posting';
    confirmDescription.value = 'This will archive the job posting. It cannot be undone.';
    confirmAction.value = () => executeAction('archive');
    showConfirmDialog.value = true;
}

async function executeAction(action: string) {
    showConfirmDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/job-postings/${props.posting.id}/${action}`, {
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
        }
    } catch {
    } finally {
        isProcessing.value = false;
    }
}
</script>

<template>
    <Head :title="`${posting.title} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <!-- Header -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ posting.title }}
                        </h1>
                        <span
                            class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium"
                            :class="getStatusBadgeClasses(posting.status_color)"
                        >
                            {{ posting.status_label }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ posting.department.name }} &middot; {{ posting.location }} &middot; {{ posting.employment_type_label }}
                    </p>
                    <p v-if="posting.job_requisition" class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                        From requisition {{ posting.job_requisition.reference_number }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/recruitment/job-postings/${posting.id}/applications`">
                        <Button variant="outline" size="sm">
                            View Applications
                        </Button>
                    </Link>
                    <Link v-if="posting.can_be_edited" :href="`/recruitment/job-postings/${posting.id}/edit`">
                        <Button variant="outline" size="sm">Edit</Button>
                    </Link>
                    <Button v-if="posting.can_be_published" size="sm" @click="confirmPublish" :disabled="isProcessing">
                        Publish
                    </Button>
                    <Button
                        v-if="posting.can_be_closed"
                        variant="outline"
                        size="sm"
                        class="text-amber-600"
                        @click="confirmClose"
                        :disabled="isProcessing"
                    >
                        Close
                    </Button>
                    <Button
                        v-if="posting.status === 'closed'"
                        variant="outline"
                        size="sm"
                        @click="confirmArchive"
                        :disabled="isProcessing"
                    >
                        Archive
                    </Button>
                </div>
            </div>

            <!-- Content -->
            <div class="space-y-6">
                <!-- Info Cards -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                        <p class="text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Salary</p>
                        <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ posting.salary_display_option_label || 'Not specified' }}
                        </p>
                        <p v-if="posting.salary_range_min && posting.salary_range_max" class="text-xs text-slate-500">
                            {{ posting.salary_range_min.toLocaleString() }} - {{ posting.salary_range_max.toLocaleString() }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                        <p class="text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Published</p>
                        <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ posting.published_at || 'Not yet' }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                        <p class="text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Created By</p>
                        <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ posting.created_by_employee.full_name }}
                        </p>
                    </div>
                </div>

                <!-- Description -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Description</h2>
                    <div class="prose dark:prose-invert max-w-none whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ posting.description }}</div>
                </div>

                <div v-if="posting.requirements" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Requirements</h2>
                    <div class="prose dark:prose-invert max-w-none whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ posting.requirements }}</div>
                </div>

                <div v-if="posting.benefits" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Benefits</h2>
                    <div class="prose dark:prose-invert max-w-none whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ posting.benefits }}</div>
                </div>

                <div v-if="posting.application_instructions" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">How to Apply</h2>
                    <div class="prose dark:prose-invert max-w-none whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ posting.application_instructions }}</div>
                </div>
            </div>
        </div>

        <!-- Confirm Dialog -->
        <Dialog v-model:open="showConfirmDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ confirmTitle }}</DialogTitle>
                    <DialogDescription>{{ confirmDescription }}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showConfirmDialog = false">Cancel</Button>
                    <Button @click="confirmAction?.()">Confirm</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
