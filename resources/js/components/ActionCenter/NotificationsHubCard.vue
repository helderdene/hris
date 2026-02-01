<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

export interface Notification {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
}

const props = defineProps<{
    notifications: Notification[] | null;
    unreadCount: number | null;
    loading?: boolean;
}>();

const isMarkingRead = ref(false);

const displayNotifications = computed(() => {
    return props.notifications?.slice(0, 5) ?? [];
});

function formatTimeAgo(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

function getNotificationTitle(notification: Notification): string {
    const data = notification.data as Record<string, string>;
    return data.title || data.message || notification.type.split('\\').pop() || 'Notification';
}

function getNotificationDescription(notification: Notification): string | null {
    const data = notification.data as Record<string, string>;
    return data.description || data.body || null;
}

async function markAsRead(notification: Notification) {
    if (notification.read_at) return;

    try {
        await fetch(`/api/notifications/${notification.id}/read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''
                ),
            },
        });
        router.reload({ only: ['notifications', 'unreadNotificationCount'] });
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
}

async function markAllAsRead() {
    if (isMarkingRead.value) return;

    isMarkingRead.value = true;
    try {
        await fetch('/api/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''
                ),
            },
        });
        router.reload({ only: ['notifications', 'unreadNotificationCount'] });
    } catch (error) {
        console.error('Failed to mark all notifications as read:', error);
    } finally {
        isMarkingRead.value = false;
    }
}
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg
                        class="h-5 w-5 text-slate-600 dark:text-slate-400"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 2a6 6 0 00-6 6c0 1.887-.454 3.665-1.257 5.234a.75.75 0 00.515 1.076 32.91 32.91 0 003.256.508 3.5 3.5 0 006.972 0 32.903 32.903 0 003.256-.508.75.75 0 00.515-1.076A11.448 11.448 0 0116 8a6 6 0 00-6-6zM8.05 14.943a33.54 33.54 0 003.9 0 2 2 0 01-3.9 0z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <CardTitle class="text-lg">Notifications</CardTitle>
                    <Badge
                        v-if="unreadCount && unreadCount > 0"
                        class="bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300"
                    >
                        {{ unreadCount }}
                    </Badge>
                </div>
                <Button
                    v-if="unreadCount && unreadCount > 0"
                    size="sm"
                    variant="ghost"
                    :disabled="isMarkingRead"
                    @click="markAllAsRead"
                >
                    Mark all read
                </Button>
            </div>
            <CardDescription>Recent notifications and alerts</CardDescription>
        </CardHeader>
        <CardContent>
            <!-- Loading state -->
            <div v-if="loading" class="space-y-3">
                <div v-for="i in 3" :key="i" class="flex items-start gap-3">
                    <Skeleton class="h-8 w-8 rounded-full shrink-0" />
                    <div class="flex-1 space-y-2">
                        <Skeleton class="h-4 w-3/4" />
                        <Skeleton class="h-3 w-1/2" />
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else-if="displayNotifications.length === 0"
                class="text-center py-6 text-slate-500 dark:text-slate-400"
            >
                <svg
                    class="mx-auto h-8 w-8 mb-2"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
                    />
                </svg>
                <p class="text-sm">No notifications</p>
            </div>

            <!-- Notification list -->
            <div v-else class="space-y-3">
                <button
                    v-for="notification in displayNotifications"
                    :key="notification.id"
                    class="w-full text-left flex items-start gap-3 p-2 rounded-lg transition-colors"
                    :class="
                        notification.read_at
                            ? 'opacity-60 hover:bg-slate-50 dark:hover:bg-slate-800/50'
                            : 'bg-blue-50/50 hover:bg-blue-50 dark:bg-blue-900/20 dark:hover:bg-blue-900/30'
                    "
                    @click="markAsRead(notification)"
                >
                    <div
                        class="h-8 w-8 rounded-full flex items-center justify-center shrink-0"
                        :class="
                            notification.read_at
                                ? 'bg-slate-100 dark:bg-slate-800'
                                : 'bg-blue-100 dark:bg-blue-900/50'
                        "
                    >
                        <svg
                            class="h-4 w-4"
                            :class="
                                notification.read_at
                                    ? 'text-slate-500 dark:text-slate-400'
                                    : 'text-blue-600 dark:text-blue-400'
                            "
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 2a6 6 0 00-6 6c0 1.887-.454 3.665-1.257 5.234a.75.75 0 00.515 1.076 32.91 32.91 0 003.256.508 3.5 3.5 0 006.972 0 32.903 32.903 0 003.256-.508.75.75 0 00.515-1.076A11.448 11.448 0 0116 8a6 6 0 00-6-6zM8.05 14.943a33.54 33.54 0 003.9 0 2 2 0 01-3.9 0z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p
                            class="text-sm font-medium truncate"
                            :class="
                                notification.read_at
                                    ? 'text-slate-700 dark:text-slate-300'
                                    : 'text-slate-900 dark:text-slate-100'
                            "
                        >
                            {{ getNotificationTitle(notification) }}
                        </p>
                        <p
                            v-if="getNotificationDescription(notification)"
                            class="text-xs text-slate-500 dark:text-slate-400 truncate"
                        >
                            {{ getNotificationDescription(notification) }}
                        </p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                            {{ formatTimeAgo(notification.created_at) }}
                        </p>
                    </div>
                    <div
                        v-if="!notification.read_at"
                        class="h-2 w-2 rounded-full bg-blue-500 shrink-0 mt-2"
                    />
                </button>
            </div>
        </CardContent>
    </Card>
</template>
