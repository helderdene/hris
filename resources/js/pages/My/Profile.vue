<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    Briefcase,
    CircleUserRound,
    FileText,
    MapPin,
    Pencil,
    Phone,
    Shield,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Address {
    street?: string | null;
    barangay?: string | null;
    city?: string | null;
    province?: string | null;
    postal_code?: string | null;
}

interface EmergencyContact {
    name?: string | null;
    relationship?: string | null;
    phone?: string | null;
}

interface EmployeeProfile {
    id: number;
    employee_number: string;
    full_name: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    suffix: string | null;
    initials: string;
    email: string | null;
    phone: string | null;
    date_of_birth: string | null;
    age: number | null;
    gender: string | null;
    civil_status: string | null;
    nationality: string | null;
    department: string | null;
    position: string | null;
    work_location: string | null;
    supervisor: string | null;
    hire_date: string | null;
    employment_type: string | null;
    employment_status: string | null;
    years_of_service: number;
    address: Address | null;
    emergency_contact: EmergencyContact | null;
    tin: string | null;
    sss_number: string | null;
    philhealth_number: string | null;
    pagibig_number: string | null;
    umid: string | null;
    passport_number: string | null;
    drivers_license: string | null;
    profile_photo_url: string | null;
}

const props = defineProps<{
    employee: EmployeeProfile | null;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Dashboard', href: '/my/dashboard' },
    { title: 'My Profile', href: '/my/profile' },
];

const editingSection = ref<string | null>(null);
const errors = ref<Record<string, string>>({});
const isSaving = ref(false);

const contactForm = ref({
    phone: props.employee?.phone ?? '',
    email: props.employee?.email ?? '',
    address: {
        street: props.employee?.address?.street ?? '',
        barangay: props.employee?.address?.barangay ?? '',
        city: props.employee?.address?.city ?? '',
        province: props.employee?.address?.province ?? '',
        postal_code: props.employee?.address?.postal_code ?? '',
    },
});

const emergencyForm = ref({
    emergency_contact: {
        name: props.employee?.emergency_contact?.name ?? '',
        relationship: props.employee?.emergency_contact?.relationship ?? '',
        phone: props.employee?.emergency_contact?.phone ?? '',
    },
});

const governmentForm = ref({
    tin: props.employee?.tin ?? '',
    sss_number: props.employee?.sss_number ?? '',
    philhealth_number: props.employee?.philhealth_number ?? '',
    pagibig_number: props.employee?.pagibig_number ?? '',
    umid: props.employee?.umid ?? '',
    passport_number: props.employee?.passport_number ?? '',
    drivers_license: props.employee?.drivers_license ?? '',
});

function startEditing(section: string): void {
    editingSection.value = section;
    errors.value = {};
}

function cancelEditing(): void {
    editingSection.value = null;
    errors.value = {};

    contactForm.value = {
        phone: props.employee?.phone ?? '',
        email: props.employee?.email ?? '',
        address: {
            street: props.employee?.address?.street ?? '',
            barangay: props.employee?.address?.barangay ?? '',
            city: props.employee?.address?.city ?? '',
            province: props.employee?.address?.province ?? '',
            postal_code: props.employee?.address?.postal_code ?? '',
        },
    };
    emergencyForm.value = {
        emergency_contact: {
            name: props.employee?.emergency_contact?.name ?? '',
            relationship: props.employee?.emergency_contact?.relationship ?? '',
            phone: props.employee?.emergency_contact?.phone ?? '',
        },
    };
    governmentForm.value = {
        tin: props.employee?.tin ?? '',
        sss_number: props.employee?.sss_number ?? '',
        philhealth_number: props.employee?.philhealth_number ?? '',
        pagibig_number: props.employee?.pagibig_number ?? '',
        umid: props.employee?.umid ?? '',
        passport_number: props.employee?.passport_number ?? '',
        drivers_license: props.employee?.drivers_license ?? '',
    };
}

function saveSection(section: string): void {
    isSaving.value = true;
    errors.value = {};

    let data: Record<string, unknown> = {};

    if (section === 'contact') {
        data = { ...contactForm.value };
    } else if (section === 'emergency') {
        data = { ...emergencyForm.value };
    } else if (section === 'government') {
        data = { ...governmentForm.value };
    }

    router.put('/my/profile', data, {
        preserveScroll: true,
        onSuccess: () => {
            editingSection.value = null;
            isSaving.value = false;
        },
        onError: (validationErrors) => {
            errors.value = validationErrors;
            isSaving.value = false;
        },
    });
}

