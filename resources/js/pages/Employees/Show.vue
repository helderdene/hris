<script setup lang="ts">
import AssignmentChangeModal from '@/Components/AssignmentChangeModal.vue';
import AssignmentHistorySection from '@/Components/AssignmentHistorySection.vue';
import EmployeeAvatar from '@/Components/EmployeeAvatar.vue';
import EmployeeStatusBadge from '@/Components/EmployeeStatusBadge.vue';
import EmployeeSyncButton from '@/Components/EmployeeSyncButton.vue';
import SyncStatusBadge from '@/Components/SyncStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import {
    type DepartmentOption,
    type EmployeeAssignmentHistory,
    type PositionOption,
    type SupervisorOption,
    type WorkLocationOption,
} from '@/types/assignment';
import { type EmployeeDeviceSync, type SyncStatus } from '@/types/sync';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CompensationTab from './Tabs/CompensationTab.vue';
import ContactTab from './Tabs/ContactTab.vue';
import DocumentsTab from './Tabs/DocumentsTab.vue';
import EmploymentTab from './Tabs/EmploymentTab.vue';
import GovernmentIdsTab from './Tabs/GovernmentIdsTab.vue';
import PersonalInfoTab from './Tabs/PersonalInfoTab.vue';

interface Position {
    id: number;
    title: string;
    code: string;
}

