<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Session {
    id: number;
    display_title: string;
    start_date: string;
    end_date: string;
    date_range: string;
    time_range: string | null;
    location: string | null;
    status: string;
    status_label: string;
    enrolled_count: number;
    effective_max_participants: number | null;
    available_slots: number | null;
    is_full: boolean;
    course?: {
        id: number;
        title: string;
        code: string;
        delivery_method_label: string | null;
    };
}

interface Filters {
    search?: string | null;
    course_id?: number | null;
}

const props = defineProps<{
    sessions: Session[];
    enrolledSessionIds: number[];
    waitlistedSessionIds: number[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'Training', href: '/my/training' },
    { title: 'Sessions', href: '/my/training/sessions' },
];

const searchQuery = ref(props.filters.search || '');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, (newSearch) => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
    debounceTimer = setTimeout(() => {
        router.get(
            '/my/training/sessions',
            { search: newSearch || undefined },
            { preserveState: true }
        );
    }, 300);
});

function getEnrollmentStatus(sessionId: number): 'enrolled' | 'waitlisted' | 'available' {
    if (props.enrolledSessionIds.includes(sessionId)) return 'enrolled';
    if (props.waitlistedSessionIds.includes(sessionId)) return 'waitlisted';
    return 'available';
}

function handleViewSession(session: Session) {
    router.visit(`/my/training/sessions/${session.id}`);
}
</script>

<template>
    <Head :title="`Training Sessions - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Available Training Sessions
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Browse and enroll in upcoming training sessions.
                    </p>
                </div>
                <Button
                    variant="outline"
                    @click="router.visit('/my/training/enrollments')"
                >
                    My Enrollments
                </Button>
            </div>

            <!-- Search -->
            <div class="w-full sm:w-64">
                <Input
                    v-model="searchQuery"
                    placeholder="Search sessions..."
                    type="search"
                />
            </div>

            <!-- Sessions Grid -->
            <div v-if="sessions.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="session in sessions"
                    :key="session.id"
                    class="rounded-xl border border-slate-200 bg-white p-5 transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
                >
                    <!-- Status Badge -->
                    <div class="mb-3 flex items-center justify-between">
                        <span
                            v-if="getEnrollmentStatus(session.id) === 'enrolled'"
                            class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                        >
                            Enrolled
                        </span>
                        <span
                            v-else-if="getEnrollmentStatus(session.id) === 'waitlisted'"
                            class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400"
                        >
                            On Waitlist
                        </span>
                        <span
                            v-else-if="session.is_full"
                            class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400"
                        >
                            Full
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
                        >
                            Open
                        </span>

                        <span class="text-xs text-slate-500">
                            {{ session.enrolled_count }}
                            <template v-if="session.effective_max_participants">
                                / {{ session.effective_max_participants }}
                            </template>
                            enrolled
                        </span>
                    </div>

                    <!-- Title -->
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {{ session.display_title }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ session.course?.title }}
                    </p>

                    <!-- Details -->
                    <div class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ session.date_range }}
                        </div>
                        <div v-if="session.time_range" class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ session.time_range }}
                        </div>
                        <div v-if="session.location" class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ session.location }}
                        </div>
                    </div>

                    <!-- Action -->
                    <div class="mt-4">
                        <Button
                            class="w-full"
                            :variant="getEnrollmentStatus(session.id) === 'available' ? 'default' : 'outline'"
                            :style="getEnrollmentStatus(session.id) === 'available' ? { backgroundColor: primaryColor } : undefined"
                            @click="handleViewSession(session)"
                        >
                            {{ getEnrollmentStatus(session.id) === 'available' ? 'View & Enroll' : 'View Details' }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
                <svg
                    class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                    No upcoming sessions
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    There are no training sessions scheduled at this time.
                </p>
            </div>
        </div>
    </TenantLayout>
</template>
