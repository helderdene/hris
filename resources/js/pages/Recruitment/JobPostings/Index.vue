<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface JobPosting {
    id: number;
    slug: string;
    title: string;
    department: { id: number; name: string };
    position: { id: number; name: string } | null;
    created_by_employee: { id: number; full_name: string };
    employment_type: string;
    employment_type_label: string;
    location: string;
    status: string;
    status_label: string;
    status_color: string;
    published_at: string | null;
    created_at: string;
    can_be_edited: boolean;
    can_be_published: boolean;
    can_be_closed: boolean;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface Department {
    id: number;
    name: string;
}

interface Filters {
    status: string | null;
    department_id: number | null;
}

const props = defineProps<{
    employee: { id: number; full_name: string } | null;
    postings: { data: JobPosting[]; links: any; meta: any };
    departments: Department[];
    statuses: StatusOption[];
    employmentTypes: { value: string; label: string }[];
    salaryDisplayOptions: { value: string; label: string }[];
    filters: Filters;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/job-postings' },
    { title: 'Job Postings', href: '/recruitment/job-postings' },
];

const selectedStatus = ref(props.filters.status || 'all');
const selectedDepartment = ref(props.filters.department_id ? String(props.filters.department_id) : 'all');
const isProcessing = ref(false);

function reloadPage() {
    const params: Record<string, string> = {};
    if (selectedStatus.value !== 'all') params.status = selectedStatus.value;
    if (selectedDepartment.value !== 'all') params.department_id = selectedDepartment.value;
    router.get(window.location.pathname, params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function getStatusBadgeClasses(color: string): string {
    switch (color) {
        case 'green':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'red':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        case 'amber':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'blue':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'slate':
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function handleStatusChange(value: string) {
    selectedStatus.value = value;
    reloadPage();
}

function handleDepartmentChange(value: string) {
    selectedDepartment.value = value;
    reloadPage();
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function executeAction(postingId: number, action: string) {
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/job-postings/${postingId}/${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            reloadPage();
        }
    } catch {
    } finally {
        isProcessing.value = false;
    }
}

async function executeDelete(postingId: number) {
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/job-postings/${postingId}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            reloadPage();
        }
    } catch {
    } finally {
        isProcessing.value = false;
    }
}
</script>

<template>
    <Head :title="`Job Postings - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Job Postings
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage job postings for your careers page.
                    </p>
                </div>
                <Link href="/recruitment/job-postings/create">
                    <Button>New Job Posting</Button>
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <Select :model-value="selectedStatus" @update:model-value="handleStatusChange">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem v-for="status in statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="selectedDepartment" @update:model-value="handleDepartmentChange">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Department" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Departments</SelectItem>
                        <SelectItem v-for="dept in departments" :key="dept.id" :value="String(dept.id)">
                            {{ dept.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div v-if="postings.data.length > 0" class="hidden md:block">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Department</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Location</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</th>
                                <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-for="posting in postings.data" :key="posting.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <Link
                                        :href="`/recruitment/job-postings/${posting.id}`"
                                        class="font-medium text-slate-900 hover:underline dark:text-slate-100"
                                    >
                                        {{ posting.title }}
                                    </Link>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ posting.department.name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ posting.location }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ posting.employment_type_label }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(posting.status_color)"
                                    >
                                        {{ posting.status_label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                                <span class="sr-only">Open menu</span>
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                                </svg>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem as-child>
                                                <Link :href="`/recruitment/job-postings/${posting.id}`">View Details</Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="posting.can_be_edited" as-child>
                                                <Link :href="`/recruitment/job-postings/${posting.id}/edit`">Edit</Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="posting.can_be_published"
                                                @click="executeAction(posting.id, 'publish')"
                                            >
                                                Publish
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="posting.can_be_closed"
                                                class="text-amber-600 focus:text-amber-600"
                                                @click="executeAction(posting.id, 'close')"
                                            >
                                                Close
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="posting.status === 'closed'"
                                                @click="executeAction(posting.id, 'archive')"
                                            >
                                                Archive
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="posting.status === 'draft'"
                                                class="text-red-600 focus:text-red-600"
                                                @click="executeDelete(posting.id)"
                                            >
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile View -->
                <div v-if="postings.data.length > 0" class="divide-y divide-slate-200 md:hidden dark:divide-slate-700">
                    <div v-for="posting in postings.data" :key="posting.id" class="space-y-2 p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <Link
                                    :href="`/recruitment/job-postings/${posting.id}`"
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ posting.title }}
                                </Link>
                                <div class="text-sm text-slate-500">{{ posting.department.name }} - {{ posting.location }}</div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClasses(posting.status_color)"
                            >
                                {{ posting.status_label }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="postings.data.length === 0" class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">No job postings found</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Create a new job posting to start attracting candidates.
                    </p>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
