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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { computed, ref, watch } from 'vue';

interface Employee {
    id: number;
    employee_number: string;
    full_name: string;
}

interface LoanTypeOption {
    value: string;
    label: string;
}

interface Loan {
    id: number;
    employee_id: number;
    loan_type: string;
    loan_code: string;
    reference_number: string | null;
    principal_amount: number;
    interest_rate: number;
    monthly_deduction: number;
    term_months: number | null;
    total_amount: number;
    start_date: string;
    expected_end_date: string | null;
    notes: string | null;
}

const props = defineProps<{
    loan: Loan | null;
    employees: Employee[];
    loanTypes: Record<string, LoanTypeOption[]>;
}>();

const emit = defineEmits<{
    success: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref({
    employee_id: '',
    loan_type: '',
    loan_code: '',
    reference_number: '',
    principal_amount: '',
    interest_rate: '0',
    monthly_deduction: '',
    term_months: '',
    total_amount: '',
    start_date: '',
    expected_end_date: '',
    notes: '',
});

const isEditing = computed(() => props.loan !== null);
const dialogTitle = computed(() =>
    isEditing.value ? 'Edit Loan' : 'Add New Loan',
);

watch(
    () => props.loan,
    (loan) => {
        if (loan) {
            form.value = {
                employee_id: String(loan.employee_id),
                loan_type: loan.loan_type,
                loan_code: loan.loan_code,
                reference_number: loan.reference_number || '',
                principal_amount: String(loan.principal_amount),
                interest_rate: String(loan.interest_rate),
                monthly_deduction: String(loan.monthly_deduction),
                term_months: loan.term_months ? String(loan.term_months) : '',
                total_amount: String(loan.total_amount),
                start_date: loan.start_date,
                expected_end_date: loan.expected_end_date || '',
                notes: loan.notes || '',
            };
        } else {
            resetForm();
        }
    },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        errors.value = {};
    } else if (!props.loan) {
        resetForm();
    }
});

function resetForm() {
    form.value = {
        employee_id: '',
        loan_type: '',
        loan_code: '',
        reference_number: '',
        principal_amount: '',
        interest_rate: '0',
        monthly_deduction: '',
        term_months: '',
        total_amount: '',
        start_date: new Date().toISOString().split('T')[0],
        expected_end_date: '',
        notes: '',
    };
}

function generateLoanCode() {
    if (form.value.loan_type && !form.value.loan_code) {
        const typeCode = form.value.loan_type.split('_')[0].toUpperCase();
        const random = Math.random().toString(36).substring(2, 6).toUpperCase();
        form.value.loan_code = `${typeCode}-${random}`;
    }
}

function calculateTotalAmount() {
    const principal = parseFloat(form.value.principal_amount) || 0;
    const rate = parseFloat(form.value.interest_rate) || 0;
    const term = parseInt(form.value.term_months) || 12;

    const total = principal * (1 + rate * (term / 12));
    form.value.total_amount = total.toFixed(2);
}

function calculateMonthlyDeduction() {
    const total = parseFloat(form.value.total_amount) || 0;
    const term = parseInt(form.value.term_months) || 12;

    if (total > 0 && term > 0) {
        form.value.monthly_deduction = (total / term).toFixed(2);
    }
}

function calculateExpectedEndDate() {
    if (form.value.start_date && form.value.term_months) {
        const startDate = new Date(form.value.start_date);
        const termMonths = parseInt(form.value.term_months) || 0;
        startDate.setMonth(startDate.getMonth() + termMonths);
        form.value.expected_end_date = startDate.toISOString().split('T')[0];
    }
}

watch(
    () => form.value.loan_type,
    () => {
        generateLoanCode();
    },
);

watch(
    () => [
        form.value.principal_amount,
        form.value.interest_rate,
        form.value.term_months,
    ],
    () => {
        calculateTotalAmount();
        calculateMonthlyDeduction();
        calculateExpectedEndDate();
    },
);

