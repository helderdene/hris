<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

export interface PendingCounts {
    leaveApprovals: number;
    requisitionApprovals: number;
    probationaryEvaluations: number;
    documentRequests: number;
    onboardingTasks: number;
}

const props = defineProps<{
    counts: PendingCounts;
}>();

interface ActionItem {
    label: string;
    count: number;
    link: string;
    icon: string;
    color: string;
}

const actionItems = computed<ActionItem[]>(() => [
    {
        label: 'Leave Requests',
        count: props.counts.leaveApprovals,
        link: '/leave/applications',
        icon: 'calendar',
        color: 'blue',
    },
    {
        label: 'Job Requisitions',
        count: props.counts.requisitionApprovals,
        link: '/recruitment/requisitions',
        icon: 'briefcase',
        color: 'purple',
    },
    {
        label: 'Probation Reviews',
        count: props.counts.probationaryEvaluations,
        link: '/employees?filter=probationary',
        icon: 'user-check',
        color: 'emerald',
    },
    {
        label: 'Document Requests',
        count: props.counts.documentRequests,
        link: '/document-requests',
        icon: 'document',
        color: 'amber',
    },
    {
        label: 'Onboarding Tasks',
        count: props.counts.onboardingTasks,
        link: '/onboarding',
        icon: 'clipboard',
        color: 'pink',
    },
]);

const totalPending = computed(() =>
    Object.values(props.counts).reduce((sum, count) => sum + count, 0)
);

