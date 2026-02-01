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
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Course {
    id: number;
    course: {
        id: number;
        title: string;
        code: string;
    };
}

interface ModuleProgress {
    id: number;
    compliance_module_id: number;
    status: string;
    status_label: string;
    progress_percentage: number;
    module?: {
        id: number;
        title: string;
        content_type_label: string;
    };
}

interface Assignment {
    id: number;
    compliance_course: Course;
    status: string;
    status_label: string;
    status_color: string;
    assigned_date: string;
    due_date: string | null;
    completed_at: string | null;
    final_score: number | null;
    completion_percentage: number;
    days_until_due: number | null;
    is_overdue: boolean;
    is_due_soon: boolean;
    progress?: ModuleProgress[];
}

interface Stats {
    total: number;
    completed: number;
    overdue: number;
    in_progress: number;
    pending: number;
    compliance_rate: number;
}

interface StatusOption {
    value: string;
    label: string;
}

interface Filters {
    status: string | null;
}

const props = defineProps<{
    assignments: Assignment[];
    stats: Stats;
    filters?: Filters;
    statusOptions?: StatusOption[];
}>();

const { tenantName, primaryColor } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'My Compliance Training', href: '/my/compliance' },
];

const statusFilter = ref(props.filters?.status ?? 'all');

const assignmentsData = computed(() => props.assignments ?? []);
const statsData = computed(() => props.stats ?? {
    total: 0,
    completed: 0,
    overdue: 0,
    in_progress: 0,
    pending: 0,
    compliance_rate: 100,
});
const statusOptionsData = computed(() => props.statusOptions ?? []);

function handleFilterChange(value: string) {
    statusFilter.value = value;
    router.get('/my/compliance', {
        status: value !== 'all' ? value : undefined,
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
    <Head :title="`My Compliance Training - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        My Compliance Training
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Complete your mandatory training assignments.
                    </p>
                </div>
                <Link href="/my/compliance/certificates">
                    <Button variant="outline">
                        View Certificates
                    </Button>
                </Link>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Compliance Rate</CardDescription>
                        <CardTitle :class="getComplianceRateColor(statsData.compliance_rate)" class="text-3xl">
                            {{ statsData.compliance_rate }}%
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ statsData.completed }} of {{ statsData.total }} completed
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Overdue</CardDescription>
                        <CardTitle :class="statsData.overdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-slate-100'" class="text-3xl">
                            {{ statsData.overdue }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Requires immediate attention
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>In Progress</CardDescription>
                        <CardTitle class="text-3xl text-blue-600 dark:text-blue-400">
                            {{ statsData.in_progress }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Continue where you left off
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Pending</CardDescription>
                        <CardTitle class="text-3xl text-amber-600 dark:text-amber-400">
                            {{ statsData.pending }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Not yet started
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <div class="flex items-center gap-3">
                <Select :model-value="statusFilter" @update:model-value="handleFilterChange">
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
            </div>

            <!-- Assignments List -->
            <div v-if="assignmentsData.length > 0" class="flex flex-col gap-4">
                <Link
                    v-for="assignment in assignmentsData"
                    :key="assignment.id"
                    :href="`/my/compliance/${assignment.id}`"
                    class="group"
                >
                    <Card class="transition-all hover:border-slate-300 hover:shadow-md dark:hover:border-slate-600">
                        <CardContent class="p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-slate-900 dark:text-slate-100 group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                            {{ assignment.compliance_course?.course?.title ?? 'Unknown Course' }}
                                        </h3>
                                        <Badge :variant="getStatusBadgeVariant(assignment.status)">
                                            {{ assignment.status_label }}
                                        </Badge>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-4 text-sm text-slate-500 dark:text-slate-400">
                                        <span>Assigned: {{ formatDate(assignment.assigned_date) }}</span>
                                        <span v-if="assignment.due_date" :class="{
                                            'text-red-600 dark:text-red-400 font-medium': assignment.is_overdue,
                                            'text-amber-600 dark:text-amber-400': assignment.is_due_soon && !assignment.is_overdue,
                                        }">
                                            Due: {{ formatDate(assignment.due_date) }}
                                            <template v-if="assignment.days_until_due !== null">
                                                <span v-if="assignment.days_until_due < 0">
                                                    ({{ Math.abs(assignment.days_until_due) }} days overdue)
                                                </span>
                                                <span v-else-if="assignment.days_until_due === 0">
                                                    (Due today)
                                                </span>
                                                <span v-else>
                                                    ({{ assignment.days_until_due }} days left)
                                                </span>
                                            </template>
                                        </span>
                                        <span v-if="assignment.final_score !== null">
                                            Score: {{ assignment.final_score }}%
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center gap-2">
                                        <Progress :model-value="assignment.completion_percentage" class="h-2 w-24" />
                                        <span class="text-sm font-medium text-slate-600 dark:text-slate-400">
                                            {{ assignment.completion_percentage }}%
                                        </span>
                                    </div>
                                    <Button
                                        :style="assignment.status !== 'completed' ? { backgroundColor: primaryColor } : {}"
                                        :variant="assignment.status === 'completed' ? 'outline' : 'default'"
                                    >
                                        {{ assignment.status === 'completed' ? 'Review' : assignment.status === 'in_progress' ? 'Continue' : 'Start' }}
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            <!-- Empty State -->
            <Card v-else>
                <CardContent class="flex flex-col items-center justify-center py-12">
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
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                        />
                    </svg>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        All caught up!
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        You have no pending compliance training at this time.
                    </p>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