async function handleSubmit() {
    isLoading.value = true;
    errors.value = {};

    const url = isEditing.value ? `/api/loans/${props.loan?.id}` : '/api/loans';
    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        employee_id: parseInt(form.value.employee_id),
        loan_type: form.value.loan_type,
        loan_code: form.value.loan_code,
        reference_number: form.value.reference_number || null,
        principal_amount: parseFloat(form.value.principal_amount),
        interest_rate: parseFloat(form.value.interest_rate),
        monthly_deduction: parseFloat(form.value.monthly_deduction),
        term_months: form.value.term_months
            ? parseInt(form.value.term_months)
            : null,
        total_amount: parseFloat(form.value.total_amount),
        start_date: form.value.start_date,
        expected_end_date: form.value.expected_end_date || null,
        notes: form.value.notes || null,
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

        if (response.ok) {
            emit('success');
        } else {
            const data = await response.json();
            if (data.errors) {
                errors.value = data.errors;
            } else {
                errors.value = { _form: data.message || 'An error occurred' };
            }
        }
    } catch {
        errors.value = { _form: 'An error occurred. Please try again.' };
    } finally {
        isLoading.value = false;
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the loan details below.'
                            : 'Fill in the loan details to add a new employee loan.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div
                    v-if="errors._form"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400"
                >
                    {{ errors._form }}
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Employee -->
                    <div class="space-y-2">
                        <Label for="employee_id">Employee</Label>
                        <Select
                            v-model="form.employee_id"
                            :disabled="isEditing"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select employee" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="emp in employees"
                                    :key="emp.id"
                                    :value="String(emp.id)"
                                >
                                    {{ emp.full_name }} ({{ emp.employee_number
                                    }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="errors.employee_id"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.employee_id[0] }}
                        </p>
                    </div>

                    <!-- Loan Type -->
                    <div class="space-y-2">
                        <Label for="loan_type">Loan Type</Label>
                        <Select v-model="form.loan_type">
                            <SelectTrigger>
                                <SelectValue placeholder="Select loan type" />
                            </SelectTrigger>
                            <SelectContent>
                                <template
                                    v-for="(types, category) in loanTypes"
                                    :key="category"
                                >
                                    <div
                                        class="px-2 py-1.5 text-xs font-semibold text-slate-500"
                                    >
                                        {{ category }}
                                    </div>
                                    <SelectItem
                                        v-for="type in types"
                                        :key="type.value"
                                        :value="type.value"
                                    >
                                        {{ type.label }}
                                    </SelectItem>
                                </template>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="errors.loan_type"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.loan_type[0] }}
                        </p>
                    </div>

                    <!-- Loan Code -->
                    <div class="space-y-2">
                        <Label for="loan_code">Loan Code</Label>
                        <Input
                            id="loan_code"
                            v-model="form.loan_code"
                            placeholder="e.g., SSS-A1B2"
                        />
                        <p
                            v-if="errors.loan_code"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.loan_code[0] }}
                        </p>
                    </div>

                    <!-- Reference Number -->
                    <div class="space-y-2">
                        <Label for="reference_number"
                            >Reference Number (Optional)</Label
                        >
                        <Input
                            id="reference_number"
                            v-model="form.reference_number"
                            placeholder="External reference"
                        />
                    </div>

                    <!-- Principal Amount -->
                    <div class="space-y-2">
                        <Label for="principal_amount">Principal Amount</Label>
                        <Input
                            id="principal_amount"
                            v-model="form.principal_amount"
                            type="number"
                            step="0.01"
                            min="1"
                            placeholder="0.00"
                        />
                        <p
                            v-if="errors.principal_amount"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.principal_amount[0] }}
                        </p>
                    </div>

                    <!-- Interest Rate -->
                    <div class="space-y-2">
                        <Label for="interest_rate"
                            >Interest Rate (Annual)</Label
                        >
                        <Input
                            id="interest_rate"
                            v-model="form.interest_rate"
                            type="number"
                            step="0.0001"
                            min="0"
                            max="1"
                            placeholder="0.10 for 10%"
                        />
                        <p class="text-xs text-slate-500">
                            Enter as decimal (e.g., 0.10 for 10%)
                        </p>
                    </div>

                    <!-- Term Months -->
                    <div class="space-y-2">
                        <Label for="term_months">Term (Months)</Label>
                        <Input
                            id="term_months"
                            v-model="form.term_months"
                            type="number"
                            min="1"
                            max="600"
                            placeholder="12"
                        />
                    </div>

                    <!-- Total Amount -->
                    <div class="space-y-2">
                        <Label for="total_amount">Total Amount</Label>
                        <Input
                            id="total_amount"
                            v-model="form.total_amount"
                            type="number"
                            step="0.01"
                            min="1"
                            placeholder="0.00"
                        />
                        <p
                            v-if="errors.total_amount"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.total_amount[0] }}
                        </p>
                    </div>

                    <!-- Monthly Deduction -->
                    <div class="space-y-2">
                        <Label for="monthly_deduction">Monthly Deduction</Label>
                        <Input
                            id="monthly_deduction"
                            v-model="form.monthly_deduction"
                            type="number"
                            step="0.01"
                            min="1"
                            placeholder="0.00"
                        />
                        <p
                            v-if="errors.monthly_deduction"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.monthly_deduction[0] }}
                        </p>
                    </div>

                    <!-- Start Date -->
                    <div class="space-y-2">
                        <Label for="start_date">Start Date</Label>
                        <Input
                            id="start_date"
                            v-model="form.start_date"
                            type="date"
                        />
                        <p
                            v-if="errors.start_date"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.start_date[0] }}
                        </p>
                    </div>

                    <!-- Expected End Date -->
                    <div class="space-y-2">
                        <Label for="expected_end_date">Expected End Date</Label>
                        <Input
                            id="expected_end_date"
                            v-model="form.expected_end_date"
                            type="date"
                        />
                    </div>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="notes">Notes (Optional)</Label>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Additional notes about this loan"
                        rows="2"
                    />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="isLoading"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isLoading">
                        {{ isLoading ? 'Saving...' : isEditing ? 'Update' : 'Create' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
