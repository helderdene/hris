<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardCheck, Search, Users, Clock, CheckCircle2, AlertCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface ChecklistSummary {
    id: number;
    status: string;
    status_label: string;
    status_color: string;
    start_date: string | null;
    completed_at: string | null;
    progress_percentage: number;
    employee_name: string | null;
    employee_number: string | null;
    employee_email: string | null;
    department: string | null;
    position: string | null;
    template_name: string | null;
    total_items: number;
    completed_items: number;
    created_at: string | null;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface Summary {
    pending: number;
    in_progress: number;
    completed: number;
    overdue: number;
}

const props = defineProps<{
    checklists: {
        data: ChecklistSummary[];
        links: unknown[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: { status: string | null; search: string | null };
    statuses: StatusOption[];
    summary: Summary;
}>();

const { tenantName } = useTenant();
const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
let searchTimeout: ReturnType<typeof setTimeout>;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Onboarding', href: '/onboarding' },
];

function applyFilters() {
    router.get(
        '/onboarding',
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(statusFilter, applyFilters);

function badgeClasses(color: string): string {
    const map: Record<string, string> = {
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
    return map[color] ?? map.slate;
}
</script>

<template>
    <Head :title="`Onboarding Management - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    Onboarding Checklists
                </h1>
                <Link href="/onboarding-templates">
                    <Button variant="outline">Manage Templates</Button>
                </Link>
            </div>

            <!-- Summary Cards -->
            <div class="mb-6 grid grid-cols-4 gap-4">
                <Card>
                    <CardContent class="flex items-center gap-4 pt-6">
                        <div class="rounded-lg bg-slate-100 p-3 dark:bg-slate-800">
                            <Clock class="h-5 w-5 text-slate-600 dark:text-slate-400" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ summary.pending }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Pending</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 pt-6">
                        <div class="rounded-lg bg-blue-100 p-3 dark:bg-blue-900/40">
                            <Users class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ summary.in_progress }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">In Progress</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 pt-6">
                        <div class="rounded-lg bg-green-100 p-3 dark:bg-green-900/40">
                            <CheckCircle2 class="h-5 w-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ summary.completed }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Completed</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 pt-6">
                        <div class="rounded-lg bg-red-100 p-3 dark:bg-red-900/40">
                            <AlertCircle class="h-5 w-5 text-red-600 dark:text-red-400" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ summary.overdue }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Overdue</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card class="mb-6">
                <CardContent class="flex items-center gap-4 pt-6">
                    <div class="relative flex-1">
                        <Search class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by employee name, number, or email..."
                            class="w-full rounded-md border-slate-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                        />
                    </div>
                    <select
                        v-model="statusFilter"
                        class="min-w-[160px] rounded-md border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="">All Statuses</option>
                        <option
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </CardContent>
            </Card>

            <!-- Table -->
            <Card>
                <CardContent class="p-0">
                    <div v-if="checklists.data.length === 0" class="flex flex-col items-center gap-3 py-12 text-center">
                        <ClipboardCheck class="h-12 w-12 text-slate-300 dark:text-slate-600" />
                        <p class="text-slate-500 dark:text-slate-400">No onboarding checklists found.</p>
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                <th class="px-6 py-3 font-medium">Employee</th>
                                <th class="px-6 py-3 font-medium">Department</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">Progress</th>
                                <th class="px-6 py-3 font-medium">Start Date</th>
                                <th class="px-6 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="checklist in checklists.data"
                                :key="checklist.id"
                                class="border-b border-slate-100 dark:border-slate-800"
                            >
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ checklist.employee_name }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ checklist.employee_number }} &middot; {{ checklist.position }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-400">
                                    {{ checklist.department ?? '---' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="badgeClasses(checklist.status_color)"
                                    >
                                        {{ checklist.status_label }}
                                    </span>
                                </td>
                                <td class="w-40 px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                            <div
                                                class="h-full rounded-full bg-blue-500 transition-all"
                                                :style="{ width: `${checklist.progress_percentage}%` }"
                                            />
                                        </div>
                                        <span class="text-xs text-slate-500">
                                            {{ checklist.completed_items }}/{{ checklist.total_items }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-400">
                                    {{ checklist.start_date ?? '---' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="`/onboarding/${checklist.id}`">
                                        <Button size="sm" variant="outline">View</Button>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
