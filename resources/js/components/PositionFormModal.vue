<script setup lang="ts">
import EnumSelect from '@/components/EnumSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { computed, ref, watch } from 'vue';

interface SalaryGrade {
    id: number;
    name: string;
    minimum_salary: string;
    midpoint_salary: string;
    maximum_salary: string;
    currency: string;
}

interface Position {
    id: number;
    title: string;
    code: string;
    description: string | null;
    salary_grade_id: number | null;
    salary_grade: SalaryGrade | null;
    job_level: string | null;
    job_level_label: string | null;
    employment_type: string | null;
    employment_type_label: string | null;
    status: string;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    position: Position | null;
    salaryGrades: SalaryGrade[];
    jobLevels: EnumOption[];
    employmentTypes: EnumOption[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    title: '',
    code: '',
    description: '',
    salary_grade_id: '' as string | number,
    job_level: '',
    employment_type: '',
    status: 'active',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEditing = computed(() => !!props.position);

const statusOptions: EnumOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const salaryGradeOptions = computed<EnumOption[]>(() => {
    return [
        { value: '', label: 'No Salary Grade' },
        ...(props.salaryGrades || []).map((grade) => ({
            value: grade.id.toString(),
            label: `${grade.name} (${formatCurrency(grade.minimum_salary, grade.currency)} - ${formatCurrency(grade.maximum_salary, grade.currency)})`,
        })),
    ];
});

const selectedSalaryGrade = computed(() => {
    if (!form.value.salary_grade_id) return null;
    return (
        (props.salaryGrades || []).find(
            (g) => g.id.toString() === form.value.salary_grade_id.toString(),
        ) || null
    );
});

watch(
    () => props.position,
    (newPosition) => {
        if (newPosition) {
            form.value = {
                title: newPosition.title,
                code: newPosition.code,
                description: newPosition.description || '',
                salary_grade_id: newPosition.salary_grade_id?.toString() || '',
                job_level: newPosition.job_level || '',
                employment_type: newPosition.employment_type || '',
                status: newPosition.status,
            };
        } else {
            resetForm();
        }
        errors.value = {};
    },
    { immediate: true },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        errors.value = {};
    }
});

function resetForm() {
    form.value = {
        title: '',
        code: '',
        description: '',
        salary_grade_id: '',
        job_level: '',
        employment_type: '',
        status: 'active',
    };
    errors.value = {};
}

function formatCurrency(
    amount: string | null,
    currency: string | null,
): string {
    if (!amount) return '-';
    const num = parseFloat(amount);
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    const url = isEditing.value
        ? `/api/organization/positions/${props.position!.id}`
        : '/api/organization/positions';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        ...form.value,
        salary_grade_id: form.value.salary_grade_id
            ? parseInt(form.value.salary_grade_id.toString())
            : null,
        description: form.value.description || null,
        job_level: form.value.job_level || null,
        employment_type: form.value.employment_type || null,
    };

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (response.ok) {
            emit('success');
        } else if (response.status === 422 && data.errors) {
            errors.value = Object.fromEntries(
                Object.entries(data.errors).map(([key, value]) => [
                    key,
                    (value as string[])[0],
                ]),
            );
        } else {
            errors.value = { general: data.message || 'An error occurred' };
        }
    } catch (error) {
        errors.value = {
            general: 'An error occurred while saving the position',
        };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Position' : 'Add Position' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the position details below.'
                            : 'Fill in the details to create a new position.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- General Error -->
                <div
                    v-if="errors.general"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
                >
                    {{ errors.general }}
                </div>

                <!-- Title -->
                <div class="space-y-2">
                    <Label for="title">Title *</Label>
                    <Input
                        id="title"
                        v-model="form.title"
                        type="text"
                        placeholder="e.g., Software Engineer"
                        :class="{ 'border-red-500': errors.title }"
                    />
                    <p v-if="errors.title" class="text-sm text-red-500">
                        {{ errors.title }}
                    </p>
                </div>

                <!-- Code -->
                <div class="space-y-2">
                    <Label for="code">Code *</Label>
                    <Input
                        id="code"
                        v-model="form.code"
                        type="text"
                        placeholder="e.g., SE-001"
                        :class="{ 'border-red-500': errors.code }"
                    />
                    <p v-if="errors.code" class="text-sm text-red-500">
                        {{ errors.code }}
                    </p>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Position description..."
                        rows="3"
                        :class="{ 'border-red-500': errors.description }"
                    />
                    <p v-if="errors.description" class="text-sm text-red-500">
                        {{ errors.description }}
                    </p>
                </div>

                <!-- Job Level -->
                <div class="space-y-2">
                    <Label for="job_level">Job Level</Label>
                    <EnumSelect
                        id="job_level"
                        v-model="form.job_level"
                        :options="jobLevels"
                        placeholder="Select job level"
                    />
                    <p v-if="errors.job_level" class="text-sm text-red-500">
                        {{ errors.job_level }}
                    </p>
                </div>

                <!-- Employment Type -->
                <div class="space-y-2">
                    <Label for="employment_type">Employment Type</Label>
                    <EnumSelect
                        id="employment_type"
                        v-model="form.employment_type"
                        :options="employmentTypes"
                        placeholder="Select employment type"
                    />
                    <p
                        v-if="errors.employment_type"
                        class="text-sm text-red-500"
                    >
                        {{ errors.employment_type }}
                    </p>
                </div>

                <!-- Salary Grade -->
                <div class="space-y-2">
                    <Label for="salary_grade_id">Salary Grade</Label>
                    <EnumSelect
                        id="salary_grade_id"
                        v-model="form.salary_grade_id"
                        :options="salaryGradeOptions"
                        placeholder="Select salary grade"
                    />
                    <p
                        v-if="errors.salary_grade_id"
                        class="text-sm text-red-500"
                    >
                        {{ errors.salary_grade_id }}
                    </p>
                    <div
                        v-if="selectedSalaryGrade"
                        class="rounded-md bg-slate-50 p-3 text-sm dark:bg-slate-800"
                    >
                        <div
                            class="font-medium text-slate-900 dark:text-slate-100"
                        >
                            {{ selectedSalaryGrade.name }}
                        </div>
                        <div class="mt-1 text-slate-600 dark:text-slate-400">
                            Range:
                            {{
                                formatCurrency(
                                    selectedSalaryGrade.minimum_salary,
                                    selectedSalaryGrade.currency,
                                )
                            }}
                            -
                            {{
                                formatCurrency(
                                    selectedSalaryGrade.maximum_salary,
                                    selectedSalaryGrade.currency,
                                )
                            }}
                        </div>
                        <div class="text-slate-600 dark:text-slate-400">
                            Midpoint:
                            {{
                                formatCurrency(
                                    selectedSalaryGrade.midpoint_salary,
                                    selectedSalaryGrade.currency,
                                )
                            }}
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="space-y-2">
                    <Label for="status">Status *</Label>
                    <EnumSelect
                        id="status"
                        v-model="form.status"
                        :options="statusOptions"
                        placeholder="Select status"
                    />
                    <p v-if="errors.status" class="text-sm text-red-500">
                        {{ errors.status }}
                    </p>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        <svg
                            v-if="isSubmitting"
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
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        {{ isEditing ? 'Update Position' : 'Create Position' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