function getColorClasses(color: string): { bg: string; text: string; badge: string } {
    const colorMap: Record<string, { bg: string; text: string; badge: string }> = {
        blue: {
            bg: 'bg-blue-50 dark:bg-blue-900/30',
            text: 'text-blue-600 dark:text-blue-400',
            badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
        },
        purple: {
            bg: 'bg-purple-50 dark:bg-purple-900/30',
            text: 'text-purple-600 dark:text-purple-400',
            badge: 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300',
        },
        emerald: {
            bg: 'bg-emerald-50 dark:bg-emerald-900/30',
            text: 'text-emerald-600 dark:text-emerald-400',
            badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
        },
        amber: {
            bg: 'bg-amber-50 dark:bg-amber-900/30',
            text: 'text-amber-600 dark:text-amber-400',
            badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
        },
        pink: {
            bg: 'bg-pink-50 dark:bg-pink-900/30',
            text: 'text-pink-600 dark:text-pink-400',
            badge: 'bg-pink-100 text-pink-700 dark:bg-pink-900/50 dark:text-pink-300',
        },
    };
    return colorMap[color] ?? colorMap.blue;
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
                        d="M6 4.75A.75.75 0 016.75 4h10.5a.75.75 0 010 1.5H6.75A.75.75 0 016 4.75zM6 10a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H6.75A.75.75 0 016 10zm0 5.25a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H6.75a.75.75 0 01-.75-.75zM1.99 4.75a1 1 0 011-1H3a1 1 0 011 1v.01a1 1 0 01-1 1h-.01a1 1 0 01-1-1v-.01zM1.99 15.25a1 1 0 011-1H3a1 1 0 011 1v.01a1 1 0 01-1 1h-.01a1 1 0 01-1-1v-.01zM1.99 10a1 1 0 011-1H3a1 1 0 011 1v.01a1 1 0 01-1 1h-.01a1 1 0 01-1-1V10z"
                        clip-rule="evenodd"
                    />
                </svg>
                <CardTitle class="text-lg">Pending Actions</CardTitle>
                <Badge
                    v-if="totalPending > 0"
                    class="bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                >
                    {{ totalPending }}
                </Badge>
            </div>
            <CardDescription>Tasks awaiting your attention</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="space-y-2">
                <Link
                    v-for="item in actionItems"
                    :key="item.label"
                    :href="item.link"
                    class="flex items-center justify-between p-3 rounded-lg transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="h-9 w-9 rounded-lg flex items-center justify-center"
                            :class="getColorClasses(item.color).bg"
                        >
                            <!-- Calendar icon -->
                            <svg
                                v-if="item.icon === 'calendar'"
                                :class="['h-5 w-5', getColorClasses(item.color).text]"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <!-- Briefcase icon -->
                            <svg
                                v-else-if="item.icon === 'briefcase'"
                                :class="['h-5 w-5', getColorClasses(item.color).text]"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M6 3.75A2.75 2.75 0 018.75 1h2.5A2.75 2.75 0 0114 3.75v.443c.572.055 1.14.122 1.706.2C17.053 4.582 18 5.75 18 7.07v3.469c0 1.126-.694 2.191-1.83 2.54-1.952.599-4.024.921-6.17.921s-4.219-.322-6.17-.921C2.694 12.73 2 11.665 2 10.539V7.07c0-1.321.947-2.489 2.294-2.676A41.047 41.047 0 016 4.193V3.75zm6.5 0v.325a41.622 41.622 0 00-5 0V3.75c0-.69.56-1.25 1.25-1.25h2.5c.69 0 1.25.56 1.25 1.25zM10 10a1 1 0 00-1 1v.01a1 1 0 001 1h.01a1 1 0 001-1V11a1 1 0 00-1-1H10z"
                                    clip-rule="evenodd"
                                />
                                <path
                                    d="M3 15.055v-.684c.126.053.255.1.39.142 2.092.642 4.313.987 6.61.987 2.297 0 4.518-.345 6.61-.987.135-.041.264-.089.39-.142v.684c0 1.347-.985 2.53-2.363 2.686a41.454 41.454 0 01-9.274 0C3.985 17.585 3 16.402 3 15.055z"
                                />
                            </svg>
                            <!-- User check icon -->
                            <svg
                                v-else-if="item.icon === 'user-check'"
                                :class="['h-5 w-5', getColorClasses(item.color).text]"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"
                                />
                            </svg>
                            <!-- Document icon -->
                            <svg
                                v-else-if="item.icon === 'document'"
                                :class="['h-5 w-5', getColorClasses(item.color).text]"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M4.5 2A1.5 1.5 0 003 3.5v13A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V7.621a1.5 1.5 0 00-.44-1.06l-4.12-4.122A1.5 1.5 0 0011.378 2H4.5zm2.25 8.5a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5zm0 3a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <!-- Clipboard icon -->
                            <svg
                                v-else-if="item.icon === 'clipboard'"
                                :class="['h-5 w-5', getColorClasses(item.color).text]"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M15.988 3.012A2.25 2.25 0 0118 5.25v6.5A2.25 2.25 0 0115.75 14H13.5V7A2.5 2.5 0 0011 4.5H8.128a2.252 2.252 0 011.884-1.488A2.25 2.25 0 0112.25 1h1.5a2.25 2.25 0 012.238 2.012zM11.5 3.25a.75.75 0 01.75-.75h1.5a.75.75 0 01.75.75v.25h-3v-.25z"
                                    clip-rule="evenodd"
                                />
                                <path
                                    fill-rule="evenodd"
                                    d="M2 7a1 1 0 011-1h8a1 1 0 011 1v10a1 1 0 01-1 1H3a1 1 0 01-1-1V7zm2 3.25a.75.75 0 01.75-.75h4.5a.75.75 0 010 1.5h-4.5a.75.75 0 01-.75-.75zm0 3.5a.75.75 0 01.75-.75h4.5a.75.75 0 010 1.5h-4.5a.75.75 0 01-.75-.75z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>
                        <span class="font-medium text-slate-900 dark:text-slate-100">
                            {{ item.label }}
                        </span>
                    </div>
                    <Badge v-if="item.count > 0" :class="getColorClasses(item.color).badge">
                        {{ item.count }}
                    </Badge>
                    <span v-else class="text-sm text-slate-400 dark:text-slate-500">
                        None
                    </span>
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
