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
import { computed } from 'vue';

export interface PriorityItem {
    id: number;
    type: string;
    priority: 'critical' | 'high' | 'medium';
    priority_label: string;
    priority_color: string;
    title: string;
    employee_name: string;
    description: string;
    hours_overdue?: number;
    hours_remaining?: number;
    link: string;
    created_at: string;
}

const props = defineProps<{
    items?: PriorityItem[];
}>();

const emit = defineEmits<{
    approve: [item: PriorityItem];
    reject: [item: PriorityItem];
}>();

const safeItems = computed(() => props.items ?? []);
const hasItems = computed(() => safeItems.value.length > 0);

function getPriorityClasses(priority: string): string {
    switch (priority) {
        case 'critical':
            return 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/30';
        case 'high':
            return 'border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-900/30';
        default:
            return 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/30';
    }
}

function getPriorityBadgeClasses(priority: string): string {
    switch (priority) {
        case 'critical':
            return 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300';
        case 'high':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300';
        default:
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300';
    }
}

function formatTimeLabel(item: PriorityItem): string {
    if (item.priority === 'critical' && item.hours_overdue) {
        return `${Math.round(item.hours_overdue)}h overdue`;
    }
    if (item.hours_remaining) {
        return `${Math.round(item.hours_remaining)}h remaining`;
    }
    return '';
}

function getTypeLabel(type: string): string {
    switch (type) {
        case 'leave_approval':
            return 'Leave Request';
        case 'requisition_approval':
            return 'Job Requisition';
        default:
            return 'Approval';
    }
}
</script>

<template>
    <Card v-if="hasItems" class="border-2 border-red-200 dark:border-red-800">
        <CardHeader class="pb-3">
            <div class="flex items-center gap-2">
                <svg
                    class="h-5 w-5 text-red-600 dark:text-red-400"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                        clip-rule="evenodd"
                    />
                </svg>
                <CardTitle class="text-lg">Priority Alerts</CardTitle>
            </div>
            <CardDescription>
                {{ safeItems.length }} item{{ safeItems.length > 1 ? 's' : '' }} need{{ safeItems.length === 1 ? 's' : '' }} immediate attention
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
            <div
                v-for="item in safeItems"
                :key="`${item.type}-${item.id}`"
                :class="[
                    'flex items-center justify-between gap-4 rounded-lg border p-3',
                    getPriorityClasses(item.priority),
                ]"
            >
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <Badge :class="getPriorityBadgeClasses(item.priority)">
                            {{ item.priority_label }}
                        </Badge>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ getTypeLabel(item.type) }}
                        </span>
                        <span
                            v-if="formatTimeLabel(item)"
                            class="text-xs font-medium"
                            :class="item.priority === 'critical' ? 'text-red-600 dark:text-red-400' : 'text-orange-600 dark:text-orange-400'"
                        >
                            {{ formatTimeLabel(item) }}
                        </span>
                    </div>
                    <p class="font-medium text-slate-900 dark:text-slate-100 truncate">
                        {{ item.employee_name }}
                    </p>
                    <p class="text-sm text-slate-600 dark:text-slate-400 truncate">
                        {{ item.description }}
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <Button
                        size="sm"
                        variant="outline"
                        class="border-green-300 text-green-700 hover:bg-green-50 dark:border-green-700 dark:text-green-400 dark:hover:bg-green-900/30"
                        @click="emit('approve', item)"
                    >
                        <svg
                            class="h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        Approve
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="border-red-300 text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/30"
                        @click="emit('reject', item)"
                    >
                        <svg
                            class="h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                            />
                        </svg>
                        Reject
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