interface Department {
    id: number;
    name: string;
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

interface Address {
    street?: string;
    barangay?: string;
    city?: string;
    province?: string;
    postal_code?: string;
}

interface EmergencyContact {
    name?: string;
    relationship?: string;
    phone?: string;
}

interface Employee {
    id: number;
    user_id: number | null;
    employee_number: string;
    profile_photo_url: string | null;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    suffix: string | null;
    full_name: string;
    initials: string;
    email: string;
    phone: string | null;
    date_of_birth: string | null;
    age: number | null;
    gender: string | null;
    civil_status: string | null;
    nationality: string | null;
    fathers_name: string | null;
    mothers_name: string | null;
    tin: string | null;
    sss_number: string | null;
    philhealth_number: string | null;
    pagibig_number: string | null;
    umid: string | null;
    passport_number: string | null;
    drivers_license: string | null;
    nbi_clearance: string | null;
    police_clearance: string | null;
    prc_license: string | null;
    employment_type: string | null;
    employment_type_label: string | null;
    employment_status: string | null;
    employment_status_label: string | null;
    hire_date: string | null;
    regularization_date: string | null;
    termination_date: string | null;
    years_of_service: number;
    basic_salary: string | null;
    pay_frequency: string | null;
    department_id: number | null;
    department: Department | null;
    position_id: number | null;
    position: Position | null;
    work_location_id: number | null;
    work_location: WorkLocation | null;
    supervisor_id: number | null;
    supervisor: Supervisor | null;
    address: Address | null;
    emergency_contact: EmergencyContact | null;
}

const props = defineProps<{
    employee: Employee;
    departments?: DepartmentOption[];
    positions?: PositionOption[];
    workLocations?: WorkLocationOption[];
    supervisorOptions?: SupervisorOption[];
    assignmentHistory?: EmployeeAssignmentHistory[];
    syncStatuses?: EmployeeDeviceSync[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Employees', href: '/employees' },
    {
        title: props.employee.full_name,
        href: `/employees/${props.employee.id}`,
    },
];

type TabId =
    | 'personal'
    | 'employment'
    | 'compensation'
    | 'government-ids'
    | 'contact'
    | 'documents'
    | 'assignment-history';

const activeTab = ref<TabId>('personal');

const tabs = [
    { id: 'personal' as TabId, label: 'Personal Info', icon: 'user' },
    { id: 'employment' as TabId, label: 'Employment', icon: 'briefcase' },
    { id: 'compensation' as TabId, label: 'Compensation', icon: 'currency' },
    { id: 'government-ids' as TabId, label: 'Government IDs', icon: 'card' },
    { id: 'contact' as TabId, label: 'Contact', icon: 'location' },
    { id: 'documents' as TabId, label: 'Documents', icon: 'file' },
    {
        id: 'assignment-history' as TabId,
        label: 'Assignment History',
        icon: 'history',
    },
];

// Modal state
const isAssignmentModalOpen = ref(false);

function handleSeparate() {
    // Placeholder - Separate (offboarding) functionality out of scope
    console.log('Separate functionality coming soon');
}

function goBack() {
    router.visit('/employees');
}

function openAssignmentModal() {
    isAssignmentModalOpen.value = true;
}

function closeAssignmentModal() {
    isAssignmentModalOpen.value = false;
}

function handleAssignmentSuccess() {
    // Reload the page to get fresh data after assignment change
    router.reload({ only: ['employee', 'assignmentHistory'] });
}

function handleSyncComplete() {
    // Reload sync statuses after a sync operation
    router.reload({ only: ['syncStatuses'] });
}
</script>

<template>
    <Head :title="`${employee.full_name} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Back Navigation -->
            <div class="flex items-center justify-between">
                <button
                    @click="goBack"
                    class="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
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
                            d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"
                        />
                    </svg>
                    Back to Employees
                </button>
                <div class="flex items-center gap-3">
                    <EmployeeSyncButton
                        v-if="syncStatuses && syncStatuses.length > 0"
                        :employee-id="employee.id"
                        :sync-statuses="syncStatuses"
                        @sync-complete="handleSyncComplete"
                    />
                    <Button
                        variant="outline"
                        @click="handleSeparate"
                        class="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
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
                                d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z"
                            />
                        </svg>
                        Separate
                    </Button>
                    <Button as-child :style="{ backgroundColor: primaryColor }">
                        <Link :href="`/employees/${employee.id}/edit`">
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
                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                />
                            </svg>
                            Edit
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Employee Profile Card -->
            <div
                class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Profile Header -->
                <div
                    class="border-b border-slate-200 p-6 dark:border-slate-700"
                >
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                        <EmployeeAvatar
                            :first-name="employee.first_name"
                            :last-name="employee.last_name"
                            :photo-url="employee.profile_photo_url"
                            size="lg"
                        />
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-3">
                                <h1
                                    class="text-xl font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    {{ employee.full_name }}
                                </h1>
                                <EmployeeStatusBadge
                                    :employment-type="employee.employment_type"
                                />
                            </div>
                            <p
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ employee.position?.title || '-' }} -
                                {{ employee.department?.name || '-' }}
                            </p>
                            <div
                                class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-slate-500 dark:text-slate-400"
                            >
                                <span class="flex items-center gap-1.5">
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
                                            d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z"
                                        />
                                    </svg>
                                    {{ employee.employee_number }}
                                </span>
                                <span
                                    v-if="employee.email"
                                    class="flex items-center gap-1.5"
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
                                            d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"
                                        />
                                    </svg>
                                    {{ employee.email }}
                                </span>
                                <span
                                    v-if="employee.phone"
                                    class="flex items-center gap-1.5"
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
                                            d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"
                                        />
                                    </svg>
                                    {{ employee.phone }}
                                </span>
                                <span
                                    v-if="employee.work_location"
                                    class="flex items-center gap-1.5"
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
                                            d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"
                                        />
                                    </svg>
                                    {{ employee.work_location.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="border-b border-slate-200 dark:border-slate-700">
                    <nav
                        class="-mb-px flex overflow-x-auto px-6"
                        aria-label="Tabs"
                    >
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                'flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors',
                                activeTab === tab.id
                                    ? 'border-blue-500 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                            ]"
                        >
                            <!-- User icon -->
                            <svg
                                v-if="tab.icon === 'user'"
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
                                    d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                                />
                            </svg>
                            <!-- Briefcase icon -->
                            <svg
                                v-if="tab.icon === 'briefcase'"
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
                                    d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z"
                                />
                            </svg>
                            <!-- Currency icon -->
                            <svg
                                v-if="tab.icon === 'currency'"
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
                                    d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"
                                />
                            </svg>
                            <!-- Card icon -->
                            <svg
                                v-if="tab.icon === 'card'"
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
                                    d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z"
                                />
                            </svg>
                            <!-- Location icon -->
                            <svg
                                v-if="tab.icon === 'location'"
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
                                    d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"
                                />
                            </svg>
                            <!-- File icon -->
                            <svg
                                v-if="tab.icon === 'file'"
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
                                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                                />
                            </svg>
                            <!-- History icon -->
                            <svg
                                v-if="tab.icon === 'history'"
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
                                    d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                />
                            </svg>
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-6">
                    <PersonalInfoTab
                        v-if="activeTab === 'personal'"
                        :employee="employee"
                    />
                    <EmploymentTab
                        v-if="activeTab === 'employment'"
                        :employee="employee"
                        @edit-assignments="openAssignmentModal"
                    />
                    <CompensationTab
                        v-if="activeTab === 'compensation'"
                        :employee-id="employee.id"
                    />
                    <GovernmentIdsTab
                        v-if="activeTab === 'government-ids'"
                        :employee="employee"
                    />
                    <ContactTab
                        v-if="activeTab === 'contact'"
                        :employee="employee"
                    />
                    <DocumentsTab
                        v-if="activeTab === 'documents'"
                        :employee="employee"
                    />
                    <Deferred
                        v-if="activeTab === 'assignment-history'"
                        data="assignmentHistory"
                    >
                        <template #fallback>
                            <AssignmentHistorySection :loading="true" />
                        </template>
                        <AssignmentHistorySection
                            :history="assignmentHistory"
                        />
                    </Deferred>
                </div>
            </div>
        </div>

        <!-- Assignment Change Modal -->
        <AssignmentChangeModal
            v-if="
                departments && positions && workLocations && supervisorOptions
            "
            v-model:open="isAssignmentModalOpen"
            :employee="employee"
            :departments="departments"
            :positions="positions"
            :work-locations="workLocations"
            :supervisor-options="supervisorOptions"
            @close="closeAssignmentModal"
            @success="handleAssignmentSuccess"
        />
    </TenantLayout>
</template>
