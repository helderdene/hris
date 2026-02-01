<script setup lang="ts">
import EnumSelect from '@/components/EnumSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface EnumOption {
    value: string;
    label: string;
    color?: string;
}

interface AuditLog {
    id: number;
    auditable_type: string;
    auditable_id: number;
    model_name: string;
    action: string;
    action_label: string;
    action_color: string;
    user_id: number | null;
    user_name: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    formatted_created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedLogs {
    data: AuditLog[];
    links: PaginationLink[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

interface Filters {
    model_type: string | null;
    action: string | null;
    user_id: string | null;
    date_from: string | null;
    date_to: string | null;
}

const props = defineProps<{
    logs: PaginatedLogs;
    filters: Filters;
    modelTypes: EnumOption[];
    actions: EnumOption[];
    users: EnumOption[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Settings', href: '/settings/audit-logs' },
    { title: 'Audit Logs', href: '/settings/audit-logs' },
];

// Filter state
const modelTypeFilter = ref(props.filters?.model_type || '');
const actionFilter = ref(props.filters?.action || '');
const userFilter = ref(props.filters?.user_id || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const showFilters = ref(true);
const expandedLogId = ref<number | null>(null);

// Model type options
const modelTypeOptions = [
    { value: '', label: 'All Models' },
    ...props.modelTypes,
];

// Action options
const actionOptions = [
    { value: '', label: 'All Actions' },
    ...props.actions,
];

// User options
const userOptions = [
    { value: '', label: 'All Users' },
    ...props.users,
];

function applyFilters() {
    router.get(
        '/settings/audit-logs',
        {
            model_type: modelTypeFilter.value || undefined,
            action: actionFilter.value || undefined,
            user_id: userFilter.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function clearFilters() {
    modelTypeFilter.value = '';
    actionFilter.value = '';
    userFilter.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    router.get(
        '/settings/audit-logs',
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

// Watch filter changes and apply
watch([modelTypeFilter, actionFilter, userFilter], () => {
    applyFilters();
});

function handleDateChange() {
    applyFilters();
}

function goToPage(url: string | null) {
    if (url) {
        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
        });
    }
}

function toggleExpand(logId: number) {
    if (expandedLogId.value === logId) {
        expandedLogId.value = null;
    } else {
        expandedLogId.value = logId;
    }
}

function getActionBadgeClasses(color: string): string {
    switch (color) {
        case 'green':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
        case 'blue':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 'red':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function formatValues(values: Record<string, unknown> | null): string {
    if (!values || Object.keys(values).length === 0) {
        return '-';
    }
    return JSON.stringify(values, null, 2);
}
</script>

<template>
    <Head :title="`Audit Logs - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Audit Logs
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Track all changes made to data in your organization.
                    </p>
                </div>
            </div>

            <!-- Filters Toggle -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <Button
                    variant="outline"
                    @click="showFilters = !showFilters"
                    data-test="filters-button"
                >
                    <svg
                        class="mr-2 h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"
                        />
                    </svg>
                    Filters
                </Button>
            </div>

            <!-- Filter Panel -->
            <div
                v-if="showFilters"
                class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
            >
                <div class="w-full sm:w-40">
                    <label
                        class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400"
                        >Model Type</label
                    >
                    <EnumSelect
                        v-model="modelTypeFilter"
                        :options="modelTypeOptions"
                        placeholder="All Models"
                    />
                </div>
                <div class="w-full sm:w-36">
                    <label
                        class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400"
                        >Action</label
                    >
                    <EnumSelect
                        v-model="actionFilter"
                        :options="actionOptions"
                        placeholder="All Actions"
                    />
                </div>
                <div class="w-full sm:w-48">
                    <label
                        class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400"
                        >User</label
                    >
                    <EnumSelect
                        v-model="userFilter"
                        :options="userOptions"
                        placeholder="All Users"
                    />
                </div>
                <div class="w-full sm:w-40">
                    <label
                        class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400"
                        >Date From</label
                    >
                    <Input
                        v-model="dateFrom"
                        type="date"
                        @change="handleDateChange"
                    />
                </div>
                <div class="w-full sm:w-40">
                    <label
                        class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400"
                        >Date To</label
                    >
                    <Input
                        v-model="dateTo"
                        type="date"
                        @change="handleDateChange"
                    />
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="clearFilters"
                    class="text-slate-600 dark:text-slate-400"
                >
                    Clear filters
                </Button>
            </div>

            <!-- Audit Logs Table -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Desktop Table -->
                <div class="hidden md:block">
                    <table
                        class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                    >
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Timestamp
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Model
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Action
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    User
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Details
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <template v-for="log in logs.data" :key="log.id">
                                <tr
                                    class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                    :data-test="`audit-row-${log.id}`"
                                    @click="toggleExpand(log.id)"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div
                                            class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                        >
                                            {{ log.formatted_created_at }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div
                                                class="font-medium text-slate-900 dark:text-slate-100"
                                            >
                                                {{ log.model_name }}
                                            </div>
                                            <div
                                                class="text-sm text-slate-500 dark:text-slate-400"
                                            >
                                                #{{ log.auditable_id }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                            :class="
                                                getActionBadgeClasses(
                                                    log.action_color,
                                                )
                                            "
                                        >
                                            {{ log.action_label }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300"
                                    >
                                        {{ log.user_name || 'System' }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400"
                                    >
                                        <button
                                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                            @click.stop="toggleExpand(log.id)"
                                        >
                                            {{
                                                expandedLogId === log.id
                                                    ? 'Hide'
                                                    : 'View'
                                            }}
                                        </button>
                                    </td>
                                </tr>
                                <!-- Expanded Details Row -->
                                <tr
                                    v-if="expandedLogId === log.id"
                                    class="bg-slate-50 dark:bg-slate-800/30"
                                >
                                    <td colspan="5" class="px-6 py-4">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <h4
                                                    class="mb-2 text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    Old Values
                                                </h4>
                                                <pre
                                                    class="overflow-x-auto rounded-lg bg-slate-100 p-3 text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                                    >{{
                                                        formatValues(
                                                            log.old_values,
                                                        )
                                                    }}</pre
                                                >
                                            </div>
                                            <div>
                                                <h4
                                                    class="mb-2 text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    New Values
                                                </h4>
                                                <pre
                                                    class="overflow-x-auto rounded-lg bg-slate-100 p-3 text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                                    >{{
                                                        formatValues(
                                                            log.new_values,
                                                        )
                                                    }}</pre
                                                >
                                            </div>
                                        </div>
                                        <div
                                            class="mt-4 flex flex-wrap gap-4 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            <span v-if="log.ip_address">
                                                <strong>IP:</strong>
                                                {{ log.ip_address }}
                                            </span>
                                            <span v-if="log.user_agent">
                                                <strong>User Agent:</strong>
                                                {{
                                                    log.user_agent.substring(
                                                        0,
                                                        80,
                                                    )
                                                }}...
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List -->
                <div
                    class="divide-y divide-slate-200 md:hidden dark:divide-slate-700"
                >
                    <div
                        v-for="log in logs.data"
                        :key="log.id"
                        class="p-4"
                        :data-test="`audit-card-${log.id}`"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ log.model_name }} #{{ log.auditable_id }}
                                </div>
                                <div
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ log.formatted_created_at }}
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="getActionBadgeClasses(log.action_color)"
                            >
                                {{ log.action_label }}
                            </span>
                        </div>
                        <div
                            class="mt-2 text-sm text-slate-600 dark:text-slate-300"
                        >
                            By {{ log.user_name || 'System' }}
                        </div>
                        <button
                            class="mt-2 text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                            @click="toggleExpand(log.id)"
                        >
                            {{ expandedLogId === log.id ? 'Hide' : 'View' }}
                            details
                        </button>
                        <!-- Expanded Details -->
                        <div
                            v-if="expandedLogId === log.id"
                            class="mt-3 space-y-3"
                        >
                            <div>
                                <h4
                                    class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Old Values
                                </h4>
                                <pre
                                    class="overflow-x-auto rounded bg-slate-100 p-2 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    >{{ formatValues(log.old_values) }}</pre
                                >
                            </div>
                            <div>
                                <h4
                                    class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    New Values
                                </h4>
                                <pre
                                    class="overflow-x-auto rounded bg-slate-100 p-2 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    >{{ formatValues(log.new_values) }}</pre
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="!logs.data || logs.data.length === 0"
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
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Zm3.75 11.625a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No audit logs found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{
                            modelTypeFilter ||
                            actionFilter ||
                            userFilter ||
                            dateFrom ||
                            dateTo
                                ? 'Try adjusting your filters.'
                                : 'Audit logs will appear here when changes are made.'
                        }}
                    </p>
                </div>

                <!-- Pagination -->
                <div
                    v-if="logs.links && logs.links.length > 3"
                    class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50 sm:px-6"
                >
                    <div
                        class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="text-sm text-slate-700 dark:text-slate-300">
                                Showing page
                                <span class="font-medium">{{
                                    logs.meta?.current_page || 1
                                }}</span>
                                of
                                <span class="font-medium">{{
                                    logs.meta?.last_page || 1
                                }}</span>
                            </p>
                        </div>
                        <div>
                            <nav
                                class="isolate inline-flex -space-x-px rounded-md shadow-sm"
                                aria-label="Pagination"
                            >
                                <button
                                    v-for="(link, index) in logs.links"
                                    :key="index"
                                    :disabled="!link.url"
                                    @click="goToPage(link.url)"
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium"
                                    :class="[
                                        link.active
                                            ? 'z-10 bg-blue-600 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                                            : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 dark:text-slate-300 dark:ring-slate-600 dark:hover:bg-slate-700',
                                        !link.url
                                            ? 'cursor-not-allowed opacity-50'
                                            : 'cursor-pointer',
                                        index === 0 ? 'rounded-l-md' : '',
                                        index === logs.links.length - 1
                                            ? 'rounded-r-md'
                                            : '',
                                    ]"
                                    v-html="link.label"
                                ></button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
