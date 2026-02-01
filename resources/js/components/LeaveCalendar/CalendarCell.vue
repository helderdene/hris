<script setup lang="ts">
import type { CalendarDay, LeaveCalendarEntry } from '@/composables/useLeaveCalendar';
import CalendarLeaveEntry from './CalendarLeaveEntry.vue';
import { computed } from 'vue';

const props = defineProps<{
    day: CalendarDay;
    maxVisibleEntries?: number;
}>();

const emit = defineEmits<{
    'entry-click': [entry: LeaveCalendarEntry];
    'more-click': [day: CalendarDay];
}>();

const maxVisible = computed(() => props.maxVisibleEntries ?? 3);
const visibleEntries = computed(() => props.day.entries.slice(0, maxVisible.value));
const moreCount = computed(() => props.day.entries.length - maxVisible.value);
</script>

<template>
    <div
        class="min-h-24 border-b border-r border-slate-200 p-1 dark:border-slate-700"
        :class="{
            'bg-slate-50 dark:bg-slate-800/30': !day.isCurrentMonth,
            'bg-blue-50 dark:bg-blue-900/20': day.isToday && day.isCurrentMonth,
            'bg-slate-100/50 dark:bg-slate-800/20': day.isWeekend && day.isCurrentMonth && !day.isToday,
        }"
    >
        <div class="mb-1 flex items-center justify-between">
            <span
                class="text-xs font-medium"
                :class="{
                    'text-slate-400 dark:text-slate-500': !day.isCurrentMonth,
                    'rounded-full bg-blue-600 px-1.5 py-0.5 text-white': day.isToday,
                    'text-slate-700 dark:text-slate-300': day.isCurrentMonth && !day.isToday,
                }"
            >
                {{ day.dayOfMonth }}
            </span>
        </div>

        <div class="flex flex-col gap-0.5">
            <CalendarLeaveEntry
                v-for="entry in visibleEntries"
                :key="entry.id"
                :entry="entry"
                :date="day.date"
                :compact="day.entries.length > 2"
                @click="emit('entry-click', entry)"
            />

            <button
                v-if="moreCount > 0"
                type="button"
                class="mt-0.5 w-full rounded bg-slate-100 px-1 py-0.5 text-center text-[10px] font-medium text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-slate-600"
                @click="emit('more-click', day)"
            >
                +{{ moreCount }} more
            </button>
        </div>
    </div>
</template>
