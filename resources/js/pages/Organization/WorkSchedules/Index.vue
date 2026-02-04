<script setup lang="ts">
import EmployeeAssignmentModal from '@/components/EmployeeAssignmentModal.vue';
import EnumSelect from '@/components/EnumSelect.vue';
import { Button } from '@/components/ui/button';
import WorkScheduleFormModal from '@/components/WorkScheduleFormModal.vue';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface TimeConfiguration {
    work_days?: string[];
    half_day_saturday?: boolean;
    start_time?: string;
    end_time?: string;
    saturday_end_time?: string | null;
    break?: { start_time: string | null; duration_minutes: number };
    required_hours_per_day?: number;
    required_hours_per_week?: number;
    core_hours?: { start_time: string; end_time: string };
    flexible_start_window?: { earliest: string; latest: string };
    shifts?: Array<{
        name: string;
        start_time: string;
        end_time: string;
        break?: { start_time: string; duration_minutes: number };
    }>;
    pattern?: string;
    daily_hours?: number;
    half_day?: { enabled: boolean; day: string | null; hours: number | null };
}

interface OvertimeRules {
    daily_threshold_hours: number;
    weekly_threshold_hours: number;
    regular_multiplier: number;
    rest_day_multiplier: number;
    holiday_multiplier: number;
}

interface NightDifferential {
    enabled: boolean;
    start_time: string;
    end_time: string;
    rate_multiplier: number;
}

interface WorkSchedule {
    id: number;
    name: string;
    code: string;
    schedule_type: string;
    schedule_type_label: string;
    description: string | null;
    status: string;
    time_configuration: TimeConfiguration;
    time_configuration_summary: string | null;
    overtime_rules: OvertimeRules | null;
    night_differential: NightDifferential | null;
    assigned_employees_count: number;
    created_at: string;
    updated_at: string;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    schedules: WorkSchedule[];
    scheduleTypes: EnumOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Work Schedules', href: '/organization/work-schedules' },
];

const isFormModalOpen = ref(false);
const editingSchedule = ref<WorkSchedule | null>(null);
const isAssignmentModalOpen = ref(false);
const assigningSchedule = ref<WorkSchedule | null>(null);
const deletingScheduleId = ref<number | null>(null);

// Filters
const statusFilter = ref<string>('');
const scheduleTypeFilter = ref<string>('');

