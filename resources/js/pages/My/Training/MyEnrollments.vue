<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Session {
    id: number;
    display_title: string;
    date_range: string;
    time_range: string | null;
    location: string | null;
    course?: {
        id: number;
        title: string;
    };
}

interface Enrollment {
    id: number;
    training_session_id: number;
    status: string;
    status_label: string;
    status_color: string;
    is_pending: boolean;
    reference_number: string | null;
    submitted_at: string | null;
    enrolled_at: string;
    attended_at: string | null;
    approver: {
        id: number;
        name: string;
        position: string | null;
    } | null;
    session?: Session;
}

interface WaitlistEntry {
    id: number;
    position: number;
    joined_at: string;
    session?: {
        id: number;
        display_title: string;
        date_range: string;
        course?: {
            id: number;
            title: string;
        };
    };
}

const props = defineProps<{
    pendingRequests: Enrollment[];
    upcomingEnrollments: Enrollment[];
    pastEnrollments: Enrollment[];
    waitlistEntries: WaitlistEntry[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'Training', href: '/my/training' },
    { title: 'My Enrollments', href: '/my/training/enrollments' },
];

const activeTab = ref<'pending' | 'upcoming' | 'past' | 'waitlist'>(
    props.pendingRequests.length > 0 ? 'pending' : 'upcoming'
);

function downloadIcal() {
    window.location.href = '/my/training/calendar.ics';
}

function handleViewSession(sessionId: number) {
    router.visit(`/my/training/sessions/${sessionId}`);
}

