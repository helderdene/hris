<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { computed, ref, watch } from 'vue';

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
    position?: string;
    department?: string;
}

interface Participant {
    id: number;
    performance_cycle_instance_id: number;
    employee_id: number;
    employee?: Employee;
    manager_id: number | null;
    manager?: Employee | null;
    is_excluded: boolean;
    status: string;
    status_label: string;
    completed_at: string | null;
}

interface PerformanceCycleInstance {
    id: number;
    name: string;
    status: string;
    is_editable: boolean;
    employee_count: number;
}

const props = defineProps<{
    instance: PerformanceCycleInstance | null;
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const participants = ref<Participant[]>([]);
const availableEmployees = ref<Employee[]>([]);
const isLoading = ref(false);
const isAssigning = ref(false);
const searchQuery = ref('');
const statusFilter = ref('all');
const selectedEmployeesToExclude = ref<number[]>([]);
const showAssignForm = ref(false);
const error = ref<string | null>(null);

const filteredParticipants = computed(() => {
    let result = participants.value;

    // Filter by search query
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (p) =>
                p.employee?.full_name.toLowerCase().includes(query) ||
                p.employee?.employee_number.toLowerCase().includes(query),
        );
    }

    // Filter by status
    if (statusFilter.value !== 'all') {
        if (statusFilter.value === 'excluded') {
            result = result.filter((p) => p.is_excluded);
        } else if (statusFilter.value === 'included') {
            result = result.filter((p) => !p.is_excluded);
        } else {
            result = result.filter(
                (p) => p.status === statusFilter.value && !p.is_excluded,
            );
        }
    }

    return result;
});

const includedCount = computed(
    () => participants.value.filter((p) => !p.is_excluded).length,
);
const excludedCount = computed(
    () => participants.value.filter((p) => p.is_excluded).length,
);
const completedCount = computed(
    () =>
        participants.value.filter(
            (p) => p.status === 'completed' && !p.is_excluded,
        ).length,
);

// Employees not yet assigned as participants
const unassignedEmployees = computed(() => {
    const participantEmployeeIds = new Set(
        participants.value.map((p) => p.employee_id),
    );
    return availableEmployees.value.filter(
        (e) => !participantEmployeeIds.has(e.id),
    );
});

watch(
    [open, () => props.instance?.id],
    async ([isOpen, instanceId]) => {
        console.log('[Participants Modal] Watch triggered - isOpen:', isOpen, 'instanceId:', instanceId);
        if (isOpen && instanceId) {
            console.log('[Participants Modal] Fetching data...');
            await fetchParticipants();
            await fetchAvailableEmployees();
        } else if (!isOpen) {
            console.log('[Participants Modal] Modal closed, resetting state');
            // Reset state when closing
            participants.value = [];
            availableEmployees.value = [];
            searchQuery.value = '';
            statusFilter.value = 'all';
            selectedEmployeesToExclude.value = [];
            showAssignForm.value = false;
            error.value = null;
        }
    },
    { immediate: true },
);

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function fetchParticipants() {
    if (!props.instance) {
        console.log('[Participants Modal] fetchParticipants: No instance');
        return;
    }

    console.log('[Participants Modal] Fetching participants for instance:', props.instance.id);
    isLoading.value = true;
    error.value = null;

    try {
        const url = `/api/organization/performance-cycle-instances/${props.instance.id}/participants`;
        console.log('[Participants Modal] Fetch URL:', url);

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        console.log('[Participants Modal] Response status:', response.status);

        if (response.ok) {
            const data = await response.json();
            console.log('[Participants Modal] Response data:', data);
            // Handle both wrapped and unwrapped responses
            const participantsList = Array.isArray(data) ? data : (data.data || []);
            console.log('[Participants Modal] Participants list:', participantsList.length);
            participants.value = participantsList;
        } else {
            console.log('[Participants Modal] Response not ok:', response.status);
            error.value = 'Failed to load participants';
        }
    } catch (e) {
        console.error('[Participants Modal] Error:', e);
        error.value = 'An error occurred while loading participants';
    } finally {
        isLoading.value = false;
    }
}

async function fetchAvailableEmployees() {
    try {
        const response = await fetch('/api/employees?status=active', {
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            availableEmployees.value = data.data || [];
        }
    } catch {
        // Silently fail - employees list is optional
    }
}

async function handleAssignAll() {
    if (!props.instance) return;

    console.log('[Participants Modal] Assigning participants for instance:', props.instance.id);
    isAssigning.value = true;
    error.value = null;

    try {
        const response = await fetch(
            `/api/organization/performance-cycle-instances/${props.instance.id}/participants/assign`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    excluded_employee_ids: selectedEmployeesToExclude.value,
                }),
            },
        );

        const data = await response.json();
        console.log('[Participants Modal] Assign response:', response.status, data);

        if (response.ok) {
            showAssignForm.value = false;
            selectedEmployeesToExclude.value = [];
            console.log('[Participants Modal] Assignment successful, fetching participants...');
            // Always fetch fresh participants after assignment to ensure correct data
            await fetchParticipants();
            emit('success');
        } else {
            error.value = data.message || 'Failed to assign participants';
        }
    } catch (e) {
        console.error('[Participants Modal] Assignment error:', e);
        error.value = 'An error occurred while assigning participants';
    } finally {
        isAssigning.value = false;
    }
}

