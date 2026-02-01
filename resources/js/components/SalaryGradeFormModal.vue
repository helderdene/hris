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
import { computed, ref, watch } from 'vue';

interface SalaryStep {
    id?: number;
    salary_grade_id?: number;
    step_number: number;
    amount: string | number;
    effective_date: string | null;
}

interface SalaryGrade {
    id: number;
    name: string;
    minimum_salary: string;
    midpoint_salary: string;
    maximum_salary: string;
    currency: string;
    status: string;
    steps: SalaryStep[];
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    salaryGrade: SalaryGrade | null;
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    name: '',
    minimum_salary: '',
    midpoint_salary: '',
    maximum_salary: '',
    currency: 'PHP',
    status: 'active',
});

const steps = ref<SalaryStep[]>([]);
const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEditing = computed(() => !!props.salaryGrade);

const statusOptions: EnumOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const currencyOptions: EnumOption[] = [
    { value: 'PHP', label: 'PHP - Philippine Peso' },
    { value: 'USD', label: 'USD - US Dollar' },
    { value: 'EUR', label: 'EUR - Euro' },
    { value: 'GBP', label: 'GBP - British Pound' },
    { value: 'JPY', label: 'JPY - Japanese Yen' },
    { value: 'SGD', label: 'SGD - Singapore Dollar' },
];

const clientValidationErrors = computed(() => {
    const errs: Record<string, string> = {};

    const min = parseFloat(form.value.minimum_salary) || 0;
    const mid = parseFloat(form.value.midpoint_salary) || 0;
    const max = parseFloat(form.value.maximum_salary) || 0;

    if (min && mid && mid < min) {
        errs.midpoint_salary =
            'Midpoint must be greater than or equal to minimum salary';
    }

    if (mid && max && max < mid) {
        errs.maximum_salary =
            'Maximum must be greater than or equal to midpoint salary';
    }

    return errs;
});

