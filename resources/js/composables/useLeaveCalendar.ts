import { computed, ref } from 'vue';

/**
 * Leave calendar entry structure from API
 */
export interface LeaveCalendarEntry {
    id: number;
    employee: {
        id: number;
        full_name: string;
        initials: string;
        department_id: number | null;
        department: string | null;
    };
    leave_type: {
        id: number;
        name: string;
        code: string;
        category: 'statutory' | 'company' | 'special';
    };
    start_date: string;
    end_date: string;
    total_days: number;
    is_half_day_start: boolean;
    is_half_day_end: boolean;
    status: string;
    status_label: string;
    reason: string;
    reference_number: string;
}

/**
 * Day cell data structure for the calendar grid
 */
export interface CalendarDay {
    date: string;
    dayOfMonth: number;
    isCurrentMonth: boolean;
    isToday: boolean;
    isWeekend: boolean;
    entries: LeaveCalendarEntry[];
}

/**
 * Composable for managing leave calendar data and state.
 *
 * Provides methods to fetch leave data, navigate months,
 * and expand multi-day leaves into individual date entries.
 *
 * @example
 * ```vue
 * <script setup>
 * const {
 *   currentYear,
 *   currentMonth,
 *   entries,
 *   isLoading,
 *   calendarDays,
 *   fetchLeaveData,
 *   nextMonth,
 *   prevMonth,
 * } = useLeaveCalendar();
 *
 * await fetchLeaveData(2026, 1);
 * </script>
 * ```
 */
