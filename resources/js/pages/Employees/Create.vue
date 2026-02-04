<script setup lang="ts">
import EnumSelect from '@/components/EnumSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Department {
    id: number;
    name: string;
}

interface Position {
    id: number;
    title: string;
}

interface WorkLocation {
    id: number;
    name: string;
}

interface SupervisorOption {
    id: number;
    full_name: string;
    employee_number: string;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    departments: Department[];
    positions: Position[];
    workLocations: WorkLocation[];
    employees: SupervisorOption[];
    employmentTypes: EnumOption[];
    employmentStatuses: EnumOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Employees', href: '/employees' },
    { title: 'Add Employee', href: '/employees/create' },
];

type SectionId = 'personal' | 'employment' | 'government-ids' | 'contact';

const activeSection = ref<SectionId>('personal');

const sections = [
    { id: 'personal' as SectionId, label: 'Personal Info', icon: 'user' },
    { id: 'employment' as SectionId, label: 'Employment', icon: 'briefcase' },
    {
        id: 'government-ids' as SectionId,
        label: 'Government IDs',
        icon: 'card',
    },
    { id: 'contact' as SectionId, label: 'Contact', icon: 'location' },
];

const form = useForm({
    // Personal Info
    first_name: '',
    middle_name: '',
    last_name: '',
    suffix: '',
    date_of_birth: '',
    gender: '',
    civil_status: '',
    nationality: '',
    fathers_name: '',
    mothers_name: '',

    // Employment
    employee_number: '',
    department_id: '',
    position_id: '',
    work_location_id: '',
    supervisor_id: '',
    employment_type: 'regular',
    employment_status: 'active',
    hire_date: '',
    regularization_date: '',
    basic_salary: '',
    pay_frequency: '',

    // Government IDs
    tin: '',
    sss_number: '',
    philhealth_number: '',
    pagibig_number: '',
    umid: '',
    passport_number: '',
    drivers_license: '',
    nbi_clearance: '',
    police_clearance: '',
    prc_license: '',

    // Contact
    email: '',
    phone: '',
    address: {
        street: '',
        barangay: '',
        city: '',
        province: '',
        postal_code: '',
    },
    emergency_contact: {
        name: '',
        relationship: '',
        phone: '',
    },
});

function formatTin(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 12);
    const parts = [];
    for (let i = 0; i < digits.length; i += 3) {
        parts.push(digits.slice(i, i + 3));
    }
    return parts.join('-');
}

watch(
    () => form.tin,
    (newValue) => {
        const formatted = formatTin(newValue);
        if (formatted !== newValue) {
            form.tin = formatted;
        }
    },
);

const genderOptions: EnumOption[] = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
    { value: 'other', label: 'Other' },
];

const civilStatusOptions: EnumOption[] = [
    { value: 'single', label: 'Single' },
    { value: 'married', label: 'Married' },
    { value: 'widowed', label: 'Widowed' },
    { value: 'separated', label: 'Separated' },
    { value: 'divorced', label: 'Divorced' },
];

const payFrequencyOptions: EnumOption[] = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'semi-monthly', label: 'Semi-monthly' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'bi-weekly', label: 'Bi-weekly' },
];

const departmentOptions = computed<EnumOption[]>(() => {
    return props.departments.map((dept) => ({
        value: dept.id.toString(),
        label: dept.name,
    }));
});

const positionOptions = computed<EnumOption[]>(() => {
    return props.positions.map((pos) => ({
        value: pos.id.toString(),
        label: pos.title,
    }));
});

const workLocationOptions = computed<EnumOption[]>(() => {
    return props.workLocations.map((loc) => ({
        value: loc.id.toString(),
        label: loc.name,
    }));
});

const supervisorOptions = computed<EnumOption[]>(() => {
    return props.employees.map((emp) => ({
        value: emp.id.toString(),
        label: `${emp.full_name} (${emp.employee_number})`,
    }));
});

