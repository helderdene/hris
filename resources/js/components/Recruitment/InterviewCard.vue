<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Panelist {
    id: number;
    employee: { id: number; full_name: string };
    is_lead: boolean;
}

interface Interview {
    id: number;
    type_label: string;
    type_color: string;
    status_label: string;
    status_color: string;
    title: string;
    scheduled_at: string;
    duration_minutes: number;
    meeting_url: string | null;
    panelists: Panelist[];
}

defineProps<{
    interview: Interview;
}>();

function getStatusBadgeClasses(color: string): string {
    const colorMap: Record<string, string> = {
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        purple: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
        emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300',
    };
    return colorMap[color] || colorMap.slate;
}

function formatDateTime(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}
</script>

<template>
    <Link
        :href="`/recruitment/interviews/${interview.id}`"
        class="block rounded-lg border border-slate-200 bg-slate-50 p-4 transition-colors hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-slate-600"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ interview.title }}</span>
                    <span :class="getStatusBadgeClasses(interview.status_color)" class="inline-flex shrink-0 rounded-md px-1.5 py-0.5 text-xs font-medium">
                        {{ interview.status_label }}
                    </span>
                </div>
                <div class="mt-1 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <span :class="getStatusBadgeClasses(interview.type_color)" class="rounded px-1.5 py-0.5 text-xs font-medium">{{ interview.type_label }}</span>
                    <span>{{ formatDateTime(interview.scheduled_at) }}</span>
                    <span>{{ interview.duration_minutes }}m</span>
                </div>
                <div v-if="interview.panelists.length" class="mt-2 text-xs text-slate-400">
                    {{ interview.panelists.map(p => p.employee.full_name).join(', ') }}
                </div>
            </div>
            <span v-if="interview.meeting_url" class="shrink-0 text-xs text-blue-500">Video</span>
        </div>
    </Link>
</template>