watch(
    () => props.salaryGrade,
    (newGrade) => {
        if (newGrade) {
            form.value = {
                name: newGrade.name,
                minimum_salary: newGrade.minimum_salary,
                midpoint_salary: newGrade.midpoint_salary,
                maximum_salary: newGrade.maximum_salary,
                currency: newGrade.currency || 'PHP',
                status: newGrade.status,
            };
            steps.value = (newGrade.steps || []).map((step) => ({
                id: step.id,
                salary_grade_id: step.salary_grade_id,
                step_number: step.step_number,
                amount: step.amount,
                effective_date: step.effective_date,
            }));
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
        name: '',
        minimum_salary: '',
        midpoint_salary: '',
        maximum_salary: '',
        currency: 'PHP',
        status: 'active',
    };
    steps.value = [];
    errors.value = {};
}

function addStep() {
    const nextStepNumber =
        steps.value.length > 0
            ? Math.max(...steps.value.map((s) => s.step_number)) + 1
            : 1;

    steps.value.push({
        step_number: nextStepNumber,
        amount: '',
        effective_date: null,
    });
}

function removeStep(index: number) {
    steps.value.splice(index, 1);
    // Renumber steps
    steps.value.forEach((step, idx) => {
        step.step_number = idx + 1;
    });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    // Check client validation first
    if (Object.keys(clientValidationErrors.value).length > 0) {
        errors.value = { ...clientValidationErrors.value };
        return;
    }

    errors.value = {};
    isSubmitting.value = true;

    const url = isEditing.value
        ? `/api/organization/salary-grades/${props.salaryGrade!.id}`
        : '/api/organization/salary-grades';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        ...form.value,
        minimum_salary: parseFloat(form.value.minimum_salary) || 0,
        midpoint_salary: parseFloat(form.value.midpoint_salary) || 0,
        maximum_salary: parseFloat(form.value.maximum_salary) || 0,
        steps: steps.value
            .filter((step) => step.amount !== '' && step.amount !== null)
            .map((step) => ({
                step_number: step.step_number,
                amount: parseFloat(String(step.amount)) || 0,
                effective_date: step.effective_date || null,
            })),
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
            general: 'An error occurred while saving the salary grade',
        };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Salary Grade' : 'Add Salary Grade' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the salary grade details and steps below.'
                            : 'Fill in the details to create a new salary grade.'
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

                <!-- Name -->
                <div class="space-y-2">
                    <Label for="name">Name *</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g., Grade 1 - Entry Level"
                        :class="{ 'border-red-500': errors.name }"
                    />
                    <p v-if="errors.name" class="text-sm text-red-500">
                        {{ errors.name }}
                    </p>
                </div>

                <!-- Salary Range -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="minimum_salary">Minimum *</Label>
                        <Input
                            id="minimum_salary"
                            v-model="form.minimum_salary"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="50000"
                            :class="{ 'border-red-500': errors.minimum_salary }"
                        />
                        <p
                            v-if="errors.minimum_salary"
                            class="text-sm text-red-500"
                        >
                            {{ errors.minimum_salary }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="midpoint_salary">Midpoint *</Label>
                        <Input
                            id="midpoint_salary"
                            v-model="form.midpoint_salary"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="75000"
                            :class="{
                                'border-red-500':
                                    errors.midpoint_salary ||
                                    clientValidationErrors.midpoint_salary,
                            }"
                        />
                        <p
                            v-if="
                                errors.midpoint_salary ||
                                clientValidationErrors.midpoint_salary
                            "
                            class="text-sm text-red-500"
                        >
                            {{
                                errors.midpoint_salary ||
                                clientValidationErrors.midpoint_salary
                            }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="maximum_salary">Maximum *</Label>
                        <Input
                            id="maximum_salary"
                            v-model="form.maximum_salary"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="100000"
                            :class="{
                                'border-red-500':
                                    errors.maximum_salary ||
                                    clientValidationErrors.maximum_salary,
                            }"
                        />
                        <p
                            v-if="
                                errors.maximum_salary ||
                                clientValidationErrors.maximum_salary
                            "
                            class="text-sm text-red-500"
                        >
                            {{
                                errors.maximum_salary ||
                                clientValidationErrors.maximum_salary
                            }}
                        </p>
                    </div>
                </div>

                <!-- Currency & Status -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="currency">Currency</Label>
                        <EnumSelect
                            id="currency"
                            v-model="form.currency"
                            :options="currencyOptions"
                            placeholder="Select currency"
                        />
                        <p v-if="errors.currency" class="text-sm text-red-500">
                            {{ errors.currency }}
                        </p>
                    </div>
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
                </div>

                <!-- Salary Steps Section -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <Label class="text-base">Salary Steps</Label>
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                Define step increments within this salary grade.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addStep"
                        >
                            <svg
                                class="mr-1.5 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 4.5v15m7.5-7.5h-15"
                                />
                            </svg>
                            Add Step
                        </Button>
                    </div>

                    <div
                        v-if="steps.length === 0"
                        class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center dark:border-slate-600"
                    >
                        <svg
                            class="mx-auto h-8 w-8 text-slate-400 dark:text-slate-500"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"
                            />
                        </svg>
                        <p
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            No steps defined. Click "Add Step" to add salary
                            increments.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="(step, index) in steps"
                            :key="index"
                            class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50"
                        >
                            <div
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                            >
                                {{ step.step_number }}
                            </div>
                            <div
                                class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2"
                            >
                                <div>
                                    <Label
                                        :for="`step-amount-${index}`"
                                        class="text-xs"
                                        >Amount *</Label
                                    >
                                    <Input
                                        :id="`step-amount-${index}`"
                                        v-model="step.amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="60000"
                                        class="mt-1"
                                        :class="{
                                            'border-red-500':
                                                errors[`steps.${index}.amount`],
                                        }"
                                    />
                                    <p
                                        v-if="errors[`steps.${index}.amount`]"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ errors[`steps.${index}.amount`] }}
                                    </p>
                                </div>
                                <div>
                                    <Label
                                        :for="`step-date-${index}`"
                                        class="text-xs"
                                        >Effective Date</Label
                                    >
                                    <Input
                                        :id="`step-date-${index}`"
                                        v-model="step.effective_date"
                                        type="date"
                                        class="mt-1"
                                        :class="{
                                            'border-red-500':
                                                errors[
                                                    `steps.${index}.effective_date`
                                                ],
                                        }"
                                    />
                                    <p
                                        v-if="
                                            errors[
                                                `steps.${index}.effective_date`
                                            ]
                                        "
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{
                                            errors[
                                                `steps.${index}.effective_date`
                                            ]
                                        }}
                                    </p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 shrink-0 p-0 text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400"
                                @click="removeStep(index)"
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
                                        d="M6 18 18 6M6 6l12 12"
                                    />
                                </svg>
                                <span class="sr-only">Remove step</span>
                            </Button>
                        </div>
                    </div>
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
                    <Button
                        type="submit"
                        :disabled="
                            isSubmitting ||
                            Object.keys(clientValidationErrors).length > 0
                        "
                    >
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
                        {{
                            isEditing
                                ? 'Update Salary Grade'
                                : 'Create Salary Grade'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
