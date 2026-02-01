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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface UrgencyOption {
    value: string;
    label: string;
    color: string;
}

interface Department {
    id: number;
    name: string;
}

interface PositionOption {
    id: number;
    name: string;
}

interface EmploymentTypeOption {
    value: string;
    label: string;
}

interface JobRequisition {
    id: number;
    reference_number: string;
    position: { id: number; name: string };
    department: { id: number; name: string };
    requested_by: { id: number; full_name: string };
    headcount: number;
    employment_type: string;
    employment_type_label: string;
    urgency: string;
    urgency_label: string;
    urgency_color: string;
    status: string;
    status_label: string;
    status_color: string;
    submitted_at: string | null;
    created_at: string;
    can_be_edited: boolean;
    can_be_cancelled: boolean;
}

interface Filters {
    status: string | null;
    urgency: string | null;
    department_id: number | null;
}

const props = defineProps<{
    employee: Employee | null;
    requisitions: { data: JobRequisition[]; links: any; meta: any };
    departments: Department[];
    positions: PositionOption[];
    statuses: StatusOption[];
    urgencies: UrgencyOption[];
    employmentTypes: EmploymentTypeOption[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/requisitions' },
    { title: 'Job Requisitions', href: '/recruitment/requisitions' },
];

const selectedStatus = ref(props.filters.status || 'all');
const selectedUrgency = ref(props.filters.urgency || 'all');
const selectedDepartment = ref(props.filters.department_id ? String(props.filters.department_id) : 'all');

const isProcessing = ref(false);

function reloadPage() {
    const params: Record<string, string> = {};
    if (selectedStatus.value !== 'all') params.status = selectedStatus.value;
    if (selectedUrgency.value !== 'all') params.urgency = selectedUrgency.value;
    if (selectedDepartment.value !== 'all') params.department_id = selectedDepartment.value;
    router.get(window.location.pathname, params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function getStatusBadgeClasses(color: string): string {
    switch (color) {
        case 'green':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'red':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        case 'amber':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'blue':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'slate':
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function handleStatusChange(value: string) {
    selectedStatus.value = value;
    reloadPage();
}

function handleUrgencyChange(value: string) {
    selectedUrgency.value = value;
    reloadPage();
}

function handleDepartmentChange(value: string) {
    selectedDepartment.value = value;
    reloadPage();
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

// Confirmation dialog
const showConfirmDialog = ref(false);
const confirmDialogTitle = ref('');
const confirmDialogDescription = ref('');
const confirmDialogAction = ref<(() => void) | null>(null);
const confirmDialogDestructive = ref(false);

// Cancel dialog
const showCancelDialog = ref(false);
const cancellingRequisition = ref<JobRequisition | null>(null);
const cancelReason = ref('');

function handleSubmitRequisition(requisition: JobRequisition) {
    confirmDialogTitle.value = 'Submit Requisition';
    confirmDialogDescription.value = `Submit ${requisition.reference_number} for approval?`;
    confirmDialogDestructive.value = false;
    confirmDialogAction.value = () => executeSubmit(requisition);
    showConfirmDialog.value = true;
}

async function executeSubmit(requisition: JobRequisition) {
    showConfirmDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/job-requisitions/${requisition.id}/submit`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            reloadPage();
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isProcessing.value = false;
    }
}

function handleCancelRequisition(requisition: JobRequisition) {
    cancellingRequisition.value = requisition;
    cancelReason.value = '';
    showCancelDialog.value = true;
}

async function executeCancelRequisition() {
    if (!cancellingRequisition.value) return;
    showCancelDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/job-requisitions/${cancellingRequisition.value.id}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ reason: cancelReason.value || null }),
        });

        if (response.ok) {
            reloadPage();
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isProcessing.value = false;
        cancellingRequisition.value = null;
    }
}

function handleDeleteRequisition(requisition: JobRequisition) {
    confirmDialogTitle.value = 'Delete Draft';
    confirmDialogDescription.value = `Delete draft ${requisition.reference_number}? This cannot be undone.`;
    confirmDialogDestructive.value = true;
    confirmDialogAction.value = () => executeDelete(requisition);
    showConfirmDialog.value = true;
}

async function executeDelete(requisition: JobRequisition) {
    showConfirmDialog.value = false;
    isProcessing.value = true;
    try {
        const response = await fetch(`/api/job-requisitions/${requisition.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            reloadPage();
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isProcessing.value = false;
    }
}

// Create dialog
const showCreateDialog = ref(false);
const createForm = ref({
    position_id: '',
    department_id: '',
    headcount: 1,
    employment_type: '',
    urgency: '',
    justification: '',
    salary_range_min: '',
    salary_range_max: '',
    preferred_start_date: '',
    remarks: '',
});
const createErrors = ref<Record<string, string>>({});
const isCreating = ref(false);

function openCreateDialog() {
    createForm.value = {
        position_id: '',
        department_id: '',
        headcount: 1,
        employment_type: '',
        urgency: '',
        justification: '',
        salary_range_min: '',
        salary_range_max: '',
        preferred_start_date: '',
        remarks: '',
    };
    createErrors.value = {};
    showCreateDialog.value = true;
}

async function executeCreate() {
    if (!props.employee) {
        return;
    }
    isCreating.value = true;
    createErrors.value = {};

    const body: Record<string, any> = {
        position_id: Number(createForm.value.position_id),
        department_id: Number(createForm.value.department_id),
        requested_by_employee_id: props.employee.id,
        headcount: Number(createForm.value.headcount),
        employment_type: createForm.value.employment_type,
        urgency: createForm.value.urgency,
        justification: createForm.value.justification,
    };
    if (createForm.value.salary_range_min) body.salary_range_min = Number(createForm.value.salary_range_min);
    if (createForm.value.salary_range_max) body.salary_range_max = Number(createForm.value.salary_range_max);
    if (createForm.value.preferred_start_date) body.preferred_start_date = createForm.value.preferred_start_date;
    if (createForm.value.remarks) body.remarks = createForm.value.remarks;

    try {
        const response = await fetch('/api/job-requisitions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        if (response.ok) {
            showCreateDialog.value = false;
            reloadPage();
        } else if (response.status === 422) {
            const data = await response.json();
            const errs: Record<string, string> = {};
            for (const [key, messages] of Object.entries(data.errors || {})) {
                errs[key] = (messages as string[])[0];
            }
            createErrors.value = errs;
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isCreating.value = false;
    }
}
</script>

<template>
    <Head :title="`Job Requisitions - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Job Requisitions
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage hiring requests and track requisition approvals.
                    </p>
                </div>
                <Button v-if="employee" @click="openCreateDialog">New Requisition</Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <Select :model-value="selectedStatus" @update:model-value="handleStatusChange">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="selectedUrgency" @update:model-value="handleUrgencyChange">
                    <SelectTrigger class="w-36">
                        <SelectValue placeholder="Urgency" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Urgency</SelectItem>
                        <SelectItem
                            v-for="u in urgencies"
                            :key="u.value"
                            :value="u.value"
                        >
                            {{ u.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="selectedDepartment" @update:model-value="handleDepartmentChange">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Department" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Departments</SelectItem>
                        <SelectItem
                            v-for="dept in departments"
                            :key="dept.id"
                            :value="String(dept.id)"
                        >
                            {{ dept.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div v-if="requisitions.data.length > 0" class="hidden md:block">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Reference</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Position</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Department</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Headcount</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Urgency</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="req in requisitions.data"
                                :key="req.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="whitespace-nowrap px-6 py-4">
                                    <Link
                                        :href="`/recruitment/requisitions/${req.id}`"
                                        class="font-medium text-slate-900 hover:underline dark:text-slate-100"
                                    >
                                        {{ req.reference_number }}
                                    </Link>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ req.position.name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ req.department.name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ req.headcount }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(req.urgency_color)"
                                    >
                                        {{ req.urgency_label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(req.status_color)"
                                    >
                                        {{ req.status_label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                                <span class="sr-only">Open menu</span>
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                                </svg>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem as-child>
                                                <Link :href="`/recruitment/requisitions/${req.id}`">View Details</Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="req.status === 'draft'"
                                                @click="handleSubmitRequisition(req)"
                                            >
                                                Submit for Approval
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="req.can_be_cancelled"
                                                class="text-amber-600 focus:text-amber-600"
                                                @click="handleCancelRequisition(req)"
                                            >
                                                Cancel
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="req.status === 'draft'"
                                                class="text-red-600 focus:text-red-600"
                                                @click="handleDeleteRequisition(req)"
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

                <!-- Mobile View -->
                <div v-if="requisitions.data.length > 0" class="divide-y divide-slate-200 md:hidden dark:divide-slate-700">
                    <div
                        v-for="req in requisitions.data"
                        :key="req.id"
                        class="space-y-2 p-4"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <Link
                                    :href="`/recruitment/requisitions/${req.id}`"
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ req.reference_number }}
                                </Link>
                                <div class="text-sm text-slate-500">{{ req.position.name }} - {{ req.department.name }}</div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClasses(req.status_color)"
                            >
                                {{ req.status_label }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">{{ req.employment_type_label }}</span>
                            <span class="font-medium">{{ req.headcount }} position(s)</span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="requisitions.data.length === 0" class="px-6 py-12 text-center">
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
                            d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0"
                        />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No job requisitions found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Create a new job requisition to start the hiring process.
                    </p>
                </div>
            </div>
        </div>

        <!-- Confirmation Dialog -->
        <Dialog v-model:open="showConfirmDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ confirmDialogTitle }}</DialogTitle>
                    <DialogDescription>{{ confirmDialogDescription }}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showConfirmDialog = false">Cancel</Button>
                    <Button
                        :class="confirmDialogDestructive ? 'bg-red-600 hover:bg-red-700' : ''"
                        @click="confirmDialogAction?.()"
                    >
                        Confirm
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Create Requisition Dialog -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>New Job Requisition</DialogTitle>
                    <DialogDescription>
                        Create a new requisition as a draft. You can submit it for approval later.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <!-- Position & Department -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="create-position">Position <span class="text-red-500">*</span></Label>
                            <Select v-model="createForm.position_id">
                                <SelectTrigger id="create-position">
                                    <SelectValue placeholder="Select position" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="pos in positions"
                                        :key="pos.id"
                                        :value="String(pos.id)"
                                    >
                                        {{ pos.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="createErrors.position_id" class="text-sm text-red-500">{{ createErrors.position_id }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="create-department">Department <span class="text-red-500">*</span></Label>
                            <Select v-model="createForm.department_id">
                                <SelectTrigger id="create-department">
                                    <SelectValue placeholder="Select department" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="dept in departments"
                                        :key="dept.id"
                                        :value="String(dept.id)"
                                    >
                                        {{ dept.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="createErrors.department_id" class="text-sm text-red-500">{{ createErrors.department_id }}</p>
                        </div>
                    </div>

                    <!-- Headcount & Employment Type -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="create-headcount">Headcount <span class="text-red-500">*</span></Label>
                            <Input
                                id="create-headcount"
                                v-model.number="createForm.headcount"
                                type="number"
                                min="1"
                                max="100"
                            />
                            <p v-if="createErrors.headcount" class="text-sm text-red-500">{{ createErrors.headcount }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="create-employment-type">Employment Type <span class="text-red-500">*</span></Label>
                            <Select v-model="createForm.employment_type">
                                <SelectTrigger id="create-employment-type">
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="et in employmentTypes"
                                        :key="et.value"
                                        :value="et.value"
                                    >
                                        {{ et.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="createErrors.employment_type" class="text-sm text-red-500">{{ createErrors.employment_type }}</p>
                        </div>
                    </div>

                    <!-- Urgency & Preferred Start Date -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="create-urgency">Urgency <span class="text-red-500">*</span></Label>
                            <Select v-model="createForm.urgency">
                                <SelectTrigger id="create-urgency">
                                    <SelectValue placeholder="Select urgency" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="u in urgencies"
                                        :key="u.value"
                                        :value="u.value"
                                    >
                                        {{ u.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="createErrors.urgency" class="text-sm text-red-500">{{ createErrors.urgency }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="create-start-date">Preferred Start Date</Label>
                            <Input
                                id="create-start-date"
                                v-model="createForm.preferred_start_date"
                                type="date"
                            />
                            <p v-if="createErrors.preferred_start_date" class="text-sm text-red-500">{{ createErrors.preferred_start_date }}</p>
                        </div>
                    </div>

                    <!-- Salary Range -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="create-salary-min">Salary Range Min</Label>
                            <Input
                                id="create-salary-min"
                                v-model="createForm.salary_range_min"
                                type="number"
                                min="0"
                                placeholder="0.00"
                            />
                            <p v-if="createErrors.salary_range_min" class="text-sm text-red-500">{{ createErrors.salary_range_min }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="create-salary-max">Salary Range Max</Label>
                            <Input
                                id="create-salary-max"
                                v-model="createForm.salary_range_max"
                                type="number"
                                min="0"
                                placeholder="0.00"
                            />
                            <p v-if="createErrors.salary_range_max" class="text-sm text-red-500">{{ createErrors.salary_range_max }}</p>
                        </div>
                    </div>

                    <!-- Justification -->
                    <div class="space-y-2">
                        <Label for="create-justification">Justification <span class="text-red-500">*</span></Label>
                        <Textarea
                            id="create-justification"
                            v-model="createForm.justification"
                            placeholder="Explain why this position is needed..."
                            rows="3"
                        />
                        <p v-if="createErrors.justification" class="text-sm text-red-500">{{ createErrors.justification }}</p>
                    </div>

                    <!-- Remarks -->
                    <div class="space-y-2">
                        <Label for="create-remarks">Remarks</Label>
                        <Textarea
                            id="create-remarks"
                            v-model="createForm.remarks"
                            placeholder="Additional notes (optional)"
                            rows="2"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showCreateDialog = false">Cancel</Button>
                    <Button @click="executeCreate" :disabled="isCreating">
                        {{ isCreating ? 'Creating...' : 'Create Draft' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Cancel Requisition Dialog -->
        <Dialog v-model:open="showCancelDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel Requisition</DialogTitle>
                    <DialogDescription>
                        Cancel {{ cancellingRequisition?.reference_number }}? You may provide a reason.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Textarea
                        v-model="cancelReason"
                        placeholder="Reason for cancellation (optional)"
                        rows="3"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showCancelDialog = false">Keep Requisition</Button>
                    <Button class="bg-amber-600 hover:bg-amber-700" @click="executeCancelRequisition">
                        Cancel Requisition
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
