<script setup lang="ts">
import GeneratePerformanceInstancesDialog from '@/components/GeneratePerformanceInstancesDialog.vue';
import PerformanceCycleFormModal from '@/components/PerformanceCycleFormModal.vue';
import PerformanceCycleInstanceFormModal from '@/components/PerformanceCycleInstanceFormModal.vue';
import PerformanceCycleParticipantsModal from '@/components/PerformanceCycleParticipantsModal.vue';
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

interface PerformanceCycle {
    id: number;
    name: string;
    code: string;
    cycle_type: string;
    cycle_type_label: string;
    description: string | null;
    status: string;
    is_default: boolean;
    is_recurring: boolean;
    instances_per_year: number | null;
    instances_count?: number;
}

interface PerformanceCycleInstance {
    id: number;
    performance_cycle_id: number;
    performance_cycle?: PerformanceCycle;
    name: string;
    year: number;
    instance_number: number;
    start_date: string;
    end_date: string;
    date_range: string;
    formatted_start_date: string;
    formatted_end_date: string;
    status: string;
    status_label: string;
    status_color: string;
    is_editable: boolean;
    is_deletable: boolean;
    allowed_transitions: Array<{ value: string; label: string }>;
    employee_count: number;
}

interface EnumOption {
    value: string;
    label: string;
    description?: string;
    is_recurring?: boolean;
    instances_per_year?: number | null;
    color?: string;
}

interface Filters {
    year: number;
    cycle_id: number | null;
}

