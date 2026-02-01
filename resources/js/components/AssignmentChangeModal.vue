<script setup lang="ts">
import EnumSelect from '@/Components/EnumSelect.vue';
import InputError from '@/components/InputError.vue';
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
import { Textarea } from '@/components/ui/textarea';
import {
    AssignmentType,
    AssignmentTypeLabels,
    type AssignmentChangeFormData,
    type AssignmentFormErrors,
    type DepartmentOption,
    type PositionOption,
    type SupervisorOption,
    type WorkLocationOption,
} from '@/types/assignment';
import { computed, ref, watch } from 'vue';

interface Employee {
    id: number;
    department_id: number | null;
    position_id: number | null;
    work_location_id: number | null;
    supervisor_id: number | null;
}

const props = defineProps<{
    employee: Employee;
    departments: DepartmentOption[];
    positions: PositionOption[];
    workLocations: WorkLocationOption[];
    supervisorOptions: SupervisorOption[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref<
    AssignmentChangeFormData & { set_as_department_head: boolean }
>({
    assignment_type: '',
    new_value_id: '',
    effective_date: '',
    remarks: '',
    set_as_department_head: false,
});

const errors = ref<AssignmentFormErrors>({});
const isSubmitting = ref(false);
const recentlySuccessful = ref(false);

/**
 * Assignment type options for the dropdown.
 */
const assignmentTypeOptions = computed(() => {
    return Object.values(AssignmentType).map((type) => ({
        value: type,
        label: AssignmentTypeLabels[type],
    }));
});

/**
 * Options for the value dropdown based on selected assignment type.
 */
const valueOptions = computed(() => {
    switch (form.value.assignment_type) {
        case AssignmentType.Department:
            return props.departments.map((dept) => ({
                value: dept.id.toString(),
                label: dept.name,
            }));
        case AssignmentType.Position:
            return props.positions.map((pos) => ({
                value: pos.id.toString(),
                label: pos.title,
            }));
        case AssignmentType.Location:
            return props.workLocations.map((loc) => ({
                value: loc.id.toString(),
                label: loc.name,
            }));
        case AssignmentType.Supervisor:
            return props.supervisorOptions
                .filter((sup) => sup.id !== props.employee.id)
                .map((sup) => ({
                    value: sup.id.toString(),
                    label: `${sup.full_name} (${sup.employee_number})`,
                }));
        default:
            return [];
    }
});

/**
 * Placeholder text for the value dropdown.
 */
const valuePlaceholder = computed(() => {
    switch (form.value.assignment_type) {
        case AssignmentType.Department:
            return 'Select department';
        case AssignmentType.Position:
            return 'Select position';
        case AssignmentType.Location:
            return 'Select work location';
        case AssignmentType.Supervisor:
            return 'Select supervisor';
        default:
            return 'Select value';
    }
});

/**
 * Get the current value ID for the selected assignment type.
 */
const currentValueId = computed(() => {
    switch (form.value.assignment_type) {
        case AssignmentType.Department:
            return props.employee.department_id;
        case AssignmentType.Position:
            return props.employee.position_id;
        case AssignmentType.Location:
            return props.employee.work_location_id;
        case AssignmentType.Supervisor:
            return props.employee.supervisor_id;
        default:
            return null;
    }
});

/**
 * Get the current value name for display.
 */
const currentValueName = computed(() => {
    const currentId = currentValueId.value;
    if (!currentId || !form.value.assignment_type) return 'None';

    switch (form.value.assignment_type) {
        case AssignmentType.Department:
            return (
                props.departments.find((d) => d.id === currentId)?.name ||
                'None'
            );
        case AssignmentType.Position:
            return (
                props.positions.find((p) => p.id === currentId)?.title || 'None'
            );
        case AssignmentType.Location:
            return (
                props.workLocations.find((l) => l.id === currentId)?.name ||
                'None'
            );
        case AssignmentType.Supervisor:
            const sup = props.supervisorOptions.find((s) => s.id === currentId);
            return sup ? sup.full_name : 'None';
        default:
            return 'None';
    }
});

/**
 * Check if form can be submitted.
 */
const canSubmit = computed(() => {
    return (
        form.value.assignment_type &&
        form.value.new_value_id &&
        form.value.effective_date &&
        !isSubmitting.value
    );
});

/**
 * Check if assignment type is department.
 */
const isDepartmentAssignment = computed(() => {
    return form.value.assignment_type === AssignmentType.Department;
});

/**
 * Reset form to initial state.
 */
function resetForm() {
    form.value = {
        assignment_type: '',
        new_value_id: '',
        effective_date: '',
        remarks: '',
        set_as_department_head: false,
    };
    errors.value = {};
}

/**
 * Get CSRF token from cookies.
 */
function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Handle form submission.
 */
async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    try {
        // Use relative URL - works because we're on the tenant subdomain
        const url = `/api/employees/${props.employee.id}/assignments`;

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                assignment_type: form.value.assignment_type,
                new_value_id: parseInt(form.value.new_value_id.toString()),
                effective_date: form.value.effective_date,
                remarks: form.value.remarks || null,
                set_as_department_head: form.value.set_as_department_head,
            }),
        });

        if (response.status === 201 || response.ok) {
            recentlySuccessful.value = true;
            emit('success');

            setTimeout(() => {
                recentlySuccessful.value = false;
                open.value = false;
                resetForm();
            }, 1500);
        } else if (response.status === 422) {
            const data = await response.json();
            if (data.errors) {
                errors.value = Object.fromEntries(
                    Object.entries(data.errors as Record<string, string[]>).map(
                        ([key, messages]) => [key, messages[0]],
                    ),
                );
            }
        } else if (response.status === 403) {
            errors.value = {
                general:
                    'You do not have permission to manage employee assignments.',
            };
        } else {
            errors.value = {
                general: 'An unexpected error occurred. Please try again.',
            };
        }
    } catch {
        errors.value = {
            general: 'An unexpected error occurred. Please try again.',
        };
    } finally {
        isSubmitting.value = false;
    }
}

