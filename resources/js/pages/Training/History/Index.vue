<script setup lang="ts">
import EnumSelect from '@/Components/EnumSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Course {
    id: number;
    title: string;
    code: string;
}

interface Trainer {
    id: number;
    full_name: string;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface Enrollment {
    id: number;
    reference_number: string | null;
    employee: {
        id: number;
        full_name: string;
        employee_number: string;
        department: string | null;
        position: string | null;
    } | null;
    course: {
        id: number;
        title: string;
        code: string;
        formatted_duration: string | null;
    } | null;
    session: {
        id: number;
        title: string;
        date_range: string;
        time_range: string | null;
        location: string | null;
    } | null;
    trainer: {
        id: number;
        full_name: string;
    } | null;
    status: string;
    status_label: string;
    status_color: string;
    attended_at: string | null;
    completion_status: string | null;
    completion_status_label: string | null;
    completion_status_color: string | null;
    assessment_score: number | null;
    certificate_number: string | null;
    certificate_issued_at: string | null;
    has_certificate: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedEnrollments {
    data: Enrollment[];
    links: PaginationLink[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

interface Filters {
    search?: string | null;
    course_id?: number | null;
    trainer_id?: number | null;
    status?: string | null;
    completion_status?: string | null;
    location?: string | null;
    date_from?: string | null;
    date_to?: string | null;
    employee_id?: number | null;
}

const props = defineProps<{
    enrollments: PaginatedEnrollments;
    courses: Course[];
    trainers: Trainer[];
    locations: string[];
    filters: Filters;
    statusOptions: StatusOption[];
    completionStatusOptions: StatusOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Training', href: '/training/courses' },
    { title: 'History', href: '/training/history' },
];

const showFilters = ref(true);
const isExporting = ref(false);
const localFilters = ref<Filters>({ ...props.filters });

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

// Computed dropdown options
const courseOptions = computed(() => [
    { value: '', label: 'All Courses' },
    ...props.courses.map((c) => ({ value: c.id.toString(), label: c.title })),
]);

const trainerOptions = computed(() => [
    { value: '', label: 'All Trainers' },
    ...props.trainers.map((t) => ({ value: t.id.toString(), label: t.full_name })),
]);

const locationOptions = computed(() => [
    { value: '', label: 'All Locations' },
    ...props.locations.map((l) => ({ value: l, label: l })),
]);

const statusSelectOptions = computed(() => [
    { value: '', label: 'All Statuses' },
    ...props.statusOptions.map((s) => ({ value: s.value, label: s.label })),
]);

const completionSelectOptions = computed(() => [
    { value: '', label: 'All Completion' },
    ...props.completionStatusOptions.map((s) => ({ value: s.value, label: s.label })),
]);

// Record counts
const recordCount = computed(() => props.enrollments?.data?.length || 0);
const totalCount = computed(() => props.enrollments?.meta?.total || recordCount.value);

// Watch filter changes with debounce
watch(
    () => localFilters.value,
    (newFilters) => {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(() => {
            applyFilters();
        }, 300);
    },
    { deep: true }
);

function applyFilters() {
    router.get(
        '/training/history',
        {
            search: localFilters.value.search || undefined,
            course_id: localFilters.value.course_id || undefined,
            trainer_id: localFilters.value.trainer_id || undefined,
            status: localFilters.value.status || undefined,
            completion_status: localFilters.value.completion_status || undefined,
            location: localFilters.value.location || undefined,
            date_from: localFilters.value.date_from || undefined,
            date_to: localFilters.value.date_to || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
}

function clearFilters() {
    localFilters.value = {
        search: null,
        course_id: null,
        trainer_id: null,
        status: null,
        completion_status: null,
        location: null,
        date_from: null,
        date_to: null,
    };
}

function goToPage(url: string | null) {
    if (url) {
        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
        });
    }
}

function handleExport() {
    isExporting.value = true;

    // Build query params
    const params = new URLSearchParams();
    if (localFilters.value.search) params.append('search', localFilters.value.search);
    if (localFilters.value.course_id) params.append('course_id', localFilters.value.course_id.toString());
    if (localFilters.value.trainer_id) params.append('trainer_id', localFilters.value.trainer_id.toString());
    if (localFilters.value.status) params.append('status', localFilters.value.status);
    if (localFilters.value.completion_status) params.append('completion_status', localFilters.value.completion_status);
    if (localFilters.value.location) params.append('location', localFilters.value.location);
    if (localFilters.value.date_from) params.append('date_from', localFilters.value.date_from);
    if (localFilters.value.date_to) params.append('date_to', localFilters.value.date_to);

    const queryString = params.toString();
    const url = `/training/history/export${queryString ? `?${queryString}` : ''}`;

    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', '');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    setTimeout(() => {
        isExporting.value = false;
    }, 2000);
}

function viewEmployee(employeeId: number) {
    router.visit(`/employees/${employeeId}`);
}

function viewSession(sessionId: number) {
    router.visit(`/training/sessions/${sessionId}`);
}

function getStatusBadgeClasses(color: string | null): string {
    const colorMap: Record<string, string> = {
        yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        gray: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-400',
    };
    return colorMap[color || 'gray'] || colorMap.gray;
}
</script>

<template>
    <Head :title="`Training History - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Training History
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ recordCount }} of {{ totalCount }} training records
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        @click="showFilters = !showFilters"
                    >
                        <Search class="mr-2 h-4 w-4" />
                        Filters
                    </Button>
                    <Button
                        @click="handleExport"
                        :disabled="isExporting"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        <component :is="isExporting ? FileSpreadsheet : Download" class="mr-2 h-4 w-4" />
                        {{ isExporting ? 'Exporting...' : 'Export Excel' }}
                    </Button>
                </div>
            </div>

            <!-- Filters Panel -->
            <div
                v-if="showFilters"
                class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
            >
                <div class="w-full sm:w-64">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Search
                    </label>
                    <Input
                        v-model="localFilters.search"
                        placeholder="Employee name or course..."
                        type="search"
                    />
                </div>
                <div class="w-full sm:w-48">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Course
                    </label>
                    <EnumSelect
                        v-model="localFilters.course_id"
                        :options="courseOptions"
                        placeholder="All Courses"
                    />
                </div>
                <div class="w-full sm:w-48">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Trainer
                    </label>
                    <EnumSelect
                        v-model="localFilters.trainer_id"
                        :options="trainerOptions"
                        placeholder="All Trainers"
                    />
                </div>
                <div class="w-full sm:w-36">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Status
                    </label>
                    <EnumSelect
                        v-model="localFilters.status"
                        :options="statusSelectOptions"
                        placeholder="All Statuses"
                    />
                </div>
                <div class="w-full sm:w-36">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Completion
                    </label>
                    <EnumSelect
                        v-model="localFilters.completion_status"
                        :options="completionSelectOptions"
                        placeholder="All Completion"
                    />
                </div>
                <div class="w-full sm:w-36">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Location
                    </label>
                    <EnumSelect
                        v-model="localFilters.location"
                        :options="locationOptions"
                        placeholder="All Locations"
                    />
                </div>
                <div class="w-full sm:w-36">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Date From
                    </label>
                    <Input v-model="localFilters.date_from" type="date" />
                </div>
                <div class="w-full sm:w-36">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Date To
                    </label>
                    <Input v-model="localFilters.date_to" type="date" />
                </div>
                <Button variant="ghost" size="sm" @click="clearFilters">
                    Clear
                </Button>
            </div>

            <!-- Data Table -->
            <div v-if="enrollments.data.length > 0" class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Employee
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Course / Session
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Date
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Location
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Trainer
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Status
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Score
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Certificate
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="enrollment in enrollments.data"
                                :key="enrollment.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-4 py-3">
                                    <div v-if="enrollment.employee">
                                        <button
                                            class="font-medium text-slate-900 hover:underline dark:text-slate-100"
                                            @click="viewEmployee(enrollment.employee.id)"
                                        >
                                            {{ enrollment.employee.full_name }}
                                        </button>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">
                                            {{ enrollment.employee.employee_number }}
                                        </p>
                                    </div>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="enrollment.course">
                                        <button
                                            v-if="enrollment.session"
                                            class="font-medium text-slate-900 hover:underline dark:text-slate-100"
                                            @click="viewSession(enrollment.session.id)"
                                        >
                                            {{ enrollment.session.title }}
                                        </button>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">
                                            {{ enrollment.course.code }} - {{ enrollment.course.title }}
                                        </p>
                                    </div>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                    <div v-if="enrollment.session">
                                        {{ enrollment.session.date_range }}
                                        <div v-if="enrollment.session.time_range" class="text-slate-400 text-xs">
                                            {{ enrollment.session.time_range }}
                                        </div>
                                    </div>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                    {{ enrollment.session?.location || '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                    {{ enrollment.trainer?.full_name || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-1">
                                        <span
                                            class="inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="getStatusBadgeClasses(enrollment.status_color)"
                                        >
                                            {{ enrollment.status_label }}
                                        </span>
                                        <span
                                            v-if="enrollment.completion_status"
                                            class="inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="getStatusBadgeClasses(enrollment.completion_status_color)"
                                        >
                                            {{ enrollment.completion_status_label }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span
                                        v-if="enrollment.assessment_score !== null"
                                        class="font-medium"
                                        :class="{
                                            'text-green-600 dark:text-green-400': enrollment.assessment_score >= 75,
                                            'text-yellow-600 dark:text-yellow-400': enrollment.assessment_score >= 50 && enrollment.assessment_score < 75,
                                            'text-red-600 dark:text-red-400': enrollment.assessment_score < 50,
                                        }"
                                    >
                                        {{ enrollment.assessment_score }}%
                                    </span>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div v-if="enrollment.has_certificate">
                                        <span class="text-green-600 dark:text-green-400">
                                            {{ enrollment.certificate_number }}
                                        </span>
                                        <p class="text-xs text-slate-400">
                                            {{ enrollment.certificate_issued_at }}
                                        </p>
                                    </div>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="enrollments.links && enrollments.links.length > 3"
                    class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50 sm:px-6"
                >
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-slate-700 dark:text-slate-300">
                                Showing page
                                <span class="font-medium">{{ enrollments.meta?.current_page || 1 }}</span>
                                of
                                <span class="font-medium">{{ enrollments.meta?.last_page || 1 }}</span>
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <button
                                    v-for="(link, index) in enrollments.links"
                                    :key="index"
                                    :disabled="!link.url"
                                    @click="goToPage(link.url)"
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium"
                                    :class="[
                                        link.active
                                            ? 'z-10 bg-blue-600 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                                            : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 dark:text-slate-300 dark:ring-slate-600 dark:hover:bg-slate-700',
                                        !link.url
                                            ? 'cursor-not-allowed opacity-50'
                                            : 'cursor-pointer',
                                        index === 0 ? 'rounded-l-md' : '',
                                        index === enrollments.links.length - 1 ? 'rounded-r-md' : '',
                                    ]"
                                    v-html="link.label"
                                ></button>
                            </nav>
                        </div>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                    No training history found
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ localFilters.search || localFilters.course_id || localFilters.status ? 'Try adjusting your filters.' : 'Training history will appear here after employees complete sessions.' }}
                </p>
            </div>
        </div>
    </TenantLayout>
</template>
