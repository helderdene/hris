<script setup lang="ts">
import BiometricDeviceFormModal from '@/Components/BiometricDeviceFormModal.vue';
import EnumSelect from '@/Components/EnumSelect.vue';
import SyncStatusBadge from '@/Components/SyncStatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { type DeviceSyncStatusMeta, type SyncStatus } from '@/types/sync';
import { Head, router, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface WorkLocation {
    id: number;
    name: string;
    code: string;
}

interface BiometricDevice {
    id: number;
    name: string;
    device_identifier: string;
    work_location_id: number;
    status: string;
    status_label: string;
    last_seen_at: string | null;
    last_seen_human: string | null;
    connection_started_at: string | null;
    is_active: boolean;
    uptime_seconds: number | null;
    uptime_human: string | null;
    work_location: WorkLocation | null;
    created_at: string;
    updated_at: string;
}

interface StatusCounts {
    total: number;
    online: number;
    offline: number;
}

interface Filters {
    status: string | null;
    work_location_id: string | null;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    devices: BiometricDevice[];
    workLocations: WorkLocation[];
    statusCounts: StatusCounts;
    filters: Filters;
    deviceSyncMeta?: Record<number, DeviceSyncStatusMeta>;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Devices', href: '/organization/devices' },
];

const isFormModalOpen = ref(false);
const editingDevice = ref<BiometricDevice | null>(null);
const deletingDeviceId = ref<number | null>(null);

// Local filter state
const statusFilter = ref(props.filters.status || '');
const locationFilter = ref(props.filters.work_location_id || '');

// Poll for real-time status updates every 30 seconds
usePoll(30000, { only: ['devices', 'statusCounts'] });

// Status filter options
const statusOptions: EnumOption[] = [
    { value: '', label: 'All Statuses' },
    { value: 'online', label: 'Online' },
    { value: 'offline', label: 'Offline' },
];

// Work location filter options
const locationOptions = computed((): EnumOption[] => {
    return [
        { value: '', label: 'All Locations' },
        ...props.workLocations.map((loc) => ({
            value: String(loc.id),
            label: loc.name,
        })),
    ];
});

// Check if any filters are active
const hasActiveFilters = computed(() => {
    return statusFilter.value !== '' || locationFilter.value !== '';
});

// Check if there are offline devices for extended period (show alert)
const hasConnectionIssues = computed(() => {
    return props.devices.some(
        (device) => device.status === 'offline' && device.is_active,
    );
});

// Number of offline active devices
const offlineActiveDeviceCount = computed(() => {
    return props.devices.filter(
        (device) => device.status === 'offline' && device.is_active,
    ).length;
});

function getStatusBadgeClasses(status: string): string {
    switch (status) {
        case 'online':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'offline':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getStatusIconClasses(status: string): string {
    switch (status) {
        case 'online':
            return 'text-green-500';
        case 'offline':
            return 'text-red-500';
        default:
            return 'text-slate-400';
    }
}

function applyFilters() {
    const params: Record<string, string> = {};
    if (statusFilter.value) {
        params.status = statusFilter.value;
    }
    if (locationFilter.value) {
        params.work_location_id = locationFilter.value;
    }
    router.get('/organization/devices', params, { preserveState: true });
}

function clearFilters() {
    statusFilter.value = '';
    locationFilter.value = '';
    router.get('/organization/devices', {}, { preserveState: true });
}

function handleAddDevice() {
    editingDevice.value = null;
    isFormModalOpen.value = true;
}

function handleEditDevice(device: BiometricDevice) {
    editingDevice.value = device;
    isFormModalOpen.value = true;
}

async function handleDeleteDevice(device: BiometricDevice) {
    if (
        !confirm(`Are you sure you want to delete the device "${device.name}"?`)
    ) {
        return;
    }

    deletingDeviceId.value = device.id;

    try {
        const response = await fetch(`/api/organization/devices/${device.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            router.reload({ only: ['devices', 'statusCounts'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete device');
        }
    } catch (error) {
        alert('An error occurred while deleting the device');
    } finally {
        deletingDeviceId.value = null;
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingDevice.value = null;
    router.reload({ only: ['devices', 'statusCounts', 'workLocations'] });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

const syncingDeviceId = ref<number | null>(null);

function getDeviceSyncStatus(deviceId: number): SyncStatus {
    const meta = props.deviceSyncMeta?.[deviceId];
    if (!meta) return 'pending';
    if (meta.failed_count > 0) return 'failed';
    if (meta.synced_count === meta.total_employees && meta.total_employees > 0)
        return 'synced';
    if (meta.pending_count > 0) return 'pending';
    return 'pending';
}

function getDeviceSyncSummary(deviceId: number): string {
    const meta = props.deviceSyncMeta?.[deviceId];
    if (!meta || meta.total_employees === 0) return 'No employees';
    return `${meta.synced_count}/${meta.total_employees} synced`;
}

async function handleSyncAllToDevice(device: BiometricDevice) {
    syncingDeviceId.value = device.id;

    try {
        const response = await fetch(
            `/api/organization/devices/${device.id}/sync-all`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ immediate: false }),
            }
        );

        if (response.ok) {
            router.reload({ only: ['devices', 'deviceSyncMeta'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to sync employees to device');
        }
    } catch (error) {
        alert('An error occurred while syncing employees');
    } finally {
        syncingDeviceId.value = null;
    }
}
</script>

<template>
    <Head :title="`Biometric Devices - ${tenantName}`" />

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
                        Biometric Devices
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage facial recognition devices and monitor their
                        connection status.
                    </p>
                </div>
                <Button
                    @click="handleAddDevice"
                    class="shrink-0"
                    :style="{ backgroundColor: primaryColor }"
                    data-test="add-device-button"
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
                            d="M12 4.5v15m7.5-7.5h-15"
                        />
                    </svg>
                    Add Device
                </Button>
            </div>

            <!-- Status Summary Cards -->
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                data-test="status-summary-cards"
            >
                <!-- Total Devices Card -->
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800"
                        >
                            <svg
                                class="h-5 w-5 text-slate-600 dark:text-slate-400"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Zm.75-12h9v9h-9v-9Z"
                                />
                            </svg>
                        </div>
                        <div>
                            <p
                                class="text-2xl font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{ statusCounts.total }}
                            </p>
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                Total Devices
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Online Devices Card -->
                <div
                    class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-900/50 dark:bg-green-900/20"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/40"
                        >
                            <svg
                                class="h-5 w-5 text-green-600 dark:text-green-400"
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
                        </div>
                        <div>
                            <p
                                class="text-2xl font-semibold text-green-700 dark:text-green-400"
                            >
                                {{ statusCounts.online }}
                            </p>
                            <p
                                class="text-sm text-green-600 dark:text-green-500"
                            >
                                Online
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Offline Devices Card -->
                <div
                    class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-900/20"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/40"
                        >
                            <svg
                                class="h-5 w-5 text-red-600 dark:text-red-400"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                />
                            </svg>
                        </div>
                        <div>
                            <p
                                class="text-2xl font-semibold text-red-700 dark:text-red-400"
                            >
                                {{ statusCounts.offline }}
                            </p>
                            <p class="text-sm text-red-600 dark:text-red-500">
                                Offline
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Connection Issues Alert Banner -->
            <div
                v-if="hasConnectionIssues"
                class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-900/20"
                data-test="connection-issues-banner"
            >
                <svg
                    class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                    />
                </svg>
                <div class="flex-1">
                    <p
                        class="text-sm font-medium text-amber-800 dark:text-amber-200"
                    >
                        Connection Issues Detected
                    </p>
                    <p class="text-sm text-amber-700 dark:text-amber-300">
                        {{ offlineActiveDeviceCount }} active device{{
                            offlineActiveDeviceCount > 1 ? 's are' : ' is'
                        }}
                        currently offline. Check network connectivity and device
                        status.
                    </p>
                </div>
            </div>

            <!-- Filter Controls -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center"
                data-test="filter-controls"
            >
                <div class="flex flex-1 flex-col gap-4 sm:flex-row">
                    <div class="w-full sm:w-48">
                        <EnumSelect
                            id="status-filter"
                            v-model="statusFilter"
                            :options="statusOptions"
                            placeholder="Filter by status"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <div class="w-full sm:w-56">
                        <EnumSelect
                            id="location-filter"
                            v-model="locationFilter"
                            :options="locationOptions"
                            placeholder="Filter by location"
                            @update:model-value="applyFilters"
                        />
                    </div>
                </div>
                <Button
                    v-if="hasActiveFilters"
                    variant="outline"
                    size="sm"
                    @click="clearFilters"
                    data-test="clear-filters-button"
                >
                    <svg
                        class="mr-1.5 h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 18 18 6M6 6l12 12"
                        />
                    </svg>
                    Clear Filters
                </Button>
            </div>

            <!-- Devices Table -->
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
                                    Device Name
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Identifier
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Location
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Last Seen
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Sync Status
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <tr
                                v-for="device in devices"
                                :key="device.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                :data-test="`device-row-${device.id}`"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ device.name }}
                                    </div>
                                    <div
                                        v-if="!device.is_active"
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Inactive
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 font-mono text-sm whitespace-nowrap text-slate-600 dark:text-slate-400"
                                >
                                    {{ device.device_identifier }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-slate-900 dark:text-slate-100"
                                >
                                    {{ device.work_location?.name || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            getStatusBadgeClasses(device.status)
                                        "
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="
                                                device.status === 'online'
                                                    ? 'animate-pulse bg-green-500'
                                                    : 'bg-red-500'
                                            "
                                        ></span>
                                        {{ device.status_label }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400"
                                >
                                    {{ device.last_seen_human || 'Never' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="flex items-center gap-2"
                                        v-if="deviceSyncMeta?.[device.id]"
                                    >
                                        <SyncStatusBadge
                                            :status="
                                                getDeviceSyncStatus(device.id)
                                            "
                                        />
                                        <span
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                getDeviceSyncSummary(device.id)
                                            }}
                                        </span>
                                    </div>
                                    <span
                                        v-else
                                        class="text-xs text-slate-400 dark:text-slate-500"
                                    >
                                        -
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 w-8 p-0"
                                                data-test="device-actions-dropdown"
                                            >
                                                <span class="sr-only"
                                                    >Open menu</span
                                                >
                                                <svg
                                                    class="h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"
                                                    />
                                                </svg>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel
                                                >Actions</DropdownMenuLabel
                                            >
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                @click="
                                                    handleEditDevice(device)
                                                "
                                                data-test="edit-device-action"
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
                                                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                                    />
                                                </svg>
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="
                                                    handleSyncAllToDevice(
                                                        device
                                                    )
                                                "
                                                :disabled="
                                                    syncingDeviceId === device.id
                                                "
                                                data-test="sync-device-action"
                                            >
                                                <svg
                                                    v-if="
                                                        syncingDeviceId ===
                                                        device.id
                                                    "
                                                    class="mr-2 h-4 w-4 animate-spin"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <circle
                                                        class="opacity-25"
                                                        cx="12"
                                                        cy="12"
                                                        r="10"
                                                        stroke="currentColor"
                                                        stroke-width="4"
                                                    ></circle>
                                                    <path
                                                        class="opacity-75"
                                                        fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                                    ></path>
                                                </svg>
                                                <svg
                                                    v-else
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
                                                        d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
                                                    />
                                                </svg>
                                                Sync All Employees
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                :disabled="
                                                    deletingDeviceId ===
                                                    device.id
                                                "
                                                @click="
                                                    handleDeleteDevice(device)
                                                "
                                                data-test="delete-device-action"
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
                                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                                                    />
                                                </svg>
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
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
                        v-for="device in devices"
                        :key="device.id"
                        class="p-4"
                        :data-test="`device-card-${device.id}`"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ device.name }}
                                </div>
                                <div
                                    class="font-mono text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ device.device_identifier }}
                                </div>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 w-8 p-0"
                                    >
                                        <span class="sr-only">Open menu</span>
                                        <svg
                                            class="h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="2"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"
                                            />
                                        </svg>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuLabel
                                        >Actions</DropdownMenuLabel
                                    >
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        @click="handleEditDevice(device)"
                                    >
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="handleSyncAllToDevice(device)"
                                        :disabled="
                                            syncingDeviceId === device.id
                                        "
                                    >
                                        Sync All Employees
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                        @click="handleDeleteDevice(device)"
                                    >
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <div
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{
                                device.work_location?.name ||
                                'No location assigned'
                            }}
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClasses(device.status)"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="
                                        device.status === 'online'
                                            ? 'animate-pulse bg-green-500'
                                            : 'bg-red-500'
                                    "
                                ></span>
                                {{ device.status_label }}
                            </span>
                            <span
                                v-if="!device.is_active"
                                class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700/50 dark:text-slate-300"
                            >
                                Inactive
                            </span>
                            <SyncStatusBadge
                                v-if="deviceSyncMeta?.[device.id]"
                                :status="getDeviceSyncStatus(device.id)"
                            />
                        </div>
                        <div
                            class="mt-2 flex items-center justify-between text-xs text-slate-400 dark:text-slate-500"
                        >
                            <span
                                >Last seen:
                                {{ device.last_seen_human || 'Never' }}</span
                            >
                            <span v-if="deviceSyncMeta?.[device.id]">
                                {{ getDeviceSyncSummary(device.id) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="devices.length === 0"
                    class="px-6 py-12 text-center"
                    data-test="empty-state"
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
                            d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Zm.75-12h9v9h-9v-9Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No devices found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Get started by adding a new biometric device.
                    </p>
                    <div class="mt-6">
                        <Button
                            @click="handleAddDevice"
                            :style="{ backgroundColor: primaryColor }"
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
                                    d="M12 4.5v15m7.5-7.5h-15"
                                />
                            </svg>
                            Add Device
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Biometric Device Form Modal -->
        <BiometricDeviceFormModal
            v-model:open="isFormModalOpen"
            :device="editingDevice"
            :work-locations="workLocations"
            @success="handleFormSuccess"
        />
    </TenantLayout>
</template>
