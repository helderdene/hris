<script setup lang="ts">
import type { LeaveCalendarEntry } from '@/composables/useLeaveCalendar';
import { useLeaveCalendar } from '@/composables/useLeaveCalendar';

const props = defineProps<{
    entry: LeaveCalendarEntry;
    date: string;
    compact?: boolean;
}>();

defineEmits<{
    click: [entry: LeaveCalendarEntry];
}>();

const { getCategoryColorClasses, isHalfDay } = useLeaveCalendar();

const isPending = props.entry.status === 'pending';
const halfDay = isHalfDay(props.entry, props.date);
</script>

<template>
    <button
        type="button"
        class="w-full truncate rounded px-1.5 py-0.5 text-left text-xs font-medium transition-colors hover:ring-1 hover:ring-slate-400 dark:hover:ring-slate-500"
        :class="[
            getCategoryColorClasses(entry.leave_type.category, isPending),
            compact ? 'text-[10px]' : '',
        ]"
        :title="`${entry.employee.full_name} - ${entry.leave_type.name}${halfDay ? ' (Half Day)' : ''}${isPending ? ' (Pending)' : ''}`"
        @click="$emit('click', entry)"
    >
        <span class="flex items-center gap-1">
            <span class="font-semibold">{{ entry.employee.initials }}</span>
            <span v-if="!compact" class="truncate">
                {{ entry.leave_type.code }}
            </span>
            <span
                v-if="halfDay"
                class="ml-auto text-[10px] opacity-75"
            >
                1/2
            </span>
        </span>
    </button>
</template>
