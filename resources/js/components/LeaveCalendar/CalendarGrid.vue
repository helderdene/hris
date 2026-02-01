<script setup lang="ts">
import type { CalendarDay, LeaveCalendarEntry } from '@/composables/useLeaveCalendar';
import CalendarCell from './CalendarCell.vue';

defineProps<{
    days: CalendarDay[];
    isLoading?: boolean;
}>();

const emit = defineEmits<{
    'entry-click': [entry: LeaveCalendarEntry];
    'day-click': [day: CalendarDay];
}>();

const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
</script>

<template>
    <div class="overflow-x-auto">
        <div class="min-w-[560px]">
            <!-- Day headers -->
            <div class="grid grid-cols-7 border-l border-t border-slate-200 dark:border-slate-700">
                <div
                    v-for="day in dayHeaders"
                    :key="day"
                    class="border-b border-r border-slate-200 bg-slate-100 px-2 py-2 text-center text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                >
                    {{ day }}
                </div>
            </div>

            <!-- Calendar grid -->
            <div
                class="relative grid grid-cols-7 border-l border-t border-slate-200 dark:border-slate-700"
                :class="{ 'opacity-50': isLoading }"
            >
                <CalendarCell
                    v-for="calendarDay in days"
                    :key="calendarDay.date"
                    :day="calendarDay"
                    @entry-click="emit('entry-click', $event)"
                    @more-click="emit('day-click', $event)"
                />

                <!-- Loading overlay -->
                <div
                    v-if="isLoading"
                    class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-slate-900/50"
                >
                    <div class="flex items-center gap-2 rounded-lg bg-white px-4 py-2 shadow-lg dark:bg-slate-800">
                        <svg
                            class="h-5 w-5 animate-spin text-slate-600 dark:text-slate-400"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        <span class="text-sm text-slate-600 dark:text-slate-400">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