function handleSubmit() {
    // Transform data before submission
    const transformedData = {
        ...form.data(),
        department_id: form.department_id
            ? parseInt(form.department_id as string)
            : null,
        position_id: form.position_id
            ? parseInt(form.position_id as string)
            : null,
        work_location_id: form.work_location_id
            ? parseInt(form.work_location_id as string)
            : null,
        supervisor_id: form.supervisor_id
            ? parseInt(form.supervisor_id as string)
            : null,
        basic_salary: form.basic_salary
            ? parseFloat(form.basic_salary as string)
            : null,
    };

    router.post('/employees', transformedData, {
        preserveScroll: true,
        onError: (errors) => {
            Object.keys(errors).forEach((key) => {
                form.setError(key as keyof typeof form.errors, errors[key]);
            });
        },
    });
}

function handleCancel() {
    router.visit('/employees');
}
</script>

<template>
    <Head :title="`Add Employee - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Add Employee
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Create a new employee record
                    </p>
                </div>
            </div>

            <!-- Form Card -->
            <div
                class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Section Navigation -->
                <div class="border-b border-slate-200 dark:border-slate-700">
                    <nav
                        class="-mb-px flex overflow-x-auto px-6"
                        aria-label="Form Sections"
                    >
                        <button
                            v-for="section in sections"
                            :key="section.id"
                            type="button"
                            @click="activeSection = section.id"
                            :class="[
                                'flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors',
                                activeSection === section.id
                                    ? 'border-blue-500 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                            ]"
                        >
                            <!-- User icon -->
                            <svg
                                v-if="section.icon === 'user'"
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
                                v-if="section.icon === 'briefcase'"
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
                            <!-- Card icon -->
                            <svg
                                v-if="section.icon === 'card'"
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
                                v-if="section.icon === 'location'"
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
                            {{ section.label }}
                        </button>
                    </nav>
                </div>

                <!-- Form Content -->
                <form @submit.prevent="handleSubmit" class="p-6">
                    <!-- Personal Info Section -->
                    <div
                        v-show="activeSection === 'personal'"
                        class="space-y-6"
                    >
                        <h3
                            class="text-lg font-medium text-slate-900 dark:text-slate-100"
                        >
                            Personal Information
                        </h3>

                        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="first_name"
                                    >First Name
                                    <span class="text-red-500">*</span></Label
                                >
                                <Input
                                    id="first_name"
                                    v-model="form.first_name"
                                    type="text"
                                    placeholder="Enter first name"
                                    :aria-invalid="!!form.errors.first_name"
                                />
                                <InputError :message="form.errors.first_name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="middle_name">Middle Name</Label>
                                <Input
                                    id="middle_name"
                                    v-model="form.middle_name"
                                    type="text"
                                    placeholder="Enter middle name"
                                />
                                <InputError
                                    :message="form.errors.middle_name"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="last_name"
                                    >Last Name
                                    <span class="text-red-500">*</span></Label
                                >
                                <Input
                                    id="last_name"
                                    v-model="form.last_name"
                                    type="text"
                                    placeholder="Enter last name"
                                    :aria-invalid="!!form.errors.last_name"
                                />
                                <InputError :message="form.errors.last_name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="suffix">Suffix</Label>
                                <Input
                                    id="suffix"
                                    v-model="form.suffix"
                                    type="text"
                                    placeholder="Jr., Sr., III, etc."
                                />
                                <InputError :message="form.errors.suffix" />
                            </div>

                            <div class="space-y-2">
                                <Label for="date_of_birth">Date of Birth</Label>
                                <Input
                                    id="date_of_birth"
                                    v-model="form.date_of_birth"
                                    type="date"
                                />
                                <InputError
                                    :message="form.errors.date_of_birth"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="gender">Gender</Label>
                                <EnumSelect
                                    id="gender"
                                    v-model="form.gender"
                                    :options="genderOptions"
                                    placeholder="Select gender"
                                />
                                <InputError :message="form.errors.gender" />
                            </div>

                            <div class="space-y-2">
                                <Label for="civil_status">Civil Status</Label>
                                <EnumSelect
                                    id="civil_status"
                                    v-model="form.civil_status"
                                    :options="civilStatusOptions"
                                    placeholder="Select civil status"
                                />
                                <InputError
                                    :message="form.errors.civil_status"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="nationality">Nationality</Label>
                                <Input
                                    id="nationality"
                                    v-model="form.nationality"
                                    type="text"
                                    placeholder="Enter nationality"
                                />
                                <InputError
                                    :message="form.errors.nationality"
                                />
                            </div>
                        </div>

                        <div
                            class="border-t border-slate-200 pt-6 dark:border-slate-700"
                        >
                            <h4
                                class="mb-4 text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Parent Information
                            </h4>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="fathers_name"
                                        >Father's Name</Label
                                    >
                                    <Input
                                        id="fathers_name"
                                        v-model="form.fathers_name"
                                        type="text"
                                        placeholder="Enter father's full name"
                                    />
                                    <InputError
                                        :message="form.errors.fathers_name"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="mothers_name"
                                        >Mother's Name</Label
                                    >
                                    <Input
                                        id="mothers_name"
                                        v-model="form.mothers_name"
                                        type="text"
                                        placeholder="Enter mother's full name"
                                    />
                                    <InputError
                                        :message="form.errors.mothers_name"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Section -->
                    <div
                        v-show="activeSection === 'employment'"
                        class="space-y-6"
                    >
                        <h3
                            class="text-lg font-medium text-slate-900 dark:text-slate-100"
                        >
                            Employment Details
                        </h3>

                        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="employee_number"
                                    >Employee Number
                                    <span class="text-red-500">*</span></Label
                                >
                                <Input
                                    id="employee_number"
                                    v-model="form.employee_number"
                                    type="text"
                                    placeholder="e.g., EMP-2026-001"
                                    :aria-invalid="
                                        !!form.errors.employee_number
                                    "
                                />
                                <InputError
                                    :message="form.errors.employee_number"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="department_id">Department</Label>
                                <EnumSelect
                                    id="department_id"
                                    v-model="form.department_id"
                                    :options="departmentOptions"
                                    placeholder="Select department"
                                />
                                <InputError
                                    :message="form.errors.department_id"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="position_id">Position</Label>
                                <EnumSelect
                                    id="position_id"
                                    v-model="form.position_id"
                                    :options="positionOptions"
                                    placeholder="Select position"
                                />
                                <InputError
                                    :message="form.errors.position_id"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="work_location_id"
                                    >Work Location</Label
                                >
                                <EnumSelect
                                    id="work_location_id"
                                    v-model="form.work_location_id"
                                    :options="workLocationOptions"
                                    placeholder="Select work location"
                                />
                                <InputError
                                    :message="form.errors.work_location_id"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="supervisor_id">Supervisor</Label>
                                <EnumSelect
                                    id="supervisor_id"
                                    v-model="form.supervisor_id"
                                    :options="supervisorOptions"
                                    placeholder="Select supervisor"
                                />
                                <InputError
                                    :message="form.errors.supervisor_id"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="employment_type"
                                    >Employment Type</Label
                                >
                                <EnumSelect
                                    id="employment_type"
                                    v-model="form.employment_type"
                                    :options="employmentTypes"
                                    placeholder="Select employment type"
                                />
                                <InputError
                                    :message="form.errors.employment_type"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="employment_status"
                                    >Employment Status</Label
                                >
                                <EnumSelect
                                    id="employment_status"
                                    v-model="form.employment_status"
                                    :options="employmentStatuses"
                                    placeholder="Select status"
                                />
                                <InputError
                                    :message="form.errors.employment_status"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="hire_date"
                                    >Hire Date
                                    <span class="text-red-500">*</span></Label
                                >
                                <Input
                                    id="hire_date"
                                    v-model="form.hire_date"
                                    type="date"
                                    :aria-invalid="!!form.errors.hire_date"
                                />
                                <InputError :message="form.errors.hire_date" />
                            </div>

                            <div class="space-y-2">
                                <Label for="regularization_date"
                                    >Regularization Date</Label
                                >
                                <Input
                                    id="regularization_date"
                                    v-model="form.regularization_date"
                                    type="date"
                                />
                                <InputError
                                    :message="form.errors.regularization_date"
                                />
                            </div>
                        </div>

                        <div
                            class="border-t border-slate-200 pt-6 dark:border-slate-700"
                        >
                            <h4
                                class="mb-4 text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Compensation
                            </h4>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="basic_salary"
                                        >Basic Salary</Label
                                    >
                                    <div class="relative">
                                        <span
                                            class="absolute top-1/2 left-3 -translate-y-1/2 text-slate-500"
                                            >PHP</span
                                        >
                                        <Input
                                            id="basic_salary"
                                            v-model="form.basic_salary"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="0.00"
                                            class="pl-12"
                                        />
                                    </div>
                                    <InputError
                                        :message="form.errors.basic_salary"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="pay_frequency"
                                        >Pay Frequency</Label
                                    >
                                    <EnumSelect
                                        id="pay_frequency"
                                        v-model="form.pay_frequency"
                                        :options="payFrequencyOptions"
                                        placeholder="Select pay frequency"
                                    />
                                    <InputError
                                        :message="form.errors.pay_frequency"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Government IDs Section -->
                    <div
                        v-show="activeSection === 'government-ids'"
                        class="space-y-6"
                    >
                        <h3
                            class="text-lg font-medium text-slate-900 dark:text-slate-100"
                        >
                            Government IDs
                        </h3>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="tin"
                                    >TIN (Tax Identification Number)</Label
                                >
                                <Input
                                    id="tin"
                                    v-model="form.tin"
                                    type="text"
                                    placeholder="123-456-789-000"
                                />
                                <p
                                    class="text-sm text-muted-foreground"
                                >
                                    Just type numbers, dashes added
                                    automatically
                                </p>
                                <InputError :message="form.errors.tin" />
                            </div>

                            <div class="space-y-2">
                                <Label for="sss_number">SSS Number</Label>
                                <Input
                                    id="sss_number"
                                    v-model="form.sss_number"
                                    type="text"
                                    placeholder="Enter SSS number"
                                />
                                <InputError :message="form.errors.sss_number" />
                            </div>

                            <div class="space-y-2">
                                <Label for="philhealth_number"
                                    >PhilHealth Number</Label
                                >
                                <Input
                                    id="philhealth_number"
                                    v-model="form.philhealth_number"
                                    type="text"
                                    placeholder="Enter PhilHealth number"
                                />
                                <InputError
                                    :message="form.errors.philhealth_number"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="pagibig_number"
                                    >Pag-IBIG Number</Label
                                >
                                <Input
                                    id="pagibig_number"
                                    v-model="form.pagibig_number"
                                    type="text"
                                    placeholder="Enter Pag-IBIG number"
                                />
                                <InputError
                                    :message="form.errors.pagibig_number"
                                />
                            </div>
                        </div>

                        <div
                            class="border-t border-slate-200 pt-6 dark:border-slate-700"
                        >
                            <h4
                                class="mb-4 text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Optional IDs
                            </h4>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="umid">UMID</Label>
                                    <Input
                                        id="umid"
                                        v-model="form.umid"
                                        type="text"
                                        placeholder="Enter UMID number"
                                    />
                                    <InputError :message="form.errors.umid" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="passport_number"
                                        >Passport Number</Label
                                    >
                                    <Input
                                        id="passport_number"
                                        v-model="form.passport_number"
                                        type="text"
                                        placeholder="Enter passport number"
                                    />
                                    <InputError
                                        :message="form.errors.passport_number"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="drivers_license"
                                        >Driver's License</Label
                                    >
                                    <Input
                                        id="drivers_license"
                                        v-model="form.drivers_license"
                                        type="text"
                                        placeholder="Enter driver's license number"
                                    />
                                    <InputError
                                        :message="form.errors.drivers_license"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="nbi_clearance"
                                        >NBI Clearance</Label
                                    >
                                    <Input
                                        id="nbi_clearance"
                                        v-model="form.nbi_clearance"
                                        type="text"
                                        placeholder="Enter NBI clearance number"
                                    />
                                    <InputError
                                        :message="form.errors.nbi_clearance"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="police_clearance"
                                        >Police Clearance</Label
                                    >
                                    <Input
                                        id="police_clearance"
                                        v-model="form.police_clearance"
                                        type="text"
                                        placeholder="Enter police clearance number"
                                    />
                                    <InputError
                                        :message="form.errors.police_clearance"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="prc_license">PRC License</Label>
                                    <Input
                                        id="prc_license"
                                        v-model="form.prc_license"
                                        type="text"
                                        placeholder="Enter PRC license number"
                                    />
                                    <InputError
                                        :message="form.errors.prc_license"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Section -->
                    <div v-show="activeSection === 'contact'" class="space-y-6">
                        <h3
                            class="text-lg font-medium text-slate-900 dark:text-slate-100"
                        >
                            Contact Information
                        </h3>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="email"
                                    >Email Address
                                    <span class="text-red-500">*</span></Label
                                >
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="employee@company.com"
                                    :aria-invalid="!!form.errors.email"
                                />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="space-y-2">
                                <Label for="phone">Phone Number</Label>
                                <Input
                                    id="phone"
                                    v-model="form.phone"
                                    type="tel"
                                    placeholder="+63 912 345 6789"
                                />
                                <InputError :message="form.errors.phone" />
                            </div>
                        </div>

                        <div
                            class="border-t border-slate-200 pt-6 dark:border-slate-700"
                        >
                            <h4
                                class="mb-4 text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Address
                            </h4>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="address_street"
                                        >Street Address</Label
                                    >
                                    <Input
                                        id="address_street"
                                        v-model="form.address.street"
                                        type="text"
                                        placeholder="House/Unit No., Street Name"
                                    />
                                    <InputError
                                        :message="form.errors['address.street']"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="address_barangay"
                                        >Barangay</Label
                                    >
                                    <Input
                                        id="address_barangay"
                                        v-model="form.address.barangay"
                                        type="text"
                                        placeholder="Enter barangay"
                                    />
                                    <InputError
                                        :message="
                                            form.errors['address.barangay']
                                        "
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="address_city"
                                        >City/Municipality</Label
                                    >
                                    <Input
                                        id="address_city"
                                        v-model="form.address.city"
                                        type="text"
                                        placeholder="Enter city or municipality"
                                    />
                                    <InputError
                                        :message="form.errors['address.city']"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="address_province"
                                        >Province</Label
                                    >
                                    <Input
                                        id="address_province"
                                        v-model="form.address.province"
                                        type="text"
                                        placeholder="Enter province"
                                    />
                                    <InputError
                                        :message="
                                            form.errors['address.province']
                                        "
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="address_postal_code"
                                        >Postal Code</Label
                                    >
                                    <Input
                                        id="address_postal_code"
                                        v-model="form.address.postal_code"
                                        type="text"
                                        placeholder="Enter postal code"
                                    />
                                    <InputError
                                        :message="
                                            form.errors['address.postal_code']
                                        "
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            class="border-t border-slate-200 pt-6 dark:border-slate-700"
                        >
                            <h4
                                class="mb-4 text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Emergency Contact
                            </h4>
                            <div class="grid gap-6 sm:grid-cols-3">
                                <div class="space-y-2">
                                    <Label for="emergency_contact_name"
                                        >Contact Name</Label
                                    >
                                    <Input
                                        id="emergency_contact_name"
                                        v-model="form.emergency_contact.name"
                                        type="text"
                                        placeholder="Full name"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                'emergency_contact.name'
                                            ]
                                        "
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="emergency_contact_relationship"
                                        >Relationship</Label
                                    >
                                    <Input
                                        id="emergency_contact_relationship"
                                        v-model="
                                            form.emergency_contact.relationship
                                        "
                                        type="text"
                                        placeholder="e.g., Spouse, Parent"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                'emergency_contact.relationship'
                                            ]
                                        "
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="emergency_contact_phone"
                                        >Phone Number</Label
                                    >
                                    <Input
                                        id="emergency_contact_phone"
                                        v-model="form.emergency_contact.phone"
                                        type="tel"
                                        placeholder="+63 912 345 6789"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                'emergency_contact.phone'
                                            ]
                                        "
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div
                        class="mt-8 flex items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-slate-700"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="handleCancel"
                            :disabled="form.processing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :style="{ backgroundColor: primaryColor }"
                            :disabled="form.processing"
                        >
                            <svg
                                v-if="form.processing"
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
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            {{
                                form.processing
                                    ? 'Creating...'
                                    : 'Create Employee'
                            }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </TenantLayout>
</template>
