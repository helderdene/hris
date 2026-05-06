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
import { computed, ref } from 'vue';

interface Employee {
    id: number;
    employee_number: string;
    full_name: string;
    department: string | null;
    position: string | null;
    employment_type: string | null;
    employment_type_label: string | null;
}

interface LoanTypeOption {
    value: string;
    label: string;
}

interface DeductionScheduleOption {
    value: string;
    label: string;
}

const props = defineProps<{
    employee: Employee;
    loanTypes: Record<string, LoanTypeOption[]>;
    deductionSchedules: DeductionScheduleOption[];
    termOptions: number[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'My Loan Applications', href: '/my/loan-applications' },
    { title: 'New Application', href: '/my/loan-applications/create' },
];

const dateFiled = computed(() =>
    new Date().toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }),
);

const form = ref({
    loan_type: '',
    purpose: '',
    amount_requested: '',
    term_months: '',
    deduction_schedule: '',
    urgency_level: '3',
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

function urgencyLabel(level: number): string {
    const labels: Record<number, string> = {
        1: 'Low',
        2: 'Somewhat Low',
        3: 'Medium',
        4: 'Somewhat High',
        5: 'High',
    };
    return labels[level] ?? '';
}

async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    const formData = new FormData();
    formData.append('employee_id', String(props.employee.id));
    formData.append('loan_type', form.value.loan_type);
    formData.append('amount_requested', form.value.amount_requested);
    formData.append('term_months', form.value.term_months);
    formData.append('deduction_schedule', form.value.deduction_schedule);
    formData.append('urgency_level', form.value.urgency_level);
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
            const flat: Record<string, string> = {};
            for (const [key, value] of Object.entries(data.errors ?? {})) {
                flat[key] = Array.isArray(value) ? value[0] : String(value);
            }
            errors.value = flat;
        }
    } catch {
        errors.value = { _form: 'An unexpected error occurred. Please try again.' };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Head :title="`New Loan Application - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    New Loan Application
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Fill out the form below to submit a loan request.
                </p>
            </div>

            <form
                @submit.prevent="handleSubmit"
                class="space-y-8 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
            >
                <div
                    v-if="errors._form"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"
                >
                    {{ errors._form }}
                </div>

                <!-- I. Employee Information -->
                <section class="space-y-3">
                    <header class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                            I. Employee Information
                        </h2>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            Inherited from your employee record
                        </span>
                    </header>

                    <dl
                        class="grid gap-x-4 gap-y-3 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-800/40"
                    >
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Date Filed
                            </dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ dateFiled }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Employee ID
                            </dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ employee.employee_number ?? '—' }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Employee Name
                            </dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ employee.full_name ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Department
                            </dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ employee.department ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Position
                            </dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ employee.position ?? '—' }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Employment Status
                            </dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ employee.employment_type_label ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <!-- II. Loan Details -->
                <section class="space-y-4">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                        II. Loan Details
                    </h2>

                    <div class="space-y-2">
                        <Label for="loan_type">
                            Type of Loan <span class="text-red-500">*</span>
                        </Label>
                        <Select v-model="form.loan_type">
                            <SelectTrigger id="loan_type">
                                <SelectValue placeholder="Select a loan type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup
                                    v-for="(options, category) in loanTypes"
                                    :key="category"
                                >
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
                        <p
                            v-if="errors.loan_type"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.loan_type }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="purpose">
                            Loan Purpose <span class="text-red-500">*</span>
                        </Label>
                        <Textarea
                            id="purpose"
                            v-model="form.purpose"
                            rows="3"
                            placeholder="Brief explanation of why you need this loan..."
                        />
                        <p
                            v-if="errors.purpose"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.purpose }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="amount_requested">
                            Loan Amount (PHP)
                            <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="amount_requested"
                            v-model="form.amount_requested"
                            type="number"
                            min="1"
                            step="0.01"
                            placeholder="0.00"
                        />
                        <p
                            v-if="errors.amount_requested"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.amount_requested }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="term_months">
                            Preferred Repayment (in months)
                            <span class="text-red-500">*</span>
                        </Label>
                        <Select v-model="form.term_months">
                            <SelectTrigger id="term_months">
                                <SelectValue placeholder="Select a term" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in termOptions"
                                    :key="option"
                                    :value="String(option)"
                                >
                                    {{ option }} months
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="errors.term_months"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.term_months }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="deduction_schedule">
                            Preferred Deduction Schedule
                            <span class="text-red-500">*</span>
                        </Label>
                        <Select v-model="form.deduction_schedule">
                            <SelectTrigger id="deduction_schedule">
                                <SelectValue placeholder="Select a schedule" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in deductionSchedules"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="errors.deduction_schedule"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.deduction_schedule }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="urgency_level">
                            Level of Urgency
                            <span class="text-red-500">*</span>
                        </Label>
                        <div class="flex items-center gap-3">
                            <input
                                id="urgency_level"
                                v-model="form.urgency_level"
                                type="range"
                                min="1"
                                max="5"
                                step="1"
                                class="flex-1 accent-slate-900 dark:accent-slate-100"
                            />
                            <span
                                class="min-w-[7rem] text-right text-sm text-slate-700 dark:text-slate-200"
                            >
                                {{ form.urgency_level }} —
                                {{ urgencyLabel(Number(form.urgency_level)) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>1 (Low)</span>
                            <span>5 (High)</span>
                        </div>
                        <p
                            v-if="errors.urgency_level"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.urgency_level }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="documents">Upload Supporting Docs</Label>
                        <Input
                            id="documents"
                            type="file"
                            multiple
                            @change="handleFileChange"
                            class="file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-700 dark:file:text-slate-300 dark:hover:file:bg-slate-600"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Optional. Accepted formats: PDF, JPG, PNG. Max 10MB
                            each.
                        </p>
                        <p
                            v-if="errors.documents"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.documents }}
                        </p>
                    </div>
                </section>

                <div
                    class="flex items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-slate-700"
                >
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
