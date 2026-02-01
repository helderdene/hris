<script setup lang="ts">
/**
 * HolidayDateIndicator Component
 *
 * A visual indicator component for marking dates as holidays in calendar/date picker UIs.
 * Shows a colored dot and optional tooltip with holiday information.
 *
 * Usage in a custom calendar:
 * ```vue
 * <template v-for="day in calendarDays" :key="day.date">
 *   <div class="calendar-day">
 *     {{ day.number }}
 *     <HolidayDateIndicator
 *       v-if="holidayMap.has(day.date)"
 *       :holiday="holidayMap.get(day.date)"
 *     />
 *   </div>
 * </template>
 * ```
 */
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { computed } from 'vue';

export interface Holiday {
    id: number;
    name: string;
    date: string;
    formatted_date?: string;
    holiday_type: string;
    holiday_type_label?: string;
    description?: string | null;
}

interface Props {
    /**
     * The holiday data for this date
     */
    holiday: Holiday;
    /**
     * Size of the indicator dot
     */
    size?: 'sm' | 'md' | 'lg';
    /**
     * Whether to show the tooltip on hover
     */
    showTooltip?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    size: 'sm',
    showTooltip: true,
});

/**
 * Get the indicator color based on holiday type
 */
const indicatorColor = computed(() => {
    switch (props.holiday.holiday_type) {
        case 'regular':
            return 'bg-red-500';
        case 'special_non_working':
            return 'bg-orange-500';
        case 'special_working':
            return 'bg-blue-500';
        case 'double':
            return 'bg-purple-500';
        default:
            return 'bg-slate-500';
    }
});

/**
 * Get the indicator size classes
 */
const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-1.5 w-1.5';
        case 'md':
            return 'h-2 w-2';
        case 'lg':
            return 'h-2.5 w-2.5';
        default:
            return 'h-1.5 w-1.5';
    }
});

/**
 * Get the background highlight color for the calendar cell
 */
const highlightClasses = computed(() => {
    switch (props.holiday.holiday_type) {
        case 'regular':
            return 'bg-red-50 dark:bg-red-950/30';
        case 'special_non_working':
            return 'bg-orange-50 dark:bg-orange-950/30';
        case 'special_working':
            return 'bg-blue-50 dark:bg-blue-950/30';
        case 'double':
            return 'bg-purple-50 dark:bg-purple-950/30';
        default:
            return 'bg-slate-50 dark:bg-slate-950/30';
    }
});

const tooltipContent = computed(() => {
    const parts = [props.holiday.name];
    if (props.holiday.holiday_type_label) {
        parts.push(`(${props.holiday.holiday_type_label})`);
    }
    return parts.join(' ');
});
</script>

<template>
    <TooltipProvider v-if="showTooltip">
        <Tooltip>
            <TooltipTrigger as-child>
                <span
                    class="inline-flex items-center justify-center"
                    data-test="holiday-indicator"
                    :data-holiday-type="holiday.holiday_type"
                >
                    <span
                        class="rounded-full"
                        :class="[indicatorColor, sizeClasses]"
                    />
                </span>
            </TooltipTrigger>
            <TooltipContent side="top" class="max-w-xs">
                <p class="font-medium">{{ holiday.name }}</p>
                <p
                    v-if="holiday.holiday_type_label"
                    class="text-xs text-muted-foreground"
                >
                    {{ holiday.holiday_type_label }}
                </p>
                <p
                    v-if="holiday.description"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    {{ holiday.description }}
                </p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
    <span
        v-else
        class="inline-flex items-center justify-center"
        data-test="holiday-indicator"
        :data-holiday-type="holiday.holiday_type"
        :title="tooltipContent"
    >
        <span class="rounded-full" :class="[indicatorColor, sizeClasses]" />
    </span>
</template>
