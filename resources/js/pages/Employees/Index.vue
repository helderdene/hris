<script setup lang="ts">
import EmployeeAvatar from '@/Components/EmployeeAvatar.vue';
import EmployeeStatusBadge from '@/Components/EmployeeStatusBadge.vue';
import EnumSelect from '@/Components/EnumSelect.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Position {
    id: number;
    title: string;
}

interface Department {
    id: number;
    name: string;
}

interface WorkLocation {
    id: number;
    name: string;
}

interface Employee {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
    initials: string;
    profile_photo_url: string | null;
    email: string;
    employment_type: string | null;
    employment_type_label: string | null;
    employment_status: string | null;
    employment_status_label: string | null;
    position: Position | null;
    department: Department | null;
    work_location: WorkLocation | null;
}

interface EnumOption {
    value: string;
    label: string;
}

interface Filters {
    search: string | null;
    department_id: string | null;
    employment_status: string | null;
}

const props = defineProps<{
    employees: Employee[];
    departments: Department[];
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Employees', href: '/employees' },
];

const searchQuery = ref(props.filters?.search || '');
const departmentFilter = ref(props.filters?.department_id || '');
const statusFilter = ref(props.filters?.employment_status || '');
const showFilters = ref(false);

const statusOptions: EnumOption[] = [
    { value: '', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'resigned', label: 'Resigned' },
    { value: 'terminated', label: 'Terminated' },
    { value: 'retired', label: 'Retired' },
    { value: 'end_of_contract', label: 'End of Contract' },
];

const departmentOptions = computed<EnumOption[]>(() => {
    return [
        { value: '', label: 'All Departments' },
        ...(props.departments || []).map((dept) => ({
            value: dept.id.toString(),
            label: dept.name,
        })),
    ];
});

const employeeCount = computed(() => props.employees?.length || 0);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

function handleSearch() {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
}

