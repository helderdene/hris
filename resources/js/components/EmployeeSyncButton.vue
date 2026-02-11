<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { type EmployeeDeviceSync } from '@/types/sync';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { CheckCircle, Loader2, RefreshCw, Unlink, XCircle } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Props {
    employeeId: number;
    syncStatuses: EmployeeDeviceSync[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'sync-complete'): void;
}>();

const isLoading = ref(false);
const loadingDeviceId = ref<number | null>(null);
const isVerifying = ref(false);
const verifiedStatuses = ref<EmployeeDeviceSync[] | null>(null);

const notification = ref<{ message: string; type: 'success' | 'error' } | null>(null);
let notificationTimeout: ReturnType<typeof setTimeout> | null = null;

function showNotification(message: string, type: 'success' | 'error') {
    if (notificationTimeout) {
        clearTimeout(notificationTimeout);
    }
    notification.value = { message, type };
    notificationTimeout = setTimeout(() => {
        notification.value = null;
    }, 4000);
}

const displayStatuses = computed(() =>
    verifiedStatuses.value ?? props.syncStatuses,
);

const hasPendingSyncs = computed(() =>
    displayStatuses.value.some(
        (s) => s.status === 'pending' || s.status === 'failed',
    ),
);

const allSynced = computed(
    () =>
        displayStatuses.value.length > 0 &&
        displayStatuses.value.every((s) => s.status === 'synced'),
);

async function verifyDevices() {
    if (props.syncStatuses.length === 0) {
        return;
    }

    isVerifying.value = true;

    try {
        const response = await axios.get(
            `/api/employees/${props.employeeId}/verify-devices`,
        );

        verifiedStatuses.value = response.data.data;
    } catch {
        // Silently fall back to prop-based statuses
    } finally {
        isVerifying.value = false;
    }
}

onMounted(() => {
    verifyDevices();
});

async function syncToDevice(deviceId: number) {
    const statuses = displayStatuses.value;
    const device = statuses.find((s) => s.device_id === deviceId);
    const deviceName = device?.device_name || `Device #${deviceId}`;

    loadingDeviceId.value = deviceId;
    isLoading.value = true;

    try {
        await axios.post(
            `/api/employees/${props.employeeId}/sync-to-devices`,
            {
                device_ids: [deviceId],
                immediate: true,
            },
        );

        showNotification(`Synced to ${deviceName}`, 'success');
        verifiedStatuses.value = null;
        router.reload({ only: ['syncStatuses'] });
        emit('sync-complete');
    } catch (error) {
        console.error('Sync failed:', error);
        showNotification(`Failed to sync to ${deviceName}`, 'error');
    } finally {
        isLoading.value = false;
        loadingDeviceId.value = null;
    }
}

async function syncToAllDevices() {
    isLoading.value = true;

    try {
        await axios.post(
            `/api/employees/${props.employeeId}/sync-to-devices`,
            {
                immediate: false,
            },
        );

        showNotification('Sync jobs queued for all devices', 'success');
        verifiedStatuses.value = null;
        router.reload({ only: ['syncStatuses'] });
        emit('sync-complete');
    } catch (error) {
        console.error('Sync failed:', error);
        showNotification('Failed to queue sync jobs', 'error');
    } finally {
        isLoading.value = false;
    }
}

async function unsyncFromDevice(deviceId: number) {
    const statuses = displayStatuses.value;
    const device = statuses.find((s) => s.device_id === deviceId);
    const deviceName = device?.device_name || `Device #${deviceId}`;

    if (!confirm(`Remove employee from ${deviceName}?`)) {
        return;
    }

    loadingDeviceId.value = deviceId;
    isLoading.value = true;

    try {
        await axios.post(
            `/api/employees/${props.employeeId}/unsync-from-device`,
            {
                device_id: deviceId,
            },
        );

        showNotification(`Removed from ${deviceName}`, 'success');
        verifiedStatuses.value = null;
        router.reload({ only: ['syncStatuses'] });
        emit('sync-complete');
    } catch (error) {
        console.error('Unsync failed:', error);
        showNotification(`Failed to remove from ${deviceName}`, 'error');
    } finally {
        isLoading.value = false;
        loadingDeviceId.value = null;
    }
}
</script>

