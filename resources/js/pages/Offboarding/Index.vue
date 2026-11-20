<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { LogOut } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface OffboardingRow {
    id: number;
    employee_id: number;
    employee_name: string | null;
    employee_number: string | null;
    department: string | null;
    separation_type_label: string;
    last_working_date: string;
    is_separated: boolean;
    has_exit_interview: boolean;
    clearance_items_count: number;
    cleared_items_count: number;
    final_pay_status: string;
    coe_status: string;
}

const props = defineProps<{
    offboardings: {
        data: OffboardingRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { status: string | null; search: string | null };
    summary: {
        in_progress: number;
        separated: number;
        final_pay_pending: number;
        coe_pending: number;
    };
}>();

const { tenantName } = useTenant();
const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
let searchTimeout: ReturnType<typeof setTimeout>;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Offboarding', href: '/offboarding' },
];

const summaryCards = [
    { label: 'In Progress', value: props.summary.in_progress },
    { label: 'Separated', value: props.summary.separated },
    { label: 'Final Pay Pending', value: props.summary.final_pay_pending },
    { label: 'COE Pending', value: props.summary.coe_pending },
];

function applyFilters(): void {
    router.get(
        '/offboarding',
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

const stepLabels: Record<string, string> = {
    pending: 'Pending',
    processing: 'Processing',
};

function stepBadge(status: string): string {
    return (
        {
            completed:
                'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
            processing:
                'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
        }[status] ??
        'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
    );
}
</script>

<template>
    <Head :title="`Offboarding - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                Offboarding
            </h1>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Card v-for="card in summaryCards" :key="card.label">
                    <CardContent class="pt-6">
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ card.value }}
                        </p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ card.label }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <Input
                    v-model="search"
                    class="sm:max-w-xs"
                    type="search"
                    aria-label="Search employees"
                    placeholder="Search by name or employee number"
                />
                <select
                    v-model="statusFilter"
                    aria-label="Filter by status"
                    class="h-9 rounded-md border border-slate-200 bg-transparent px-3 text-sm dark:border-slate-800"
                >
                    <option value="">All statuses</option>
                    <option value="in_progress">In progress</option>
                    <option value="separated">Separated</option>
                </select>
            </div>

            <Card>
                <CardContent class="overflow-x-auto p-0">
                    <div
                        v-if="offboardings.data.length === 0"
                        class="flex flex-col items-center gap-3 py-12 text-center"
                    >
                        <LogOut
                            class="h-12 w-12 text-slate-300 dark:text-slate-600"
                        />
                        <p class="text-slate-500 dark:text-slate-400">
                            No offboardings found.
                        </p>
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700 dark:text-slate-400"
                            >
                                <th class="px-6 py-3 font-medium">Employee</th>
                                <th class="px-6 py-3 font-medium">Type</th>
                                <th class="px-6 py-3 font-medium">
                                    Last Working Day
                                </th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">
                                    Exit Interview
                                </th>
                                <th class="px-6 py-3 font-medium">Clearance</th>
                                <th class="px-6 py-3 font-medium">Final Pay</th>
                                <th class="px-6 py-3 font-medium">COE</th>
                                <th class="px-6 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in offboardings.data"
                                :key="row.id"
                                class="border-b border-slate-100 dark:border-slate-800"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ row.employee_name }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ row.employee_number }}
                                        <template v-if="row.department">
                                            &middot; {{ row.department }}
                                        </template>
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-slate-600 dark:text-slate-400"
                                >
                                    {{ row.separation_type_label }}
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-slate-600 dark:text-slate-400"
                                >
                                    {{ row.last_working_date }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            row.is_separated
                                                ? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400'
                                        "
                                    >
                                        {{
                                            row.is_separated
                                                ? 'Separated'
                                                : 'In progress'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-slate-600 dark:text-slate-400"
                                >
                                    {{
                                        row.has_exit_interview
                                            ? 'Scheduled'
                                            : '---'
                                    }}
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-slate-600 dark:text-slate-400"
                                >
                                    {{ row.cleared_items_count }}/{{
                                        row.clearance_items_count
                                    }}
                                    cleared
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="stepBadge(row.final_pay_status)"
                                    >
                                        {{
                                            stepLabels[row.final_pay_status] ??
                                            'Released'
                                        }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="stepBadge(row.coe_status)"
                                    >
                                        {{
                                            stepLabels[row.coe_status] ??
                                            'Issued'
                                        }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link
                                        :href="`/employees/${row.employee_id}?tab=offboarding`"
                                    >
                                        <Button size="sm" variant="outline">
                                            View
                                        </Button>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <nav
                v-if="offboardings.links.length > 3"
                class="flex flex-wrap gap-1"
                aria-label="Pagination"
            >
                <template v-for="link in offboardings.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md border border-slate-200 px-3 py-1 text-sm dark:border-slate-700"
                        :class="{
                            'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900':
                                link.active,
                        }"
                    >
                        <span v-html="link.label" />
                    </Link>
                </template>
            </nav>
        </div>
    </TenantLayout>
</template>
