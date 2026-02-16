<script setup lang="ts">
import { Button } from '@/components/ui/button';
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
import { Clock, FileText } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
    department: string | null;
    position: string | null;
}

interface OvertimeRequest {
    id: number;
    reference_number: string;
    employee: Employee;
    overtime_date: string;
    expected_minutes: number;
    expected_hours_formatted: string;
    overtime_type: string;
    overtime_type_label: string;
    overtime_type_color: string;
    reason: string;
    status: string;
    status_label: string;
    status_color: string;
    submitted_at: string | null;
    created_at: string;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface OvertimeTypeOption {
    value: string;
    label: string;
    color: string;
}

interface PaginatedData {
    data: OvertimeRequest[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    requests: PaginatedData;
    statuses: StatusOption[];
    overtimeTypes: OvertimeTypeOption[];
    filters: {
        status: string | null;
        overtime_type: string | null;
        search: string | null;
    };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Time & Attendance', href: '/attendance' },
    { title: 'OT Requests', href: '/overtime/requests' },
];

const selectedStatus = ref(props.filters.status || 'all');
const selectedType = ref(props.filters.overtime_type || 'all');

function reloadPage() {
    const params: Record<string, string> = {};
    if (selectedStatus.value !== 'all') {
        params.status = selectedStatus.value;
    }
    if (selectedType.value !== 'all') {
        params.overtime_type = selectedType.value;
    }
    router.get(window.location.pathname, params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function handleStatusChange(value: string) {
    selectedStatus.value = value;
    reloadPage();
}

function handleTypeChange(value: string) {
    selectedType.value = value;
    reloadPage();
}

function getStatusBadgeClasses(color: string): string {
    switch (color) {
        case 'green':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'red':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        case 'amber':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'slate':
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getTypeBadgeClasses(color: string): string {
    switch (color) {
        case 'blue':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'orange':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300';
        case 'purple':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head :title="`OT Requests - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Overtime Requests
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    View and manage all overtime requests across the organization.
                </p>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <Select :model-value="selectedStatus" @update:model-value="handleStatusChange">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="selectedType" @update:model-value="handleTypeChange">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="OT Type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Types</SelectItem>
                        <SelectItem
                            v-for="type in overtimeTypes"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Requests Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div v-if="requests.data.length > 0">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Reference
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Employee
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Type
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Duration
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Status
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="req in requests.data"
                                :key="req.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="whitespace-nowrap px-6 py-4">
                                    <Link
                                        :href="`/overtime/requests/${req.id}`"
                                        class="font-medium text-slate-900 hover:underline dark:text-slate-100"
                                    >
                                        {{ req.reference_number }}
                                    </Link>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                        {{ req.employee.full_name }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ req.employee.department }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ formatDate(req.overtime_date) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getTypeBadgeClasses(req.overtime_type_color)"
                                    >
                                        {{ req.overtime_type_label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ req.expected_hours_formatted }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(req.status_color)"
                                    >
                                        {{ req.status_label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <Link :href="`/overtime/requests/${req.id}`">
                                        <Button variant="ghost" size="sm">View</Button>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="requests.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-6 py-3 dark:border-slate-700">
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Showing {{ (requests.current_page - 1) * requests.per_page + 1 }} to {{ Math.min(requests.current_page * requests.per_page, requests.total) }} of {{ requests.total }}
                        </p>
                        <div class="flex gap-1">
                            <template v-for="link in requests.links" :key="link.label">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="rounded-md px-3 py-1 text-sm"
                                    :class="link.active ? 'bg-blue-500 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'"
                                    v-html="link.label"
                                    preserve-state
                                />
                                <span v-else class="rounded-md px-3 py-1 text-sm text-slate-400" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="px-6 py-12 text-center">
                    <FileText class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No overtime requests found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        No overtime requests match the current filters.
                    </p>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
