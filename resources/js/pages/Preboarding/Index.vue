<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import PreboardingProgressBar from '@/components/preboarding/PreboardingProgressBar.vue';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { StatusOption } from '@/types/preboarding';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardCheck, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface ChecklistSummary {
    id: number;
    status: string;
    status_label: string;
    status_color: string;
    deadline: string | null;
    completed_at: string | null;
    progress_percentage: number;
    candidate_name: string | null;
    candidate_email: string | null;
    position_title: string | null;
    total_items: number;
    approved_items: number;
    created_at: string | null;
}

const props = defineProps<{
    checklists: {
        data: ChecklistSummary[];
        links: unknown[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: { status: string | null; search: string | null };
    statuses: StatusOption[];
}>();

const { tenantName } = useTenant();
const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
let searchTimeout: ReturnType<typeof setTimeout>;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pre-boarding', href: '/preboarding' },
];

function applyFilters() {
    router.get(
        '/preboarding',
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
    <Head :title="`Pre-boarding Management - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    Pre-boarding Checklists
                </h1>
            </div>

            <!-- Filters -->
            <Card class="mb-6">
                <CardContent class="flex items-center gap-4 pt-6">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by candidate name or email..."
                            class="w-full rounded-md border-slate-300 pl-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                        />
                    </div>
                    <select
                        v-model="statusFilter"
                        class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
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
                        <p class="text-slate-500 dark:text-slate-400">No checklists found.</p>
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                <th class="px-6 py-3 font-medium">Candidate</th>
                                <th class="px-6 py-3 font-medium">Position</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">Progress</th>
                                <th class="px-6 py-3 font-medium">Deadline</th>
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
                                        {{ checklist.candidate_name }}
                                    </div>
                                    <div class="text-xs text-slate-500">{{ checklist.candidate_email }}</div>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-400">
                                    {{ checklist.position_title }}
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
                                    <PreboardingProgressBar
                                        :percentage="checklist.progress_percentage"
                                        :approved-count="checklist.approved_items"
                                        :total-count="checklist.total_items"
                                    />
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-400">
                                    {{ checklist.deadline ?? '---' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="`/preboarding/${checklist.id}`">
                                        <Button size="sm" variant="outline">Review</Button>
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
