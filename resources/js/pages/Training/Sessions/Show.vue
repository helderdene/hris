<script setup lang="ts">
import DeleteConfirmationModal from '@/components/DeleteConfirmationModal.vue';
import EnrollmentListTable from '@/components/EnrollmentListTable.vue';
import SessionStatusBadge from '@/components/SessionStatusBadge.vue';
import TrainingSessionFormModal from '@/components/TrainingSessionFormModal.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Employee {
    id: number;
    full_name: string;
    employee_number: string | null;
    department?: string | null;
    position?: string | null;
}

interface Enrollment {
    id: number;
    employee_id: number;
    status: string;
    status_label: string;
    status_color: string;
    is_pending: boolean;
    reference_number: string | null;
    enrolled_at: string;
    submitted_at: string | null;
    attended_at: string | null;
    can_cancel: boolean;
    can_mark_attendance: boolean;
    employee?: Employee;
    approver?: {
        id: number;
        name: string;
        position: string | null;
    };
}

interface WaitlistEntry {
    id: number;
    employee_id: number;
    position: number;
    status: string;
    status_label: string;
    joined_at: string;
    employee?: Employee;
}

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
    max_participants: number | null;
}

interface Session {
    id: number;
    course_id: number;
    title: string | null;
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
    max_participants: number | null;
    effective_max_participants: number | null;
    enrolled_count: number;
    available_slots: number | null;
    is_full: boolean;
    notes: string | null;
    course?: Course;
    instructor?: { id: number; full_name: string };
    creator?: { id: number; full_name: string };
    enrollments?: Enrollment[];
    waitlist?: WaitlistEntry[];
    waitlist_count?: number;
}

interface StatusOption {
    value: string;
    label: string;
}

const props = defineProps<{
    session: Session;
    courses: Course[];
    instructors: { id: number; full_name: string }[];
    availableEmployees: { id: number; full_name: string; employee_number: string }[];
    statusOptions: StatusOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Training', href: '/training/courses' },
    { title: 'Sessions', href: '/training/sessions' },
    { title: props.session.display_title, href: `/training/sessions/${props.session.id}` },
];

const isFormModalOpen = ref(false);
const isCancelModalOpen = ref(false);
const isCancelling = ref(false);
const isPublishing = ref(false);
const selectedEmployeeId = ref<string>('');
const isEnrolling = ref(false);

const canPublish = computed(() => props.session.status === 'draft');
const canCancel = computed(() => ['draft', 'scheduled'].includes(props.session.status));
const canEnroll = computed(() => props.session.status === 'scheduled');

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handlePublish() {
    isPublishing.value = true;
    try {
        const response = await fetch(`/api/training/sessions/${props.session.id}/publish`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            router.reload();
        }
    } finally {
        isPublishing.value = false;
    }
}

async function confirmCancel() {
    isCancelling.value = true;
    try {
        const response = await fetch(`/api/training/sessions/${props.session.id}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ reason: 'Cancelled by administrator' }),
        });

        if (response.ok) {
            isCancelModalOpen.value = false;
            router.reload();
        }
    } finally {
        isCancelling.value = false;
    }
}

async function handleEnrollEmployee() {
    if (!selectedEmployeeId.value) return;

    isEnrolling.value = true;
    try {
        const response = await fetch('/api/training/enrollments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                training_session_id: props.session.id,
                employee_id: parseInt(selectedEmployeeId.value),
            }),
        });

        if (response.ok) {
            selectedEmployeeId.value = '';
            router.reload();
        }
    } finally {
        isEnrolling.value = false;
    }
}

async function handleCancelEnrollment(enrollment: Enrollment) {
    const response = await fetch(`/api/training/enrollments/${enrollment.id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
    });

    if (response.ok) {
        router.reload();
    }
}

async function handleMarkAttended(enrollment: Enrollment) {
    const response = await fetch(`/api/training/enrollments/${enrollment.id}/attended`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
    });

    if (response.ok) {
        router.reload();
    }
}

async function handleMarkNoShow(enrollment: Enrollment) {
    const response = await fetch(`/api/training/enrollments/${enrollment.id}/no-show`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
    });

    if (response.ok) {
        router.reload();
    }
}

