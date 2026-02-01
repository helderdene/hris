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
import { ref, onMounted } from 'vue';

interface Requisition {
    id: number;
    reference_number: string;
    position_id: number;
    department_id: number;
    title: string | null;
    employment_type: string;
    salary_range_min: number | null;
    salary_range_max: number | null;
}

const props = defineProps<{
    employee: { id: number; full_name: string } | null;
    departments: { id: number; name: string }[];
    positions: { id: number; name: string }[];
    employmentTypes: { value: string; label: string }[];
    salaryDisplayOptions: { value: string; label: string }[];
    requisition: Requisition | null;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/job-postings' },
    { title: 'Job Postings', href: '/recruitment/job-postings' },
    { title: 'Create', href: '/recruitment/job-postings/create' },
];

const form = ref({
    title: '',
    department_id: '',
    position_id: '',
    description: '',
    requirements: '',
    benefits: '',
    employment_type: '',
    location: '',
    salary_display_option: 'hidden',
    salary_range_min: '',
    salary_range_max: '',
    application_instructions: '',
    job_requisition_id: '',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

onMounted(() => {
    if (props.requisition) {
        form.value.title = props.requisition.title || '';
        form.value.department_id = String(props.requisition.department_id);
        form.value.position_id = String(props.requisition.position_id);
        form.value.employment_type = props.requisition.employment_type;
        form.value.job_requisition_id = String(props.requisition.id);
        if (props.requisition.salary_range_min) form.value.salary_range_min = String(props.requisition.salary_range_min);
        if (props.requisition.salary_range_max) form.value.salary_range_max = String(props.requisition.salary_range_max);
    }
});

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    if (!props.employee) return;
    isSubmitting.value = true;
    errors.value = {};

    const body: Record<string, any> = {
        created_by_employee_id: props.employee.id,
        title: form.value.title,
        department_id: Number(form.value.department_id),
        description: form.value.description,
        employment_type: form.value.employment_type,
        location: form.value.location,
        salary_display_option: form.value.salary_display_option,
    };

    if (form.value.position_id) body.position_id = Number(form.value.position_id);
    if (form.value.requirements) body.requirements = form.value.requirements;
    if (form.value.benefits) body.benefits = form.value.benefits;
    if (form.value.salary_range_min) body.salary_range_min = Number(form.value.salary_range_min);
    if (form.value.salary_range_max) body.salary_range_max = Number(form.value.salary_range_max);
    if (form.value.application_instructions) body.application_instructions = form.value.application_instructions;
    if (form.value.job_requisition_id) body.job_requisition_id = Number(form.value.job_requisition_id);

    try {
        const response = await fetch('/api/job-postings', {
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
            router.visit(`/recruitment/job-postings/${data.data.id}`);
        } else if (response.status === 422) {
            const data = await response.json();
            const errs: Record<string, string> = {};
            for (const [key, messages] of Object.entries(data.errors || {})) {
                errs[key] = (messages as string[])[0];
            }
            errors.value = errs;
        }
    } catch {
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Head :title="`Create Job Posting - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl">
            <div class="mb-6">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Create Job Posting</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Create a new job posting as a draft. You can publish it later.
                </p>
                <p v-if="requisition" class="mt-2 text-sm text-blue-600 dark:text-blue-400">
                    Pre-filled from requisition {{ requisition.reference_number }}
                </p>
            </div>

            <div class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                <!-- Title -->
                <div class="space-y-2">
                    <Label for="title">Job Title <span class="text-red-500">*</span></Label>
                    <Input id="title" v-model="form.title" placeholder="e.g. Senior Software Engineer" />
                    <p v-if="errors.title" class="text-sm text-red-500">{{ errors.title }}</p>
                </div>

                <!-- Department & Position -->
                <div class="grid gap-4 sm:grid-cols-2">
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
                    <div class="space-y-2">
                        <Label for="position">Position</Label>
                        <Select v-model="form.position_id">
                            <SelectTrigger id="position">
                                <SelectValue placeholder="Select position (optional)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="pos in positions" :key="pos.id" :value="String(pos.id)">
                                    {{ pos.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.position_id" class="text-sm text-red-500">{{ errors.position_id }}</p>
                    </div>
                </div>

                <!-- Employment Type & Location -->
                <div class="grid gap-4 sm:grid-cols-2">
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
                    <div class="space-y-2">
                        <Label for="location">Location <span class="text-red-500">*</span></Label>
                        <Input id="location" v-model="form.location" placeholder="e.g. Metro Manila, Remote" />
                        <p v-if="errors.location" class="text-sm text-red-500">{{ errors.location }}</p>
                    </div>
                </div>

                <!-- Salary -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="salary-display">Salary Display</Label>
                        <Select v-model="form.salary_display_option">
                            <SelectTrigger id="salary-display">
                                <SelectValue placeholder="Display option" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in salaryDisplayOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label for="salary-min">Salary Min</Label>
                        <Input id="salary-min" v-model="form.salary_range_min" type="number" min="0" placeholder="0.00" />
                        <p v-if="errors.salary_range_min" class="text-sm text-red-500">{{ errors.salary_range_min }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="salary-max">Salary Max</Label>
                        <Input id="salary-max" v-model="form.salary_range_max" type="number" min="0" placeholder="0.00" />
                        <p v-if="errors.salary_range_max" class="text-sm text-red-500">{{ errors.salary_range_max }}</p>
                    </div>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="description">Description <span class="text-red-500">*</span></Label>
                    <Textarea id="description" v-model="form.description" placeholder="Describe the role and responsibilities..." rows="6" />
                    <p v-if="errors.description" class="text-sm text-red-500">{{ errors.description }}</p>
                </div>

                <!-- Requirements -->
                <div class="space-y-2">
                    <Label for="requirements">Requirements</Label>
                    <Textarea id="requirements" v-model="form.requirements" placeholder="List qualifications and skills needed..." rows="4" />
                </div>

                <!-- Benefits -->
                <div class="space-y-2">
                    <Label for="benefits">Benefits</Label>
                    <Textarea id="benefits" v-model="form.benefits" placeholder="Describe benefits and perks..." rows="3" />
                </div>

                <!-- Application Instructions -->
                <div class="space-y-2">
                    <Label for="instructions">Application Instructions</Label>
                    <Textarea id="instructions" v-model="form.application_instructions" placeholder="How should candidates apply?" rows="3" />
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <Button variant="outline" @click="router.visit('/recruitment/job-postings')">Cancel</Button>
                    <Button @click="handleSubmit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Creating...' : 'Create Draft' }}
                    </Button>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
