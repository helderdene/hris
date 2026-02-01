import { onMounted, onUnmounted, ref, type Ref } from 'vue';
import { useTenant } from './useTenant';

/**
 * Attendance log entry from real-time broadcast
 */
export interface AttendanceLogEntry {
    id: number;
    employee_id: number | null;
    employee_name: string | null;
    employee_code: string;
    confidence: number;
    logged_at: string;
    device_name: string;
    direction: 'in' | 'out' | string | null;
}

/**
 * Composable for real-time attendance log updates via WebSocket.
 *
 * Subscribes to the tenant's attendance channel and provides
 * reactive access to incoming attendance logs.
 *
 * @example
 * ```vue
 * <script setup>
 * const { logs, isConnected, latestLog } = useAttendanceLive({ maxLogs: 50 });
 * </script>
 *
 * <template>
 *   <div v-if="isConnected">
 *     <div v-for="log in logs" :key="log.id">
 *       {{ log.employee_name }} - {{ log.logged_at }}
 *     </div>
 *   </div>
 * </template>
 * ```
 */
export function useAttendanceLive(options: { maxLogs?: number } = {}) {
    const { maxLogs = 100 } = options;
    const { tenantId } = useTenant();

    const logs: Ref<AttendanceLogEntry[]> = ref([]);
    const latestLog: Ref<AttendanceLogEntry | null> = ref(null);
    const isConnected = ref(false);
    const connectionError: Ref<string | null> = ref(null);

    let channel: ReturnType<typeof window.Echo.private> | null = null;

    /**
     * Subscribe to the attendance channel for the current tenant.
     */
    function subscribe() {
        if (!tenantId.value || !window.Echo) {
            connectionError.value = 'Echo not initialized or no tenant context';
            return;
        }

        try {
            channel = window.Echo.private(
                `tenant.${tenantId.value}.attendance`,
            );

            channel
                .listen('.log.received', (event: AttendanceLogEntry) => {
                    // Add to front of logs array
                    logs.value.unshift(event);
                    latestLog.value = event;

                    // Trim to max logs
                    if (logs.value.length > maxLogs) {
                        logs.value = logs.value.slice(0, maxLogs);
                    }
                })
                .subscribed(() => {
                    isConnected.value = true;
                    connectionError.value = null;
                })
                .error((error: unknown) => {
                    isConnected.value = false;
                    connectionError.value =
                        error instanceof Error
                            ? error.message
                            : 'Connection failed';
                });
        } catch (error) {
            connectionError.value =
                error instanceof Error ? error.message : 'Failed to subscribe';
        }
    }

    /**
     * Unsubscribe from the attendance channel.
     */
    function unsubscribe() {
        if (channel && tenantId.value) {
            window.Echo.leave(`tenant.${tenantId.value}.attendance`);
            channel = null;
            isConnected.value = false;
        }
    }

    /**
     * Clear all logs from memory.
     */
    function clearLogs() {
        logs.value = [];
        latestLog.value = null;
    }

    // Auto-subscribe on mount, unsubscribe on unmount
    onMounted(() => {
        subscribe();
    });

    onUnmounted(() => {
        unsubscribe();
    });

    return {
        logs,
        latestLog,
        isConnected,
        connectionError,
        subscribe,
        unsubscribe,
        clearLogs,
    };
}
