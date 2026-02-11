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
import { CheckCircle, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

const hasPendingSyncs = computed(() =>
    props.syncStatuses.some(
        (s) => s.status === 'pending' || s.status === 'failed'
    )
);

async function syncToDevice(deviceId: number) {
    const device = props.syncStatuses.find((s) => s.device_id === deviceId);
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
        router.reload({ only: ['syncStatuses'] });
        emit('sync-complete');
    } catch (error) {
        console.error('Sync failed:', error);
        showNotification('Failed to queue sync jobs', 'error');
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <div class="relative">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="isLoading || syncStatuses.length === 0"
                    class="gap-2"
                >
                    <svg
                        v-if="isLoading"
                        class="h-4 w-4 animate-spin"
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
                    Sync to Devices
                    <span
                        v-if="hasPendingSyncs"
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
                <DropdownMenuSeparator v-if="syncStatuses.length > 0" />
                <DropdownMenuItem
                    v-for="sync in syncStatuses"
                    :key="sync.id"
                    @click="syncToDevice(sync.device_id)"
                    :disabled="isLoading && loadingDeviceId === sync.device_id"
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
                        {{ sync.device_name || `Device #${sync.device_id}` }}
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