export function useLeaveCalendar() {
    // State
    const entries = ref<LeaveCalendarEntry[]>([]);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const currentYear = ref(new Date().getFullYear());
    const currentMonth = ref(new Date().getMonth() + 1);

    // Cache for avoiding redundant API calls
    const cache = new Map<string, LeaveCalendarEntry[]>();

    /**
     * Get CSRF token from cookies
     */
    function getCsrfToken(): string {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    /**
     * Generate cache key for year/month
     */
    function getCacheKey(
        year: number,
        month: number,
        departmentId?: number,
        showPending?: boolean,
    ): string {
        return `${year}-${month}-${departmentId ?? 'all'}-${showPending ?? true}`;
    }

    /**
     * Fetch leave data for a specific month.
     */
    async function fetchLeaveData(
        year: number,
        month: number,
        departmentId?: number,
        showPending: boolean = true,
        useCache: boolean = true,
    ): Promise<LeaveCalendarEntry[]> {
        const cacheKey = getCacheKey(year, month, departmentId, showPending);

        // Return cached data if available
        if (useCache && cache.has(cacheKey)) {
            const cachedData = cache.get(cacheKey)!;
            entries.value = cachedData;
            currentYear.value = year;
            currentMonth.value = month;
            return cachedData;
        }

        isLoading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams({
                year: String(year),
                month: String(month),
                show_pending: String(showPending),
            });

            if (departmentId !== undefined) {
                params.append('department_id', String(departmentId));
            }

            const response = await fetch(`/api/leave-calendar?${params}`, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(
                    `Failed to fetch leave calendar: ${response.statusText}`,
                );
            }

            const data = await response.json();
            // Handle both wrapped {data: [...]} and unwrapped [...] responses
            const leaveData: LeaveCalendarEntry[] = Array.isArray(data) ? data : (data.data || []);

            // Cache the results
            cache.set(cacheKey, leaveData);

            entries.value = leaveData;
            currentYear.value = year;
            currentMonth.value = month;

            return leaveData;
        } catch (e) {
            error.value =
                e instanceof Error
                    ? e.message
                    : 'Failed to fetch leave calendar';
            return [];
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Navigate to the next month
     */
    function nextMonth(): { year: number; month: number } {
        if (currentMonth.value === 12) {
            currentYear.value++;
            currentMonth.value = 1;
        } else {
            currentMonth.value++;
        }
        return { year: currentYear.value, month: currentMonth.value };
    }

    /**
     * Navigate to the previous month
     */
    function prevMonth(): { year: number; month: number } {
        if (currentMonth.value === 1) {
            currentYear.value--;
            currentMonth.value = 12;
        } else {
            currentMonth.value--;
        }
        return { year: currentYear.value, month: currentMonth.value };
    }

    /**
     * Go to a specific month
     */
    function goToMonth(year: number, month: number): void {
        currentYear.value = year;
        currentMonth.value = month;
    }

    /**
     * Go to the current month (today)
     */
    function goToToday(): { year: number; month: number } {
        const now = new Date();
        currentYear.value = now.getFullYear();
        currentMonth.value = now.getMonth() + 1;
        return { year: currentYear.value, month: currentMonth.value };
    }

    /**
     * Get entries for a specific date
     */
    function getEntriesForDate(dateStr: string): LeaveCalendarEntry[] {
        return entries.value.filter((entry) => {
            return dateStr >= entry.start_date && dateStr <= entry.end_date;
        });
    }

    /**
     * Check if a date is a half day for a specific entry
     */
    function isHalfDay(entry: LeaveCalendarEntry, dateStr: string): boolean {
        if (dateStr === entry.start_date && entry.is_half_day_start) {
            return true;
        }
        if (dateStr === entry.end_date && entry.is_half_day_end) {
            return true;
        }
        return false;
    }

    /**
     * Get calendar days for the current month with entries mapped
     */
    const calendarDays = computed((): CalendarDay[] => {
        const year = currentYear.value;
        const month = currentMonth.value;

        const firstDayOfMonth = new Date(year, month - 1, 1);
        const lastDayOfMonth = new Date(year, month, 0);
        const daysInMonth = lastDayOfMonth.getDate();

        // Get day of week for first day (0 = Sunday)
        const startDayOfWeek = firstDayOfMonth.getDay();

        // Get today for comparison
        const today = new Date();
        const todayStr = formatDate(today);

        const days: CalendarDay[] = [];

        // Add days from previous month to fill the first week
        const prevMonth = month === 1 ? 12 : month - 1;
        const prevMonthYear = month === 1 ? year - 1 : year;
        const daysInPrevMonth = new Date(prevMonthYear, prevMonth, 0).getDate();

        for (let i = startDayOfWeek - 1; i >= 0; i--) {
            const day = daysInPrevMonth - i;
            const dateStr = `${prevMonthYear}-${String(prevMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayOfWeek = new Date(prevMonthYear, prevMonth - 1, day).getDay();

            days.push({
                date: dateStr,
                dayOfMonth: day,
                isCurrentMonth: false,
                isToday: dateStr === todayStr,
                isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
                entries: getEntriesForDate(dateStr),
            });
        }

        // Add days of current month
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayOfWeek = new Date(year, month - 1, day).getDay();

            days.push({
                date: dateStr,
                dayOfMonth: day,
                isCurrentMonth: true,
                isToday: dateStr === todayStr,
                isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
                entries: getEntriesForDate(dateStr),
            });
        }

        // Add days from next month to complete the grid (6 rows * 7 days = 42)
        const remainingDays = 42 - days.length;
        const nextMonth = month === 12 ? 1 : month + 1;
        const nextMonthYear = month === 12 ? year + 1 : year;

        for (let day = 1; day <= remainingDays; day++) {
            const dateStr = `${nextMonthYear}-${String(nextMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayOfWeek = new Date(nextMonthYear, nextMonth - 1, day).getDay();

            days.push({
                date: dateStr,
                dayOfMonth: day,
                isCurrentMonth: false,
                isToday: dateStr === todayStr,
                isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
                entries: getEntriesForDate(dateStr),
            });
        }

        return days;
    });

    /**
     * Get the month name for display
     */
    const monthName = computed((): string => {
        const date = new Date(currentYear.value, currentMonth.value - 1, 1);
        return date.toLocaleDateString('en-US', { month: 'long' });
    });

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
     * Clear the cache
     */
    function clearCache(): void {
        cache.clear();
    }

    /**
     * Get color classes for a leave category
     */
    function getCategoryColorClasses(
        category: 'statutory' | 'company' | 'special',
        isPending: boolean = false,
    ): string {
        const opacity = isPending ? 'opacity-70' : '';

        switch (category) {
            case 'statutory':
                return `bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 ${opacity}`;
            case 'company':
                return `bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 ${opacity}`;
            case 'special':
                return `bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300 ${opacity}`;
            default:
                return `bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 ${opacity}`;
        }
    }

    return {
        // State
        entries,
        isLoading,
        error,
        currentYear,
        currentMonth,

        // Computed
        calendarDays,
        monthName,

        // Methods
        fetchLeaveData,
        nextMonth,
        prevMonth,
        goToMonth,
        goToToday,
        getEntriesForDate,
        isHalfDay,
        clearCache,
        getCategoryColorClasses,
    };
}
