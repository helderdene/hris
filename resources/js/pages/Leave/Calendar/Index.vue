<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import CalendarFilters from '@/components/LeaveCalendar/CalendarFilters.vue';
import CalendarGrid from '@/components/LeaveCalendar/CalendarGrid.vue';
import CalendarNavigation from '@/components/LeaveCalendar/CalendarNavigation.vue';
import LeaveDetailModal from '@/components/LeaveCalendar/LeaveDetailModal.vue';
import type { LeaveCalendarEntry } from '@/composables/useLeaveCalendar';
import { useLeaveCalendar } from '@/composables/useLeaveCalendar';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';

interface Department {
    id: number;
    name: string;
    code: string;
}

interface LeaveType {
    id: number;
    name: string;
    code: string;
    category: string;
    category_label: string;
}

interface Employee {
    id: number;
    full_name: string;
    department_id: number | null;
}

interface Filters {
    year: number;
    month: number;
    department_id: number | null;
    show_pending: boolean;
}

const props = defineProps<{
    employee: Employee | null;
    departments: Department[];
    leaveTypes: LeaveType[];
    filters: Filters;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Leave', href: '/leave/applications' },
    { title: 'Calendar', href: '/leave/calendar' },
];

const {
    calendarDays,
    currentYear,
    currentMonth,
    monthName,
    isLoading,
    fetchLeaveData,
    nextMonth,
    prevMonth,
    goToToday,
} = useLeaveCalendar();

// Filter state
const departmentId = ref<number | null>(props.filters.department_id);
const showPending = ref(props.filters.show_pending);

// Modal state
const selectedEntry = ref<LeaveCalendarEntry | null>(null);
const showDetailModal = ref(false);

// Initialize calendar with props
currentYear.value = props.filters.year;
currentMonth.value = props.filters.month;

// Fetch data on mount and when filters change
async function loadData() {
    await fetchLeaveData(
        currentYear.value,
        currentMonth.value,
        departmentId.value ?? undefined,
        showPending.value,
        false,
    );
}

onMounted(() => {
    loadData();
});

// Watch for filter changes
watch([departmentId, showPending], () => {
    loadData();
});

// Navigation handlers
async function handlePrev() {
    const { year, month } = prevMonth();
    await fetchLeaveData(
        year,
        month,
        departmentId.value ?? undefined,
        showPending.value,
        true,
    );
}

async function handleNext() {
    const { year, month } = nextMonth();
    await fetchLeaveData(
        year,
        month,
        departmentId.value ?? undefined,
        showPending.value,
        true,
    );
}

async function handleToday() {
    const { year, month } = goToToday();
    await fetchLeaveData(
        year,
        month,
        departmentId.value ?? undefined,
        showPending.value,
        true,
    );
}

// Entry click handler
function handleEntryClick(entry: LeaveCalendarEntry) {
    selectedEntry.value = entry;
    showDetailModal.value = true;
}

// Legend data
const legendItems = [
    { category: 'statutory', label: 'Statutory' },
    { category: 'company', label: 'Company' },
    { category: 'special', label: 'Special' },
] as const;

function getLegendColorClass(category: 'statutory' | 'company' | 'special'): string {
    switch (category) {
        case 'statutory':
            return 'bg-blue-100 dark:bg-blue-900/30';
        case 'company':
            return 'bg-green-100 dark:bg-green-900/30';
        case 'special':
            return 'bg-purple-100 dark:bg-purple-900/30';
    }
}
</script>

<template>
    <Head :title="`Leave Calendar - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Leave Calendar
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    View team leave schedules and plan around availability.
                </p>
            </div>

            <!-- Filters and Navigation -->
            <Card>
                <CardContent class="flex flex-col gap-4 pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <CalendarFilters
                        :departments="departments"
                        v-model:department-id="departmentId"
                        v-model:show-pending="showPending"
                    />
                    <CalendarNavigation
                        :year="currentYear"
                        :month-name="monthName"
                        :is-loading="isLoading"
                        @prev="handlePrev"
                        @next="handleNext"
                        @today="handleToday"
                    />
                </CardContent>
            </Card>

            <!-- Calendar Grid -->
            <Card>
                <CardContent class="p-0">
                    <CalendarGrid
                        :days="calendarDays"
                        :is-loading="isLoading"
                        @entry-click="handleEntryClick"
                    />
                </CardContent>
            </Card>

            <!-- Legend -->
            <div class="flex flex-wrap items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                <span class="font-medium">Legend:</span>
                <div
                    v-for="item in legendItems"
                    :key="item.category"
                    class="flex items-center gap-2"
                >
                    <span
                        class="h-3 w-3 rounded"
                        :class="getLegendColorClass(item.category)"
                    />
                    <span>{{ item.label }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded bg-slate-200 opacity-70 dark:bg-slate-600" />
                    <span>Pending</span>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <LeaveDetailModal
            :entry="selectedEntry"
            v-model:open="showDetailModal"
        />
    </TenantLayout>
</template>