<template>
    <div class="relative">
        <!-- All devices verified as synced -->
        <div v-if="allSynced && !isVerifying" class="flex items-center gap-2">
            <span
                class="inline-flex items-center gap-1.5 rounded-md bg-green-50 px-2.5 py-1.5 text-sm font-medium text-green-700 ring-1 ring-green-600/20 ring-inset dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20"
            >
                <CheckCircle class="h-3.5 w-3.5" />
                Synced
            </span>
            <Button
                variant="ghost"
                size="sm"
                class="h-7 w-7 p-0"
                :disabled="isLoading"
                @click="syncToAllDevices"
                title="Re-sync to all devices"
            >
                <RefreshCw class="h-3.5 w-3.5" />
            </Button>
            <!-- Unsync: single device goes directly, multiple devices show a picker -->
            <DropdownMenu v-if="displayStatuses.length > 1">
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 w-7 p-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                        :disabled="isLoading"
                        title="Unsync from device"
                    >
                        <Unlink class="h-3.5 w-3.5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                    <DropdownMenuItem
                        v-for="sync in displayStatuses"
                        :key="sync.id"
                        @click="unsyncFromDevice(sync.device_id)"
                        :disabled="isLoading"
                        class="cursor-pointer text-red-600 dark:text-red-400"
                    >
                        <Unlink class="mr-2 h-4 w-4" />
                        {{ sync.device_name || `Device #${sync.device_id}` }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Button
                v-else-if="displayStatuses.length === 1"
                variant="ghost"
                size="sm"
                class="h-7 w-7 p-0 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                :disabled="isLoading"
                title="Unsync from device"
                @click="unsyncFromDevice(displayStatuses[0].device_id)"
            >
                <Unlink class="h-3.5 w-3.5" />
            </Button>
        </div>

        <!-- Verifying or needs sync -->
        <DropdownMenu v-else>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="isLoading || displayStatuses.length === 0"
                    class="gap-2"
                >
                    <Loader2
                        v-if="isLoading || isVerifying"
                        class="h-4 w-4 animate-spin"
                    />
                    <svg
                        v-else
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
                            d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
                        />
                    </svg>
                    {{ isVerifying ? 'Verifying...' : 'Sync to Devices' }}
                    <span
                        v-if="hasPendingSyncs && !isVerifying"
                        class="flex h-2 w-2"
                    >
                        <span
                            class="absolute inline-flex h-2 w-2 animate-ping rounded-full bg-yellow-400 opacity-75"
                        ></span>
                        <span
                            class="relative inline-flex h-2 w-2 rounded-full bg-yellow-500"
                        ></span>
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
                <DropdownMenuItem
                    @click="syncToAllDevices"
                    :disabled="isLoading"
                    class="cursor-pointer"
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
                            d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"
                        />
                    </svg>
                    Sync to All Devices
                </DropdownMenuItem>
                <DropdownMenuSeparator
                    v-if="displayStatuses.length > 0"
                />
                <DropdownMenuItem
                    v-for="sync in displayStatuses"
                    :key="sync.id"
                    @click="syncToDevice(sync.device_id)"
                    :disabled="
                        isLoading && loadingDeviceId === sync.device_id
                    "
                    class="cursor-pointer"
                >
                    <span class="flex items-center gap-2">
                        <span
                            :class="[
                                'h-2 w-2 rounded-full',
                                sync.status === 'synced'
                                    ? 'bg-green-500'
                                    : sync.status === 'failed'
                                      ? 'bg-red-500'
                                      : sync.status === 'syncing'
                                        ? 'bg-blue-500'
                                        : 'bg-yellow-500',
                            ]"
                        ></span>
                        {{
                            sync.device_name ||
                            `Device #${sync.device_id}`
                        }}
                    </span>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Notification popup -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-if="notification"
                class="absolute right-0 top-full z-50 mt-2 flex w-64 items-center gap-2 rounded-lg border px-4 py-3 shadow-lg"
                :class="
                    notification.type === 'success'
                        ? 'border-green-200 bg-green-50 text-green-800'
                        : 'border-red-200 bg-red-50 text-red-800'
                "
            >
                <CheckCircle
                    v-if="notification.type === 'success'"
                    class="h-4 w-4 shrink-0 text-green-500"
                />
                <XCircle
                    v-else
                    class="h-4 w-4 shrink-0 text-red-500"
                />
                <span class="text-sm font-medium">
                    {{ notification.message }}
                </span>
            </div>
        </Transition>
    </div>
</template>