const statusOptions: EnumOption[] = [
    { value: '', label: 'All Status' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const scheduleTypeOptions = computed<EnumOption[]>(() => {
    return [{ value: '', label: 'All Types' }, ...props.scheduleTypes];
});

const filteredSchedules = computed(() => {
    return props.schedules.filter((schedule) => {
        if (statusFilter.value && schedule.status !== statusFilter.value) {
            return false;
        }
        if (
            scheduleTypeFilter.value &&
            schedule.schedule_type !== scheduleTypeFilter.value
        ) {
            return false;
        }
        return true;
    });
});

// Schedule type counts for summary cards
const scheduleTypeCounts = computed(() => {
    return {
        fixed: props.schedules.filter((s) => s.schedule_type === 'fixed')
            .length,
        flexible: props.schedules.filter((s) => s.schedule_type === 'flexible')
            .length,
        shifting: props.schedules.filter((s) => s.schedule_type === 'shifting')
            .length,
        compressed: props.schedules.filter(
            (s) => s.schedule_type === 'compressed',
        ).length,
    };
});

function getStatusBadgeClasses(status: string): string {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'inactive':
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getScheduleTypeBadgeClasses(type: string): string {
    switch (type) {
        case 'fixed':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 'flexible':
            return 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300';
        case 'shifting':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case 'compressed':
            return 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getWorkDaysDisplay(schedule: WorkSchedule): string {
    const config = schedule.time_configuration;
    if (config.work_days) {
        const dayCount = config.work_days.length;
        return `${dayCount} days/week`;
    }
    if (config.pattern) {
        return config.pattern === '4x10' ? '4 days/week' : '4.5 days/week';
    }
    return '-';
}

function getHoursDisplay(schedule: WorkSchedule): string {
    const config = schedule.time_configuration;
    if (config.required_hours_per_day) {
        return `${config.required_hours_per_day}h/day`;
    }
    if (config.daily_hours) {
        return `${config.daily_hours}h/day`;
    }
    if (config.start_time && config.end_time) {
        return `${config.start_time} - ${config.end_time}`;
    }
    return '8h/day';
}

function getTypicalScheduleDisplay(
    schedule: WorkSchedule,
): { in: string; out: string; break: string } | null {
    const config = schedule.time_configuration;

    if (schedule.schedule_type === 'shifting' && config.shifts?.length) {
        const firstShift = config.shifts[0];
        return {
            in: formatTime(firstShift.start_time),
            out: formatTime(firstShift.end_time),
            break: firstShift.break
                ? `${firstShift.break.duration_minutes} min`
                : '30 min',
        };
    }

    if (config.start_time && config.end_time) {
        return {
            in: formatTime(config.start_time),
            out: formatTime(config.end_time),
            break: config.break
                ? `${config.break.duration_minutes} min`
                : '60 min',
        };
    }

    if (config.flexible_start_window) {
        return {
            in: formatTime(config.flexible_start_window.earliest),
            out: formatTime(
                config.flexible_start_window.latest.replace('10:00', '19:00'),
            ),
            break: config.break
                ? `${config.break.duration_minutes} min`
                : '60 min',
        };
    }

    return null;
}

function formatTime(time: string): string {
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function getWorkDaysForDisplay(schedule: WorkSchedule): string[] {
    const days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    const config = schedule.time_configuration;

    if (config.work_days) {
        return days.map((d) => {
            const dayMap: Record<string, string> = {
                monday: 'mon',
                tuesday: 'tue',
                wednesday: 'wed',
                thursday: 'thu',
                friday: 'fri',
                saturday: 'sat',
                sunday: 'sun',
            };
            const workDaysShort = config.work_days!.map((wd) => dayMap[wd]);
            return workDaysShort.includes(d) ? d : '';
        });
    }

    return days;
}

function isDayActive(schedule: WorkSchedule, dayShort: string): boolean {
    const config = schedule.time_configuration;
    const dayMap: Record<string, string> = {
        sun: 'sunday',
        mon: 'monday',
        tue: 'tuesday',
        wed: 'wednesday',
        thu: 'thursday',
        fri: 'friday',
        sat: 'saturday',
    };

    if (config.work_days) {
        return config.work_days.includes(dayMap[dayShort]);
    }

    return false;
}

function handleAddSchedule() {
    editingSchedule.value = null;
    isFormModalOpen.value = true;
}

function handleEditSchedule(schedule: WorkSchedule) {
    editingSchedule.value = schedule;
    isFormModalOpen.value = true;
}

function handleManageAssignments(schedule: WorkSchedule) {
    assigningSchedule.value = schedule;
    isAssignmentModalOpen.value = true;
}

async function handleDeleteSchedule(schedule: WorkSchedule) {
    if (
        !confirm(
            `Are you sure you want to delete the schedule "${schedule.name}"?`,
        )
    ) {
        return;
    }

    deletingScheduleId.value = schedule.id;

    try {
        const response = await fetch(
            `/api/organization/work-schedules/${schedule.id}`,
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        if (response.ok) {
            router.reload({ only: ['schedules'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete schedule');
        }
    } catch (error) {
        alert('An error occurred while deleting the schedule');
    } finally {
        deletingScheduleId.value = null;
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingSchedule.value = null;
    router.reload({ only: ['schedules'] });
}

function handleAssignmentSuccess() {
    isAssignmentModalOpen.value = false;
    assigningSchedule.value = null;
    router.reload({ only: ['schedules'] });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Head :title="`Work Schedules - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Work Schedules
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage employee work schedules and shift configurations
                    </p>
                </div>
                <Button
                    @click="handleAddSchedule"
                    class="shrink-0"
                    :style="{ backgroundColor: primaryColor }"
                    data-test="add-schedule-button"
                >
                    <svg
                        class="mr-2 h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 4.5v15m7.5-7.5h-15"
                        />
                    </svg>
                    Create Schedule
                </Button>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                        >
                            Fixed
                        </span>
                        <span
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                            >{{ scheduleTypeCounts.fixed }}</span
                        >
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Standard fixed hours
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="inline-flex items-center rounded-md bg-teal-100 px-2 py-1 text-xs font-medium text-teal-700 dark:bg-teal-900/30 dark:text-teal-300"
                        >
                            Flexible
                        </span>
                        <span
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                            >{{ scheduleTypeCounts.flexible }}</span
                        >
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Flexible time in/out
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="inline-flex items-center rounded-md bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                        >
                            Shifting
                        </span>
                        <span
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                            >{{ scheduleTypeCounts.shifting }}</span
                        >
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Rotating shifts
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="inline-flex items-center rounded-md bg-violet-100 px-2 py-1 text-xs font-medium text-violet-700 dark:bg-violet-900/30 dark:text-violet-300"
                        >
                            Compressed
                        </span>
                        <span
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                            >{{ scheduleTypeCounts.compressed }}</span
                        >
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        4-day work week
                    </p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <svg
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
                        />
                    </svg>
                    <input
                        type="text"
                        placeholder="Search schedules..."
                        class="w-full rounded-md border border-slate-200 bg-white py-2 pr-4 pl-10 text-sm placeholder:text-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900"
                    />
                </div>
                <div class="w-full sm:w-40">
                    <EnumSelect
                        v-model="statusFilter"
                        :options="statusOptions"
                        placeholder="All Status"
                    />
                </div>
            </div>

            <!-- Schedule Count -->
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Showing {{ filteredSchedules.length }} of
                {{ schedules.length }} schedules
            </p>

            <!-- Schedule Cards Grid -->
            <div class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="schedule in filteredSchedules"
                    :key="schedule.id"
                    class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                    :data-test="`schedule-card-${schedule.id}`"
                >
                    <!-- Card Header -->
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <h3
                                class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{ schedule.name }}
                            </h3>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="
                                    getScheduleTypeBadgeClasses(
                                        schedule.schedule_type,
                                    )
                                "
                            >
                                {{ schedule.schedule_type_label }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClasses(schedule.status)"
                            >
                                <svg
                                    v-if="schedule.status === 'active'"
                                    class="h-3 w-3"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                {{
                                    schedule.status === 'active'
                                        ? 'Active'
                                        : 'Inactive'
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Schedule Code and Description -->
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Code: {{ schedule.code }}
                    </p>
                    <p
                        v-if="schedule.description"
                        class="mt-2 text-sm text-slate-600 dark:text-slate-300"
                    >
                        {{ schedule.description }}
                    </p>

                    <!-- Hours/Days Info -->
                    <div
                        class="mt-4 flex items-center gap-6 text-sm text-slate-600 dark:text-slate-300"
                    >
                        <div class="flex items-center gap-2">
                            <svg
                                class="h-4 w-4 text-slate-400"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                />
                            </svg>
                            {{ getHoursDisplay(schedule) }},
                            {{ getWorkDaysDisplay(schedule) }}
                        </div>
                        <div class="flex items-center gap-2">
                            <svg
                                class="h-4 w-4 text-slate-400"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
                                />
                            </svg>
                            {{
                                schedule.overtime_rules
                                    ? '15 min grace period'
                                    : '0 min grace period'
                            }}
                        </div>
                    </div>

                    <!-- Typical Schedule -->
                    <div
                        v-if="getTypicalScheduleDisplay(schedule)"
                        class="mt-4 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50"
                    >
                        <p
                            class="mb-2 text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                        >
                            Typical Schedule
                        </p>
                        <div class="flex items-center gap-6 text-sm">
                            <div>
                                <span class="text-slate-500 dark:text-slate-400"
                                    >In:
                                </span>
                                <span
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                    >{{
                                        getTypicalScheduleDisplay(schedule)?.in
                                    }}</span
                                >
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400"
                                    >Out:
                                </span>
                                <span
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                    >{{
                                        getTypicalScheduleDisplay(schedule)?.out
                                    }}</span
                                >
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400"
                                    >Break:
                                </span>
                                <span
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                    >{{
                                        getTypicalScheduleDisplay(schedule)
                                            ?.break
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Work Days -->
                    <div class="mt-4 flex items-center gap-1">
                        <span
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
                            class="flex h-8 w-10 items-center justify-center rounded-md text-xs font-medium"
                            :class="
                                isDayActive(schedule, day.toLowerCase())
                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                    : 'border border-slate-200 text-slate-400 dark:border-slate-700'
                            "
                        >
                            {{ day }}
                        </span>
                    </div>

                    <!-- Card Footer -->
                    <div
                        class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4 dark:border-slate-800"
                    >
                        <div
                            class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            <svg
                                class="h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                                />
                            </svg>
                            {{ schedule.assigned_employees_count }} employees
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0"
                                @click="handleManageAssignments(schedule)"
                                title="Manage Assignments"
                            >
                                <svg
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"
                                    />
                                </svg>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0"
                                @click="handleEditSchedule(schedule)"
                                title="View Details"
                            >
                                <svg
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                                    />
                                </svg>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0"
                                @click="handleEditSchedule(schedule)"
                                title="Edit Schedule"
                            >
                                <svg
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                    />
                                </svg>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-red-500 hover:text-red-600"
                                :disabled="deletingScheduleId === schedule.id"
                                @click="handleDeleteSchedule(schedule)"
                                title="Delete Schedule"
                            >
                                <svg
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                                    />
                                </svg>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-if="filteredSchedules.length === 0"
                class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
            >
                <svg
                    class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                    />
                </svg>
                <h3
                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    No schedules found
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{
                        schedules.length === 0
                            ? 'Get started by creating a new work schedule.'
                            : 'Try adjusting your filters.'
                    }}
                </p>
                <div v-if="schedules.length === 0" class="mt-6">
                    <Button
                        @click="handleAddSchedule"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        <svg
                            class="mr-2 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 4.5v15m7.5-7.5h-15"
                            />
                        </svg>
                        Create Schedule
                    </Button>
                </div>
            </div>
        </div>

        <!-- Work Schedule Form Modal -->
        <WorkScheduleFormModal
            v-model:open="isFormModalOpen"
            :schedule="editingSchedule"
            :schedule-types="scheduleTypes"
            @success="handleFormSuccess"
        />

        <!-- Employee Assignment Modal -->
        <EmployeeAssignmentModal
            v-model:open="isAssignmentModalOpen"
            :schedule="assigningSchedule"
            @success="handleAssignmentSuccess"
        />
    </TenantLayout>
</template>
