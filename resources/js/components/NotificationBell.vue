<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import {
    useNotifications,
    type Notification,
} from '@/composables/useNotifications';
import { router } from '@inertiajs/vue3';
import { Bell, Check, Download, FileText, Inbox } from 'lucide-vue-next';

const {
    notifications,
    unreadCount,
    isLoading,
    hasUnread,
    hasNotifications,
    markAsRead,
    markAllAsRead,
    getDownloadUrl,
    hasDownload,
} = useNotifications();

function handleNotificationClick(notification: Notification) {
    if (!notification.is_read) {
        markAsRead(notification.id);
    }
    if (notification.url) {
        router.visit(notification.url);
    }
}

function handleMarkAllAsRead() {
    markAllAsRead();
}

function handleDownload(notification: Notification) {
    if (hasDownload(notification)) {
        // Open download in new tab to trigger browser download
        window.open(getDownloadUrl(notification.id), '_blank');

        // Mark as read if not already
        if (!notification.is_read) {
            markAsRead(notification.id);
        }
    }
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative text-slate-600 hover:text-slate-900"
                aria-label="Notifications"
            >
                <Bell class="h-5 w-5" />
                <!-- Unread badge -->
                <span
                    v-if="hasUnread"
                    class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-medium text-white"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            class="w-80"
            align="end"
            :side-offset="8"
        >
            <!-- Header -->
            <div class="flex items-center justify-between px-3 py-2">
                <DropdownMenuLabel class="p-0 text-sm font-semibold">
                    Notifications
                </DropdownMenuLabel>
                <button
                    v-if="hasUnread"
                    type="button"
                    class="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800"
                    @click.stop="handleMarkAllAsRead"
                >
                    <Check class="h-3 w-3" />
                    Mark all as read
                </button>
            </div>

            <DropdownMenuSeparator />

            <!-- Loading state -->
            <div v-if="isLoading && !hasNotifications" class="space-y-2 p-3">
                <div v-for="i in 3" :key="i" class="flex gap-3">
                    <Skeleton class="h-10 w-10 rounded-full" />
                    <div class="flex-1 space-y-2">
                        <Skeleton class="h-4 w-3/4" />
                        <Skeleton class="h-3 w-1/2" />
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else-if="!hasNotifications"
                class="flex flex-col items-center justify-center py-8 text-slate-500"
            >
                <Inbox class="mb-2 h-10 w-10 text-slate-300" />
                <p class="text-sm">No notifications</p>
            </div>

            <!-- Notifications list -->
            <DropdownMenuGroup v-else class="max-h-80 overflow-y-auto">
                <DropdownMenuItem
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="flex cursor-pointer flex-col items-start gap-1 p-3"
                    :class="{
                        'bg-blue-50/50': !notification.is_read,
                    }"
                    @click="handleNotificationClick(notification)"
                >
                    <div class="flex w-full items-start justify-between gap-2">
                        <div class="flex items-start gap-3">
                            <!-- Icon -->
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                                :class="
                                    notification.is_read
                                        ? 'bg-slate-100 text-slate-500'
                                        : 'bg-blue-100 text-blue-600'
                                "
                            >
                                <FileText class="h-5 w-5" />
                            </div>

                            <!-- Content -->
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm font-medium text-slate-900"
                                    :class="{
                                        'font-semibold': !notification.is_read,
                                    }"
                                >
                                    {{ notification.title }}
                                </p>
                                <p class="text-xs text-slate-600">
                                    {{ notification.message }}
                                </p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ notification.time_ago }}
                                </p>
                            </div>
                        </div>

                        <!-- Unread indicator -->
                        <div
                            v-if="!notification.is_read"
                            class="mt-1 h-2 w-2 shrink-0 rounded-full bg-blue-500"
                        />
                    </div>

                    <!-- Download button for payslip notifications -->
                    <button
                        v-if="hasDownload(notification)"
                        type="button"
                        class="mt-2 flex w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-700"
                        @click.stop="handleDownload(notification)"
                    >
                        <Download class="h-3.5 w-3.5" />
                        Download PDF
                    </button>
                </DropdownMenuItem>
            </DropdownMenuGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
