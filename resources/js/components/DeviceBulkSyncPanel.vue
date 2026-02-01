<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    type DeviceSyncStatusMeta,
    type EmployeeDeviceSync,
} from '@/types/sync';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SyncStatusBadge from './SyncStatusBadge.vue';

interface Props {
    deviceId: number;
    deviceName: string;
    syncStatuses: EmployeeDeviceSync[];
    meta: DeviceSyncStatusMeta;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'sync-complete'): void;
}>();

const isLoading = ref(false);

const syncProgress = computed(() => {
    if (props.meta.total_employees === 0) return 100;
    return Math.round(
        (props.meta.synced_count / props.meta.total_employees) * 100
    );
});

const overallStatus = computed(() => {
    if (props.meta.failed_count > 0) return 'failed';
    if (props.meta.pending_count > 0) return 'pending';
    if (props.meta.synced_count === props.meta.total_employees) return 'synced';
    return 'pending';
});

async function syncAll() {
    isLoading.value = true;

    try {
        await router.post(
            `/api/organization/devices/${props.deviceId}/sync-all`,
            {
                immediate: false,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    isLoading.value = false;
                    emit('sync-complete');
                },
            }
        );
    } catch (error) {
        console.error('Bulk sync failed:', error);
        isLoading.value = false;
    }
}

async function retryFailed() {
    isLoading.value = true;

    const failedEmployeeIds = props.syncStatuses
        .filter((s) => s.status === 'failed')
        .map((s) => s.employee_id);

    try {
        await router.post(
            `/api/organization/devices/${props.deviceId}/sync-all`,
            {
                employee_ids: failedEmployeeIds,
                immediate: true,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    isLoading.value = false;
                    emit('sync-complete');
                },
            }
        );
    } catch (error) {
        console.error('Retry failed:', error);
        isLoading.value = false;
    }
}
</script>

<template>
    <div
        class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
    >
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h4
                        class="text-sm font-medium text-slate-900 dark:text-slate-100"
                    >
                        {{ deviceName }}
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ meta.synced_count }} of {{ meta.total_employees }}
                        employees synced
                    </p>
                </div>
                <SyncStatusBadge :status="overallStatus" />
            </div>
            <div class="flex items-center gap-2">
                <Button
                    v-if="meta.failed_count > 0"
                    variant="outline"
                    size="sm"
                    @click="retryFailed"
                    :disabled="isLoading"
                    class="text-red-600 hover:text-red-700"
                >
                    <svg
                        v-if="isLoading"
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
                    Retry Failed ({{ meta.failed_count }})
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    @click="syncAll"
                    :disabled="isLoading"
                >
                    <svg
                        v-if="isLoading"
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
                    Sync All
                </Button>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mt-3">
            <div
                class="h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
            >
                <div
                    class="h-full transition-all duration-300"
                    :class="[
                        overallStatus === 'synced'
                            ? 'bg-green-500'
                            : overallStatus === 'failed'
                              ? 'bg-red-500'
                              : 'bg-blue-500',
                    ]"
                    :style="{ width: `${syncProgress}%` }"
                ></div>
            </div>
        </div>
    </div>
</template>
