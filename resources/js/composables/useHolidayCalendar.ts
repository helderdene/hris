import { computed, ref } from 'vue';

/**
 * Holiday data structure returned from the API
 */
export interface Holiday {
    id: number;
    name: string;
    date: string;
    formatted_date: string;
    holiday_type: string;
    holiday_type_label: string;
    description: string | null;
    is_national: boolean;
    year: number;
    work_location_id: number | null;
    work_location: {
        id: number;
        name: string;
        code: string;
    } | null;
    scope_label: string;
}

/**
 * Month data from calendar API
 */
interface CalendarMonth {
    month: string;
    month_number: number;
    year: number;
    holidays: Holiday[];
}

/**
 * Calendar API response
 */
interface CalendarResponse {
    year: number;
    months: CalendarMonth[];
    total_holidays: number;
}

/**
 * Cache key generator for holidays
 */
function getCacheKey(year: number, workLocationId?: number): string {
    return `holidays_${year}_${workLocationId ?? 'all'}`;
}

/**
 * Composable for accessing holiday calendar data.
 *
 * Provides methods to fetch, query, and check holidays for use in
 * leave filing date pickers and other calendar-related features.
 *
 * Features:
 * - Fetch holidays for a specific year/date range
 * - Check if a specific date is a holiday
 * - Get holidays that overlap with a date range
 * - Caches results to avoid redundant API calls
 *
 * @example
 * ```vue
 * <script setup>
 * const {
 *   holidays,
 *   isLoading,
 *   fetchHolidaysForRange,
 *   isHoliday,
 *   getHolidaysInRange
 * } = useHolidayCalendar();
 *
 * // Fetch holidays for current year
 * await fetchHolidaysForRange(new Date('2026-01-01'), new Date('2026-12-31'));
 *
 * // Check if a date is a holiday
 * if (isHoliday('2026-12-25')) {
 *   console.log('Christmas is a holiday!');
 * }
 *
 * // Get holidays in a leave date range
 * const overlapping = getHolidaysInRange(['2026-12-24', '2026-12-25', '2026-12-26']);
 * </script>
 * ```
 */