async function handleToggleExclusion(participant: Participant) {
    if (!props.instance?.is_editable) return;

    try {
        const response = await fetch(
            `/api/organization/performance-cycle-instances/${props.instance.id}/participants/${participant.id}`,
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    is_excluded: !participant.is_excluded,
                }),
            },
        );

        if (response.ok) {
            const data = await response.json();
            const index = participants.value.findIndex(
                (p) => p.id === participant.id,
            );
            if (index !== -1) {
                participants.value[index] = data.data || data;
            }
            emit('success');
        } else {
            const data = await response.json();
            error.value = data.message || 'Failed to update participant';
        }
    } catch {
        error.value = 'An error occurred while updating participant';
    }
}

async function handleRemoveParticipant(participant: Participant) {
    if (!props.instance?.is_editable) return;

    if (
        !confirm(
            `Remove ${participant.employee?.full_name} from this evaluation cycle?`,
        )
    ) {
        return;
    }

    try {
        const response = await fetch(
            `/api/organization/performance-cycle-instances/${props.instance.id}/participants/${participant.id}`,
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
            participants.value = participants.value.filter(
                (p) => p.id !== participant.id,
            );
            emit('success');
        } else {
            const data = await response.json();
            error.value = data.message || 'Failed to remove participant';
        }
    } catch {
        error.value = 'An error occurred while removing participant';
    }
}

function getStatusBadgeClasses(status: string, isExcluded: boolean): string {
    if (isExcluded) {
        return 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400';
    }
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'in_progress':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        default:
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
    }
}

