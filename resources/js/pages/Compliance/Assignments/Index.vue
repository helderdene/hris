<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
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
import { Progress } from '@/components/ui/progress';
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
import axios from 'axios';
import { computed, reactive, ref } from 'vue';

interface Employee {
    id: number;
    employee_number: string;
    full_name: string;
    department: string | null;
    position: string | null;
}

interface Course {
    id: number;
    course: {
        id: number;
        title: string;
        code: string;
    };
}

interface Assignment {
    id: number;
    compliance_course_id: number;
    compliance_course: Course;
    employee_id: number;
    employee: Employee;
    status: string;
    status_label: string;
    status_color: string;
    assigned_date: string;
    due_date: string | null;
    completed_at: string | null;
    final_score: number | null;
    completion_percentage: number;
    days_until_due: number | null;
    is_overdue: boolean;
    is_due_soon: boolean;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface Department {
    id: number;
    name: string;
}

interface EmployeeOption {
    id: number;
    employee_number: string;
    full_name: string;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Filters {
    status: string | null;
    compliance_course_id: string | null;
    department_id: string | null;
    is_overdue: string | null;
}

const props = defineProps<{
    assignments: Assignment[];
    pagination: Pagination;
    courses: Course[];
    employees: EmployeeOption[];
    filters: Filters;
    statusOptions: StatusOption[];
    departments: Department[];
}>();

const { tenantName, primaryColor } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compliance', href: '/compliance' },
    { title: 'Assignments', href: '/compliance/assignments' },
];

const statusFilter = ref(props.filters.status ?? 'all');
const courseFilter = ref(props.filters.compliance_course_id ?? 'all');
const departmentFilter = ref(props.filters.department_id ?? 'all');
const overdueFilter = ref(props.filters.is_overdue ?? 'all');

const assignmentsData = computed(() => props.assignments ?? []);
const paginationData = computed(() => props.pagination ?? { current_page: 1, last_page: 1, per_page: 25, total: 0 });
const coursesData = computed(() => props.courses ?? []);
const employeesData = computed(() => props.employees ?? []);
const departmentsData = computed(() => props.departments ?? []);
const statusOptionsData = computed(() => props.statusOptions ?? []);

function applyFilters() {
    router.get('/compliance/assignments', {
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        compliance_course_id: courseFilter.value !== 'all' ? courseFilter.value : undefined,
        department_id: departmentFilter.value !== 'all' ? departmentFilter.value : undefined,
        is_overdue: overdueFilter.value === '1' ? '1' : undefined,
    }, { preserveState: true });
}

function goToPage(page: number) {
    router.get('/compliance/assignments', {
        page,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        compliance_course_id: courseFilter.value !== 'all' ? courseFilter.value : undefined,
        department_id: departmentFilter.value !== 'all' ? departmentFilter.value : undefined,
        is_overdue: overdueFilter.value === '1' ? '1' : undefined,
    }, { preserveState: true });
}

function getStatusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'overdue':
        case 'expired':
            return 'destructive';
        case 'in_progress':
            return 'secondary';
        default:
            return 'outline';
    }
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString();
}

// Assign Training Dialog
const showAssignDialog = ref(false);
const assignForm = reactive({
    compliance_course_id: '',
    employee_id: '',
    days_to_complete: '',
    errors: {} as Record<string, string>,
    processing: false,
});

function resetAssignForm() {
    assignForm.compliance_course_id = '';
    assignForm.employee_id = '';
    assignForm.days_to_complete = '';
    assignForm.errors = {};
    assignForm.processing = false;
}

function openAssignDialog() {
    resetAssignForm();
    showAssignDialog.value = true;
}

async function submitAssignment() {
    assignForm.processing = true;
    assignForm.errors = {};

    try {
        const payload = {
            compliance_course_id: assignForm.compliance_course_id ? parseInt(assignForm.compliance_course_id) : null,
            employee_id: assignForm.employee_id ? parseInt(assignForm.employee_id) : null,
            days_to_complete: assignForm.days_to_complete ? parseInt(assignForm.days_to_complete) : null,
        };

        await axios.post('/api/compliance/assignments', payload);
        showAssignDialog.value = false;
        router.reload({ only: ['assignments', 'pagination'] });
    } catch (error: any) {
        console.error('Assignment creation error:', error.response?.data);
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors || {};
            for (const [key, messages] of Object.entries(validationErrors)) {
                assignForm.errors[key] = Array.isArray(messages) ? messages[0] : (messages as string);
            }
        } else {
            assignForm.errors.general = error.response?.data?.message || 'An error occurred';
        }
    } finally {
        assignForm.processing = false;
    }
}
</script>

