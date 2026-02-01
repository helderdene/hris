<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    Calendar,
    ChevronLeft,
    ChevronRight,
    Users,
    User,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface LeaveTypeInfo {
    id: number;
    name: string;
    code: string;
    category: string;
    category_label: string;
}

interface CalendarEntry {
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
        category: string;
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

const props = defineProps<{
    employee: {
        id: number;
        full_name: string;
        department_id: number | null;
    } | null;
    departmentName: string | null;
    leaveTypes: LeaveTypeInfo[];
    filters: {
        year: number;
        month: number;
    };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self-Service', href: '/my/dashboard' },
    { title: 'My Leave', href: '/my/leave' },
    { title: 'Calendar', href: '/my/leave/calendar' },
];

const currentYear = ref(props.filters.year);
const currentMonth = ref(props.filters.month);
const viewMode = ref<'my' | 'team'>('my');
const calendarEntries = ref<CalendarEntry[]>([]);
const isLoading = ref(false);

const monthNames = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

const currentMonthName = computed(
    () => `${monthNames[currentMonth.value - 1]} ${currentYear.value}`,
);

const daysInMonth = computed(() => {
    return new Date(currentYear.value, currentMonth.value, 0).getDate();
});

const firstDayOfWeek = computed(() => {
    return new Date(currentYear.value, currentMonth.value - 1, 1).getDay();
});

const calendarDays = computed(() => {
    const days: (number | null)[] = [];
    for (let i = 0; i < firstDayOfWeek.value; i++) {
        days.push(null);
    }
    for (let d = 1; d <= daysInMonth.value; d++) {
        days.push(d);
    }
    return days;
});

function getEntriesForDay(day: number): CalendarEntry[] {
    const dateStr = `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return calendarEntries.value.filter((entry) => {
        return entry.start_date <= dateStr && entry.end_date >= dateStr;
    });
}

function categoryColor(category: string): string {
    const map: Record<string, string> = {
        annual:
            'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
        sick: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
        statutory:
            'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400',
        special:
            'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
    };
    return (
        map[category] ??
        'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
    );
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function fetchCalendarData(): Promise<void> {
    isLoading.value = true;
    try {
        const params = new URLSearchParams({
            year: String(currentYear.value),
            month: String(currentMonth.value),
        });

        if (
            viewMode.value === 'my' &&
            props.employee
        ) {
            params.set('employee_id', String(props.employee.id));
        } else if (
            viewMode.value === 'team' &&
            props.employee?.department_id
        ) {
            params.set(
                'department_id',
                String(props.employee.department_id),
            );
        }

        const response = await fetch(`/api/leave-calendar?${params}`, {
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        const data = await response.json();
        calendarEntries.value = data.data ?? [];
    } finally {
        isLoading.value = false;
    }
}

function prevMonth(): void {
    if (currentMonth.value === 1) {
        currentMonth.value = 12;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
}

function nextMonth(): void {
    if (currentMonth.value === 12) {
        currentMonth.value = 1;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
}

watch([currentYear, currentMonth, viewMode], fetchCalendarData);
onMounted(fetchCalendarData);
</script>

<template>
    <Head :title="`Leave Calendar - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Leave Calendar
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    View your leaves and your team's schedule.
                </p>
            </div>

            <!-- Controls -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <!-- Month Navigation -->
                <div class="flex items-center gap-3">
                    <Button variant="outline" size="icon" @click="prevMonth">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <span
                        class="min-w-[160px] text-center text-lg font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ currentMonthName }}
                    </span>
                    <Button variant="outline" size="icon" @click="nextMonth">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>

                <!-- View Toggle -->
                <div
                    class="flex rounded-lg border border-slate-200 dark:border-slate-700"
                >
                    <button
                        class="flex items-center gap-2 rounded-l-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="
                            viewMode === 'my'
                                ? 'bg-blue-500 text-white'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                        "
                        @click="viewMode = 'my'"
                    >
                        <User class="h-4 w-4" />
                        My Leaves
                    </button>
                    <button
                        class="flex items-center gap-2 rounded-r-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="
                            viewMode === 'team'
                                ? 'bg-blue-500 text-white'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                        "
                        @click="viewMode = 'team'"
                    >
                        <Users class="h-4 w-4" />
                        Team Leaves
                    </button>
                </div>
            </div>

            <!-- Department Info -->
            <p
                v-if="viewMode === 'team' && departmentName"
                class="text-sm text-slate-500 dark:text-slate-400"
            >
                Showing leaves for
                <span class="font-medium">{{ departmentName }}</span>
                department
            </p>

            <!-- No Employee Profile -->
            <div
                v-if="!employee"
                class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
            >
                <Calendar
                    class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                />
                <h3
                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    No employee profile
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    No employee profile is linked to your account.
                </p>
            </div>

            <!-- Calendar Grid -->
            <Card v-else class="dark:border-slate-700 dark:bg-slate-900">
                <CardContent class="p-0">
                    <!-- Loading overlay -->
                    <div
                        v-if="isLoading"
                        class="flex items-center justify-center py-20"
                    >
                        <div
                            class="h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-blue-500"
                        />
                    </div>

                    <div v-else>
                        <!-- Day Headers -->
                        <div
                            class="grid grid-cols-7 border-b border-slate-200 dark:border-slate-700"
                        >
                            <div
                                v-for="day in [
                                    'Sun',
                                    'Mon',
                                    'Tue',
                                    'Wed',
                                    'Thu',
                                    'Fri',
                                    'Sat',
                                ]"
                                :key="day"
                                class="px-2 py-3 text-center text-xs font-semibold text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ day }}
                            </div>
                        </div>

                        <!-- Calendar Days -->
                        <div class="grid grid-cols-7">
                            <div
                                v-for="(day, index) in calendarDays"
                                :key="index"
                                class="min-h-[100px] border-b border-r border-slate-200 p-2 dark:border-slate-700"
                                :class="{
                                    'bg-slate-50 dark:bg-slate-800/50':
                                        !day,
                                }"
                            >
                                <template v-if="day">
                                    <div
                                        class="mb-1 text-sm font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{ day }}
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <div
                                            v-for="entry in getEntriesForDay(
                                                day,
                                            )"
                                            :key="entry.id"
                                            class="truncate rounded px-1.5 py-0.5 text-xs font-medium"
                                            :class="
                                                categoryColor(
                                                    entry.leave_type
                                                        .category,
                                                )
                                            "
                                            :title="`${entry.employee.full_name} - ${entry.leave_type.name}`"
                                        >
                                            {{
                                                viewMode === 'team'
                                                    ? entry.employee
                                                          .initials
                                                    : ''
                                            }}
                                            {{ entry.leave_type.code }}
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Legend -->
            <div
                v-if="employee"
                class="flex flex-wrap gap-4 text-xs text-slate-500 dark:text-slate-400"
            >
                <div
                    v-for="type in leaveTypes"
                    :key="type.id"
                    class="flex items-center gap-1.5"
                >
                    <span
                        class="inline-block h-3 w-3 rounded"
                        :class="categoryColor(type.category)"
                    />
                    {{ type.name }} ({{ type.code }})
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
