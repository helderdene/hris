import {
    download,
    index,
    markAllAsRead as markAllAsReadAction,
    markAsRead,
} from '@/actions/App/Http/Controllers/Api/NotificationController';
import { useTenant } from '@/composables/useTenant';
import type { Notification } from '@/types';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref } from 'vue';

export type { Notification } from '@/types';

interface NotificationsResponse {
    notifications: Notification[];
    unread_count: number;
}

interface BroadcastNotification {
    id: string;
    type: string;
    title?: string;
    message?: string;
    [key: string]: unknown;
}

const FALLBACK_POLL_INTERVAL = 120000; // 2 minutes fallback

export function useNotifications() {
    const { tenantSlug } = useTenant();
    const page = usePage();

    const notifications = ref<Notification[]>([]);
    const unreadCount = ref(0);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    let pollInterval: ReturnType<typeof setInterval> | null = null;
    let echoChannel: any = null;

    /**
     * Fetch notifications from the API.
     */
    async function fetch() {
        if (!tenantSlug.value) {
            return;
        }

        try {
            isLoading.value = true;
            error.value = null;

            const response = await axios.get<NotificationsResponse>(
                index.url(tenantSlug.value),
            );

            notifications.value = response.data.notifications;
            unreadCount.value = response.data.unread_count;
        } catch (err) {
            error.value =
                err instanceof Error
                    ? err.message
                    : 'Failed to fetch notifications';
            console.error('Failed to fetch notifications:', err);
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Mark a specific notification as read.
     */
    async function markNotificationAsRead(notificationId: string) {
        if (!tenantSlug.value) {
            return;
        }

        try {
            await axios.post(
                markAsRead.url({
                    tenant: tenantSlug.value,
                    notification: notificationId,
                }),
            );

            // Update local state
            const notification = notifications.value.find(
                (n) => n.id === notificationId,
            );
            if (notification) {
                notification.is_read = true;
                notification.read_at = new Date().toISOString();
                unreadCount.value = Math.max(0, unreadCount.value - 1);
            }
        } catch (err) {
            console.error('Failed to mark notification as read:', err);
            throw err;
        }
    }

    /**
     * Mark all notifications as read.
     */
    async function markAllNotificationsAsRead() {
        if (!tenantSlug.value) {
            return;
        }

        try {
            await axios.post(markAllAsReadAction.url(tenantSlug.value));

            // Update local state
            notifications.value.forEach((n) => {
                n.is_read = true;
                n.read_at = new Date().toISOString();
            });
            unreadCount.value = 0;
        } catch (err) {
            console.error('Failed to mark all notifications as read:', err);
            throw err;
        }
    }

    /**
     * Get the download URL for a notification's file attachment.
     */
    function getDownloadUrl(notificationId: string): string {
        if (!tenantSlug.value) {
            return '';
        }

        return download.url({
            tenant: tenantSlug.value,
            notification: notificationId,
        });
    }

    /**
     * Check if a notification has a downloadable file.
     */
    function hasDownload(notification: Notification): boolean {
        return !!notification.file_path;
    }

    /**
     * Subscribe to real-time notifications via Echo.
     */
    function subscribeToEcho() {
        const userId = (page.props.auth as { user: { id: number } })?.user?.id;
        if (!userId || !window.Echo) {
            return;
        }

        echoChannel = window.Echo.private(`App.Models.User.${userId}`)
            .notification((notification: BroadcastNotification) => {
                // Add the new notification to the top of the list
                const newNotification: Notification = {
                    id: notification.id,
                    type: notification.type ?? 'Notification',
                    title: notification.title ?? 'Notification',
                    message: notification.message ?? '',
                    is_read: false,
                    read_at: null,
                    created_at: new Date().toISOString(),
                    time_ago: 'just now',
                    url: (notification.url as string) ?? null,
                    file_path: (notification.file_path as string) ?? null,
                    file_name: (notification.file_name as string) ?? null,
                };

                notifications.value.unshift(newNotification);
                unreadCount.value++;

                // Keep only the latest 20
                if (notifications.value.length > 20) {
                    notifications.value = notifications.value.slice(0, 20);
                }
            });
    }

    /**
     * Unsubscribe from Echo channel.
     */
    function unsubscribeFromEcho() {
        const userId = (page.props.auth as { user: { id: number } })?.user?.id;
        if (userId && window.Echo) {
            window.Echo.leave(`App.Models.User.${userId}`);
            echoChannel = null;
        }
    }

    /**
     * Start fallback polling for notifications.
     */
    function startPolling() {
        if (pollInterval) {
            return;
        }

        pollInterval = setInterval(fetch, FALLBACK_POLL_INTERVAL);
    }

    /**
     * Stop polling for notifications.
     */
    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    // Computed properties
    const hasUnread = computed(() => unreadCount.value > 0);
    const unreadNotifications = computed(() =>
        notifications.value.filter((n) => !n.is_read),
    );
    const readNotifications = computed(() =>
        notifications.value.filter((n) => n.is_read),
    );
    const hasNotifications = computed(() => notifications.value.length > 0);

    // Auto-start on mount: initial fetch, Echo subscription, and fallback polling
    onMounted(() => {
        if (tenantSlug.value) {
            fetch();
            subscribeToEcho();
            startPolling();
        }
    });

    onUnmounted(() => {
        unsubscribeFromEcho();
        stopPolling();
    });

    return {
        // State
        notifications,
        unreadCount,
        isLoading,
        error,

        // Computed
        hasUnread,
        hasNotifications,
        unreadNotifications,
        readNotifications,

        // Actions
        fetch,
        markAsRead: markNotificationAsRead,
        markAllAsRead: markAllNotificationsAsRead,
        getDownloadUrl,
        hasDownload,
        startPolling,
        stopPolling,
    };
}
