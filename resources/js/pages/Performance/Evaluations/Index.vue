<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { computed, ref, watch } from 'vue';

interface PerformanceInstance {
    id: number;
    name: string;
    cycle_name: string | null;
    year: number;
    status: string;
    status_label: string;
}

interface EvaluationStatus {
    value: string;
    label: string;
}

interface ParticipantProgress {
    total_reviewers: number;
    submitted_reviewers: number;
    percentage: number;
}

interface Participant {
    id: number;
    employee: {
        id: number;
        full_name: string;
        employee_number: string;
        position: { id: number; title: string } | null;
        department: { id: number; name: string } | null;
    };
    instance: {
        id: number;
        name: string;
        year: number;
    };
    evaluation_status: string;
    evaluation_status_label: string;
    evaluation_status_color_class: string;
    progress: ParticipantProgress;
    self_evaluation_due_date: string | null;
    peer_review_due_date: string | null;
    manager_review_due_date: string | null;
}

interface Filters {
    instance_id: number | null;
    evaluation_status: string | null;
    department_id: number | null;
    search: string | null;
}

const props = defineProps<{
    participants: {
        data: Participant[];
        links: unknown;
        meta: unknown;
    };
    instances: PerformanceInstance[];
    evaluationStatuses: EvaluationStatus[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Performance', href: '/performance/kpis' },
    { title: '360 Evaluations', href: '/performance/evaluations' },
];

const selectedInstanceId = ref(
    props.filters.instance_id ? String(props.filters.instance_id) : 'all',
);
const selectedStatus = ref(props.filters.evaluation_status || 'all');
const searchQuery = ref(props.filters.search || '');

const participantsData = computed(() => props.participants?.data ?? []);
const instancesData = computed(() => props.instances ?? []);

const instanceOptions = computed(() => {
    return instancesData.value.map((instance) => ({
        value: String(instance.id),
        label: `${instance.name} (${instance.year})`,
    }));
});

const statusOptions = computed(() => {
    return props.evaluationStatuses.map((status) => ({
        value: status.value,
        label: status.label,
    }));
});

function applyFilters() {
    router.get(
        '/performance/evaluations',
        {
            instance_id: selectedInstanceId.value !== 'all' ? selectedInstanceId.value : undefined,
            evaluation_status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
            search: searchQuery.value || undefined,
        },
        { preserveState: true },
    );
}

function handleInstanceChange(instanceId: string) {
    selectedInstanceId.value = instanceId;
    applyFilters();
}

function handleStatusChange(status: string) {
    selectedStatus.value = status;
    applyFilters();
}

function handleSearch() {
    applyFilters();
}

function clearFilters() {
    selectedInstanceId.value = 'all';
    selectedStatus.value = 'all';
    searchQuery.value = '';
    router.get('/performance/evaluations', {}, { preserveState: true });
}

function getProgressBarColor(percentage: number): string {
    if (percentage >= 100) return 'bg-emerald-500';
    if (percentage >= 75) return 'bg-blue-500';
    if (percentage >= 50) return 'bg-yellow-500';
    return 'bg-slate-400';
}
</script>

<template>
    <Head :title="`360 Evaluations - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        360-Degree Evaluations
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        View and manage employee performance evaluations with multi-source feedback.
                    </p>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                    <div class="flex-1">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Performance Cycle
                        </label>
                        <Select
                            :model-value="selectedInstanceId"
                            @update:model-value="handleInstanceChange"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="All cycles" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All cycles</SelectItem>
                                <SelectItem
                                    v-for="option in instanceOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex-1">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Status
                        </label>
                        <Select
                            :model-value="selectedStatus"
                            @update:model-value="handleStatusChange"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                <SelectItem
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex-1">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Search
                        </label>
                        <div class="flex gap-2">
                            <Input
                                v-model="searchQuery"
                                type="search"
                                placeholder="Search by name or employee number..."
                                @keyup.enter="handleSearch"
                            />
                            <Button variant="outline" @click="handleSearch">
                                Search
                            </Button>
                        </div>
                    </div>

                    <Button variant="ghost" @click="clearFilters">
                        Clear
                    </Button>
                </div>
            </div>

            <!-- Participants Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Employee
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Cycle
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-center text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Progress
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="participant in participantsData"
                                :key="participant.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-200 text-sm font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                        >
                                            {{
                                                participant.employee.full_name
                                                    .split(' ')
                                                    .map((n) => n[0])
                                                    .join('')
                                                    .slice(0, 2)
                                            }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ participant.employee.full_name }}
                                            </p>
                                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                                {{ participant.employee.position?.title || 'No Position' }}
                                                <span v-if="participant.employee.department">
                                                    · {{ participant.employee.department.name }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm text-slate-900 dark:text-slate-100">
                                        {{ participant.instance.name }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ participant.instance.year }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                        :class="participant.evaluation_status_color_class"
                                    >
                                        {{ participant.evaluation_status_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <div
                                            class="h-2 w-20 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
                                        >
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="getProgressBarColor(participant.progress.percentage)"
                                                :style="{ width: `${participant.progress.percentage}%` }"
                                            />
                                        </div>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ participant.progress.submitted_reviewers }}/{{
                                                participant.progress.total_reviewers
                                            }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="`/performance/evaluations/${participant.id}`">
                                        <Button variant="outline" size="sm">
                                            View
                                        </Button>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div
                    v-if="participantsData.length === 0"
                    class="px-6 py-12 text-center"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                        />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No evaluations found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Try adjusting your filters or select a different performance cycle.
                    </p>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
