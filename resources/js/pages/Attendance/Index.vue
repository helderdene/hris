<script setup lang="ts">
import AttendanceDirectionBadge from '@/components/AttendanceDirectionBadge.vue';
import EnumSelect from '@/components/EnumSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAttendanceLive } from '@/composables/useAttendanceLive';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Wifi, WifiOff } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Device {
    id: number;
    name: string;
    device_identifier: string;
}

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
}

interface AttendanceLog {
    id: number;
    employee_id: number | null;
    employee_code: string;
    employee_name: string | null;
    logged_at: string;
    logged_at_human: string;
    logged_at_time: string;
    logged_at_date: string;
    direction: 'in' | 'out' | string | null;
    confidence: string | null;
    confidence_percent: number | null;
    verification_method: 'face' | 'fingerprint' | 'unknown';
    device: Device | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedLogs {
    data: AttendanceLog[];
    links: PaginationLink[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

interface EnumOption {
    value: string;
    label: string;
}

interface Filters {
    date_from: string | null;
    date_to: string | null;
    employee_id: string | null;
    device_id: string | null;
}

const props = defineProps<{
    logs: PaginatedLogs;
    employees: Employee[];
    devices: Device[];
    filters: Filters;
}>();

const { tenantName } = useTenant();

// Real-time attendance updates
const { logs: liveLogs, isConnected } = useAttendanceLive({ maxLogs: 20 });

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Attendance Logs', href: '/attendance' },
];

// Filter state
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const employeeFilter = ref(props.filters?.employee_id || '');
const deviceFilter = ref(props.filters?.device_id || '');
const showFilters = ref(false);

// Dropdown options
const employeeOptions = computed<EnumOption[]>(() => {
    return [
        { value: '', label: 'All Employees' },
        ...(props.employees || []).map((emp) => ({
            value: emp.id.toString(),
            label: `${emp.full_name} (${emp.employee_number})`,
        })),
    ];
});

const deviceOptions = computed<EnumOption[]>(() => {
    return [
        { value: '', label: 'All Devices' },
        ...(props.devices || []).map((device) => ({
            value: device.id.toString(),
            label: device.name,
        })),
    ];
});

// Computed logs count
const logCount = computed(() => props.logs?.data?.length || 0);
const totalCount = computed(() => props.logs?.meta?.total || logCount.value);

// Track newly received live log IDs for animation
const newLogIds = ref<Set<number>>(new Set());

// Watch for new live logs and add them to animation set
watch(
    () => liveLogs.value,
    (newLogs, oldLogs) => {
        if (newLogs.length > (oldLogs?.length || 0)) {
            const latestLog = newLogs[0];
            if (latestLog) {
                newLogIds.value.add(latestLog.id);
                // Remove from animation set after animation completes
                setTimeout(() => {
                    newLogIds.value.delete(latestLog.id);
                }, 2000);
            }
        }
    },
    { deep: true },
);

function applyFilters() {
    router.get(
        '/attendance',
        {
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            employee_id: employeeFilter.value || undefined,
            device_id: deviceFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function clearFilters() {
    dateFrom.value = new Date().toISOString().split('T')[0];
    dateTo.value = new Date().toISOString().split('T')[0];
    employeeFilter.value = '';
    deviceFilter.value = '';
    router.get(
        '/attendance',
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

// Watch filter changes and apply
watch([employeeFilter, deviceFilter], () => {
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

function formatConfidence(confidence: number | null): string {
    if (confidence === null) {
        return '-';
    }
    return `${confidence}%`;
}

function formatVerificationMethod(log: AttendanceLog): string {
    if (log.verification_method === 'fingerprint') {
        return 'Fingerprint';
    }
    if (log.verification_method === 'face') {
        return `Face (${log.confidence_percent}%)`;
    }
    return 'Unknown';
}
</script>

<template>
    <Head :title="`Attendance Logs - ${tenantName}`" />

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
                        Attendance Logs
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ logCount }} of {{ totalCount }} entries
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Connection Status Indicator -->
                    <div
                        class="flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm"
                        :class="
                            isConnected
                                ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400'
                                : 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400'
                        "
                    >
                        <span v-if="isConnected" class="relative flex h-2 w-2">
                            <span
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"
                            ></span>
                            <span
                                class="relative inline-flex h-2 w-2 rounded-full bg-green-500"
                            ></span>
                        </span>
                        <span
                            v-else
                            class="inline-flex h-2 w-2 rounded-full bg-red-500"
                        ></span>
                        <component
                            :is="isConnected ? Wifi : WifiOff"
                            class="h-4 w-4"
                        />
                        <span>{{ isConnected ? 'Live' : 'Offline' }}</span>
                    </div>
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
                <div class="w-full sm:w-56">
                    <label
                        class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400"
                        >Employee</label
                    >
                    <EnumSelect
                        v-model="employeeFilter"
                        :options="employeeOptions"
                        placeholder="All Employees"
                    />
                </div>
                <div class="w-full sm:w-48">
                    <label
                        class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400"
                        >Device</label
                    >
                    <EnumSelect
                        v-model="deviceFilter"
                        :options="deviceOptions"
                        placeholder="All Devices"
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

            <!-- Real-time Live Logs Section (when connected and has new logs) -->
            <div
                v-if="isConnected && liveLogs.length > 0"
                class="rounded-xl border border-green-200 bg-green-50/50 p-4 dark:border-green-800 dark:bg-green-900/10"
            >
                <div class="mb-3 flex items-center gap-2">
                    <span class="relative flex h-2 w-2">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"
                        ></span>
                        <span
                            class="relative inline-flex h-2 w-2 rounded-full bg-green-500"
                        ></span>
                    </span>
                    <span
                        class="text-sm font-medium text-green-700 dark:text-green-400"
                        >Live Updates</span
                    >
                </div>
                <div class="space-y-2">
                    <div
                        v-for="log in liveLogs.slice(0, 5)"
                        :key="log.id"
                        class="flex items-center justify-between rounded-lg bg-white p-3 shadow-sm dark:bg-slate-800"
                        :class="{
                            'animate-pulse ring-2 ring-green-400':
                                newLogIds.has(log.id),
                        }"
                    >
                        <div class="flex items-center gap-3">
                            <AttendanceDirectionBadge
                                :direction="log.direction"
                            />
                            <div>
                                <span
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                    >{{
                                        log.employee_name || log.employee_code
                                    }}</span
                                >
                                <span
                                    class="ml-2 text-sm text-slate-500 dark:text-slate-400"
                                    >{{ log.device_name }}</span
                                >
                            </div>
                        </div>
                        <span class="text-sm text-slate-500 dark:text-slate-400"
                            >Just now</span
                        >
                    </div>
                </div>
            </div>

            <!-- Attendance Logs Table -->
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
                                    Employee
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Time
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Direction
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Device
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Verification
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <tr
                                v-for="log in logs.data"
                                :key="log.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                :data-test="`attendance-row-${log.id}`"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div
                                            class="font-medium text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                log.employee_name ||
                                                'Unknown Employee'
                                            }}
                                        </div>
                                        <div
                                            class="text-sm text-blue-600 dark:text-blue-400"
                                        >
                                            {{ log.employee_code }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div
                                            class="font-medium text-slate-900 dark:text-slate-100"
                                        >
                                            {{ log.logged_at_time }}
                                        </div>
                                        <div
                                            class="text-sm text-slate-500 dark:text-slate-400"
                                        >
                                            {{ log.logged_at_date }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <AttendanceDirectionBadge
                                        :direction="log.direction"
                                    />
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300"
                                >
                                    {{ log.device?.name || '-' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300"
                                >
                                    {{ formatVerificationMethod(log) }}
                                </td>
                            </tr>
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
                        :data-test="`attendance-card-${log.id}`"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ log.employee_name || 'Unknown Employee' }}
                                </div>
                                <div
                                    class="text-sm text-blue-600 dark:text-blue-400"
                                >
                                    {{ log.employee_code }}
                                </div>
                            </div>
                            <AttendanceDirectionBadge
                                :direction="log.direction"
                            />
                        </div>
                        <div
                            class="mt-3 flex flex-wrap items-center gap-3 text-sm text-slate-500 dark:text-slate-400"
                        >
                            <span>{{ log.logged_at_time }}</span>
                            <span>-</span>
                            <span>{{ log.logged_at_date }}</span>
                        </div>
                        <div
                            class="mt-2 flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400"
                        >
                            <span v-if="log.device">{{ log.device.name }}</span>
                            <span>{{ formatVerificationMethod(log) }}</span>
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
                            d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No attendance logs found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{
                            dateFrom || dateTo || employeeFilter || deviceFilter
                                ? 'Try adjusting your filters.'
                                : 'Attendance logs will appear here when employees clock in.'
                        }}
                    </p>
                </div>

                <!-- Pagination -->
                <div
                    v-if="logs.links && logs.links.length > 3"
                    class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50 sm:px-6"
                >
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
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
