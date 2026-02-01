<script setup lang="ts">
import EnumSelect from '@/components/EnumSelect.vue';
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
import { Label } from '@/components/ui/label';
import { computed, ref, watch } from 'vue';

interface WorkSchedule {
    id: number;
    name: string;
    code: string;
    schedule_type: string;
    assigned_employees_count: number;
}

interface Employee {
    id: number;
    first_name: string;
    last_name: string;
    employee_id: string;
    email: string;
}

interface Assignment {
    id: number;
    employee_id: number;
    work_schedule_id: number;
    shift_name: string | null;
    effective_date: string;
    end_date: string | null;
    employee?: Employee;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    schedule: WorkSchedule | null;
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const employees = ref<Employee[]>([]);
const assignments = ref<Assignment[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref({
    employee_id: '',
    effective_date: new Date().toISOString().split('T')[0],
    end_date: '',
});

const employeeOptions = computed<EnumOption[]>(() => {
    // Filter out employees that are already assigned
    const assignedEmployeeIds = assignments.value.map((a) => a.employee_id);
    return employees.value
        .filter((e) => !assignedEmployeeIds.includes(e.id))
        .map((employee) => ({
            value: employee.id.toString(),
            label: `${employee.first_name} ${employee.last_name} (${employee.employee_id})`,
        }));
});

watch(open, async (isOpen) => {
    if (isOpen && props.schedule) {
        await Promise.all([loadEmployees(), loadAssignments()]);
    } else {
        resetForm();
    }
});

function resetForm() {
    form.value = {
        employee_id: '',
        effective_date: new Date().toISOString().split('T')[0],
        end_date: '',
    };
    errors.value = {};
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function loadEmployees() {
    try {
        const response = await fetch('/api/employees', {
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            employees.value = data.data || data;
        }
    } catch (error) {
        console.error('Failed to load employees:', error);
    }
}

async function loadAssignments() {
    if (!props.schedule) return;

    isLoading.value = true;
    try {
        const response = await fetch(
            `/api/organization/work-schedules/${props.schedule.id}/assignments`,
            {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            },
        );

        if (response.ok) {
            const data = await response.json();
            assignments.value = data.data || data;
        }
    } catch (error) {
        console.error('Failed to load assignments:', error);
    } finally {
        isLoading.value = false;
    }
}

async function handleSubmit() {
    if (!props.schedule || !form.value.employee_id) {
        errors.value = { employee_id: 'Please select an employee' };
        return;
    }

    errors.value = {};
    isSubmitting.value = true;

    try {
        const response = await fetch(
            `/api/organization/work-schedules/${props.schedule.id}/assignments`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    employee_id: parseInt(form.value.employee_id),
                    effective_date: form.value.effective_date,
                    end_date: form.value.end_date || null,
                }),
            },
        );

        const data = await response.json();

        if (response.ok) {
            await loadAssignments();
            resetForm();
            emit('success');
        } else if (response.status === 422 && data.errors) {
            errors.value = Object.fromEntries(
                Object.entries(data.errors).map(([key, value]) => [
                    key,
                    (value as string[])[0],
                ]),
            );
        } else {
            errors.value = { general: data.message || 'An error occurred' };
        }
    } catch (error) {
        errors.value = {
            general: 'An error occurred while assigning the employee',
        };
    } finally {
        isSubmitting.value = false;
    }
}

async function handleRemoveAssignment(assignment: Assignment) {
    if (!props.schedule) return;
    if (!confirm('Are you sure you want to remove this assignment?')) return;

    try {
        const response = await fetch(
            `/api/organization/work-schedules/${props.schedule.id}/assignments/${assignment.id}`,
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
            await loadAssignments();
            emit('success');
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to remove assignment');
        }
    } catch (error) {
        alert('An error occurred while removing the assignment');
    }
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle> Manage Employee Assignments </DialogTitle>
                <DialogDescription v-if="schedule">
                    Assign employees to {{ schedule.name }} ({{
                        schedule.code
                    }})
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-6">
                <!-- General Error -->
                <div
                    v-if="errors.general"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
                >
                    {{ errors.general }}
                </div>

                <!-- Add Assignment Form -->
                <div
                    class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                >
                    <h4
                        class="mb-4 text-sm font-medium text-slate-900 dark:text-slate-100"
                    >
                        Add New Assignment
                    </h4>
                    <form @submit.prevent="handleSubmit" class="space-y-4">
                        <!-- Employee Select -->
                        <div class="space-y-2">
                            <Label for="employee_id">Employee *</Label>
                            <EnumSelect
                                id="employee_id"
                                v-model="form.employee_id"
                                :options="employeeOptions"
                                placeholder="Select an employee"
                            />
                            <p
                                v-if="errors.employee_id"
                                class="text-sm text-red-500"
                            >
                                {{ errors.employee_id }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Effective Date -->
                            <div class="space-y-2">
                                <Label for="effective_date"
                                    >Effective Date *</Label
                                >
                                <Input
                                    id="effective_date"
                                    v-model="form.effective_date"
                                    type="date"
                                    :class="{
                                        'border-red-500': errors.effective_date,
                                    }"
                                />
                                <p
                                    v-if="errors.effective_date"
                                    class="text-sm text-red-500"
                                >
                                    {{ errors.effective_date }}
                                </p>
                            </div>

                            <!-- End Date -->
                            <div class="space-y-2">
                                <Label for="end_date"
                                    >End Date (optional)</Label
                                >
                                <Input
                                    id="end_date"
                                    v-model="form.end_date"
                                    type="date"
                                    :class="{
                                        'border-red-500': errors.end_date,
                                    }"
                                />
                                <p
                                    v-if="errors.end_date"
                                    class="text-sm text-red-500"
                                >
                                    {{ errors.end_date }}
                                </p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="isSubmitting || !form.employee_id"
                            >
                                <svg
                                    v-if="isSubmitting"
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
                                Assign Employee
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Current Assignments -->
                <div>
                    <h4
                        class="mb-3 text-sm font-medium text-slate-900 dark:text-slate-100"
                    >
                        Current Assignments ({{ assignments.length }})
                    </h4>

                    <div v-if="isLoading" class="py-8 text-center">
                        <svg
                            class="mx-auto h-6 w-6 animate-spin text-slate-400"
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

                    <div
                        v-else-if="assignments.length === 0"
                        class="rounded-lg bg-slate-50 py-6 text-center dark:bg-slate-800/50"
                    >
                        <svg
                            class="mx-auto h-8 w-8 text-slate-400"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                            />
                        </svg>
                        <p
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            No employees assigned yet
                        </p>
                    </div>

                    <div
                        v-else
                        class="divide-y divide-slate-100 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-700"
                    >
                        <div
                            v-for="assignment in assignments"
                            :key="assignment.id"
                            class="flex items-center justify-between p-3"
                        >
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ assignment.employee?.first_name }}
                                    {{ assignment.employee?.last_name }}
                                </div>
                                <div
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Effective:
                                    {{ formatDate(assignment.effective_date) }}
                                    <span v-if="assignment.end_date">
                                        - {{ formatDate(assignment.end_date) }}
                                    </span>
                                </div>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                                @click="handleRemoveAssignment(assignment)"
                            >
                                <svg
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M6 18 18 6M6 6l12 12"
                                    />
                                </svg>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="open = false">
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
