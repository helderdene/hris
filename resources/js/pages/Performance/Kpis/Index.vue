<script setup lang="ts">
import KpiAssignmentFormModal from '@/components/KpiAssignmentFormModal.vue';
import KpiTemplateFormModal from '@/components/KpiTemplateFormModal.vue';
import RecordProgressDialog from '@/components/RecordProgressDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface KpiTemplate {
    id: number;
    name: string;
    code: string;
    description: string | null;
    metric_unit: string;
    default_target: number | null;
    default_weight: number;
    category: string | null;
    is_active: boolean;
    assignments_count?: number;
}

interface KpiAssignment {
    id: number;
    kpi_template_id: number;
    performance_cycle_participant_id: number;
    target_value: number;
    weight: number;
    actual_value: number | null;
    achievement_percentage: number | null;
    status: string;
    status_label: string;
    status_color: string;
    notes: string | null;
    completed_at: string | null;
    kpi_template?: KpiTemplate;
    participant?: {
        id: number;
        employee_id: number;
        employee_name: string;
        employee_code: string | null;
        instance_id: number;
    };
}

interface PerformanceInstance {
    id: number;
    name: string;
    cycle_name: string | null;
    year: number;
}

interface Participant {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_code: string | null;
}

interface EnumOption {
    value: string;
    label: string;
    description?: string;
    color?: string;
}

interface Filters {
    instance_id: number | null;
    status: string | null;
    participant_id: number | null;
}