/**
 * Handle modal close.
 */
function handleClose() {
    if (!isSubmitting.value) {
        resetForm();
        emit('close');
    }
}

/**
 * Handle open state change.
 */
function handleOpenChange(isOpen: boolean) {
    open.value = isOpen;
    if (!isOpen) {
        handleClose();
    }
}

// Reset value selection and department head checkbox when assignment type changes
watch(
    () => form.value.assignment_type,
    () => {
        form.value.new_value_id = '';
        form.value.set_as_department_head = false;
    },
);

// Initialize form when modal opens
watch(open, (isOpen) => {
    if (isOpen) {
        // Set default effective date to today
        form.value.effective_date = new Date().toISOString().split('T')[0];
    } else {
        resetForm();
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <!-- Responsive modal width: full on mobile, max-w-lg on larger screens -->
        <DialogContent
            class="max-h-[90vh] w-[95vw] overflow-y-auto sm:max-w-lg"
            data-test="assignment-change-modal"
        >
            <form @submit.prevent="handleSubmit" class="space-y-4 sm:space-y-6">
                <DialogHeader class="space-y-2 sm:space-y-3">
                    <DialogTitle class="text-lg sm:text-xl"
                        >Change Assignment</DialogTitle
                    >
                    <DialogDescription class="text-sm">
                        Update the employee's position, department, work
                        location, or supervisor assignment.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3 sm:space-y-4">
                    <!-- General Error -->
                    <div
                        v-if="errors.general"
                        class="rounded-md bg-red-50 p-2.5 text-sm text-red-700 sm:p-3 dark:bg-red-900/30 dark:text-red-400"
                    >
                        {{ errors.general }}
                    </div>

                    <!-- Assignment Type Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="assignment-type" class="text-sm">
                            Assignment Type <span class="text-red-500">*</span>
                        </Label>
                        <EnumSelect
                            id="assignment-type"
                            v-model="form.assignment_type"
                            :options="assignmentTypeOptions"
                            placeholder="Select assignment type"
                            data-test="assignment-type-select"
                        />
                        <InputError :message="errors.assignment_type" />
                    </div>

                    <!-- Current Value Display (when type is selected) -->
                    <div
                        v-if="form.assignment_type"
                        class="rounded-md bg-slate-50 p-2.5 sm:p-3 dark:bg-slate-800"
                    >
                        <p
                            class="text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                        >
                            Current
                            {{
                                AssignmentTypeLabels[
                                    form.assignment_type as AssignmentType
                                ]
                            }}:
                        </p>
                        <p
                            class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100"
                        >
                            {{ currentValueName }}
                        </p>
                    </div>

                    <!-- New Value Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="new-value" class="text-sm">
                            New Value <span class="text-red-500">*</span>
                        </Label>
                        <EnumSelect
                            id="new-value"
                            v-model="form.new_value_id"
                            :options="valueOptions"
                            :placeholder="valuePlaceholder"
                            :disabled="!form.assignment_type"
                            data-test="new-value-select"
                        />
                        <InputError :message="errors.new_value_id" />
                    </div>

                    <!-- Set as Department Head Checkbox (only for department assignment) -->
                    <div
                        v-if="isDepartmentAssignment && form.new_value_id"
                        class="flex items-start space-x-3 rounded-md border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20"
                    >
                        <input
                            id="set-as-department-head"
                            type="checkbox"
                            v-model="form.set_as_department_head"
                            class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700"
                            data-test="set-department-head-checkbox"
                        />
                        <div class="space-y-1">
                            <label
                                for="set-as-department-head"
                                class="cursor-pointer text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                Set as Department Head
                            </label>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                This will also update the department's head and
                                reflect in the organization chart.
                            </p>
                        </div>
                    </div>

                    <!-- Effective Date Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="effective-date" class="text-sm">
                            Effective Date <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="effective-date"
                            type="date"
                            v-model="form.effective_date"
                            class="w-full"
                            :class="{ 'border-red-500': errors.effective_date }"
                            data-test="effective-date-input"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            The date when this assignment change takes effect.
                        </p>
                        <InputError :message="errors.effective_date" />
                    </div>

                    <!-- Remarks Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="remarks" class="text-sm">Remarks</Label>
                        <Textarea
                            id="remarks"
                            v-model="form.remarks"
                            placeholder="Optional: Reason for the assignment change..."
                            rows="2"
                            class="w-full resize-none sm:resize-y"
                            :class="{ 'border-red-500': errors.remarks }"
                            data-test="remarks-textarea"
                        />
                        <InputError :message="errors.remarks" />
                    </div>
                </div>

                <!-- Responsive footer: stacked on mobile, inline on larger screens -->
                <DialogFooter
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full sm:w-auto"
                        @click="handleOpenChange(false)"
                        :disabled="isSubmitting"
                        data-test="cancel-button"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        class="w-full sm:w-auto"
                        :disabled="!canSubmit"
                        data-test="submit-button"
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
                        {{ isSubmitting ? 'Saving...' : 'Save Assignment' }}
                    </Button>
                </DialogFooter>

                <!-- Success Message -->
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="recentlySuccessful"
                        class="text-center text-sm text-green-600 dark:text-green-400"
                    >
                        Assignment updated successfully!
                    </p>
                </Transition>
            </form>
        </DialogContent>
    </Dialog>
</template>
