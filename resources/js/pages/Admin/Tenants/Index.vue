<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import type { AdminTenantListItem } from '@/types';
import { ref, watch } from 'vue';
import { Search } from 'lucide-vue-next';

const props = defineProps<{
    tenants: {
        data: AdminTenantListItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
    };
    plans: Array<{ id: number; name: string }>;
    filters: {
        search?: string;
        plan_id?: string;
        status?: string;
        sort?: string;
        direction?: string;
    };
}>();

const search = ref(props.filters.search ?? '');
const planId = ref(props.filters.plan_id ?? '');
const status = ref(props.filters.status ?? '');

let debounceTimer: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        applyFilters({ search: value });
    }, 300);
});

watch([planId, status], () => {
    applyFilters({
        plan_id: planId.value,
        status: status.value,
    });
});

function applyFilters(overrides: Record<string, string> = {}) {
    router.get(
        '/admin/tenants',
        {
            search: search.value || undefined,
            plan_id: planId.value || undefined,
            status: status.value || undefined,
            sort: props.filters.sort || undefined,
            direction: props.filters.direction || undefined,
            ...overrides,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function sort(field: string) {
    const direction =
        props.filters.sort === field && props.filters.direction === 'asc'
            ? 'desc'
            : 'asc';
    applyFilters({ sort: field, direction });
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const statusClasses: Record<string, string> = {
    trial: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
    active: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
    expired: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
    cancelled: 'border border-slate-300 text-slate-600 dark:border-slate-600 dark:text-slate-400',
    none: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
};
</script>

<template>
    <AdminLayout
        :breadcrumbs="[
            { title: 'Admin', href: '/admin' },
            { title: 'Tenants', href: '/admin/tenants' },
        ]"
    >
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                Tenants
            </h1>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-4">
                <div class="relative flex-1 sm:max-w-xs">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search tenants..."
                        class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-10 pr-4 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                    />
                </div>
                <select
                    v-model="planId"
                    class="rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                >
                    <option value="">All Plans</option>
                    <option v-for="plan in plans" :key="plan.id" :value="String(plan.id)">
                        {{ plan.name }}
                    </option>
                </select>
                <select
                    v-model="status"
                    class="rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                >
                    <option value="">All Statuses</option>
                    <option value="trial">Trial</option>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="no_subscription">No Subscription</option>
                </select>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800/50">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th
                                class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                                @click="sort('name')"
                            >
                                Name
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Slug
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Plan
                            </th>
                            <th
                                class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                                @click="sort('employee_count_cache')"
                            >
                                Employees
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Status
                            </th>
                            <th
                                class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                                @click="sort('created_at')"
                            >
                                Created
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <tr
                            v-for="tenant in tenants.data"
                            :key="tenant.id"
                            class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/50"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/admin/tenants/${tenant.id}`"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    {{ tenant.name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                {{ tenant.slug }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                {{ tenant.plan_name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                {{ tenant.employee_count }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        statusClasses[tenant.status] || statusClasses.none,
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                    ]"
                                >
                                    {{ tenant.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                {{ formatDate(tenant.created_at) }}
                            </td>
                        </tr>
                        <tr v-if="tenants.data.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                            >
                                No tenants found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="tenants.last_page > 1" class="flex justify-center gap-1">
                <template v-for="link in tenants.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-lg px-3 py-2 text-sm transition-colors"
                        :class="
                            link.active
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                        "
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded-lg px-3 py-2 text-sm text-slate-400 dark:text-slate-600"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AdminLayout>
</template>
