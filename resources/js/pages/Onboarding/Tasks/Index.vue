<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ClipboardList, CheckCircle2, Clock, AlertCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface TaskItem {
    id: number;
    category: string;
    category_label: string;
    category_icon: string;
    name: string;
    description: string | null;
    assigned_role: string;
    assigned_role_label: string;
    assigned_role_color: string;
    is_required: boolean;
    due_date: string | null;
    is_overdue: boolean;
    status: string;
    status_label: string;
    status_color: string;
}

interface TaskGroup {
    checklist_id: number;
    employee_name: string | null;
    employee_number: string | null;
    start_date: string | null;
    items: TaskItem[];
}

const props = defineProps<{
    tasks: {
        data: TaskGroup[];
        links: unknown[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: { category: string | null; role: string | null };
    categories: { value: string; label: string }[];
    roles: { value: string; label: string; color: string }[];
    userRoles: string[];
}>();

const { tenantName } = useTenant();
const categoryFilter = ref(props.filters.category || '');
const roleFilter = ref(props.filters.role || '');
const processing = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Onboarding Tasks', href: '/onboarding-tasks' },
];

function applyFilters() {
    router.get(
        '/onboarding-tasks',
        {
            category: categoryFilter.value || undefined,
            role: roleFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch([categoryFilter, roleFilter], applyFilters);

function badgeClasses(color: string): string {
    const map: Record<string, string> = {
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
        orange: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400',
        purple: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
    return map[color] ?? map.slate;
}

function completeItem(itemId: number) {
    processing.value = true;
    router.post(`/api/onboarding-items/${itemId}/complete`, {}, {
        preserveState: true,
        onFinish: () => {
            processing.value = false;
        },
        onSuccess: () => {
            router.reload({ only: ['tasks'] });
        },
    });
}
</script>

<template>
    <Head :title="`Onboarding Tasks - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    My Onboarding Tasks
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Tasks assigned to you based on your role
                </p>
            </div>

            <!-- Filters -->
            <Card class="mb-6">
                <CardContent class="flex items-center gap-4 pt-6">
                    <select
                        v-model="categoryFilter"
                        class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="">All Categories</option>
                        <option
                            v-for="category in categories"
                            :key="category.value"
                            :value="category.value"
                        >
                            {{ category.label }}
                        </option>
                    </select>
                    <select
                        v-model="roleFilter"
                        class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="">All Roles</option>
                        <option
                            v-for="role in roles"
                            :key="role.value"
                            :value="role.value"
                        >
                            {{ role.label }}
                        </option>
                    </select>
                </CardContent>
            </Card>

            <!-- Tasks by Employee -->
            <div v-if="tasks.data.length === 0" class="rounded-lg border border-slate-200 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-800">
                <ClipboardList class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" />
                <p class="mt-3 text-slate-500 dark:text-slate-400">No pending tasks assigned to you.</p>
            </div>

            <div v-else class="space-y-6">
                <Card v-for="group in tasks.data" :key="group.checklist_id">
                    <CardHeader>
                        <CardTitle class="flex items-center justify-between">
                            <span>{{ group.employee_name }}</span>
                            <span class="text-sm font-normal text-slate-500">
                                {{ group.employee_number }}
                                <span v-if="group.start_date"> &middot; Start: {{ group.start_date }}</span>
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="item in group.items"
                            :key="item.id"
                            class="flex items-center justify-between rounded-lg border p-4"
                            :class="item.is_overdue ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-900/20' : 'border-slate-200 dark:border-slate-700'"
                        >
                            <div class="flex items-start gap-3">
                                <Clock
                                    v-if="item.status === 'in_progress'"
                                    class="mt-0.5 h-5 w-5 text-amber-500"
                                />
                                <AlertCircle
                                    v-else-if="item.is_overdue"
                                    class="mt-0.5 h-5 w-5 text-red-500"
                                />
                                <div
                                    v-else
                                    class="mt-0.5 h-5 w-5 rounded-full border-2 border-slate-300 dark:border-slate-600"
                                />
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ item.name }}
                                    </p>
                                    <p v-if="item.description" class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ item.description }}
                                    </p>
                                    <div class="mt-2 flex items-center gap-2 text-xs">
                                        <span
                                            class="rounded-full px-2 py-0.5 font-medium"
                                            :class="badgeClasses(item.assigned_role_color)"
                                        >
                                            {{ item.assigned_role_label }}
                                        </span>
                                        <span class="text-slate-500 dark:text-slate-400">
                                            {{ item.category_label }}
                                        </span>
                                        <span v-if="item.due_date" class="text-slate-500 dark:text-slate-400">
                                            Due: {{ item.due_date }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <Button
                                size="sm"
                                :disabled="processing"
                                @click="completeItem(item.id)"
                            >
                                <CheckCircle2 class="mr-1.5 h-4 w-4" />
                                Complete
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </TenantLayout>
</template>