const props = defineProps<{
    cycles: PerformanceCycle[];
    instances: PerformanceCycleInstance[];
    cycleTypes: EnumOption[];
    instanceStatuses: EnumOption[];
    availableYears: number[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Performance Cycles', href: '/organization/performance-cycles' },
];

const activeTab = ref('instances');
const selectedYear = ref(String(props.filters.year));
const selectedCycleId = ref(
    props.filters.cycle_id ? String(props.filters.cycle_id) : 'all',
);

// Cycle modal state
const isCycleModalOpen = ref(false);
const editingCycle = ref<PerformanceCycle | null>(null);
const deletingCycleId = ref<number | null>(null);

// Instance modal state
const isInstanceModalOpen = ref(false);
const editingInstance = ref<PerformanceCycleInstance | null>(null);
const deletingInstanceId = ref<number | null>(null);

// Generate dialog state
const isGenerateDialogOpen = ref(false);

// Participants modal state
const isParticipantsModalOpen = ref(false);
const selectedInstanceForParticipants = ref<PerformanceCycleInstance | null>(null);

function getStatusBadgeClasses(status: string): string {
    switch (status) {
        case 'draft':
            return 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300';
        case 'active':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'in_evaluation':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'closed':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getCycleTypeBadgeClasses(type: string): string {
    switch (type) {
        case 'annual':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'mid_year':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
        case 'probationary':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300';
        case 'project_based':
            return 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

// Safe accessors for data arrays
const cyclesData = computed(() => props.cycles ?? []);
const instancesData = computed(() => props.instances ?? []);
const yearsData = computed(() => props.availableYears ?? []);

const cycleOptions = computed(() => {
    return [
        { value: 'all', label: 'All Cycles' },
        ...cyclesData.value.map((cycle) => ({
            value: String(cycle.id),
            label: cycle.name,
        })),
    ];
});

function handleYearChange(year: string) {
    selectedYear.value = year;
    router.get(
        '/organization/performance-cycles',
        {
            year: year,
            cycle_id:
                selectedCycleId.value !== 'all'
                    ? selectedCycleId.value
                    : undefined,
        },
        { preserveState: true },
    );
}

function handleCycleChange(cycleId: string) {
    selectedCycleId.value = cycleId;
    router.get(
        '/organization/performance-cycles',
        {
            year: selectedYear.value,
            cycle_id: cycleId !== 'all' ? cycleId : undefined,
        },
        { preserveState: true },
    );
}

// Cycle handlers
function handleAddCycle() {
    editingCycle.value = null;
    isCycleModalOpen.value = true;
}

function handleEditCycle(cycle: PerformanceCycle) {
    editingCycle.value = cycle;
    isCycleModalOpen.value = true;
}

async function handleDeleteCycle(cycle: PerformanceCycle) {
    if (
        !confirm(
            `Are you sure you want to delete the performance cycle "${cycle.name}"? This will also delete all draft instances associated with it.`,
        )
    ) {
        return;
    }

    deletingCycleId.value = cycle.id;

    try {
        const response = await fetch(
            `/api/organization/performance-cycles/${cycle.id}`,
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
            router.reload({ only: ['cycles', 'instances'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete cycle');
        }
    } catch {
        alert('An error occurred while deleting the cycle');
    } finally {
        deletingCycleId.value = null;
    }
}

function handleCycleFormSuccess() {
    isCycleModalOpen.value = false;
    editingCycle.value = null;
    router.reload({ only: ['cycles'] });
}

// Instance handlers
function handleAddInstance() {
    editingInstance.value = null;
    isInstanceModalOpen.value = true;
}

function handleEditInstance(instance: PerformanceCycleInstance) {
    editingInstance.value = instance;
    isInstanceModalOpen.value = true;
}

async function handleDeleteInstance(instance: PerformanceCycleInstance) {
    if (
        !confirm(
            `Are you sure you want to delete the instance "${instance.name}"?`,
        )
    ) {
        return;
    }

    deletingInstanceId.value = instance.id;

    try {
        const response = await fetch(
            `/api/organization/performance-cycle-instances/${instance.id}`,
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
            router.reload({ only: ['instances'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete instance');
        }
    } catch {
        alert('An error occurred while deleting the instance');
    } finally {
        deletingInstanceId.value = null;
    }
}

async function handleStatusChange(
    instance: PerformanceCycleInstance,
    newStatus: string,
) {
    try {
        const response = await fetch(
            `/api/organization/performance-cycle-instances/${instance.id}/status`,
            {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ status: newStatus }),
            },
        );

        if (response.ok) {
            router.reload({ only: ['instances'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to update status');
        }
    } catch {
        alert('An error occurred while updating the status');
    }
}

function handleInstanceFormSuccess() {
    isInstanceModalOpen.value = false;
    editingInstance.value = null;
    router.reload({ only: ['instances'] });
}

function openGenerateDialog() {
    isGenerateDialogOpen.value = true;
}

function handleGenerateSuccess() {
    isGenerateDialogOpen.value = false;
    router.reload({ only: ['instances'] });
}

function handleManageParticipants(instance: PerformanceCycleInstance) {
    selectedInstanceForParticipants.value = instance;
    isParticipantsModalOpen.value = true;
}

function handleParticipantsSuccess() {
    router.reload({ only: ['instances'] });
}

function handleParticipantsModalClose(isOpen: boolean) {
    if (!isOpen) {
        // Reset the selected instance when modal closes
        selectedInstanceForParticipants.value = null;
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Head :title="`Performance Cycles - ${tenantName}`" />

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
                        Performance Cycles
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage performance evaluation cycles and instances.
                    </p>
                </div>
            </div>

            <!-- Tabs -->
            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="mb-4">
                    <TabsTrigger value="instances">Cycle Instances</TabsTrigger>
                    <TabsTrigger value="cycles"
                        >Performance Cycles</TabsTrigger
                    >
                </TabsList>

                <!-- Instances Tab -->
                <TabsContent value="instances">
                    <div class="flex flex-col gap-4">
                        <!-- Filters and Actions -->
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex flex-wrap items-center gap-3">
                                <Select
                                    :model-value="selectedYear"
                                    @update:model-value="handleYearChange"
                                >
                                    <SelectTrigger class="w-32">
                                        <SelectValue placeholder="Year" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="year in yearsData"
                                            :key="year"
                                            :value="String(year)"
                                        >
                                            {{ year }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <Select
                                    :model-value="selectedCycleId"
                                    @update:model-value="handleCycleChange"
                                >
                                    <SelectTrigger class="w-44">
                                        <SelectValue
                                            placeholder="Filter by cycle"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in cycleOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="flex gap-2">
                                <Button
                                    variant="outline"
                                    @click="openGenerateDialog"
                                    :disabled="cyclesData.length === 0"
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
                                            d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3"
                                        />
                                    </svg>
                                    Generate Instances
                                </Button>
                                <Button
                                    @click="handleAddInstance"
                                    :style="{ backgroundColor: primaryColor }"
                                    :disabled="cyclesData.length === 0"
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
                                    Add Instance
                                </Button>
                            </div>
                        </div>

                        <!-- Instances Table -->
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
                                                Instance
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Date Range
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Employees
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
                                                <span class="sr-only"
                                                    >Actions</span
                                                >
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-200 dark:divide-slate-700"
                                    >
                                        <tr
                                            v-for="instance in instancesData"
                                            :key="instance.id"
                                            class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                        >
                                            <td class="px-6 py-4">
                                                <div
                                                    class="font-medium text-slate-900 dark:text-slate-100"
                                                >
                                                    {{ instance.name }}
                                                </div>
                                                <div
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        instance
                                                            .performance_cycle
                                                            ?.name ||
                                                        'Unknown Cycle'
                                                    }}
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <div
                                                    class="text-sm text-slate-900 dark:text-slate-100"
                                                >
                                                    {{ instance.date_range }}
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <div
                                                    class="text-sm text-slate-900 dark:text-slate-100"
                                                >
                                                    {{ instance.employee_count }}
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <DropdownMenu
                                                    v-if="
                                                        (
                                                            instance.allowed_transitions ??
                                                            []
                                                        ).length > 0
                                                    "
                                                >
                                                    <DropdownMenuTrigger
                                                        as-child
                                                    >
                                                        <button
                                                            class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
                                                            :class="
                                                                getStatusBadgeClasses(
                                                                    instance.status,
                                                                )
                                                            "
                                                        >
                                                            {{
                                                                instance.status_label
                                                            }}
                                                            <svg
                                                                class="h-3 w-3"
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                                stroke-width="2"
                                                                stroke="currentColor"
                                                            >
                                                                <path
                                                                    stroke-linecap="round"
                                                                    stroke-linejoin="round"
                                                                    d="M19.5 8.25l-7.5 7.5-7.5-7.5"
                                                                />
                                                            </svg>
                                                        </button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="start"
                                                    >
                                                        <DropdownMenuLabel
                                                            >Change
                                                            Status</DropdownMenuLabel
                                                        >
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            v-for="transition in instance.allowed_transitions"
                                                            :key="
                                                                transition.value
                                                            "
                                                            @click="
                                                                handleStatusChange(
                                                                    instance,
                                                                    transition.value,
                                                                )
                                                            "
                                                        >
                                                            {{
                                                                transition.label
                                                            }}
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                                <span
                                                    v-else
                                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                                    :class="
                                                        getStatusBadgeClasses(
                                                            instance.status,
                                                        )
                                                    "
                                                >
                                                    {{ instance.status_label }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                            >
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        as-child
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            class="h-8 w-8 p-0"
                                                        >
                                                            <span
                                                                class="sr-only"
                                                                >Open menu</span
                                                            >
                                                            <svg
                                                                class="h-4 w-4"
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                                stroke-width="2"
                                                                stroke="currentColor"
                                                            >
                                                                <path
                                                                    stroke-linecap="round"
                                                                    stroke-linejoin="round"
                                                                    d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"
                                                                />
                                                            </svg>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                    >
                                                        <DropdownMenuLabel
                                                            >Actions</DropdownMenuLabel
                                                        >
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            @click="
                                                                handleManageParticipants(
                                                                    instance,
                                                                )
                                                            "
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
                                                                    d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                                                                />
                                                            </svg>
                                                            Manage Participants
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            v-if="
                                                                instance.is_editable
                                                            "
                                                            @click="
                                                                handleEditInstance(
                                                                    instance,
                                                                )
                                                            "
                                                        >
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            v-if="
                                                                instance.is_deletable
                                                            "
                                                            class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                            :disabled="
                                                                deletingInstanceId ===
                                                                instance.id
                                                            "
                                                            @click="
                                                                handleDeleteInstance(
                                                                    instance,
                                                                )
                                                            "
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
                                v-if="instancesData.length === 0"
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
                                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
                                    />
                                </svg>
                                <h3
                                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    No cycle instances
                                </h3>
                                <p
                                    class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        cyclesData.length === 0
                                            ? 'Create a performance cycle first, then generate instances.'
                                            : 'Generate instances for a cycle or add them manually.'
                                    }}
                                </p>
                                <div class="mt-6">
                                    <Button
                                        v-if="cyclesData.length > 0"
                                        @click="openGenerateDialog"
                                        :style="{
                                            backgroundColor: primaryColor,
                                        }"
                                    >
                                        Generate Instances
                                    </Button>
                                    <Button
                                        v-else
                                        @click="
                                            activeTab = 'cycles';
                                            handleAddCycle();
                                        "
                                        :style="{
                                            backgroundColor: primaryColor,
                                        }"
                                    >
                                        Create Performance Cycle
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- Cycles Tab -->
                <TabsContent value="cycles">
                    <div class="flex flex-col gap-4">
                        <!-- Actions -->
                        <div class="flex justify-end">
                            <Button
                                @click="handleAddCycle"
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
                                Add Cycle
                            </Button>
                        </div>

                        <!-- Cycles Table -->
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
                                                Type
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Status
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Instances/Year
                                            </th>
                                            <th
                                                scope="col"
                                                class="relative px-6 py-3"
                                            >
                                                <span class="sr-only"
                                                    >Actions</span
                                                >
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-200 dark:divide-slate-700"
                                    >
                                        <tr
                                            v-for="cycle in cyclesData"
                                            :key="cycle.id"
                                            class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                        >
                                            <td class="px-6 py-4">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <div
                                                        class="font-medium text-slate-900 dark:text-slate-100"
                                                    >
                                                        {{ cycle.name }}
                                                    </div>
                                                    <span
                                                        v-if="cycle.is_default"
                                                        class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
                                                    >
                                                        Default
                                                    </span>
                                                </div>
                                                <div
                                                    class="text-sm text-slate-500 dark:text-slate-400"
                                                >
                                                    {{ cycle.code }}
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <span
                                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                                    :class="
                                                        getCycleTypeBadgeClasses(
                                                            cycle.cycle_type,
                                                        )
                                                    "
                                                >
                                                    {{ cycle.cycle_type_label }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <span
                                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                                    :class="
                                                        cycle.status ===
                                                        'active'
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400'
                                                    "
                                                >
                                                    {{
                                                        cycle.status ===
                                                        'active'
                                                            ? 'Active'
                                                            : 'Inactive'
                                                    }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <div
                                                    class="text-sm text-slate-900 dark:text-slate-100"
                                                >
                                                    {{
                                                        cycle.instances_per_year ||
                                                        'Variable'
                                                    }}
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                            >
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        as-child
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            class="h-8 w-8 p-0"
                                                        >
                                                            <span
                                                                class="sr-only"
                                                                >Open menu</span
                                                            >
                                                            <svg
                                                                class="h-4 w-4"
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                                stroke-width="2"
                                                                stroke="currentColor"
                                                            >
                                                                <path
                                                                    stroke-linecap="round"
                                                                    stroke-linejoin="round"
                                                                    d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"
                                                                />
                                                            </svg>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                    >
                                                        <DropdownMenuLabel
                                                            >Actions</DropdownMenuLabel
                                                        >
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            @click="
                                                                handleEditCycle(
                                                                    cycle,
                                                                )
                                                            "
                                                        >
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                            :disabled="
                                                                deletingCycleId ===
                                                                cycle.id
                                                            "
                                                            @click="
                                                                handleDeleteCycle(
                                                                    cycle,
                                                                )
                                                            "
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
                                v-if="cyclesData.length === 0"
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
                                        d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3"
                                    />
                                </svg>
                                <h3
                                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    No performance cycles
                                </h3>
                                <p
                                    class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Create a performance cycle to start
                                    generating evaluation instances.
                                </p>
                                <div class="mt-6">
                                    <Button
                                        @click="handleAddCycle"
                                        :style="{
                                            backgroundColor: primaryColor,
                                        }"
                                    >
                                        Create Performance Cycle
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>

        <!-- Modals -->
        <PerformanceCycleFormModal
            v-model:open="isCycleModalOpen"
            :cycle="editingCycle"
            :cycle-types="cycleTypes"
            @success="handleCycleFormSuccess"
        />

        <PerformanceCycleInstanceFormModal
            v-model:open="isInstanceModalOpen"
            :instance="editingInstance"
            :cycles="cyclesData"
            @success="handleInstanceFormSuccess"
        />

        <GeneratePerformanceInstancesDialog
            v-model:open="isGenerateDialogOpen"
            :cycles="cyclesData"
            :available-years="yearsData"
            :default-year="filters.year"
            @success="handleGenerateSuccess"
        />

        <PerformanceCycleParticipantsModal
            v-model:open="isParticipantsModalOpen"
            :instance="selectedInstanceForParticipants"
            @success="handleParticipantsSuccess"
            @update:open="handleParticipantsModalClose"
        />
    </TenantLayout>
</template>