const props = defineProps<{
    templates: KpiTemplate[];
    assignments: KpiAssignment[];
    instances: PerformanceInstance[];
    participants: Participant[];
    kpiStatuses: EnumOption[];
    categories: string[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Performance', href: '/performance/kpis' },
    { title: 'KPIs', href: '/performance/kpis' },
];

const activeTab = ref('assignments');
const selectedInstanceId = ref(
    props.filters.instance_id ? String(props.filters.instance_id) : 'all',
);
const selectedStatus = ref(props.filters.status ?? 'all');

// Template modal state
const isTemplateModalOpen = ref(false);
const editingTemplate = ref<KpiTemplate | null>(null);
const deletingTemplateId = ref<number | null>(null);

// Assignment modal state
const isAssignmentModalOpen = ref(false);
const editingAssignment = ref<KpiAssignment | null>(null);
const deletingAssignmentId = ref<number | null>(null);

// Progress dialog state
const isProgressDialogOpen = ref(false);
const progressAssignment = ref<KpiAssignment | null>(null);

function getStatusBadgeClasses(status: string): string {
    switch (status) {
        case 'pending':
            return 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300';
        case 'in_progress':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'completed':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

// Safe accessors for data arrays
const templatesData = computed(() => props.templates ?? []);
const assignmentsData = computed(() => props.assignments ?? []);
const instancesData = computed(() => props.instances ?? []);
const participantsData = computed(() => props.participants ?? []);

const instanceOptions = computed(() => {
    return [
        { value: 'all', label: 'All Instances' },
        ...instancesData.value.map((instance) => ({
            value: String(instance.id),
            label: `${instance.name} (${instance.year})`,
        })),
    ];
});

const statusOptions = computed(() => {
    return [
        { value: 'all', label: 'All Statuses' },
        ...props.kpiStatuses.map((status) => ({
            value: status.value,
            label: status.label,
        })),
    ];
});

function handleInstanceChange(instanceId: string) {
    selectedInstanceId.value = instanceId;
    router.get(
        '/performance/kpis',
        {
            instance_id: instanceId !== 'all' ? instanceId : undefined,
            status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        },
        { preserveState: true },
    );
}

function handleStatusChange(status: string) {
    selectedStatus.value = status;
    router.get(
        '/performance/kpis',
        {
            instance_id: selectedInstanceId.value !== 'all' ? selectedInstanceId.value : undefined,
            status: status !== 'all' ? status : undefined,
        },
        { preserveState: true },
    );
}

// Template handlers
function handleAddTemplate() {
    editingTemplate.value = null;
    isTemplateModalOpen.value = true;
}

function handleEditTemplate(template: KpiTemplate) {
    editingTemplate.value = template;
    isTemplateModalOpen.value = true;
}

async function handleDeleteTemplate(template: KpiTemplate) {
    if (
        !confirm(
            `Are you sure you want to delete the KPI template "${template.name}"?`,
        )
    ) {
        return;
    }

    deletingTemplateId.value = template.id;

    try {
        const response = await fetch(
            `/api/performance/kpi-templates/${template.id}`,
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
            router.reload({ only: ['templates'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete template');
        }
    } catch {
        alert('An error occurred while deleting the template');
    } finally {
        deletingTemplateId.value = null;
    }
}

function handleTemplateFormSuccess() {
    isTemplateModalOpen.value = false;
    editingTemplate.value = null;
    router.reload({ only: ['templates'] });
}

// Assignment handlers
function handleAddAssignment() {
    editingAssignment.value = null;
    isAssignmentModalOpen.value = true;
}

function handleEditAssignment(assignment: KpiAssignment) {
    editingAssignment.value = assignment;
    isAssignmentModalOpen.value = true;
}

async function handleDeleteAssignment(assignment: KpiAssignment) {
    if (
        !confirm(
            `Are you sure you want to delete this KPI assignment?`,
        )
    ) {
        return;
    }

    deletingAssignmentId.value = assignment.id;

    try {
        const response = await fetch(
            `/api/performance/kpi-assignments/${assignment.id}`,
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
            router.reload({ only: ['assignments'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete assignment');
        }
    } catch {
        alert('An error occurred while deleting the assignment');
    } finally {
        deletingAssignmentId.value = null;
    }
}

function handleAssignmentFormSuccess() {
    isAssignmentModalOpen.value = false;
    editingAssignment.value = null;
    router.reload({ only: ['assignments'] });
}

// Progress handlers
function handleRecordProgress(assignment: KpiAssignment) {
    progressAssignment.value = assignment;
    isProgressDialogOpen.value = true;
}

function handleProgressSuccess() {
    isProgressDialogOpen.value = false;
    progressAssignment.value = null;
    router.reload({ only: ['assignments'] });
}

async function handleMarkComplete(assignment: KpiAssignment) {
    if (!confirm('Are you sure you want to mark this KPI as completed?')) {
        return;
    }

    try {
        const response = await fetch(
            `/api/performance/kpi-assignments/${assignment.id}/complete`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        if (response.ok) {
            router.reload({ only: ['assignments'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to mark as complete');
        }
    } catch {
        alert('An error occurred');
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function formatNumber(value: number | string | null): string {
    if (value === null || value === undefined) return '-';
    const num = Number(value);
    if (isNaN(num)) return '-';
    return new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(num);
}

function formatPercentage(value: number | string | null): string {
    if (value === null || value === undefined) return '-';
    const num = Number(value);
    if (isNaN(num)) return '-';
    return `${num.toFixed(1)}%`;
}
</script>

<template>
    <Head :title="`KPIs - ${tenantName}`" />

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
                        Key Performance Indicators
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage KPI templates and track employee performance.
                    </p>
                </div>
            </div>

            <!-- Tabs -->
            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="mb-4">
                    <TabsTrigger value="assignments">KPI Assignments</TabsTrigger>
                    <TabsTrigger value="templates">KPI Templates</TabsTrigger>
                </TabsList>

                <!-- Assignments Tab -->
                <TabsContent value="assignments">
                    <div class="flex flex-col gap-4">
                        <!-- Filters and Actions -->
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex flex-wrap items-center gap-3">
                                <Select
                                    :model-value="selectedInstanceId"
                                    @update:model-value="handleInstanceChange"
                                >
                                    <SelectTrigger class="w-56">
                                        <SelectValue placeholder="Filter by instance" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in instanceOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <Select
                                    :model-value="selectedStatus"
                                    @update:model-value="handleStatusChange"
                                >
                                    <SelectTrigger class="w-40">
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
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

                            <Button
                                @click="handleAddAssignment"
                                :style="{ backgroundColor: primaryColor }"
                                :disabled="templatesData.length === 0"
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
                                Assign KPI
                            </Button>
                        </div>

                        <!-- Assignments Table -->
                        <div
                            class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                        >
                            <div class="hidden md:block">
                                <table
                                    class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                                >
                                    <thead
                                        class="bg-slate-50 dark:bg-slate-800/50"
                                    >
                                        <tr>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                KPI
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Employee
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Target
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Actual
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Achievement
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Status
                                            </th>
                                            <th
                                                scope="col"
                                                class="relative px-6 py-3"
                                            >
                                                <span class="sr-only">Actions</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-200 dark:divide-slate-700"
                                    >
                                        <tr
                                            v-for="assignment in assignmentsData"
                                            :key="assignment.id"
                                            class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                        >
                                            <td class="px-6 py-4">
                                                <div
                                                    class="font-medium text-slate-900 dark:text-slate-100"
                                                >
                                                    {{ assignment.kpi_template?.name || 'Unknown' }}
                                                </div>
                                                <div
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    {{ assignment.kpi_template?.metric_unit }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div
                                                    class="text-sm text-slate-900 dark:text-slate-100"
                                                >
                                                    {{ assignment.participant?.employee_name || 'Unknown' }}
                                                </div>
                                                <div
                                                    class="text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    {{ assignment.participant?.employee_code }}
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right whitespace-nowrap"
                                            >
                                                <span class="text-sm text-slate-900 dark:text-slate-100">
                                                    {{ formatNumber(assignment.target_value) }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right whitespace-nowrap"
                                            >
                                                <span class="text-sm text-slate-900 dark:text-slate-100">
                                                    {{ formatNumber(assignment.actual_value) }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right whitespace-nowrap"
                                            >
                                                <span
                                                    class="text-sm font-medium"
                                                    :class="
                                                        assignment.achievement_percentage !== null &&
                                                        assignment.achievement_percentage >= 100
                                                            ? 'text-green-600 dark:text-green-400'
                                                            : 'text-slate-900 dark:text-slate-100'
                                                    "
                                                >
                                                    {{ formatPercentage(assignment.achievement_percentage) }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <span
                                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                                    :class="getStatusBadgeClasses(assignment.status)"
                                                >
                                                    {{ assignment.status_label }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                            >
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <Button variant="outline" size="sm">
                                                            Actions
                                                            <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                            </svg>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            v-if="assignment.status !== 'completed'"
                                                            @click="handleRecordProgress(assignment)"
                                                        >
                                                            Record Progress
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            v-if="assignment.status !== 'completed'"
                                                            @click="handleMarkComplete(assignment)"
                                                        >
                                                            Mark Complete
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            @click="handleEditAssignment(assignment)"
                                                        >
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                            :disabled="deletingAssignmentId === assignment.id"
                                                            @click="handleDeleteAssignment(assignment)"
                                                        >
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-if="assignmentsData.length === 0"
                                class="px-6 py-12 text-center"
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
                                        d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"
                                    />
                                </svg>
                                <h3
                                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    No KPI assignments
                                </h3>
                                <p
                                    class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        templatesData.length === 0
                                            ? 'Create a KPI template first, then assign it to employees.'
                                            : 'Assign KPIs to employees to track their performance.'
                                    }}
                                </p>
                                <div class="mt-6">
                                    <Button
                                        v-if="templatesData.length > 0"
                                        @click="handleAddAssignment"
                                        :style="{ backgroundColor: primaryColor }"
                                    >
                                        Assign KPI
                                    </Button>
                                    <Button
                                        v-else
                                        @click="activeTab = 'templates'; handleAddTemplate();"
                                        :style="{ backgroundColor: primaryColor }"
                                    >
                                        Create KPI Template
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- Templates Tab -->
                <TabsContent value="templates">
                    <div class="flex flex-col gap-4">
                        <!-- Actions -->
                        <div class="flex justify-end">
                            <Button
                                @click="handleAddTemplate"
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
                                Add Template
                            </Button>
                        </div>

                        <!-- Templates Table -->
                        <div
                            class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                        >
                            <div class="hidden md:block">
                                <table
                                    class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                                >
                                    <thead
                                        class="bg-slate-50 dark:bg-slate-800/50"
                                    >
                                        <tr>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Name
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Category
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Metric Unit
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Default Target
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Status
                                            </th>
                                            <th
                                                scope="col"
                                                class="relative px-6 py-3"
                                            >
                                                <span class="sr-only">Actions</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-200 dark:divide-slate-700"
                                    >
                                        <tr
                                            v-for="template in templatesData"
                                            :key="template.id"
                                            class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                        >
                                            <td class="px-6 py-4">
                                                <div
                                                    class="font-medium text-slate-900 dark:text-slate-100"
                                                >
                                                    {{ template.name }}
                                                </div>
                                                <div
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    {{ template.code }}
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <span
                                                    v-if="template.category"
                                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300"
                                                >
                                                    {{ template.category }}
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    -
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <span class="text-sm text-slate-900 dark:text-slate-100">
                                                    {{ template.metric_unit }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right whitespace-nowrap"
                                            >
                                                <span class="text-sm text-slate-900 dark:text-slate-100">
                                                    {{ formatNumber(template.default_target) }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <span
                                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                                    :class="
                                                        template.is_active
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400'
                                                    "
                                                >
                                                    {{ template.is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                            >
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <Button variant="outline" size="sm">
                                                            Actions
                                                            <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                            </svg>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            @click="handleEditTemplate(template)"
                                                        >
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                            :disabled="deletingTemplateId === template.id"
                                                            @click="handleDeleteTemplate(template)"
                                                        >
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-if="templatesData.length === 0"
                                class="px-6 py-12 text-center"
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
                                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                                    />
                                </svg>
                                <h3
                                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    No KPI templates
                                </h3>
                                <p
                                    class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Create reusable KPI templates that can be assigned to employees.
                                </p>
                                <div class="mt-6">
                                    <Button
                                        @click="handleAddTemplate"
                                        :style="{ backgroundColor: primaryColor }"
                                    >
                                        Create KPI Template
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>

        <!-- Modals -->
        <KpiTemplateFormModal
            v-model:open="isTemplateModalOpen"
            :template="editingTemplate"
            :categories="categories"
            @success="handleTemplateFormSuccess"
        />

        <KpiAssignmentFormModal
            v-model:open="isAssignmentModalOpen"
            :assignment="editingAssignment"
            :templates="templatesData.filter(t => t.is_active)"
            :instances="instancesData"
            :participants="participantsData"
            :selected-instance-id="selectedInstanceId !== 'all' ? Number(selectedInstanceId) : null"
            @success="handleAssignmentFormSuccess"
        />

        <RecordProgressDialog
            v-model:open="isProgressDialogOpen"
            :assignment="progressAssignment"
            @success="handleProgressSuccess"
        />
    </TenantLayout>
</template>