function applyFilters() {
    router.get(
        '/employees',
        {
            search: searchQuery.value || undefined,
            department_id: departmentFilter.value || undefined,
            employment_status: statusFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function clearFilters() {
    searchQuery.value = '';
    departmentFilter.value = '';
    statusFilter.value = '';
    router.get(
        '/employees',
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

watch([departmentFilter, statusFilter], () => {
    applyFilters();
});

function viewEmployee(employee: Employee) {
    router.visit(`/employees/${employee.id}`);
}

function editEmployee(employee: Employee) {
    router.visit(`/employees/${employee.id}/edit`);
}

function handleExport() {
    // Placeholder - Export functionality out of scope
    console.log('Export functionality coming soon');
}

function handleAddEmployee() {
    router.visit('/employees/create');
}
</script>

<template>
    <Head :title="`Employees - ${tenantName}`" />

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
                        Employees
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ employeeCount }} of {{ employeeCount }} employees
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Button
                        variant="outline"
                        @click="handleExport"
                        data-test="export-button"
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
                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"
                            />
                        </svg>
                        Export
                    </Button>
                    <Button
                        as-child
                        :style="{ backgroundColor: primaryColor }"
                        data-test="add-employee-button"
                    >
                        <Link href="/employees/create">
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
                            Add Employee
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <svg
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
                        />
                    </svg>
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search by name, employee number, or position..."
                        class="pl-9"
                        @input="handleSearch"
                        data-test="search-input"
                    />
                </div>
                <Button
                    variant="outline"
                    @click="showFilters = !showFilters"
                    data-test="filters-button"
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
                            d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"
                        />
                    </svg>
                    Filters
                </Button>
            </div>

            <!-- Filter Panel -->
            <div
                v-if="showFilters"
                class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
            >
                <div class="w-full sm:w-48">
                    <label
                        class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400"
                        >Department</label
                    >
                    <EnumSelect
                        v-model="departmentFilter"
                        :options="departmentOptions"
                        placeholder="All Departments"
                    />
                </div>
                <div class="w-full sm:w-40">
                    <label
                        class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400"
                        >Status</label
                    >
                    <EnumSelect
                        v-model="statusFilter"
                        :options="statusOptions"
                        placeholder="All Statuses"
                    />
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="clearFilters"
                    class="text-slate-600 dark:text-slate-400"
                >
                    Clear filters
                </Button>
            </div>

            <!-- Employees Table -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Desktop Table -->
                <div class="hidden md:block">
                    <table
                        class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                    >
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Employee
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Position
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Department
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Location
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Status
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <tr
                                v-for="employee in employees"
                                :key="employee.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                :data-test="`employee-row-${employee.id}`"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <EmployeeAvatar
                                            :first-name="employee.first_name"
                                            :last-name="employee.last_name"
                                            :photo-url="employee.profile_photo_url"
                                            size="md"
                                        />
                                        <div>
                                            <div
                                                class="font-medium text-slate-900 dark:text-slate-100"
                                            >
                                                {{ employee.full_name }}
                                            </div>
                                            <div
                                                class="text-sm text-blue-600 dark:text-blue-400"
                                            >
                                                {{ employee.employee_number }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300"
                                >
                                    {{ employee.position?.title || '-' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300"
                                >
                                    {{ employee.department?.name || '-' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300"
                                >
                                    {{ employee.work_location?.name || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <EmployeeStatusBadge
                                        :employment-type="
                                            employee.employment_type
                                        "
                                    />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <button
                                            @click="viewEmployee(employee)"
                                            class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                                            title="View"
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
                                                    d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"
                                                />
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                                                />
                                            </svg>
                                        </button>
                                        <button
                                            @click="editEmployee(employee)"
                                            class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                                            title="Edit"
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
                                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                                />
                                            </svg>
                                        </button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <button
                                                    class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                                                    title="More options"
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
                                                            d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"
                                                        />
                                                    </svg>
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuLabel
                                                    >Actions</DropdownMenuLabel
                                                >
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    @click="
                                                        viewEmployee(employee)
                                                    "
                                                >
                                                    View Profile
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="
                                                        editEmployee(employee)
                                                    "
                                                >
                                                    Edit Employee
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List -->
                <div
                    class="divide-y divide-slate-200 md:hidden dark:divide-slate-700"
                >
                    <div
                        v-for="employee in employees"
                        :key="employee.id"
                        class="p-4"
                        :data-test="`employee-card-${employee.id}`"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <EmployeeAvatar
                                    :first-name="employee.first_name"
                                    :last-name="employee.last_name"
                                    :photo-url="employee.profile_photo_url"
                                    size="md"
                                />
                                <div>
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ employee.full_name }}
                                    </div>
                                    <div
                                        class="text-sm text-blue-600 dark:text-blue-400"
                                    >
                                        {{ employee.employee_number }}
                                    </div>
                                </div>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 w-8 p-0"
                                    >
                                        <span class="sr-only">Open menu</span>
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
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        @click="viewEmployee(employee)"
                                    >
                                        View Profile
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="editEmployee(employee)"
                                    >
                                        Edit Employee
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <div
                            class="mt-3 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            <span v-if="employee.position">{{
                                employee.position.title
                            }}</span>
                            <span
                                v-if="employee.position && employee.department"
                                >-</span
                            >
                            <span v-if="employee.department">{{
                                employee.department.name
                            }}</span>
                        </div>
                        <div
                            v-if="employee.work_location"
                            class="mt-1 text-xs text-slate-400 dark:text-slate-500"
                        >
                            {{ employee.work_location.name }}
                        </div>
                        <div class="mt-2">
                            <EmployeeStatusBadge
                                :employment-type="employee.employment_type"
                            />
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="!employees || employees.length === 0"
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
                            d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No employees found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{
                            searchQuery || departmentFilter || statusFilter
                                ? 'Try adjusting your filters.'
                                : 'Get started by adding your first employee.'
                        }}
                    </p>
                    <div
                        v-if="
                            !searchQuery && !departmentFilter && !statusFilter
                        "
                        class="mt-6"
                    >
                        <Button
                            as-child
                            :style="{ backgroundColor: primaryColor }"
                        >
                            <Link href="/employees/create">
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
                                Add Employee
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
