<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { LeaveCalendarEntry } from '@/composables/useLeaveCalendar';
import { useLeaveCalendar } from '@/composables/useLeaveCalendar';
import { Link } from '@inertiajs/vue3';
import { CalendarIcon, ClockIcon, UserIcon } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    entry: LeaveCalendarEntry | null;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { getCategoryColorClasses } = useLeaveCalendar();

const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

function formatDate(dateStr: string): string {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-PH', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getDateRange(entry: LeaveCalendarEntry): string {
    if (entry.start_date === entry.end_date) {
        return formatDate(entry.start_date);
    }
    return `${formatDate(entry.start_date)} - ${formatDate(entry.end_date)}`;
}

function getStatusBadgeClasses(status: string): string {
    switch (status) {
        case 'approved':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'pending':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'rejected':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300';
    }
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent v-if="entry" class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <UserIcon class="h-5 w-5 text-slate-500" />
                    {{ entry.employee.full_name }}
                </DialogTitle>
                <DialogDescription>
                    {{ entry.employee.department ?? 'No Department' }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- Leave Type -->
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-500 dark:text-slate-400">Leave Type</span>
                    <span
                        class="rounded-md px-2 py-1 text-xs font-medium"
                        :class="getCategoryColorClasses(entry.leave_type.category, false)"
                    >
                        {{ entry.leave_type.name }}
                    </span>
                </div>

                <!-- Status -->
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-500 dark:text-slate-400">Status</span>
                    <Badge :class="getStatusBadgeClasses(entry.status)">
                        {{ entry.status_label }}
                    </Badge>
                </div>

                <!-- Dates -->
                <div class="flex items-start gap-3">
                    <CalendarIcon class="mt-0.5 h-4 w-4 text-slate-400" />
                    <div>
                        <div class="text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ getDateRange(entry) }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            <span v-if="entry.is_half_day_start && entry.start_date === entry.end_date">
                                Half Day
                            </span>
                            <span v-else-if="entry.is_half_day_start || entry.is_half_day_end">
                                <span v-if="entry.is_half_day_start">Half day start</span>
                                <span v-if="entry.is_half_day_start && entry.is_half_day_end"> / </span>
                                <span v-if="entry.is_half_day_end">Half day end</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Duration -->
                <div class="flex items-center gap-3">
                    <ClockIcon class="h-4 w-4 text-slate-400" />
                    <span class="text-sm text-slate-900 dark:text-slate-100">
                        {{ entry.total_days }} day{{ entry.total_days !== 1 ? 's' : '' }}
                    </span>
                </div>

                <!-- Reason -->
                <div v-if="entry.reason" class="border-t border-slate-200 pt-4 dark:border-slate-700">
                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Reason</div>
                    <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                        {{ entry.reason }}
                    </p>
                </div>

                <!-- Reference Number -->
                <div class="text-xs text-slate-400 dark:text-slate-500">
                    Ref: {{ entry.reference_number }}
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="isOpen = false">
                    Close
                </Button>
                <Link :href="`/leave/applications/${entry.id}`">
                    <Button>View Full Details</Button>
                </Link>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
