<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { BookOpen, Plus, TrendingUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface DevelopmentPlan {
    id: number;
    title: string;
    status: string;
    status_label: string;
    status_color: string;
    start_date: string | null;
    target_completion_date: string | null;
    progress: number;
    is_overdue: boolean;
    items_count?: number;
    created_at: string;
}

interface Statistics {
    total: number;
    active: number;
    completed: number;
    pending_approval: number;
    overall_progress: number;
}

interface EnumOption {
    value: string;
    label: string;
    description?: string;
    color?: string;
}

interface Filters {
    status: string | null;
}

const props = defineProps<{
    plans: {
        data: DevelopmentPlan[];
        links: unknown;
        meta: unknown;
    };
    statistics: Statistics;
    statuses: EnumOption[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'Development Plans', href: '/my/development-plans' },
];

const selectedStatus = ref(props.filters.status ?? '');

const plansData = computed(() => props.plans?.data ?? []);

function handleFilterChange(value: string) {
    selectedStatus.value = value;
    router.get(
        '/my/development-plans',
        { status: value || undefined },
        { preserveState: true },
    );
}

function handleCreatePlan() {
    router.visit('/my/development-plans/create');
}

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString();
}
</script>

<template>
    <Head :title="`Development Plans - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        My Development Plans
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Track your professional development and growth areas.
                    </p>
                </div>

                <Button @click="handleCreatePlan" :style="{ backgroundColor: primaryColor }">
                    <Plus class="mr-2 h-4 w-4" />
                    Create Plan
                </Button>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                        {{ statistics.active }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">Active Plans</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ statistics.completed }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">Completed</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                        {{ statistics.pending_approval }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">Pending Approval</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                        {{ statistics.overall_progress?.toFixed(0) ?? 0 }}%
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">Overall Progress</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex items-center gap-4">
                <Select :model-value="selectedStatus" @update:model-value="handleFilterChange">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="All Statuses" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">All Statuses</SelectItem>
                        <SelectItem v-for="status in statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Plans List -->
            <div class="flex flex-col gap-4">
                <Card
                    v-for="plan in plansData"
                    :key="plan.id"
                    class="cursor-pointer transition-shadow hover:shadow-md"
                    @click="router.visit(`/my/development-plans/${plan.id}`)"
                >
                    <CardContent class="p-4">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-4">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400">
                                    <BookOpen class="h-5 w-5" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                                        {{ plan.title }}
                                    </h3>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                        <span :class="plan.status_color" class="rounded-full px-2 py-0.5 text-xs font-medium">
                                            {{ plan.status_label }}
                                        </span>
                                        <span v-if="plan.items_count">{{ plan.items_count }} items</span>
                                        <span v-if="plan.target_completion_date">
                                            Due: {{ formatDate(plan.target_completion_date) }}
                                        </span>
                                        <span v-if="plan.is_overdue" class="text-red-600 dark:text-red-400">
                                            Overdue
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <div class="flex items-center gap-2">
                                    <TrendingUp class="h-4 w-4 text-slate-400" />
                                    <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                        {{ plan.progress.toFixed(0) }}%
                                    </span>
                                </div>
                                <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div
                                        class="h-full rounded-full bg-indigo-500 transition-all"
                                        :style="{ width: `${plan.progress}%` }"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty State -->
                <div
                    v-if="plansData.length === 0"
                    class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
                >
                    <BookOpen class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No development plans yet
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Create your first development plan to start tracking your growth.
                    </p>
                    <div class="mt-6">
                        <Button @click="handleCreatePlan" :style="{ backgroundColor: primaryColor }">
                            Create Your First Plan
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
