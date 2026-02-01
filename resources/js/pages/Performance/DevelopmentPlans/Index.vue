<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { BookOpen, TrendingUp, User } from 'lucide-vue-next';
import { ref } from 'vue';

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
    employee: {
        id: number;
        full_name: string;
        position: string | null;
    };
}

interface EnumOption {
    value: string;
    label: string;
    description?: string;
    color?: string;
}

interface Filters {
    status: string | null;
    employee_id: string | null;
    pending_only: boolean;
}

const props = defineProps<{
    plans: {
        data: DevelopmentPlan[];
        links: unknown;
        meta: unknown;
    };
    pendingCount: number;
    statuses: EnumOption[];
    filters: Filters;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Performance', href: '#' },
    { title: 'Development Plans', href: '/performance/development-plans' },
];

const selectedStatus = ref(props.filters.status ?? '');
const pendingOnly = ref(props.filters.pending_only ?? false);

function handleFilterChange() {
    router.get(
        '/performance/development-plans',
        {
            status: selectedStatus.value || undefined,
            pending_only: pendingOnly.value || undefined,
        },
        { preserveState: true },
    );
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
                        Development Plans
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Review and manage employee development plans.
                    </p>
                </div>

                <Badge v-if="pendingCount > 0" variant="destructive" class="h-8 px-3">
                    {{ pendingCount }} pending approval
                </Badge>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-4">
                <Select v-model="selectedStatus" @update:model-value="handleFilterChange">
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

                <div class="flex items-center gap-2">
                    <Checkbox
                        id="pending-only"
                        :checked="pendingOnly"
                        @update:checked="val => { pendingOnly = val; handleFilterChange(); }"
                    />
                    <label for="pending-only" class="text-sm text-slate-600 dark:text-slate-400">
                        Pending Approval Only
                    </label>
                </div>
            </div>

            <!-- Plans List -->
            <div class="flex flex-col gap-4">
                <Card
                    v-for="plan in plans.data"
                    :key="plan.id"
                    class="cursor-pointer transition-shadow hover:shadow-md"
                    @click="router.visit(`/performance/development-plans/${plan.id}`)"
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
                                    <div class="mt-1 flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                        <User class="h-4 w-4" />
                                        <span>{{ plan.employee.full_name }}</span>
                                        <span v-if="plan.employee.position" class="text-slate-400">
                                            - {{ plan.employee.position }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                                        <span :class="plan.status_color" class="rounded-full px-2 py-0.5 text-xs font-medium">
                                            {{ plan.status_label }}
                                        </span>
                                        <span v-if="plan.items_count" class="text-slate-500">{{ plan.items_count }} items</span>
                                        <span v-if="plan.target_completion_date" class="text-slate-500">
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
                    v-if="plans.data.length === 0"
                    class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
                >
                    <BookOpen class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No development plans found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        No plans match your current filters.
                    </p>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
