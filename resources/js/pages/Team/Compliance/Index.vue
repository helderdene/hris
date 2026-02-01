<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
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
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Employee {
    id: number;
    employee_number: string;
    full_name: string;
    department: string | null;
    position: string | null;
}

interface Course {
    id: number;
    course: {
        id: number;
        title: string;
        code: string;
    };
}

interface Assignment {
    id: number;
    compliance_course: Course;
    employee: Employee;
    status: string;
    status_label: string;
    status_color: string;
    due_date: string | null;
    completion_percentage: number;
    days_until_due: number | null;
    is_overdue: boolean;
    is_due_soon: boolean;
}

interface Stats {
    team_size: number;
    total_assignments: number;
    completed: number;
    overdue: number;
    in_progress: number;
    pending: number;
    compliance_rate: number;
}

interface TeamMember {
    id: number;
    full_name: string;
    employee_number: string;
}

interface StatusOption {
    value: string;
    label: string;
}

interface Filters {
    status: string | null;
    employee_id: string | null;
}

const props = defineProps<{
    assignments: Assignment[];
    stats: Stats;
    teamMembers: TeamMember[];
    filters?: Filters;
    statusOptions?: StatusOption[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Team Compliance', href: '/team/compliance' },
];

const statusFilter = ref(props.filters?.status ?? 'all');
const employeeFilter = ref(props.filters?.employee_id ?? 'all');

const assignmentsData = computed(() => props.assignments ?? []);
const statsData = computed(() => props.stats ?? {
    team_size: 0,
    total_assignments: 0,
    completed: 0,
    overdue: 0,
    in_progress: 0,
    pending: 0,
    compliance_rate: 100,
});
const teamMembersData = computed(() => props.teamMembers ?? []);
const statusOptionsData = computed(() => props.statusOptions ?? []);

function applyFilters() {
    router.get('/team/compliance', {
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        employee_id: employeeFilter.value !== 'all' ? employeeFilter.value : undefined,
    }, { preserveState: true });
}

function getStatusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'overdue':
        case 'expired':
            return 'destructive';
        case 'in_progress':
            return 'secondary';
        default:
            return 'outline';
    }
}

function getComplianceRateColor(rate: number): string {
    if (rate >= 90) return 'text-green-600 dark:text-green-400';
    if (rate >= 70) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString();
}
</script>

<template>
    <Head :title="`Team Compliance - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Team Compliance
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Track your team's compliance training progress.
                </p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Team Size</CardDescription>
                        <CardTitle class="text-2xl">
                            {{ statsData.team_size }}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Compliance Rate</CardDescription>
                        <CardTitle :class="getComplianceRateColor(statsData.compliance_rate)" class="text-2xl">
                            {{ statsData.compliance_rate }}%
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Overdue</CardDescription>
                        <CardTitle :class="statsData.overdue > 0 ? 'text-red-600 dark:text-red-400' : ''" class="text-2xl">
                            {{ statsData.overdue }}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>In Progress</CardDescription>
                        <CardTitle class="text-2xl text-blue-600 dark:text-blue-400">
                            {{ statsData.in_progress }}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Completed</CardDescription>
                        <CardTitle class="text-2xl text-green-600 dark:text-green-400">
                            {{ statsData.completed }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <Select :model-value="statusFilter" @update:model-value="(v) => { statusFilter = v; applyFilters(); }">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Status</SelectItem>
                        <SelectItem
                            v-for="option in statusOptionsData"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="employeeFilter" @update:model-value="(v) => { employeeFilter = v; applyFilters(); }">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Team Member" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Team Members</SelectItem>
                        <SelectItem
                            v-for="member in teamMembersData"
                            :key="member.id"
                            :value="String(member.id)"
                        >
                            {{ member.full_name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Assignments Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Training Assignments</CardTitle>
                    <CardDescription>
                        {{ statsData.total_assignments }} total assignments
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="assignmentsData.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead>
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Employee
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Course
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Status
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Progress
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Due Date
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                <tr
                                    v-for="assignment in assignmentsData"
                                    :key="assignment.id"
                                    class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                >
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-medium text-slate-900 dark:text-slate-100">
                                            {{ assignment.employee?.full_name }}
                                        </div>
                                        <div class="text-sm text-slate-500 dark:text-slate-400">
                                            {{ assignment.employee?.employee_number }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-sm text-slate-900 dark:text-slate-100">
                                            {{ assignment.compliance_course?.course?.title }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <Badge :variant="getStatusBadgeVariant(assignment.status)">
                                            {{ assignment.status_label }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <Progress :model-value="assignment.completion_percentage" class="h-2 w-20" />
                                            <span class="text-sm text-slate-500 dark:text-slate-400">
                                                {{ assignment.completion_percentage }}%
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div :class="{
                                            'text-red-600 dark:text-red-400 font-medium': assignment.is_overdue,
                                            'text-amber-600 dark:text-amber-400': assignment.is_due_soon && !assignment.is_overdue,
                                            'text-slate-600 dark:text-slate-400': !assignment.is_overdue && !assignment.is_due_soon,
                                        }">
                                            {{ formatDate(assignment.due_date) }}
                                            <div v-if="assignment.days_until_due !== null" class="text-xs">
                                                <template v-if="assignment.days_until_due < 0">
                                                    {{ Math.abs(assignment.days_until_due) }}d overdue
                                                </template>
                                                <template v-else-if="assignment.days_until_due === 0">
                                                    Due today
                                                </template>
                                                <template v-else>
                                                    {{ assignment.days_until_due }}d left
                                                </template>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="flex flex-col items-center justify-center py-12">
                        <svg
                            class="h-12 w-12 text-slate-400 dark:text-slate-500"
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
                        <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                            No team assignments
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Your team has no compliance training assignments yet.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
