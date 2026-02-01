<script setup lang="ts">
import DeleteConfirmationModal from '@/components/DeleteConfirmationModal.vue';
import SessionStatusBadge from '@/components/SessionStatusBadge.vue';
import TrainingSessionFormModal from '@/components/TrainingSessionFormModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { ref, watch } from 'vue';

interface Course {
    id: number;
    title: string;
    code: string;
    max_participants: number | null;
}

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
    status_color: string;
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
    instructor?: {
        id: number;
        full_name: string;
    };
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface Filters {
    status?: string | null;
    course_id?: number | null;
    search?: string | null;
}

const props = defineProps<{
    sessions: Session[];
    courses: Course[];
    instructors: { id: number; full_name: string }[];
    filters: Filters;
    statusOptions: StatusOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Training', href: '/training/courses' },
    { title: 'Sessions', href: '/training/sessions' },
];

const isFormModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingSession = ref<Session | null>(null);
const deletingSession = ref<Session | null>(null);
const isDeleting = ref(false);

const localFilters = ref<Filters>({ ...props.filters });
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => localFilters.value,
    (newFilters) => {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(() => {
            router.get(
                '/training/sessions',
                {
                    status: newFilters.status || undefined,
                    course_id: newFilters.course_id || undefined,
                    search: newFilters.search || undefined,
                },
                { preserveState: true }
            );
        }, 300);
    },
    { deep: true }
);

function handleAddSession() {
    editingSession.value = null;
    isFormModalOpen.value = true;
}

function handleViewSession(session: Session) {
    router.visit(`/training/sessions/${session.id}`);
}

function handleDeleteSession(session: Session) {
    deletingSession.value = session;
    isDeleteModalOpen.value = true;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function confirmDelete() {
    if (!deletingSession.value) return;

    isDeleting.value = true;

    try {
        const response = await fetch(
            `/api/training/sessions/${deletingSession.value.id}`,
            {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            }
        );

        if (response.ok) {
            isDeleteModalOpen.value = false;
            deletingSession.value = null;
            router.reload({ only: ['sessions'] });
        }
    } finally {
        isDeleting.value = false;
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingSession.value = null;
    router.reload({ only: ['sessions'] });
}

function clearFilters() {
    localFilters.value = { status: null, course_id: null, search: null };
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
                        Training Sessions
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Schedule and manage training sessions for your courses.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        @click="router.visit('/training/calendar')"
                    >
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Calendar
                    </Button>
                    <Button
                        @click="handleAddSession"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Session
                    </Button>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-4">
                <div class="w-full sm:w-64">
                    <Input
                        v-model="localFilters.search"
                        placeholder="Search sessions..."
                        type="search"
                    />
                </div>
                <div class="w-full sm:w-48">
                    <Select v-model="localFilters.status">
                        <SelectTrigger>
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="null">All Statuses</SelectItem>
                            <SelectItem
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="w-full sm:w-64">
                    <Select v-model="localFilters.course_id">
                        <SelectTrigger>
                            <SelectValue placeholder="All Courses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="null">All Courses</SelectItem>
                            <SelectItem
                                v-for="course in courses"
                                :key="course.id"
                                :value="course.id"
                            >
                                {{ course.title }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <Button variant="ghost" size="sm" @click="clearFilters">
                    Clear
                </Button>
            </div>

            <!-- Sessions Table -->
            <div v-if="sessions.length > 0" class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700">
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Session
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Enrollment
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Location
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="session in sessions"
                                :key="session.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div>
                                        <button
                                            class="font-medium text-slate-900 hover:underline dark:text-slate-100"
                                            @click="handleViewSession(session)"
                                        >
                                            {{ session.display_title }}
                                        </button>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">
                                            {{ session.course?.title }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                    <div>{{ session.date_range }}</div>
                                    <div v-if="session.time_range" class="text-slate-400">
                                        {{ session.time_range }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <SessionStatusBadge :status="session.status" :label="session.status_label" />
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span
                                        :class="{
                                            'text-green-600 dark:text-green-400': !session.is_full,
                                            'text-red-600 dark:text-red-400': session.is_full,
                                        }"
                                    >
                                        {{ session.enrolled_count }}
                                        <template v-if="session.effective_max_participants">
                                            / {{ session.effective_max_participants }}
                                        </template>
                                    </span>
                                    <span v-if="session.is_full" class="ml-1 text-xs text-red-500">
                                        (Full)
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                    {{ session.location || 'TBD' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="handleViewSession(session)"
                                        >
                                            View
                                        </Button>
                                        <Button
                                            v-if="session.status === 'draft'"
                                            variant="ghost"
                                            size="sm"
                                            class="text-red-600 hover:text-red-700"
                                            @click="handleDeleteSession(session)"
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
                    No sessions found
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Get started by scheduling your first training session.
                </p>
                <div class="mt-6">
                    <Button @click="handleAddSession" :style="{ backgroundColor: primaryColor }">
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Session
                    </Button>
                </div>
            </div>
        </div>

        <!-- Session Form Modal -->
        <TrainingSessionFormModal
            v-model:open="isFormModalOpen"
            :session="editingSession"
            :courses="courses"
            :instructors="instructors"
            :status-options="statusOptions"
            @success="handleFormSuccess"
        />

        <!-- Delete Confirmation Modal -->
        <DeleteConfirmationModal
            v-model:open="isDeleteModalOpen"
            title="Delete Session"
            :description="`Are you sure you want to delete the session '${deletingSession?.display_title ?? ''}'? This action cannot be undone.`"
            :processing="isDeleting"
            @confirm="confirmDelete"
        />
    </TenantLayout>
</template>
