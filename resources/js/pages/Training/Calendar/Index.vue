<script setup lang="ts">
import SessionStatusBadge from '@/components/SessionStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface CalendarEntry {
    id: number;
    title: string;
    start: string;
    end: string;
    start_time: string | null;
    end_time: string | null;
    status: string;
    status_color: string;
    location: string | null;
    is_full: boolean;
    enrolled_count: number;
    available_slots: number | null;
    course?: {
        id: number;
        title: string;
        code: string;
    };
}

interface Course {
    id: number;
    title: string;
    code: string;
}

const props = defineProps<{
    sessions: CalendarEntry[];
    courses: Course[];
    currentYear: number;
    currentMonth: number;
    monthName: string;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Training', href: '/training/courses' },
    { title: 'Calendar', href: '/training/calendar' },
];

const selectedSession = ref<CalendarEntry | null>(null);

const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const calendarDays = computed(() => {
    const year = props.currentYear;
    const month = props.currentMonth - 1; // JS months are 0-indexed

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();

    const days: { date: number; isCurrentMonth: boolean; sessions: CalendarEntry[] }[] = [];

    // Previous month days
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    for (let i = startingDayOfWeek - 1; i >= 0; i--) {
        days.push({
            date: prevMonthLastDay - i,
            isCurrentMonth: false,
            sessions: [],
        });
    }

    // Current month days
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const sessionsOnDay = props.sessions.filter((s) => {
            const start = new Date(s.start);
            const end = new Date(s.end);
            const current = new Date(dateStr);
            return current >= start && current <= end;
        });

        days.push({
            date: day,
            isCurrentMonth: true,
            sessions: sessionsOnDay,
        });
    }

    // Next month days
    const remainingCells = 42 - days.length;
    for (let i = 1; i <= remainingCells; i++) {
        days.push({
            date: i,
            isCurrentMonth: false,
            sessions: [],
        });
    }

    return days;
});

function navigateMonth(delta: number) {
    let newMonth = props.currentMonth + delta;
    let newYear = props.currentYear;

    if (newMonth < 1) {
        newMonth = 12;
        newYear--;
    } else if (newMonth > 12) {
        newMonth = 1;
        newYear++;
    }

    router.get('/training/calendar', { year: newYear, month: newMonth }, { preserveState: true });
}

function goToToday() {
    const today = new Date();
    router.get(
        '/training/calendar',
        { year: today.getFullYear(), month: today.getMonth() + 1 },
        { preserveState: true }
    );
}

function handleSessionClick(session: CalendarEntry) {
    selectedSession.value = session;
}

function goToSession(session: CalendarEntry) {
    router.visit(`/training/sessions/${session.id}`);
}

function getStatusColorClass(status: string): string {
    const colorMap: Record<string, string> = {
        draft: 'bg-slate-400',
        scheduled: 'bg-blue-500',
        in_progress: 'bg-yellow-500',
        completed: 'bg-green-500',
        cancelled: 'bg-red-500',
    };
    return colorMap[status] || 'bg-slate-400';
}
</script>