<template>
    <Head :title="`Compliance Assignments - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Training Assignments
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        View and manage employee compliance training assignments.
                    </p>
                </div>
                <Button :style="{ backgroundColor: primaryColor }" @click="openAssignDialog">
                    Assign Training
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <Select :model-value="statusFilter" @update:model-value="(v) => { statusFilter = v; applyFilters(); }">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Status</SelectItem>
                        <SelectItem
                            v-for="option in statusOptionsData"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="courseFilter" @update:model-value="(v) => { courseFilter = v; applyFilters(); }">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Course" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Courses</SelectItem>
                        <SelectItem
                            v-for="course in coursesData"
                            :key="course.id"
                            :value="String(course.id)"
                        >
                            {{ course.course?.title ?? 'Untitled' }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="departmentFilter" @update:model-value="(v) => { departmentFilter = v; applyFilters(); }">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Department" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Departments</SelectItem>
                        <SelectItem
                            v-for="dept in departmentsData"
                            :key="dept.id"
                            :value="String(dept.id)"
                        >
                            {{ dept.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="overdueFilter" @update:model-value="(v) => { overdueFilter = v; applyFilters(); }">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="Overdue" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All</SelectItem>
                        <SelectItem value="1">Overdue Only</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div v-if="assignmentsData.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Employee
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Course
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Progress
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Due Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Score
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="assignment in assignmentsData"
                                :key="assignment.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ assignment.employee?.full_name ?? 'Unknown' }}
                                    </div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ assignment.employee?.employee_number }} -
                                        {{ assignment.employee?.department ?? 'No Dept' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-slate-100">
                                        {{ assignment.compliance_course?.course?.title ?? 'Unknown' }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ assignment.compliance_course?.course?.code }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Badge :variant="getStatusBadgeVariant(assignment.status)">
                                        {{ assignment.status_label }}
                                    </Badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <Progress :model-value="assignment.completion_percentage" class="h-2 w-24" />
                                        <span class="text-sm text-slate-500 dark:text-slate-400">
                                            {{ assignment.completion_percentage }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm" :class="{
                                        'text-red-600 dark:text-red-400 font-medium': assignment.is_overdue,
                                        'text-amber-600 dark:text-amber-400': assignment.is_due_soon && !assignment.is_overdue,
                                        'text-slate-900 dark:text-slate-100': !assignment.is_overdue && !assignment.is_due_soon,
                                    }">
                                        {{ formatDate(assignment.due_date) }}
                                    </div>
                                    <div v-if="assignment.days_until_due !== null" class="text-xs text-slate-500 dark:text-slate-400">
                                        <template v-if="assignment.days_until_due < 0">
                                            {{ Math.abs(assignment.days_until_due) }} days overdue
                                        </template>
                                        <template v-else-if="assignment.days_until_due === 0">
                                            Due today
                                        </template>
                                        <template v-else>
                                            {{ assignment.days_until_due }} days left
                                        </template>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">
                                    <template v-if="assignment.final_score !== null">
                                        {{ assignment.final_score }}%
                                    </template>
                                    <template v-else>
                                        -
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="flex flex-col items-center justify-center py-12"
                >
                    <svg
                        class="h-12 w-12 text-slate-400 dark:text-slate-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"
                        />
                    </svg>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        No assignments found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Try adjusting your filters or create new assignments.
                    </p>
                </div>

                <!-- Pagination -->
                <div
                    v-if="paginationData.last_page > 1"
                    class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-6 py-3 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Showing {{ (paginationData.current_page - 1) * paginationData.per_page + 1 }} to
                        {{ Math.min(paginationData.current_page * paginationData.per_page, paginationData.total) }} of
                        {{ paginationData.total }} results
                    </div>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="paginationData.current_page === 1"
                            @click="goToPage(paginationData.current_page - 1)"
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="paginationData.current_page === paginationData.last_page"
                            @click="goToPage(paginationData.current_page + 1)"
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign Training Dialog -->
        <Dialog v-model:open="showAssignDialog">
            <DialogContent class="sm:max-w-[450px]">
                <DialogHeader>
                    <DialogTitle>Assign Training</DialogTitle>
                    <DialogDescription>
                        Manually assign compliance training to an employee.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitAssignment" class="flex flex-col gap-4">
                    <!-- General error display -->
                    <div v-if="Object.keys(assignForm.errors).length > 0" class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                        <ul class="list-inside list-disc">
                            <li v-for="(error, key) in assignForm.errors" :key="key">{{ error }}</li>
                        </ul>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="assign_course">Compliance Course *</Label>
                        <Select v-model="assignForm.compliance_course_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select course" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="course in coursesData"
                                    :key="course.id"
                                    :value="String(course.id)"
                                >
                                    {{ course.course?.title ?? 'Untitled' }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="assign_employee">Employee *</Label>
                        <Select v-model="assignForm.employee_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select employee" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="emp in employeesData"
                                    :key="emp.id"
                                    :value="String(emp.id)"
                                >
                                    {{ emp.full_name }} ({{ emp.employee_number }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="assign_days">Days to Complete (optional)</Label>
                        <Input
                            id="assign_days"
                            v-model="assignForm.days_to_complete"
                            type="number"
                            min="1"
                            max="365"
                            placeholder="Use course default"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Leave blank to use the course's default deadline.
                        </p>
                    </div>

                    <DialogFooter class="gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showAssignDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="assignForm.processing"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            {{ assignForm.processing ? 'Assigning...' : 'Assign Training' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