export function useHolidayCalendar() {
    // State
    const holidays = ref<Holiday[]>([]);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // Cache for avoiding redundant API calls
    // Map from cache key to array of holidays
    const cache = new Map<string, Holiday[]>();

    /**
     * Get CSRF token from cookies
     */
    function getCsrfToken(): string {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    /**
     * Fetch holidays for a date range.
     *
     * Uses the year from the start date to fetch all holidays for that year,
     * then filters to the specific range if needed. Results are cached.
     *
     * @param startDate - Start of the date range
     * @param endDate - End of the date range
     * @param workLocationId - Optional work location ID for location-specific holidays
     * @returns Array of holidays in the date range
     */
    async function fetchHolidaysForRange(
        startDate: Date | string,
        endDate: Date | string,
        workLocationId?: number,
    ): Promise<Holiday[]> {
        const start =
            typeof startDate === 'string' ? new Date(startDate) : startDate;
        const end = typeof endDate === 'string' ? new Date(endDate) : endDate;

        const startYear = start.getFullYear();
        const endYear = end.getFullYear();

        // Collect holidays from all years in the range
        const allHolidays: Holiday[] = [];

        for (let year = startYear; year <= endYear; year++) {
            const yearHolidays = await fetchHolidaysForYear(
                year,
                workLocationId,
            );
            allHolidays.push(...yearHolidays);
        }

        // Filter to only holidays within the exact date range
        const startDateStr = formatDate(start);
        const endDateStr = formatDate(end);

        const filtered = allHolidays.filter((holiday) => {
            const holidayDate = holiday.date;
            return holidayDate >= startDateStr && holidayDate <= endDateStr;
        });

        // Update the reactive holidays array
        holidays.value = filtered;

        return filtered;
    }

    /**
     * Fetch all holidays for a specific year
     */
    async function fetchHolidaysForYear(
        year: number,
        workLocationId?: number,
    ): Promise<Holiday[]> {
        const cacheKey = getCacheKey(year, workLocationId);

        // Return cached data if available
        if (cache.has(cacheKey)) {
            return cache.get(cacheKey)!;
        }

        isLoading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams({ year: String(year) });
            if (workLocationId !== undefined) {
                params.append('work_location_id', String(workLocationId));
            }

            const response = await fetch(
                `/api/organization/holidays/calendar?${params}`,
                {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getCsrfToken(),
                    },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                throw new Error(
                    `Failed to fetch holidays: ${response.statusText}`,
                );
            }

            const data: CalendarResponse = await response.json();

            // Flatten holidays from all months
            const yearHolidays = data.months.flatMap((month) => month.holidays);

            // Cache the results
            cache.set(cacheKey, yearHolidays);

            return yearHolidays;
        } catch (e) {
            error.value =
                e instanceof Error ? e.message : 'Failed to fetch holidays';
            return [];
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Check if a specific date is a holiday.
     *
     * Uses the cached holidays data. If the date's year hasn't been
     * fetched yet, returns false. Use fetchHolidaysForRange first.
     *
     * @param date - The date to check (Date object or YYYY-MM-DD string)
     * @returns True if the date is a holiday
     */
    function isHoliday(date: Date | string): boolean {
        const dateStr = typeof date === 'string' ? date : formatDate(date);
        return holidays.value.some((h) => h.date === dateStr);
    }

    /**
     * Get holiday information for a specific date.
     *
     * @param date - The date to check (Date object or YYYY-MM-DD string)
     * @returns The holiday object if the date is a holiday, null otherwise
     */
    function getHoliday(date: Date | string): Holiday | null {
        const dateStr = typeof date === 'string' ? date : formatDate(date);
        return holidays.value.find((h) => h.date === dateStr) ?? null;
    }

    /**
     * Get all holidays that fall within a list of dates.
     *
     * Useful for checking if leave dates include holidays.
     *
     * @param dates - Array of dates to check (Date objects or YYYY-MM-DD strings)
     * @returns Array of holidays that overlap with the provided dates
     */
    function getHolidaysInRange(dates: (Date | string)[]): Holiday[] {
        const dateStrings = dates.map((d) =>
            typeof d === 'string' ? d : formatDate(d),
        );

        return holidays.value.filter((holiday) =>
            dateStrings.includes(holiday.date),
        );
    }

    /**
     * Get a map of dates to holidays for efficient lookup.
     *
     * Useful for date picker highlighting.
     */
    const holidayMap = computed(() => {
        const map = new Map<string, Holiday>();
        for (const holiday of holidays.value) {
            map.set(holiday.date, holiday);
        }
        return map;
    });

    /**
     * Get all holiday dates as a Set for efficient membership testing.
     */
    const holidayDates = computed(() => {
        return new Set(holidays.value.map((h) => h.date));
    });

    /**
     * Clear the cache for a specific year or all cached data.
     *
     * @param year - Optional year to clear (clears all if not provided)
     */
    function clearCache(year?: number): void {
        if (year !== undefined) {
            // Clear all cache keys for the specified year
            for (const key of cache.keys()) {
                if (key.startsWith(`holidays_${year}_`)) {
                    cache.delete(key);
                }
            }
        } else {
            cache.clear();
        }
    }

    /**
     * Format a Date object as YYYY-MM-DD string
     */
    function formatDate(date: Date): string {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    /**
     * Generate the warning message for leave dates that include holidays.
     *
     * @param holidayCount - Number of holidays in the date range
     * @returns The warning message string
     */
    function getHolidayWarningMessage(holidayCount: number): string {
        if (holidayCount === 0) {
            return '';
        }
        const plural = holidayCount === 1 ? '' : 's';
        return `Your selected dates include ${holidayCount} holiday${plural}. Leave will still be filed but holiday dates do not consume leave credits.`;
    }

    return {
        // State
        holidays,
        isLoading,
        error,

        // Methods
        fetchHolidaysForRange,
        fetchHolidaysForYear,
        isHoliday,
        getHoliday,
        getHolidaysInRange,
        clearCache,
        getHolidayWarningMessage,

        // Computed
        holidayMap,
        holidayDates,
    };
}
