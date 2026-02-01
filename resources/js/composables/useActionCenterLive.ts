import { onMounted, onUnmounted, ref, watch, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTenant } from './useTenant';

/**
 * Action center update event from real-time broadcast
 */
export interface ActionCenterUpdate {
    action: string;
    data: Record<string, unknown>;
    timestamp: string;
}

/**
 * Options for the useActionCenterLive composable
 */
export interface ActionCenterLiveOptions {
    /** Enable polling fallback (default: true) */
    enablePolling?: boolean;
    /** Polling interval in milliseconds (default: 60000) */
    pollingInterval?: number;
    /** Callback when an update is received */
    onUpdate?: (update: ActionCenterUpdate) => void;
}

/**
 * Composable for real-time Action Center updates via WebSocket.
 *
 * Subscribes to the tenant's action-center channel and provides
 * reactive access to incoming updates with polling fallback.
 *
 * @example
 * ```vue
 * <script setup>
 * const { isConnected, lastUpdate, refresh } = useActionCenterLive({
 *   onUpdate: (update) => {
 *     console.log('Action center updated:', update);
 *   }
 * });
 * </script>
 *
 * <template>
 *   <div v-if="isConnected" class="text-green-500">Live</div>
 *   <div v-else class="text-amber-500">Polling</div>
 * </template>
 * ```
 */
export function useActionCenterLive(options: ActionCenterLiveOptions = {}) {
    const {
        enablePolling = true,
        pollingInterval = 60000,
        onUpdate,
    } = options;

    const { tenantId } = useTenant();

    const isConnected = ref(false);
    const connectionError: Ref<string | null> = ref(null);
    const lastUpdate: Ref<ActionCenterUpdate | null> = ref(null);
    const updates: Ref<ActionCenterUpdate[]> = ref([]);

    let channel: ReturnType<typeof window.Echo.private> | null = null;
    let pollingTimer: ReturnType<typeof setInterval> | null = null;
    let isPolling = ref(false);

    /**
     * Subscribe to the action center channel for the current tenant.
     */
    function subscribe() {
        if (!tenantId.value || !window.Echo) {
            connectionError.value = 'Echo not initialized or no tenant context';
            startPollingFallback();
            return;
        }

        try {
            const channelName = `tenant.${tenantId.value}.action-center`;
            channel = window.Echo.private(channelName);

            channel
                .listen('.action-center.updated', (event: ActionCenterUpdate) => {
                    handleUpdate(event);
                })
                .subscribed(() => {
                    isConnected.value = true;
                    connectionError.value = null;
                    stopPolling();
                })
                .error((error: unknown) => {
                    console.error('[ActionCenterLive] Channel error:', error);
                    isConnected.value = false;
                    connectionError.value =
                        error instanceof Error
                            ? error.message
                            : 'Connection failed';
                    startPollingFallback();
                });
        } catch (error) {
            console.error('[ActionCenterLive] Subscribe error:', error);
            connectionError.value =
                error instanceof Error ? error.message : 'Failed to subscribe';
            startPollingFallback();
        }
    }

    /**
     * Unsubscribe from the action center channel.
     */
    function unsubscribe() {
        if (channel && tenantId.value) {
            window.Echo.leave(`tenant.${tenantId.value}.action-center`);
            channel = null;
            isConnected.value = false;
        }
        stopPolling();
    }

    /**
     * Handle an incoming update event.
     */
    function handleUpdate(event: ActionCenterUpdate) {
        lastUpdate.value = event;
        updates.value.unshift(event);

        // Keep only the last 50 updates
        if (updates.value.length > 50) {
            updates.value = updates.value.slice(0, 50);
        }

        // Call the onUpdate callback if provided
        if (onUpdate) {
            onUpdate(event);
        }
    }

    /**
     * Start polling fallback when WebSocket connection fails.
     */
    function startPollingFallback() {
        if (!enablePolling || pollingTimer) {
            return;
        }

        isPolling.value = true;
        pollingTimer = setInterval(() => {
            refresh();
        }, pollingInterval);
    }

    /**
     * Stop the polling fallback.
     */
    function stopPolling() {
        if (pollingTimer) {
            clearInterval(pollingTimer);
            pollingTimer = null;
        }
        isPolling.value = false;
    }

    /**
     * Manually refresh the page data via Inertia.
     */
    function refresh() {
        router.reload({
            only: [
                'pendingActions',
                'priorityItems',
                'notifications',
                'unreadNotificationCount',
                'activityFeed',
                'pendingLeaveDetails',
                'pendingRequisitionDetails',
            ],
            preserveScroll: true,
        });
    }

    /**
     * Clear all stored updates.
     */
    function clearUpdates() {
        updates.value = [];
        lastUpdate.value = null;
    }

    // Auto-subscribe on mount, unsubscribe on unmount
    onMounted(() => {
        subscribe();
    });

    onUnmounted(() => {
        unsubscribe();
    });

    // Watch for tenant changes and re-subscribe
    watch(
        () => tenantId.value,
        (newTenantId, oldTenantId) => {
            if (newTenantId && newTenantId !== oldTenantId) {
                unsubscribe();
                subscribe();
            }
        },
    );

    return {
        isConnected,
        isPolling,
        connectionError,
        lastUpdate,
        updates,
        subscribe,
        unsubscribe,
        refresh,
        clearUpdates,
    };
}
