<script setup lang="ts">
import { Button } from '@/components/ui/button';
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
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
}

const props = defineProps<{
    employee: Employee | null;
    employees: { id: number; full_name: string; employee_number: string }[];
    departments: { id: number; name: string }[];
    positions: { id: number; name: string }[];
    urgencies: { value: string; label: string; color: string }[];
    employmentTypes: { value: string; label: string }[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/requisitions' },
    { title: 'Job Requisitions', href: '/recruitment/requisitions' },
    { title: 'Create', href: '/recruitment/requisitions/create' },
];

const form = ref({
    requested_by_employee_id: props.employee ? String(props.employee.id) : '',
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

const errors = ref<Record<string, string>>({});
const submitError = ref('');
const isSubmitting = ref(false);

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    isSubmitting.value = true;
    errors.value = {};
    submitError.value = '';

    const body: Record<string, any> = {
        position_id: Number(form.value.position_id),
        department_id: Number(form.value.department_id),
        requested_by_employee_id: Number(form.value.requested_by_employee_id),
        headcount: Number(form.value.headcount),
        employment_type: form.value.employment_type,
        urgency: form.value.urgency,
        justification: form.value.justification,
    };
    if (form.value.salary_range_min) body.salary_range_min = Number(form.value.salary_range_min);
    if (form.value.salary_range_max) body.salary_range_max = Number(form.value.salary_range_max);
    if (form.value.preferred_start_date) body.preferred_start_date = form.value.preferred_start_date;
    if (form.value.remarks) body.remarks = form.value.remarks;

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
            const data = await response.json();
            router.visit(`/recruitment/requisitions/${data.id}`);
            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            const errs: Record<string, string> = {};
            for (const [key, messages] of Object.entries(data.errors || {})) {
                errs[key] = (messages as string[])[0];
            }
            errors.value = errs;
            submitError.value = 'Please correct the highlighted fields and try again.';
        } else if (response.status === 419) {
            submitError.value = 'Your session expired. Please refresh the page and try again.';
        } else {
            const message = await response.text();
            console.error('Create requisition failed', response.status, message);
            submitError.value = `Could not create the requisition (error ${response.status}). Please try again.`;
        }
    } catch (e) {
        console.error('Create requisition request errored', e);
        submitError.value = 'A network error occurred. Please check your connection and try again.';
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Head :title="`Create Job Requisition - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl">
            <div class="mb-6">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Create Job Requisition</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Create a new requisition as a draft. You can submit it for approval later.
                </p>
            </div>

            <div class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                <!-- Submit error banner -->
                <div
                    v-if="submitError"
                    class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300"
                >
                    {{ submitError }}
                </div>

                <!-- Requested By -->
                <div class="space-y-2">
                    <Label for="requested-by">Requested By <span class="text-red-500">*</span></Label>
                    <Select v-model="form.requested_by_employee_id">
                        <SelectTrigger id="requested-by">
                            <SelectValue placeholder="Select requesting employee" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="emp in employees" :key="emp.id" :value="String(emp.id)">
                                {{ emp.full_name }} ({{ emp.employee_number }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors.requested_by_employee_id" class="text-sm text-red-500">{{ errors.requested_by_employee_id }}</p>
                </div>

                <!-- Position & Department -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="position">Position <span class="text-red-500">*</span></Label>
                        <Select v-model="form.position_id">
                            <SelectTrigger id="position">
                                <SelectValue placeholder="Select position" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="pos in positions" :key="pos.id" :value="String(pos.id)">
                                    {{ pos.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.position_id" class="text-sm text-red-500">{{ errors.position_id }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="department">Department <span class="text-red-500">*</span></Label>
                        <Select v-model="form.department_id">
                            <SelectTrigger id="department">
                                <SelectValue placeholder="Select department" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="dept in departments" :key="dept.id" :value="String(dept.id)">
                                    {{ dept.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.department_id" class="text-sm text-red-500">{{ errors.department_id }}</p>
                    </div>
                </div>

                <!-- Headcount & Employment Type -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="headcount">Headcount <span class="text-red-500">*</span></Label>
                        <Input id="headcount" v-model.number="form.headcount" type="number" min="1" max="100" />
                        <p v-if="errors.headcount" class="text-sm text-red-500">{{ errors.headcount }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="employment-type">Employment Type <span class="text-red-500">*</span></Label>
                        <Select v-model="form.employment_type">
                            <SelectTrigger id="employment-type">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="et in employmentTypes" :key="et.value" :value="et.value">
                                    {{ et.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.employment_type" class="text-sm text-red-500">{{ errors.employment_type }}</p>
                    </div>
                </div>

                <!-- Urgency & Preferred Start Date -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="urgency">Urgency <span class="text-red-500">*</span></Label>
                        <Select v-model="form.urgency">
                            <SelectTrigger id="urgency">
                                <SelectValue placeholder="Select urgency" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="u in urgencies" :key="u.value" :value="u.value">
                                    {{ u.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.urgency" class="text-sm text-red-500">{{ errors.urgency }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="start-date">Preferred Start Date</Label>
                        <Input id="start-date" v-model="form.preferred_start_date" type="date" />
                        <p v-if="errors.preferred_start_date" class="text-sm text-red-500">{{ errors.preferred_start_date }}</p>
                    </div>
                </div>

                <!-- Salary Range -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="salary-min">Salary Range Min</Label>
                        <Input id="salary-min" v-model="form.salary_range_min" type="number" min="0" placeholder="0.00" />
                        <p v-if="errors.salary_range_min" class="text-sm text-red-500">{{ errors.salary_range_min }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="salary-max">Salary Range Max</Label>
                        <Input id="salary-max" v-model="form.salary_range_max" type="number" min="0" placeholder="0.00" />
                        <p v-if="errors.salary_range_max" class="text-sm text-red-500">{{ errors.salary_range_max }}</p>
                    </div>
                </div>

                <!-- Justification -->
                <div class="space-y-2">
                    <Label for="justification">Justification <span class="text-red-500">*</span></Label>
                    <Textarea
                        id="justification"
                        v-model="form.justification"
                        placeholder="Explain why this position is needed..."
                        rows="3"
                    />
                    <p v-if="errors.justification" class="text-sm text-red-500">{{ errors.justification }}</p>
                </div>

                <!-- Remarks -->
                <div class="space-y-2">
                    <Label for="remarks">Remarks</Label>
                    <Textarea id="remarks" v-model="form.remarks" placeholder="Additional notes (optional)" rows="2" />
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <Button variant="outline" @click="router.visit('/recruitment/requisitions')">Cancel</Button>
                    <Button @click="handleSubmit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Creating...' : 'Create Draft' }}
                    </Button>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