<template>
    <Head :title="`Training Calendar - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Training Calendar
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        View scheduled training sessions by month.
                    </p>
                </div>
                <Button
                    variant="outline"
                    @click="router.visit('/training/sessions')"
                >
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    List View
                </Button>
            </div>

            <!-- Calendar Navigation -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="navigateMonth(-1)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Button>
                    <h2 class="min-w-48 text-center text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {{ monthName }}
                    </h2>
                    <Button variant="outline" size="sm" @click="navigateMonth(1)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Button>
                </div>
                <Button variant="ghost" size="sm" @click="goToToday">
                    Today
                </Button>
            </div>

            <div class="flex gap-6">
                <!-- Calendar Grid -->
                <div class="flex-1 rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                    <!-- Week Headers -->
                    <div class="grid grid-cols-7 border-b border-slate-200 dark:border-slate-700">
                        <div
                            v-for="day in weekDays"
                            :key="day"
                            class="px-2 py-3 text-center text-xs font-medium uppercase text-slate-500 dark:text-slate-400"
                        >
                            {{ day }}
                        </div>
                    </div>

                    <!-- Calendar Days -->
                    <div class="grid grid-cols-7">
                        <div
                            v-for="(day, index) in calendarDays"
                            :key="index"
                            :class="[
                                'min-h-24 border-b border-r border-slate-200 p-1 dark:border-slate-700',
                                !day.isCurrentMonth && 'bg-slate-50 dark:bg-slate-800/50',
                                index % 7 === 6 && 'border-r-0',
                            ]"
                        >
                            <div
                                :class="[
                                    'mb-1 text-sm',
                                    day.isCurrentMonth ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500',
                                ]"
                            >
                                {{ day.date }}
                            </div>
                            <div class="space-y-1">
                                <button
                                    v-for="session in day.sessions.slice(0, 2)"
                                    :key="session.id"
                                    class="w-full truncate rounded px-1 py-0.5 text-left text-xs text-white"
                                    :class="getStatusColorClass(session.status)"
                                    @click="handleSessionClick(session)"
                                >
                                    {{ session.title }}
                                </button>
                                <div
                                    v-if="day.sessions.length > 2"
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    +{{ day.sessions.length - 2 }} more
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Session Details Sidebar -->
                <div
                    v-if="selectedSession"
                    class="w-80 shrink-0 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-start justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            {{ selectedSession.title }}
                        </h3>
                        <button
                            class="text-slate-400 hover:text-slate-600"
                            @click="selectedSession = null"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-2">
                        <SessionStatusBadge :status="selectedSession.status" />
                    </div>

                    <div class="mt-4 space-y-3 text-sm">
                        <div>
                            <span class="text-slate-500 dark:text-slate-400">Course:</span>
                            <span class="ml-2 text-slate-900 dark:text-slate-100">
                                {{ selectedSession.course?.title }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400">Date:</span>
                            <span class="ml-2 text-slate-900 dark:text-slate-100">
                                {{ new Date(selectedSession.start).toLocaleDateString() }}
                                <template v-if="selectedSession.start !== selectedSession.end">
                                    - {{ new Date(selectedSession.end).toLocaleDateString() }}
                                </template>
                            </span>
                        </div>
                        <div v-if="selectedSession.start_time">
                            <span class="text-slate-500 dark:text-slate-400">Time:</span>
                            <span class="ml-2 text-slate-900 dark:text-slate-100">
                                {{ selectedSession.start_time }}
                                <template v-if="selectedSession.end_time">
                                    - {{ selectedSession.end_time }}
                                </template>
                            </span>
                        </div>
                        <div v-if="selectedSession.location">
                            <span class="text-slate-500 dark:text-slate-400">Location:</span>
                            <span class="ml-2 text-slate-900 dark:text-slate-100">
                                {{ selectedSession.location }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400">Enrollment:</span>
                            <span class="ml-2 text-slate-900 dark:text-slate-100">
                                {{ selectedSession.enrolled_count }}
                                <template v-if="selectedSession.available_slots !== null">
                                    / {{ selectedSession.enrolled_count + selectedSession.available_slots }}
                                </template>
                                <span v-if="selectedSession.is_full" class="ml-1 text-red-500">(Full)</span>
                            </span>
                        </div>
                    </div>

                    <Button
                        class="mt-4 w-full"
                        :style="{ backgroundColor: primaryColor }"
                        @click="goToSession(selectedSession)"
                    >
                        View Details
                    </Button>
                </div>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <span class="text-slate-500 dark:text-slate-400">Status:</span>
                <div class="flex items-center gap-1">
                    <span class="h-3 w-3 rounded bg-slate-400"></span>
                    <span class="text-slate-600 dark:text-slate-400">Draft</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="h-3 w-3 rounded bg-blue-500"></span>
                    <span class="text-slate-600 dark:text-slate-400">Scheduled</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="h-3 w-3 rounded bg-yellow-500"></span>
                    <span class="text-slate-600 dark:text-slate-400">In Progress</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="h-3 w-3 rounded bg-green-500"></span>
                    <span class="text-slate-600 dark:text-slate-400">Completed</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="h-3 w-3 rounded bg-red-500"></span>
                    <span class="text-slate-600 dark:text-slate-400">Cancelled</span>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
