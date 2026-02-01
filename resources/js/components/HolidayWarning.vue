<script setup lang="ts">
/**
 * HolidayWarning Component
 *
 * Displays a warning alert when selected leave dates include holidays.
 * The warning is informational only - it does not block form submission.
 *
 * Usage:
 * ```vue
 * <HolidayWarning
 *   :holidays="overlappingHolidays"
 *   v-if="overlappingHolidays.length > 0"
 * />
 * ```
 */
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';

export interface HolidayData {
    id: number;
    name: string;
    date: string;
    formatted_date?: string;
    holiday_type_label?: string;
}

interface Props {
    /**
     * Array of holidays that overlap with selected dates
     */
    holidays: HolidayData[];
    /**
     * Custom title for the warning (optional)
     */
    title?: string;
    /**
     * Whether to show the list of holiday names (optional)
     */
    showHolidayList?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Holiday Notice',
    showHolidayList: true,
});

const holidayCount = computed(() => props.holidays.length);

const warningMessage = computed(() => {
    const count = holidayCount.value;
    if (count === 0) {
        return '';
    }
    const plural = count === 1 ? '' : 's';
    return `Your selected dates include ${count} holiday${plural}. Leave will still be filed but holiday dates do not consume leave credits.`;
});

const sortedHolidays = computed(() => {
    return [...props.holidays].sort((a, b) => a.date.localeCompare(b.date));
});
</script>

<template>
    <Alert
        v-if="holidays.length > 0"
        variant="warning"
        data-test="holiday-warning"
    >
        <AlertTriangle class="size-4" />
        <AlertTitle>{{ title }}</AlertTitle>
        <AlertDescription>
            <p>{{ warningMessage }}</p>
            <ul
                v-if="showHolidayList && holidays.length > 0"
                class="mt-2 list-inside list-disc text-sm"
            >
                <li v-for="holiday in sortedHolidays" :key="holiday.id">
                    <span class="font-medium">{{ holiday.name }}</span>
                    <span
                        v-if="holiday.formatted_date"
                        class="text-amber-600 dark:text-amber-400"
                    >
                        ({{ holiday.formatted_date }})
                    </span>
                    <span
                        v-if="holiday.holiday_type_label"
                        class="text-amber-500 dark:text-amber-500"
                    >
                        - {{ holiday.holiday_type_label }}
                    </span>
                </li>
            </ul>
        </AlertDescription>
    </Alert>
</template>
