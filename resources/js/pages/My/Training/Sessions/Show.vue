<script setup lang="ts">
import SessionStatusBadge from '@/components/SessionStatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Course {
    id: number;
    title: string;
    code: string;
    delivery_method: string;
    delivery_method_label: string;
    level: string | null;
    level_label: string | null;
    duration_hours: number | null;
    formatted_duration: string | null;
}

interface Session {
    id: number;
    display_title: string;
    start_date: string;
    end_date: string;
    date_range: string;
    start_time: string | null;
    end_time: string | null;
    time_range: string | null;
    location: string | null;
    virtual_link: string | null;
    status: string;
    status_label: string;
    enrolled_count: number;
    effective_max_participants: number | null;
    available_slots: number | null;
    is_full: boolean;
    notes: string | null;
    course?: Course;
    instructor?: { id: number; full_name: string };
}

interface Enrollment {
    id: number;
    status: string;
    status_label: string;
    is_pending: boolean;
    enrolled_at: string;
    submitted_at: string | null;
    reference_number: string | null;
    request_reason: string | null;
    approver: {
        id: number;
        name: string;
        position: string | null;
    } | null;
}

const props = defineProps<{
    session: Session;
    enrollment: Enrollment | null;
    waitlistPosition: number | null;
    isPending: boolean;
    isEnrolled: boolean;
    isOnWaitlist: boolean;
    canEnroll: boolean;
    canRequestEnrollment: boolean;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'Training', href: '/my/training' },
    { title: 'Sessions', href: '/my/training/sessions' },
    { title: props.session.display_title, href: `/my/training/sessions/${props.session.id}` },
];