async function handleRemoveFromWaitlist(entry: WaitlistEntry) {
    const response = await fetch(`/api/training/waitlist/${entry.id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
    });

    if (response.ok) {
        router.reload();
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    router.reload();
}
</script>

<template>
    <Head :title="`${session.display_title} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
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
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="canPublish"
                        @click="handlePublish"
                        :disabled="isPublishing"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        {{ isPublishing ? 'Publishing...' : 'Publish' }}
                    </Button>
                    <Button variant="outline" @click="isFormModalOpen = true">
                        Edit
                    </Button>
                    <Button
                        v-if="canCancel"
                        variant="destructive"
                        @click="isCancelModalOpen = true"
                    >
                        Cancel Session
                    </Button>
                </div>
            </div>

            <!-- Main Content -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Enrollments -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                Enrollments
                                <span class="ml-2 text-sm font-normal text-slate-500">
                                    ({{ session.enrolled_count }}
                                    <template v-if="session.effective_max_participants">
                                        / {{ session.effective_max_participants }}
                                    </template>)
                                </span>
                            </h2>
                            <div v-if="canEnroll && availableEmployees.length > 0" class="flex items-center gap-2">
                                <Select v-model="selectedEmployeeId">
                                    <SelectTrigger class="w-64">
                                        <SelectValue placeholder="Select employee to enroll" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="emp in availableEmployees"
                                            :key="emp.id"
                                            :value="String(emp.id)"
                                        >
                                            {{ emp.full_name }} ({{ emp.employee_number }})
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button
                                    size="sm"
                                    :disabled="!selectedEmployeeId || isEnrolling"
                                    :style="{ backgroundColor: primaryColor }"
                                    @click="handleEnrollEmployee"
                                >
                                    {{ isEnrolling ? 'Enrolling...' : 'Enroll' }}
                                </Button>
                            </div>
                        </div>

                        <div class="mt-4">
                            <EnrollmentListTable
                                v-if="session.enrollments && session.enrollments.length > 0"
                                :enrollments="session.enrollments"
                                :session-status="session.status"
                                @cancel="handleCancelEnrollment"
                                @mark-attended="handleMarkAttended"
                                @mark-no-show="handleMarkNoShow"
                            />
                            <p v-else class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                                No enrollments yet.
                            </p>
                        </div>
                    </div>

                    <!-- Waitlist -->
                    <div
                        v-if="session.waitlist && session.waitlist.length > 0"
                        class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Waitlist
                            <span class="ml-2 text-sm font-normal text-slate-500">
                                ({{ session.waitlist.filter(w => w.status === 'waiting').length }})
                            </span>
                        </h2>

                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-700">
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-slate-500">#</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-slate-500">Employee</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-slate-500">Joined</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-slate-500">Status</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium uppercase text-slate-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                    <tr v-for="entry in session.waitlist" :key="entry.id">
                                        <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                            {{ entry.position }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                                {{ entry.employee?.full_name }}
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                {{ entry.employee?.department }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                            {{ new Date(entry.joined_at).toLocaleDateString() }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                :class="{
                                                    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400': entry.status === 'waiting',
                                                    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': entry.status === 'promoted',
                                                    'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300': entry.status === 'cancelled' || entry.status === 'expired',
                                                }"
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            >
                                                {{ entry.status_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <Button
                                                v-if="entry.status === 'waiting'"
                                                variant="ghost"
                                                size="sm"
                                                class="text-red-600 hover:text-red-700"
                                                @click="handleRemoveFromWaitlist(entry)"
                                            >
                                                Remove
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div
                        v-if="session.notes"
                        class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Notes
                        </h2>
                        <p class="mt-3 whitespace-pre-wrap text-slate-600 dark:text-slate-400">
                            {{ session.notes }}
                        </p>
                    </div>
                </div>

                <!-- Right Column - Details -->
                <div class="space-y-6">
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
                                    <span v-if="session.is_full" class="ml-1 text-sm text-red-500">(Full)</span>
                                </dd>
                            </div>
                            <div v-if="session.creator">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Created By</dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ session.creator.full_name }}</dd>
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
                                    <span class="text-slate-500">({{ session.course.code }})</span>
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

        <!-- Edit Modal -->
        <TrainingSessionFormModal
            v-model:open="isFormModalOpen"
            :session="session"
            :courses="courses"
            :instructors="instructors"
            :status-options="statusOptions"
            @success="handleFormSuccess"
        />

        <!-- Cancel Confirmation Modal -->
        <DeleteConfirmationModal
            v-model:open="isCancelModalOpen"
            title="Cancel Session"
            description="Are you sure you want to cancel this session? All enrolled employees will be notified and their enrollments will be cancelled."
            confirm-text="Cancel Session"
            :processing="isCancelling"
            @confirm="confirmCancel"
        />
    </TenantLayout>
</template>
