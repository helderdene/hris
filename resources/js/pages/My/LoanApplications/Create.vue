<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
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

interface LoanTypeOption {
    value: string;
    label: string;
}

const props = defineProps<{
    employee: Employee;
    loanTypes: Record<string, LoanTypeOption[]>;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'My Loan Applications', href: '/my/loan-applications' },
    { title: 'New Application', href: '/my/loan-applications/create' },
];

const form = ref({
    loan_type: '',
    amount_requested: '',
    term_months: '',
    purpose: '',
});

const documents = ref<File[]>([]);
const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files) {
        documents.value = Array.from(target.files);
    }
}

async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    const formData = new FormData();
    formData.append('employee_id', String(props.employee.id));
    formData.append('loan_type', form.value.loan_type);
    formData.append('amount_requested', form.value.amount_requested);
    formData.append('term_months', form.value.term_months);
    formData.append('purpose', form.value.purpose);

    documents.value.forEach((file, index) => {
        formData.append(`documents[${index}]`, file);
    });

    try {
        const response = await fetch('/api/loan-applications', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: formData,
        });

        if (response.ok) {
            router.visit('/my/loan-applications');
        } else if (response.status === 422) {
            const data = await response.json();
            errors.value = data.errors || {};
        } else {
            const data = await response.json();
        }
    } catch {
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Head :title="`New Loan Application - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    New Loan Application
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Fill out the form below to submit a loan request.
                </p>
            </div>

            <!-- Form -->
            <form @submit.prevent="handleSubmit" class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                <!-- Loan Type -->
                <div class="space-y-2">
                    <Label for="loan_type">Loan Type</Label>
                    <Select v-model="form.loan_type">
                        <SelectTrigger id="loan_type">
                            <SelectValue placeholder="Select a loan type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup v-for="(options, category) in loanTypes" :key="category">
                                <SelectLabel>{{ category }}</SelectLabel>
                                <SelectItem
                                    v-for="option in options"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <p v-if="errors.loan_type" class="text-sm text-red-600 dark:text-red-400">
                        {{ errors.loan_type }}
                    </p>
                </div>

                <!-- Amount Requested -->
                <div class="space-y-2">
                    <Label for="amount_requested">Amount Requested (PHP)</Label>
                    <Input
                        id="amount_requested"
                        v-model="form.amount_requested"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                    />
                    <p v-if="errors.amount_requested" class="text-sm text-red-600 dark:text-red-400">
                        {{ errors.amount_requested }}
                    </p>
                </div>

                <!-- Term in Months -->
                <div class="space-y-2">
                    <Label for="term_months">Term in Months</Label>
                    <Input
                        id="term_months"
                        v-model="form.term_months"
                        type="number"
                        min="1"
                        placeholder="e.g. 12"
                    />
                    <p v-if="errors.term_months" class="text-sm text-red-600 dark:text-red-400">
                        {{ errors.term_months }}
                    </p>
                </div>

                <!-- Purpose -->
                <div class="space-y-2">
                    <Label for="purpose">Purpose</Label>
                    <Textarea
                        id="purpose"
                        v-model="form.purpose"
                        rows="4"
                        placeholder="Describe the purpose of this loan"
                    />
                    <p v-if="errors.purpose" class="text-sm text-red-600 dark:text-red-400">
                        {{ errors.purpose }}
                    </p>
                </div>

                <!-- Documents -->
                <div class="space-y-2">
                    <Label for="documents">Supporting Documents</Label>
                    <Input
                        id="documents"
                        type="file"
                        multiple
                        @change="handleFileChange"
                        class="file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-700 dark:file:text-slate-300 dark:hover:file:bg-slate-600"
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Upload any supporting documents (optional). Accepted formats: PDF, JPG, PNG.
                    </p>
                    <p v-if="errors.documents" class="text-sm text-red-600 dark:text-red-400">
                        {{ errors.documents }}
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-slate-700">
                    <Link href="/my/loan-applications">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                    <Button
                        type="submit"
                        :disabled="isSubmitting"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        {{ isSubmitting ? 'Creating...' : 'Create Application' }}
                    </Button>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