const isEnrolling = ref(false);
const isRequesting = ref(false);
const isCancelling = ref(false);
const message = ref<{ type: 'success' | 'error'; text: string } | null>(null);
const showRequestDialog = ref(false);
const requestReason = ref('');

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleEnroll() {
    isEnrolling.value = true;
    message.value = null;

    try {
        const response = await fetch(`/my/training/sessions/${props.session.id}/enroll`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        const data = await response.json();

        if (response.ok) {
            message.value = { type: 'success', text: data.message };
            setTimeout(() => router.reload(), 1000);
        } else {
            message.value = { type: 'error', text: data.message || 'An error occurred.' };
        }
    } catch {
        message.value = { type: 'error', text: 'An error occurred. Please try again.' };
    } finally {
        isEnrolling.value = false;
    }
}

async function handleCancelEnrollment() {
    if (!props.enrollment) return;

    isCancelling.value = true;
    message.value = null;

    try {
        const response = await fetch(`/my/training/enrollments/${props.enrollment.id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        const data = await response.json();

        if (response.ok) {
            message.value = { type: 'success', text: data.message };
            setTimeout(() => router.reload(), 1000);
        } else {
            message.value = { type: 'error', text: data.message || 'An error occurred.' };
        }
    } catch {
        message.value = { type: 'error', text: 'An error occurred. Please try again.' };
    } finally {
        isCancelling.value = false;
    }
}

async function handleRequestEnrollment() {
    isRequesting.value = true;
    message.value = null;

    try {
        const response = await fetch(`/my/training/sessions/${props.session.id}/request`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ reason: requestReason.value }),
        });

        const data = await response.json();

        if (response.ok) {
            showRequestDialog.value = false;
            message.value = { type: 'success', text: data.message };
            setTimeout(() => router.reload(), 1000);
        } else {
            message.value = { type: 'error', text: data.message || 'An error occurred.' };
        }
    } catch {
        message.value = { type: 'error', text: 'An error occurred. Please try again.' };
    } finally {
        isRequesting.value = false;
    }
}

async function handleCancelRequest() {
    if (!props.enrollment) return;

    isCancelling.value = true;
    message.value = null;

    try {
        const response = await fetch(`/my/training/enrollments/${props.enrollment.id}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        const data = await response.json();

        if (response.ok) {
            message.value = { type: 'success', text: data.message };
            setTimeout(() => router.reload(), 1000);
        } else {
            message.value = { type: 'error', text: data.message || 'An error occurred.' };
        }
    } catch {
        message.value = { type: 'error', text: 'An error occurred. Please try again.' };
    } finally {
        isCancelling.value = false;
    }
}

function downloadIcal() {
    window.location.href = '/my/training/calendar.ics';
}
</script>

<template>
    <Head :title="`${session.display_title} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Message -->
            <div
                v-if="message"
                :class="[
                    'rounded-lg p-4',
                    message.type === 'success' ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                ]"
            >
                {{ message.text }}
            </div>

            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ session.display_title }}
                        </h1>
                        <SessionStatusBadge :status="session.status" :label="session.status_label" />
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ session.course?.title }} ({{ session.course?.code }})
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column - Details -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Enrollment Status Card -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Your Status
                        </h2>

                        <div class="mt-4">
                            <div v-if="isPending" class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                                    <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-amber-700 dark:text-amber-400">Request Pending Approval</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        <template v-if="enrollment?.approver">
                                            Awaiting approval from {{ enrollment.approver.name }}
                                        </template>
                                        <template v-else>
                                            Your enrollment request is being reviewed.
                                        </template>
                                    </p>
                                </div>
                            </div>

                            <div v-else-if="isEnrolled" class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-green-700 dark:text-green-400">You're enrolled!</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        Enrolled on {{ new Date(enrollment!.enrolled_at).toLocaleDateString() }}
                                    </p>
                                </div>
                            </div>

                            <div v-else-if="isOnWaitlist" class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900/30">
                                    <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-yellow-700 dark:text-yellow-400">You're on the waitlist</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        Position #{{ waitlistPosition }}. You'll be notified if a spot opens up.
                                    </p>
                                </div>
                            </div>

                            <div v-else-if="session.is_full" class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-red-700 dark:text-red-400">Session is full</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        You can join the waitlist to be notified if a spot opens.
                                    </p>
                                </div>
                            </div>

                            <div v-else class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-blue-700 dark:text-blue-400">Spots available</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ session.available_slots }} spots remaining. Enroll now!
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <Button
                                v-if="canRequestEnrollment"
                                :disabled="isRequesting"
                                :style="{ backgroundColor: primaryColor }"
                                @click="showRequestDialog = true"
                            >
                                Request Enrollment
                            </Button>
                            <Button
                                v-if="canEnroll && !canRequestEnrollment"
                                :disabled="isEnrolling"
                                :style="{ backgroundColor: primaryColor }"
                                @click="handleEnroll"
                            >
                                {{ isEnrolling ? 'Enrolling...' : session.is_full ? 'Join Waitlist' : 'Enroll Now' }}
                            </Button>
                            <Button
                                v-if="isPending"
                                variant="outline"
                                :disabled="isCancelling"
                                @click="handleCancelRequest"
                            >
                                {{ isCancelling ? 'Cancelling...' : 'Cancel Request' }}
                            </Button>
                            <Button
                                v-if="isEnrolled"
                                variant="outline"
                                :disabled="isCancelling"
                                @click="handleCancelEnrollment"
                            >
                                {{ isCancelling ? 'Cancelling...' : 'Cancel Enrollment' }}
                            </Button>
                            <Button
                                v-if="isEnrolled"
                                variant="outline"
                                @click="downloadIcal"
                            >
                                <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Add to Calendar
                            </Button>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div
                        v-if="session.notes"
                        class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Session Notes
                        </h2>
                        <p class="mt-3 whitespace-pre-wrap text-slate-600 dark:text-slate-400">
                            {{ session.notes }}
                        </p>
                    </div>
                </div>

                <!-- Right Column - Info -->
                <div class="space-y-6">
                    <!-- Session Details -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Session Details
                        </h2>
                        <dl class="mt-4 space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Date</dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ session.date_range }}</dd>
                            </div>
                            <div v-if="session.time_range">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Time</dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ session.time_range }}</dd>
                            </div>
                            <div v-if="session.location">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Location</dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ session.location }}</dd>
                            </div>
                            <div v-if="session.virtual_link">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Virtual Link</dt>
                                <dd class="mt-1">
                                    <a
                                        :href="session.virtual_link"
                                        target="_blank"
                                        class="text-blue-600 hover:underline dark:text-blue-400"
                                    >
                                        Join Online
                                    </a>
                                </dd>
                            </div>
                            <div v-if="session.instructor">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Instructor</dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ session.instructor.full_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Capacity</dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                                    {{ session.enrolled_count }}
                                    <template v-if="session.effective_max_participants">
                                        / {{ session.effective_max_participants }}
                                    </template>
                                    enrolled
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Course Info -->
                    <div v-if="session.course" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Course Info
                        </h2>
                        <dl class="mt-4 space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Course</dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                                    {{ session.course.title }}
                                </dd>
                            </div>
                            <div v-if="session.course.delivery_method_label">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Delivery</dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ session.course.delivery_method_label }}</dd>
                            </div>
                            <div v-if="session.course.level_label">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Level</dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ session.course.level_label }}</dd>
                            </div>
                            <div v-if="session.course.formatted_duration">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Duration</dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ session.course.formatted_duration }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Request Enrollment Dialog -->
        <Dialog v-model:open="showRequestDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Request Training Enrollment</DialogTitle>
                    <DialogDescription>
                        Submit a request to enroll in this training session. Your supervisor will review and approve your request.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        Reason for Enrollment (Optional)
                    </label>
                    <Textarea
                        v-model="requestReason"
                        placeholder="Why do you want to attend this training?"
                        rows="3"
                        class="mt-2"
                    />
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showRequestDialog = false"
                        :disabled="isRequesting"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleRequestEnrollment"
                        :disabled="isRequesting"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        {{ isRequesting ? 'Submitting...' : 'Submit Request' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