function toggleEmployeeExclusion(employeeId: number) {
    const index = selectedEmployeesToExclude.value.indexOf(employeeId);
    if (index === -1) {
        selectedEmployeesToExclude.value.push(employeeId);
    } else {
        selectedEmployeesToExclude.value.splice(index, 1);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>
                    Manage Participants
                    <span
                        v-if="instance"
                        class="font-normal text-slate-500 dark:text-slate-400"
                    >
                        - {{ instance.name }}
                    </span>
                </DialogTitle>
                <DialogDescription>
                    Assign and manage employees participating in this
                    performance evaluation cycle.
                </DialogDescription>
            </DialogHeader>

            <!-- Error Message -->
            <div
                v-if="error"
                class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
            >
                {{ error }}
            </div>

            <!-- Stats Summary -->
            <div
                class="grid grid-cols-3 gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
            >
                <div class="text-center">
                    <div
                        class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                    >
                        {{ includedCount }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Included
                    </div>
                </div>
                <div class="text-center">
                    <div
                        class="text-2xl font-bold text-green-600 dark:text-green-400"
                    >
                        {{ completedCount }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Completed
                    </div>
                </div>
                <div class="text-center">
                    <div
                        class="text-2xl font-bold text-slate-400 dark:text-slate-500"
                    >
                        {{ excludedCount }}
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Excluded
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search employees..."
                        class="w-full sm:w-64"
                    />
                    <Select v-model="statusFilter">
                        <SelectTrigger class="w-36">
                            <SelectValue placeholder="Filter status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All</SelectItem>
                            <SelectItem value="included">Included</SelectItem>
                            <SelectItem value="excluded">Excluded</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="completed">Completed</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Button
                    v-if="instance?.is_editable && participants.length === 0"
                    @click="showAssignForm = true"
                    :disabled="isAssigning"
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
                            d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"
                        />
                    </svg>
                    Assign All Employees
                </Button>
            </div>

            <!-- Assign Form (when no participants) -->
            <div
                v-if="showAssignForm"
                class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
            >
                <h4 class="mb-3 font-medium text-slate-900 dark:text-slate-100">
                    Assign All Active Employees
                </h4>
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                    This will assign all active employees to this evaluation
                    cycle. You can optionally exclude specific employees below.
                </p>

                <div
                    v-if="unassignedEmployees.length > 0"
                    class="mb-4 max-h-48 overflow-y-auto rounded border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800"
                >
                    <div
                        v-for="employee in unassignedEmployees"
                        :key="employee.id"
                        class="flex items-center gap-3 border-b border-slate-100 px-3 py-2 last:border-0 dark:border-slate-700"
                    >
                        <input
                            type="checkbox"
                            :id="`exclude-${employee.id}`"
                            :checked="
                                selectedEmployeesToExclude.includes(employee.id)
                            "
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700"
                            @change="toggleEmployeeExclusion(employee.id)"
                        />
                        <label
                            :for="`exclude-${employee.id}`"
                            class="flex-1 cursor-pointer text-sm"
                        >
                            <span
                                class="font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ employee.full_name }}
                            </span>
                            <span class="text-slate-500 dark:text-slate-400">
                                ({{ employee.employee_number }})
                            </span>
                        </label>
                    </div>
                </div>

                <p
                    v-if="selectedEmployeesToExclude.length > 0"
                    class="mb-4 text-sm text-amber-600 dark:text-amber-400"
                >
                    {{ selectedEmployeesToExclude.length }} employee(s) will be
                    marked as excluded.
                </p>

                <div class="flex gap-2">
                    <Button @click="handleAssignAll" :disabled="isAssigning">
                        <svg
                            v-if="isAssigning"
                            class="mr-2 h-4 w-4 animate-spin"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        Assign Employees
                    </Button>
                    <Button
                        variant="outline"
                        @click="showAssignForm = false"
                        :disabled="isAssigning"
                    >
                        Cancel
                    </Button>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="flex items-center justify-center py-8">
                <svg
                    class="h-8 w-8 animate-spin text-slate-400"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    />
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                </svg>
            </div>

            <!-- Participants Table -->
            <div
                v-else-if="participants.length > 0"
                class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700"
            >
                <!-- Table Header -->
                <div
                    class="hidden border-b border-slate-200 bg-slate-50 md:block dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <div class="grid grid-cols-12 gap-4 px-4 py-3">
                        <div
                            class="col-span-4 text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                        >
                            Employee
                        </div>
                        <div
                            class="col-span-3 text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                        >
                            Manager
                        </div>
                        <div
                            class="col-span-2 text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                        >
                            Status
                        </div>
                        <div
                            v-if="instance?.is_editable"
                            class="col-span-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                        >
                            Actions
                        </div>
                    </div>
                </div>

                <!-- Table Body -->
                <div class="divide-y divide-slate-200 dark:divide-slate-700">
                    <div
                        v-for="participant in filteredParticipants"
                        :key="participant.id"
                        class="grid grid-cols-1 gap-2 px-4 py-3 md:grid-cols-12 md:items-center md:gap-4"
                        :class="{
                            'bg-slate-50 dark:bg-slate-800/30':
                                participant.is_excluded,
                        }"
                    >
                        <!-- Employee -->
                        <div
                            class="md:col-span-4"
                            :class="{ 'opacity-50': participant.is_excluded }"
                        >
                            <div
                                class="font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ participant.employee?.full_name }}
                            </div>
                            <div
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ participant.employee?.employee_number }}
                                <span v-if="participant.employee?.position">
                                    - {{ participant.employee?.position }}
                                </span>
                            </div>
                        </div>

                        <!-- Manager -->
                        <div class="md:col-span-3">
                            <span
                                v-if="participant.manager"
                                class="text-slate-700 dark:text-slate-300"
                            >
                                {{ participant.manager.full_name }}
                            </span>
                            <span
                                v-else
                                class="text-slate-400 dark:text-slate-500"
                            >
                                Not assigned
                            </span>
                        </div>

                        <!-- Status -->
                        <div class="md:col-span-2">
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="
                                    getStatusBadgeClasses(
                                        participant.status,
                                        participant.is_excluded,
                                    )
                                "
                            >
                                {{
                                    participant.is_excluded
                                        ? 'Excluded'
                                        : participant.status_label
                                }}
                            </span>
                        </div>

                        <!-- Actions -->
                        <div
                            v-if="instance?.is_editable"
                            class="flex items-center justify-end gap-1 md:col-span-3"
                        >
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0"
                                :title="
                                    participant.is_excluded
                                        ? 'Include'
                                        : 'Exclude'
                                "
                                @click="handleToggleExclusion(participant)"
                            >
                                <svg
                                    v-if="participant.is_excluded"
                                    class="h-4 w-4 text-green-600"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="h-4 w-4 text-slate-400"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"
                                    />
                                </svg>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-red-600 hover:text-red-700 dark:text-red-400"
                                title="Remove"
                                @click="handleRemoveParticipant(participant)"
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
                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                                    />
                                </svg>
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- No Results -->
                <div
                    v-if="filteredParticipants.length === 0"
                    class="py-8 text-center text-slate-500 dark:text-slate-400"
                >
                    No participants match your filters.
                </div>
            </div>

            <!-- Empty State (no participants yet) -->
            <div v-else-if="!showAssignForm" class="py-8 text-center">
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
                        d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                    />
                </svg>
                <h3
                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    No participants assigned
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{
                        instance?.is_editable
                            ? 'Click "Assign All Employees" to add participants to this cycle.'
                            : 'This cycle has no participants and cannot be edited.'
                    }}
                </p>
                <div v-if="instance?.is_editable" class="mt-4">
                    <Button @click="showAssignForm = true">
                        Assign All Employees
                    </Button>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
