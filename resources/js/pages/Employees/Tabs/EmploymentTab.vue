<script setup lang="ts">
import LabelValueList from '@/Components/LabelValueList.vue';
import { Button } from '@/components/ui/button';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Department {
    id: number;
    name: string;
    code: string;
}

interface Position {
    id: number;
    title: string;
    code: string;
}

interface WorkLocation {
    id: number;
    name: string;
    code: string;
    city: string;
}

interface Supervisor {
    id: number;
    full_name: string;
    employee_number: string;
}

interface Employee {
    employee_number: string;
    employment_type: string | null;
    employment_type_label: string | null;
    employment_status: string | null;
    employment_status_label: string | null;
    hire_date: string | null;
    regularization_date: string | null;
    years_of_service: number;
    basic_salary: string | null;
    pay_frequency: string | null;
    department: Department | null;
    position: Position | null;
    work_location: WorkLocation | null;
    supervisor: Supervisor | null;
}

const props = defineProps<{
    employee: Employee;
}>();

const emit = defineEmits<{
    (e: 'edit-assignments'): void;
}>();

const page = usePage();

/**
 * Check if current user can manage employees.
 */
const canManageEmployees = computed(
    () => page.props.tenant?.can_manage_employees ?? false,
);

function formatDate(dateStr: string | null): string | null {
    if (!dateStr) return null;
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatSalary(salary: string | null): string | null {
    if (!salary) return null;
    const num = parseFloat(salary);
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
}

function formatYearsOfService(years: number): string {
    if (years === 0) return 'Less than 1 year';
    if (years === 1) return '1 year';
    return `${years.toFixed(1)} years`;
}

/**
 * Current assignments section items.
 */
const assignmentItems = computed(() => [
    { label: 'Position', value: props.employee.position?.title },
    { label: 'Department', value: props.employee.department?.name },
    { label: 'Work Location', value: props.employee.work_location?.name },
    { label: 'Supervisor', value: props.employee.supervisor?.full_name },
]);

/**
 * Employment details section items.
 */
const employmentItems = computed(() => [
    { label: 'Employee Number', value: props.employee.employee_number },
    { label: 'Employment Status', value: props.employee.employment_type_label },
    { label: 'Date Hired', value: formatDate(props.employee.hire_date) },
    {
        label: 'Regularization Date',
        value: formatDate(props.employee.regularization_date),
    },
    {
        label: 'Years of Service',
        value: formatYearsOfService(props.employee.years_of_service),
    },
    { label: 'Basic Salary', value: formatSalary(props.employee.basic_salary) },
    { label: 'Pay Frequency', value: props.employee.pay_frequency },
]);

function handleEditAssignments() {
    emit('edit-assignments');
}
</script>

<template>
    <div class="space-y-6">
        <!-- Current Assignments Section -->
        <div>
            <div class="mb-3 flex items-center justify-between">
                <h3
                    class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    Current Assignments
                </h3>
                <Button
                    v-if="canManageEmployees"
                    variant="outline"
                    size="sm"
                    @click="handleEditAssignments"
                    data-test="edit-assignments-button"
                >
                    <svg
                        class="mr-1.5 h-3.5 w-3.5"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                        />
                    </svg>
                    Edit Assignments
                </Button>
            </div>
            <div
                class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
            >
                <LabelValueList :items="assignmentItems" />
            </div>
        </div>

        <!-- Employment Details Section -->
        <div>
            <h3
                class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100"
            >
                Employment Details
            </h3>
            <LabelValueList :items="employmentItems" />
        </div>
    </div>
</template>