function capitalize(value: string | null | undefined): string {
    if (!value) return '---';
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function displayValue(value: string | number | null | undefined): string {
    if (value == null || value === '') return '---';
    return String(value);
}
</script>

<template>
    <Head :title="`My Profile - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div v-if="employee" class="flex flex-col gap-6">
            <!-- Profile Header -->
            <div class="flex items-center gap-4">
                <div
                    v-if="employee.profile_photo_url"
                    class="h-20 w-20 shrink-0 overflow-hidden rounded-full"
                >
                    <img
                        :src="employee.profile_photo_url"
                        :alt="employee.full_name"
                        class="h-full w-full object-cover"
                    />
                </div>
                <div
                    v-else
                    class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xl font-semibold text-blue-600 dark:bg-blue-900 dark:text-blue-400"
                >
                    {{ employee.initials }}
                </div>
                <div>
                    <h1
                        class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                    >
                        {{ employee.full_name }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ employee.position ?? 'No Position' }} &middot;
                        {{ employee.department ?? 'No Department' }}
                    </p>
                    <p class="text-xs text-slate-400 dark:text-slate-500">
                        {{ employee.employee_number }}
                    </p>
                </div>
            </div>

            <!-- Personal Information (Read-only) -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <CircleUserRound class="h-5 w-5 text-blue-500" />
                        Personal Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Full Name</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ employee.full_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Date of Birth</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.date_of_birth) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Age</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ employee.age != null ? `${employee.age} years` : '---' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Gender</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ capitalize(employee.gender) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Civil Status</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ capitalize(employee.civil_status) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Nationality</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.nationality) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Employment Details (Read-only) -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Briefcase class="h-5 w-5 text-green-500" />
                        Employment Details
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Employee Number</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ employee.employee_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Department</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.department) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Position</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.position) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Work Location</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">
                                <span class="inline-flex items-center gap-1">
                                    <MapPin v-if="employee.work_location" class="h-3.5 w-3.5 text-slate-400" />
                                    {{ displayValue(employee.work_location) }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Supervisor</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.supervisor) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Hire Date</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.hire_date) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Employment Type</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ capitalize(employee.employment_type) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Employment Status</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ capitalize(employee.employment_status) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Years of Service</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ employee.years_of_service }} {{ employee.years_of_service === 1 ? 'year' : 'years' }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Contact Information (Editable) -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Phone class="h-5 w-5 text-purple-500" />
                            Contact Information
                        </CardTitle>
                        <Button
                            v-if="editingSection !== 'contact'"
                            variant="ghost"
                            size="sm"
                            @click="startEditing('contact')"
                        >
                            <Pencil class="mr-1 h-4 w-4" />
                            Edit
                        </Button>
                        <div v-else class="flex gap-2">
                            <Button variant="ghost" size="sm" @click="cancelEditing()">
                                <X class="mr-1 h-4 w-4" />
                                Cancel
                            </Button>
                            <Button size="sm" :disabled="isSaving" @click="saveSection('contact')">
                                {{ isSaving ? 'Saving...' : 'Save' }}
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- View mode -->
                    <div v-if="editingSection !== 'contact'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Phone</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.phone) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Email</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.email) }}</p>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Address</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">
                                {{
                                    [
                                        employee.address?.street,
                                        employee.address?.barangay,
                                        employee.address?.city,
                                        employee.address?.province,
                                        employee.address?.postal_code,
                                    ]
                                        .filter(Boolean)
                                        .join(', ') || '---'
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- Edit mode -->
                    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="phone">Phone</Label>
                            <Input id="phone" v-model="contactForm.phone" class="mt-1" />
                            <p v-if="errors.phone" class="mt-1 text-xs text-red-500">{{ errors.phone }}</p>
                        </div>
                        <div>
                            <Label for="email">Email</Label>
                            <Input id="email" v-model="contactForm.email" type="email" class="mt-1" />
                            <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email }}</p>
                        </div>
                        <div>
                            <Label for="street">Street</Label>
                            <Input id="street" v-model="contactForm.address.street" class="mt-1" />
                        </div>
                        <div>
                            <Label for="barangay">Barangay</Label>
                            <Input id="barangay" v-model="contactForm.address.barangay" class="mt-1" />
                        </div>
                        <div>
                            <Label for="city">City</Label>
                            <Input id="city" v-model="contactForm.address.city" class="mt-1" />
                        </div>
                        <div>
                            <Label for="province">Province</Label>
                            <Input id="province" v-model="contactForm.address.province" class="mt-1" />
                        </div>
                        <div>
                            <Label for="postal_code">Postal Code</Label>
                            <Input id="postal_code" v-model="contactForm.address.postal_code" class="mt-1" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Emergency Contact (Editable) -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Shield class="h-5 w-5 text-red-500" />
                            Emergency Contact
                        </CardTitle>
                        <Button
                            v-if="editingSection !== 'emergency'"
                            variant="ghost"
                            size="sm"
                            @click="startEditing('emergency')"
                        >
                            <Pencil class="mr-1 h-4 w-4" />
                            Edit
                        </Button>
                        <div v-else class="flex gap-2">
                            <Button variant="ghost" size="sm" @click="cancelEditing()">
                                <X class="mr-1 h-4 w-4" />
                                Cancel
                            </Button>
                            <Button size="sm" :disabled="isSaving" @click="saveSection('emergency')">
                                {{ isSaving ? 'Saving...' : 'Save' }}
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- View mode -->
                    <div v-if="editingSection !== 'emergency'" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Name</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.emergency_contact?.name) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Relationship</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ capitalize(employee.emergency_contact?.relationship) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Phone</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.emergency_contact?.phone) }}</p>
                        </div>
                    </div>

                    <!-- Edit mode -->
                    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <Label for="ec_name">Name</Label>
                            <Input id="ec_name" v-model="emergencyForm.emergency_contact.name" class="mt-1" />
                            <p v-if="errors['emergency_contact.name']" class="mt-1 text-xs text-red-500">{{ errors['emergency_contact.name'] }}</p>
                        </div>
                        <div>
                            <Label for="ec_relationship">Relationship</Label>
                            <Input id="ec_relationship" v-model="emergencyForm.emergency_contact.relationship" class="mt-1" />
                            <p v-if="errors['emergency_contact.relationship']" class="mt-1 text-xs text-red-500">{{ errors['emergency_contact.relationship'] }}</p>
                        </div>
                        <div>
                            <Label for="ec_phone">Phone</Label>
                            <Input id="ec_phone" v-model="emergencyForm.emergency_contact.phone" class="mt-1" />
                            <p v-if="errors['emergency_contact.phone']" class="mt-1 text-xs text-red-500">{{ errors['emergency_contact.phone'] }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Government IDs (Editable) -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <FileText class="h-5 w-5 text-amber-500" />
                            Government IDs
                        </CardTitle>
                        <Button
                            v-if="editingSection !== 'government'"
                            variant="ghost"
                            size="sm"
                            @click="startEditing('government')"
                        >
                            <Pencil class="mr-1 h-4 w-4" />
                            Edit
                        </Button>
                        <div v-else class="flex gap-2">
                            <Button variant="ghost" size="sm" @click="cancelEditing()">
                                <X class="mr-1 h-4 w-4" />
                                Cancel
                            </Button>
                            <Button size="sm" :disabled="isSaving" @click="saveSection('government')">
                                {{ isSaving ? 'Saving...' : 'Save' }}
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- View mode -->
                    <div v-if="editingSection !== 'government'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">TIN</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.tin) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">SSS Number</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.sss_number) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">PhilHealth Number</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.philhealth_number) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Pag-IBIG Number</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.pagibig_number) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">UMID</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.umid) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Passport Number</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.passport_number) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">Driver's License</p>
                            <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ displayValue(employee.drivers_license) }}</p>
                        </div>
                    </div>

                    <!-- Edit mode -->
                    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <Label for="tin">TIN</Label>
                            <Input id="tin" v-model="governmentForm.tin" class="mt-1" />
                            <p v-if="errors.tin" class="mt-1 text-xs text-red-500">{{ errors.tin }}</p>
                        </div>
                        <div>
                            <Label for="sss_number">SSS Number</Label>
                            <Input id="sss_number" v-model="governmentForm.sss_number" class="mt-1" />
                            <p v-if="errors.sss_number" class="mt-1 text-xs text-red-500">{{ errors.sss_number }}</p>
                        </div>
                        <div>
                            <Label for="philhealth_number">PhilHealth Number</Label>
                            <Input id="philhealth_number" v-model="governmentForm.philhealth_number" class="mt-1" />
                            <p v-if="errors.philhealth_number" class="mt-1 text-xs text-red-500">{{ errors.philhealth_number }}</p>
                        </div>
                        <div>
                            <Label for="pagibig_number">Pag-IBIG Number</Label>
                            <Input id="pagibig_number" v-model="governmentForm.pagibig_number" class="mt-1" />
                            <p v-if="errors.pagibig_number" class="mt-1 text-xs text-red-500">{{ errors.pagibig_number }}</p>
                        </div>
                        <div>
                            <Label for="umid">UMID</Label>
                            <Input id="umid" v-model="governmentForm.umid" class="mt-1" />
                            <p v-if="errors.umid" class="mt-1 text-xs text-red-500">{{ errors.umid }}</p>
                        </div>
                        <div>
                            <Label for="passport_number">Passport Number</Label>
                            <Input id="passport_number" v-model="governmentForm.passport_number" class="mt-1" />
                            <p v-if="errors.passport_number" class="mt-1 text-xs text-red-500">{{ errors.passport_number }}</p>
                        </div>
                        <div>
                            <Label for="drivers_license">Driver's License</Label>
                            <Input id="drivers_license" v-model="governmentForm.drivers_license" class="mt-1" />
                            <p v-if="errors.drivers_license" class="mt-1 text-xs text-red-500">{{ errors.drivers_license }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- No employee record -->
        <div v-else class="flex flex-col items-center justify-center py-16">
            <CircleUserRound class="h-16 w-16 text-slate-300 dark:text-slate-600" />
            <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                No Employee Record
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Your account is not linked to an employee record. Please contact HR.
            </p>
        </div>
    </TenantLayout>
</template>
