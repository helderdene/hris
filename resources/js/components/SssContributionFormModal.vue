<script setup lang="ts">
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

interface SssBracket {
    id?: number;
    min_salary: number;
    max_salary: number | null;
    monthly_salary_credit: number;
    employee_contribution: number;
    employer_contribution: number;
    total_contribution: number;
    ec_contribution: number;
}

interface SssTable {
    id: number;
    effective_from: string;
    description: string | null;
    employee_rate: number;
    employer_rate: number;
    is_active: boolean;
    brackets: SssBracket[];
}

const props = defineProps<{
    sssTable: SssTable | null;
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const isSubmitting = ref(false);
const errors = ref<Record<string, string[]>>({});

const form = ref({
    effective_from: '',
    description: '',
    employee_rate: 0.045,
    employer_rate: 0.095,
    is_active: true,
    brackets: [] as SssBracket[],
});

const isEditing = computed(() => props.sssTable !== null);
const dialogTitle = computed(() =>
    isEditing.value ? 'Edit SSS Contribution Table' : 'Add SSS Contribution Table',
);

watch(
    () => props.sssTable,
    (newValue) => {
        if (newValue) {
            form.value = {
                effective_from: newValue.effective_from,
                description: newValue.description || '',
                employee_rate: newValue.employee_rate,
                employer_rate: newValue.employer_rate,
                is_active: newValue.is_active,
                brackets: newValue.brackets.map((b) => ({ ...b })),
            };
        } else {
            resetForm();
        }
    },
    { immediate: true },
);

watch(open, (newValue) => {
    if (!newValue) {
        errors.value = {};
        if (!props.sssTable) {
            resetForm();
        }
    }
});

function resetForm() {
    form.value = {
        effective_from: new Date().toISOString().split('T')[0],
        description: '',
        employee_rate: 0.045,
        employer_rate: 0.095,
        is_active: true,
        brackets: [],
    };
}

function addBracket() {
    const lastBracket = form.value.brackets[form.value.brackets.length - 1];
    const newMinSalary = lastBracket ? (lastBracket.max_salary || 0) + 0.01 : 0;

    form.value.brackets.push({
        min_salary: newMinSalary,
        max_salary: null,
        monthly_salary_credit: 0,
        employee_contribution: 0,
        employer_contribution: 0,
        total_contribution: 0,
        ec_contribution: 10,
    });
}

function removeBracket(index: number) {
    form.value.brackets.splice(index, 1);
}

function updateTotalContribution(index: number) {
    const bracket = form.value.brackets[index];
    bracket.total_contribution =
        bracket.employee_contribution + bracket.employer_contribution;
}

async function handleSubmit() {
    isSubmitting.value = true;
    errors.value = {};

    const url = isEditing.value
        ? `/api/organization/contributions/sss/${props.sssTable!.id}`
        : '/api/organization/contributions/sss';

    const method = isEditing.value ? 'PUT' : 'POST';

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(form.value),
        });

        if (response.ok) {
            open.value = false;
            emit('success');
        } else {
            const data = await response.json();
            if (data.errors) {
                errors.value = data.errors;
            } else {
                alert(data.message || 'An error occurred');
            }
        }
    } catch (error) {
        alert('An error occurred while saving the SSS contribution table');
    } finally {
        isSubmitting.value = false;
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    {{ isEditing ? 'Update the SSS contribution table and brackets.' : 'Create a new SSS contribution table with brackets.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-6">
                <!-- Basic Info -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="effective_from">Effective Date</Label>
                        <Input
                            id="effective_from"
                            v-model="form.effective_from"
                            type="date"
                            required
                        />
                        <p
                            v-if="errors.effective_from"
                            class="text-sm text-red-500"
                        >
                            {{ errors.effective_from[0] }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <Input
                            id="description"
                            v-model="form.description"
                            type="text"
                            placeholder="e.g., 2025 SSS Contribution Table"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="employee_rate">Employee Rate</Label>
                        <Input
                            id="employee_rate"
                            v-model.number="form.employee_rate"
                            type="number"
                            step="0.0001"
                            min="0"
                            max="1"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="employer_rate">Employer Rate</Label>
                        <Input
                            id="employer_rate"
                            v-model.number="form.employer_rate"
                            type="number"
                            step="0.0001"
                            min="0"
                            max="1"
                        />
                    </div>

                    <div class="flex items-center space-x-2">
                        <input
                            id="is_active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                        />
                        <Label for="is_active">Active</Label>
                    </div>
                </div>

                <!-- Brackets Section -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium">Contribution Brackets</h3>
                        <Button type="button" variant="outline" size="sm" @click="addBracket">
                            Add Bracket
                        </Button>
                    </div>

                    <div v-if="form.brackets.length === 0" class="rounded-lg border border-dashed border-slate-300 p-6 text-center dark:border-slate-700">
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            No brackets added yet. Click "Add Bracket" to add contribution brackets.
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="(bracket, index) in form.brackets"
                            :key="index"
                            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Bracket {{ index + 1 }}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-red-500 hover:text-red-700"
                                    @click="removeBracket(index)"
                                >
                                    Remove
                                </Button>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-4">
                                <div class="space-y-1">
                                    <Label :for="`min_salary_${index}`" class="text-xs">Min Salary</Label>
                                    <Input
                                        :id="`min_salary_${index}`"
                                        v-model.number="bracket.min_salary"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <Label :for="`max_salary_${index}`" class="text-xs">Max Salary</Label>
                                    <Input
                                        :id="`max_salary_${index}`"
                                        v-model.number="bracket.max_salary"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="No max"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <Label :for="`msc_${index}`" class="text-xs">Monthly Salary Credit</Label>
                                    <Input
                                        :id="`msc_${index}`"
                                        v-model.number="bracket.monthly_salary_credit"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <Label :for="`ec_${index}`" class="text-xs">EC Contribution</Label>
                                    <Input
                                        :id="`ec_${index}`"
                                        v-model.number="bracket.ec_contribution"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <Label :for="`ee_${index}`" class="text-xs">Employee Contribution</Label>
                                    <Input
                                        :id="`ee_${index}`"
                                        v-model.number="bracket.employee_contribution"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        @input="updateTotalContribution(index)"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <Label :for="`er_${index}`" class="text-xs">Employer Contribution</Label>
                                    <Input
                                        :id="`er_${index}`"
                                        v-model.number="bracket.employer_contribution"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        @input="updateTotalContribution(index)"
                                    />
                                </div>
                                <div class="space-y-1 sm:col-span-2">
                                    <Label :for="`total_${index}`" class="text-xs">Total Contribution</Label>
                                    <Input
                                        :id="`total_${index}`"
                                        v-model.number="bracket.total_contribution"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        readonly
                                        class="bg-slate-50 dark:bg-slate-800"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
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
                        {{ isSubmitting ? 'Saving...' : isEditing ? 'Update Table' : 'Create Table' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
