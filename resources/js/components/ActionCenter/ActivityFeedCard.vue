<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { computed } from 'vue';

export interface ActivityItem {
    id: number;
    action: string;
    action_label: string;
    action_color: string;
    model_name: string;
    user_name: string | null;
    created_at: string;
}

const props = defineProps<{
    activities: ActivityItem[] | null;
    loading?: boolean;
}>();

const displayActivities = computed(() => {
    return props.activities?.slice(0, 10) ?? [];
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

function getActionBadgeClasses(color: string): string {
    switch (color) {
        case 'green':
            return 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300';
        case 'blue':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300';
        case 'red':
            return 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
    }
}

function formatModelName(name: string): string {
    // Convert PascalCase to readable format
    return name.replace(/([a-z])([A-Z])/g, '$1 $2');
}
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <div class="flex items-center gap-2">
                <svg
                    class="h-5 w-5 text-slate-600 dark:text-slate-400"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z"
                        clip-rule="evenodd"
                    />
                </svg>
                <CardTitle class="text-lg">Activity Feed</CardTitle>
            </div>
            <CardDescription>Recent changes and events</CardDescription>
        </CardHeader>
        <CardContent>
            <!-- Loading state -->
            <div v-if="loading" class="space-y-3">
                <div v-for="i in 5" :key="i" class="flex items-start gap-3">
                    <Skeleton class="h-2 w-2 rounded-full shrink-0 mt-2" />
                    <div class="flex-1 space-y-2">
                        <Skeleton class="h-4 w-3/4" />
                        <Skeleton class="h-3 w-1/4" />
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else-if="displayActivities.length === 0"
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
                        d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <p class="text-sm">No recent activity</p>
            </div>

            <!-- Activity list -->
            <div v-else class="relative">
                <!-- Timeline line -->
                <div
                    class="absolute left-[5px] top-2 bottom-2 w-px bg-slate-200 dark:bg-slate-700"
                />

                <div class="space-y-4">
                    <div
                        v-for="activity in displayActivities"
                        :key="activity.id"
                        class="relative flex items-start gap-3 pl-1"
                    >
                        <!-- Timeline dot -->
                        <div
                            class="relative z-10 h-2.5 w-2.5 rounded-full mt-1.5 shrink-0"
                            :class="{
                                'bg-green-500': activity.action_color === 'green',
                                'bg-blue-500': activity.action_color === 'blue',
                                'bg-red-500': activity.action_color === 'red',
                                'bg-slate-400': !['green', 'blue', 'red'].includes(activity.action_color),
                            }"
                        />

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <Badge :class="getActionBadgeClasses(activity.action_color)">
                                    {{ activity.action_label }}
                                </Badge>
                                <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ formatModelName(activity.model_name) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                <span v-if="activity.user_name">
                                    by {{ activity.user_name }}
                                </span>
                                <span v-else>
                                    System
                                </span>
                                <span>{{ formatTimeAgo(activity.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
