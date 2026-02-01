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

const props = defineProps<{
    posting: {
        id: number;
        title: string;
        department_id: number;
        position_id: number | null;
        description: string;
        requirements: string | null;
        benefits: string | null;
        employment_type: string;
        location: string;
        salary_display_option: string | null;
        salary_range_min: number | null;
        salary_range_max: number | null;
        application_instructions: string | null;
        status: string;
        can_be_edited: boolean;
    };
    departments: { id: number; name: string }[];
    positions: { id: number; name: string }[];
    employmentTypes: { value: string; label: string }[];
    salaryDisplayOptions: { value: string; label: string }[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/job-postings' },
    { title: 'Job Postings', href: '/recruitment/job-postings' },
    { title: 'Edit', href: `/recruitment/job-postings/${props.posting.id}/edit` },
];

const form = ref({
    title: props.posting.title,
    department_id: String(props.posting.department_id),
    position_id: props.posting.position_id ? String(props.posting.position_id) : '',
    description: props.posting.description,
    requirements: props.posting.requirements || '',
    benefits: props.posting.benefits || '',
    employment_type: props.posting.employment_type,
    location: props.posting.location,
    salary_display_option: props.posting.salary_display_option || 'hidden',
    salary_range_min: props.posting.salary_range_min ? String(props.posting.salary_range_min) : '',
    salary_range_max: props.posting.salary_range_max ? String(props.posting.salary_range_max) : '',
    application_instructions: props.posting.application_instructions || '',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    isSubmitting.value = true;
    errors.value = {};

    const body: Record<string, any> = {
        title: form.value.title,
        department_id: Number(form.value.department_id),
        description: form.value.description,
        employment_type: form.value.employment_type,
        location: form.value.location,
        salary_display_option: form.value.salary_display_option,
        requirements: form.value.requirements || null,
        benefits: form.value.benefits || null,
        application_instructions: form.value.application_instructions || null,
    };

    if (form.value.position_id) body.position_id = Number(form.value.position_id);
    if (form.value.salary_range_min) body.salary_range_min = Number(form.value.salary_range_min);
    if (form.value.salary_range_max) body.salary_range_max = Number(form.value.salary_range_max);

    try {
        const response = await fetch(`/api/job-postings/${props.posting.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        if (response.ok) {
            router.visit(`/recruitment/job-postings/${props.posting.id}`);
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
    <Head :title="`Edit ${posting.title} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl">
            <div class="mb-6">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Edit Job Posting</h1>
            </div>

            <div class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                <div class="space-y-2">
                    <Label for="title">Job Title <span class="text-red-500">*</span></Label>
                    <Input id="title" v-model="form.title" />
                    <p v-if="errors.title" class="text-sm text-red-500">{{ errors.title }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="department">Department <span class="text-red-500">*</span></Label>
                        <Select v-model="form.department_id">
                            <SelectTrigger id="department"><SelectValue placeholder="Select department" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="dept in departments" :key="dept.id" :value="String(dept.id)">{{ dept.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.department_id" class="text-sm text-red-500">{{ errors.department_id }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="position">Position</Label>
                        <Select v-model="form.position_id">
                            <SelectTrigger id="position"><SelectValue placeholder="Select position" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="pos in positions" :key="pos.id" :value="String(pos.id)">{{ pos.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="employment-type">Employment Type <span class="text-red-500">*</span></Label>
                        <Select v-model="form.employment_type">
                            <SelectTrigger id="employment-type"><SelectValue placeholder="Select type" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="et in employmentTypes" :key="et.value" :value="et.value">{{ et.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.employment_type" class="text-sm text-red-500">{{ errors.employment_type }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="location">Location <span class="text-red-500">*</span></Label>
                        <Input id="location" v-model="form.location" />
                        <p v-if="errors.location" class="text-sm text-red-500">{{ errors.location }}</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="salary-display">Salary Display</Label>
                        <Select v-model="form.salary_display_option">
                            <SelectTrigger id="salary-display"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in salaryDisplayOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label for="salary-min">Salary Min</Label>
                        <Input id="salary-min" v-model="form.salary_range_min" type="number" min="0" />
                    </div>
                    <div class="space-y-2">
                        <Label for="salary-max">Salary Max</Label>
                        <Input id="salary-max" v-model="form.salary_range_max" type="number" min="0" />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="description">Description <span class="text-red-500">*</span></Label>
                    <Textarea id="description" v-model="form.description" rows="6" />
                    <p v-if="errors.description" class="text-sm text-red-500">{{ errors.description }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="requirements">Requirements</Label>
                    <Textarea id="requirements" v-model="form.requirements" rows="4" />
                </div>

                <div class="space-y-2">
                    <Label for="benefits">Benefits</Label>
                    <Textarea id="benefits" v-model="form.benefits" rows="3" />
                </div>

                <div class="space-y-2">
                    <Label for="instructions">Application Instructions</Label>
                    <Textarea id="instructions" v-model="form.application_instructions" rows="3" />
                </div>

                <div class="flex justify-end gap-3">
                    <Button variant="outline" @click="router.visit(`/recruitment/job-postings/${posting.id}`)">Cancel</Button>
                    <Button @click="handleSubmit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