function getStatusClass(status: string): string {
    const classMap: Record<string, string> = {
        pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        confirmed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        attended: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        no_show: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        cancelled: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        rejected: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };
    return classMap[status] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="`My Enrollments - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        My Training Enrollments
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        View your training sessions and download your training calendar.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="downloadIcal">
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export Calendar
                    </Button>
                    <Button
                        :style="{ backgroundColor: primaryColor }"
                        @click="router.visit('/my/training/sessions')"
                    >
                        Browse Sessions
                    </Button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-slate-200 dark:border-slate-700">
                <nav class="-mb-px flex gap-6">
                    <button
                        v-if="pendingRequests.length > 0"
                        :class="[
                            'border-b-2 pb-3 text-sm font-medium transition-colors',
                            activeTab === 'pending'
                                ? 'border-current text-slate-900 dark:text-slate-100'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                        ]"
                        :style="activeTab === 'pending' ? { color: primaryColor } : undefined"
                        @click="activeTab = 'pending'"
                    >
                        Pending ({{ pendingRequests.length }})
                    </button>
                    <button
                        :class="[
                            'border-b-2 pb-3 text-sm font-medium transition-colors',
                            activeTab === 'upcoming'
                                ? 'border-current text-slate-900 dark:text-slate-100'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                        ]"
                        :style="activeTab === 'upcoming' ? { color: primaryColor } : undefined"
                        @click="activeTab = 'upcoming'"
                    >
                        Upcoming ({{ upcomingEnrollments.length }})
                    </button>
                    <button
                        :class="[
                            'border-b-2 pb-3 text-sm font-medium transition-colors',
                            activeTab === 'past'
                                ? 'border-current text-slate-900 dark:text-slate-100'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                        ]"
                        :style="activeTab === 'past' ? { color: primaryColor } : undefined"
                        @click="activeTab = 'past'"
                    >
                        Past ({{ pastEnrollments.length }})
                    </button>
                    <button
                        :class="[
                            'border-b-2 pb-3 text-sm font-medium transition-colors',
                            activeTab === 'waitlist'
                                ? 'border-current text-slate-900 dark:text-slate-100'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                        ]"
                        :style="activeTab === 'waitlist' ? { color: primaryColor } : undefined"
                        @click="activeTab = 'waitlist'"
                    >
                        Waitlist ({{ waitlistEntries.length }})
                    </button>
                </nav>
            </div>

            <!-- Pending Requests -->
            <div v-if="activeTab === 'pending'">
                <div v-if="pendingRequests.length > 0" class="space-y-4">
                    <div
                        v-for="request in pendingRequests"
                        :key="request.id"
                        class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-800 dark:bg-amber-900/10"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ request.session?.display_title }}
                                    </h3>
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                            getStatusClass(request.status),
                                        ]"
                                    >
                                        {{ request.status_label }}
                                    </span>
                                </div>
                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ request.session?.course?.title }}
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-slate-600 dark:text-slate-400">
                                    <span class="flex items-center gap-1">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        {{ request.session?.date_range }}
                                    </span>
                                    <span v-if="request.approver" class="flex items-center gap-1">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Approver: {{ request.approver.name }}
                                    </span>
                                </div>
                                <p v-if="request.submitted_at" class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                                    Submitted on {{ formatDate(request.submitted_at) }}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="handleViewSession(request.training_session_id)"
                            >
                                View
                            </Button>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-slate-500 dark:text-slate-400">No pending enrollment requests.</p>
                </div>
            </div>

            <!-- Upcoming Enrollments -->
            <div v-if="activeTab === 'upcoming'">
                <div v-if="upcomingEnrollments.length > 0" class="space-y-4">
                    <div
                        v-for="enrollment in upcomingEnrollments"
                        :key="enrollment.id"
                        class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="flex-1">
                            <h3 class="font-medium text-slate-900 dark:text-slate-100">
                                {{ enrollment.session?.display_title }}
                            </h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ enrollment.session?.course?.title }}
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-slate-600 dark:text-slate-400">
                                <span class="flex items-center gap-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ enrollment.session?.date_range }}
                                </span>
                                <span v-if="enrollment.session?.time_range" class="flex items-center gap-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ enrollment.session?.time_range }}
                                </span>
                                <span v-if="enrollment.session?.location" class="flex items-center gap-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    </svg>
                                    {{ enrollment.session?.location }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    getStatusClass(enrollment.status),
                                ]"
                            >
                                {{ enrollment.status_label }}
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="handleViewSession(enrollment.training_session_id)"
                            >
                                View
                            </Button>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-slate-500 dark:text-slate-400">No upcoming training sessions.</p>
                    <Button
                        class="mt-4"
                        :style="{ backgroundColor: primaryColor }"
                        @click="router.visit('/my/training/sessions')"
                    >
                        Browse Available Sessions
                    </Button>
                </div>
            </div>

            <!-- Past Enrollments -->
            <div v-if="activeTab === 'past'">
                <div v-if="pastEnrollments.length > 0" class="space-y-4">
                    <div
                        v-for="enrollment in pastEnrollments"
                        :key="enrollment.id"
                        class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="flex-1">
                            <h3 class="font-medium text-slate-900 dark:text-slate-100">
                                {{ enrollment.session?.display_title }}
                            </h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ enrollment.session?.course?.title }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ enrollment.session?.date_range }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    getStatusClass(enrollment.status),
                                ]"
                            >
                                {{ enrollment.status_label }}
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-slate-500 dark:text-slate-400">No past training sessions.</p>
                </div>
            </div>

            <!-- Waitlist -->
            <div v-if="activeTab === 'waitlist'">
                <div v-if="waitlistEntries.length > 0" class="space-y-4">
                    <div
                        v-for="entry in waitlistEntries"
                        :key="entry.id"
                        class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="flex-1">
                            <h3 class="font-medium text-slate-900 dark:text-slate-100">
                                {{ entry.session?.display_title }}
                            </h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ entry.session?.course?.title }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ entry.session?.date_range }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                Position #{{ entry.position }}
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="handleViewSession(entry.session!.id)"
                            >
                                View
                            </Button>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-slate-500 dark:text-slate-400">You're not on any waitlists.</p>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
