<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

interface PayrollPeriod {
    id: number;
    name: string;
    cutoff_start: string;
    cutoff_end: string;
    year: number;
}

interface AdjustmentTypeOption {
    value: string;
    label: string;
}

interface RecurringIntervalOption {
    value: string;
    label: string;
    description: string;
}

interface Adjustment {
    id: number;
    employee_id: number;
    adjustment_type: string;
    adjustment_code: string;
    name: string;
    description: string | null;
    amount: number;
    is_taxable: boolean;
    frequency: string;
    recurring_start_date: string | null;
    recurring_end_date: string | null;
    recurring_interval: string | null;
    remaining_occurrences: number | null;
    has_balance_tracking: boolean;
    total_amount: number | null;
    target_payroll_period_id: number | null;
    notes: string | null;
}

const props = defineProps<{
    adjustment: Adjustment | null;
    employees: Employee[];
    payrollPeriods: PayrollPeriod[];
    adjustmentTypes: Record<string, Record<string, AdjustmentTypeOption[]>>;
    recurringIntervals: RecurringIntervalOption[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const isLoading = ref(false);
const errors = ref<Record<string, string | string[]>>({});

const form = ref({
    employee_id: '',
    adjustment_type: '',
    adjustment_code: '',
    name: '',
    description: '',
    amount: '',
    is_taxable: true,
    frequency: 'one_time',
    recurring_start_date: '',
    recurring_end_date: '',
    recurring_interval: 'every_period',
    remaining_occurrences: '',
    has_balance_tracking: false,
    total_amount: '',
    target_payroll_period_id: '',
    notes: '',
});

const isEditing = computed(() => props.adjustment !== null);
const dialogTitle = computed(() =>
    isEditing.value ? 'Edit Adjustment' : 'Add New Adjustment',
);
const isRecurring = computed(() => form.value.frequency === 'recurring');
const showBalanceTracking = computed(() => {
    const type = form.value.adjustment_type;
    return type.startsWith('loan_');
});

const selectedYear = ref<string>(String(new Date().getFullYear()));

const availableYears = computed(() => {
    const years = [...new Set(props.payrollPeriods.map((p) => p.year))];
    return years.sort((a, b) => b - a);
});

const filteredPayrollPeriods = computed(() => {
    if (!selectedYear.value) return [];
    return props.payrollPeriods.filter(
        (p) => p.year === parseInt(selectedYear.value),
    );
});

watch(selectedYear, () => {
    form.value.target_payroll_period_id = '';
});

watch(
    () => props.adjustment,
    (adjustment) => {
        if (adjustment) {
            form.value = {
                employee_id: String(adjustment.employee_id),
                adjustment_type: adjustment.adjustment_type,
                adjustment_code: adjustment.adjustment_code,
                name: adjustment.name,
                description: adjustment.description || '',
                amount: String(adjustment.amount),
                is_taxable: adjustment.is_taxable,
                frequency: adjustment.frequency,
                recurring_start_date: adjustment.recurring_start_date || '',
                recurring_end_date: adjustment.recurring_end_date || '',
                recurring_interval: adjustment.recurring_interval || 'every_period',
                remaining_occurrences: adjustment.remaining_occurrences
                    ? String(adjustment.remaining_occurrences)
                    : '',
                has_balance_tracking: adjustment.has_balance_tracking,
                total_amount: adjustment.total_amount
                    ? String(adjustment.total_amount)
                    : '',
                target_payroll_period_id: adjustment.target_payroll_period_id
                    ? String(adjustment.target_payroll_period_id)
                    : '',
                notes: adjustment.notes || '',
            };
            // Set the year based on the target period
            if (adjustment.target_payroll_period_id) {
                const period = props.payrollPeriods.find(
                    (p) => p.id === adjustment.target_payroll_period_id,
                );
                if (period) {
                    selectedYear.value = String(period.year);
                }
            }
        } else {
            resetForm();
        }
    },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        errors.value = {};
    } else if (!props.adjustment) {
        resetForm();
    }
});

watch(
    () => form.value.adjustment_type,
    (type) => {
        if (type && !form.value.name) {
            const flatTypes = flattenAdjustmentTypes();
            const typeOption = flatTypes.find((t) => t.value === type);
            if (typeOption) {
                form.value.name = typeOption.label;
            }
        }
        if (type && !form.value.adjustment_code) {
            generateAdjustmentCode();
        }
        if (type.startsWith('loan_')) {
            form.value.has_balance_tracking = true;
            form.value.frequency = 'recurring';
        }
    },
);

function flattenAdjustmentTypes(): AdjustmentTypeOption[] {
    const types: AdjustmentTypeOption[] = [];
    for (const category in props.adjustmentTypes) {
        for (const group in props.adjustmentTypes[category]) {
            types.push(...props.adjustmentTypes[category][group]);
        }
    }
    return types;
}

function resetForm() {
    form.value = {
        employee_id: '',
        adjustment_type: '',
        adjustment_code: '',
        name: '',
        description: '',
        amount: '',
        is_taxable: true,
        frequency: 'one_time',
        recurring_start_date: new Date().toISOString().split('T')[0],
        recurring_end_date: '',
        recurring_interval: 'every_period',
        remaining_occurrences: '',
        has_balance_tracking: false,
        total_amount: '',
        target_payroll_period_id: '',
        notes: '',
    };
    selectedYear.value = String(new Date().getFullYear());
}

function generateAdjustmentCode() {
    if (form.value.adjustment_type && !form.value.adjustment_code) {
        const typeCode = form.value.adjustment_type
            .split('_')
            .map((p) => p[0])
            .join('')
            .toUpperCase();
        const random = Math.random().toString(36).substring(2, 6).toUpperCase();
        form.value.adjustment_code = `${typeCode}-${random}`;
    }
}

async function handleSubmit() {
    isLoading.value = true;
    errors.value = {};

    const url = isEditing.value
        ? `/api/adjustments/${props.adjustment?.id}`
        : '/api/adjustments';
    const method = isEditing.value ? 'PUT' : 'POST';

    const payload: Record<string, unknown> = {
        employee_id: parseInt(form.value.employee_id),
        adjustment_type: form.value.adjustment_type,
        adjustment_code: form.value.adjustment_code,
        name: form.value.name,
        description: form.value.description || null,
        amount: parseFloat(form.value.amount),
        is_taxable: form.value.is_taxable,
        frequency: form.value.frequency,
        notes: form.value.notes || null,
    };

    if (form.value.frequency === 'recurring') {
        payload.recurring_start_date = form.value.recurring_start_date || null;
        payload.recurring_end_date = form.value.recurring_end_date || null;
        payload.recurring_interval = form.value.recurring_interval || null;
        payload.remaining_occurrences = form.value.remaining_occurrences
            ? parseInt(form.value.remaining_occurrences)
            : null;
    } else {
        payload.target_payroll_period_id = form.value.target_payroll_period_id
            ? parseInt(form.value.target_payroll_period_id)
            : null;
    }

    if (form.value.has_balance_tracking) {
        payload.has_balance_tracking = true;
        payload.total_amount = parseFloat(form.value.total_amount);
    } else {
        payload.has_balance_tracking = false;
    }

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

function getError(field: string): string | undefined {
    const error = errors.value[field];
    if (Array.isArray(error)) {
        return error[0];
    }
    return error as string | undefined;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the adjustment details below.'
                            : 'Fill in the details to add a new payroll adjustment.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div
                    v-if="getError('_form')"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400"
                >
                    {{ getError('_form') }}
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
                                    {{ emp.full_name }} ({{ emp.employee_number }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="getError('employee_id')"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ getError('employee_id') }}
                        </p>
                    </div>

                    <!-- Adjustment Type -->
                    <div class="space-y-2">
                        <Label for="adjustment_type">Adjustment Type</Label>
                        <Select v-model="form.adjustment_type">
                            <SelectTrigger>
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <template
                                    v-for="(groups, category) in adjustmentTypes"
                                    :key="category"
                                >
                                    <div
                                        class="px-2 py-1.5 text-xs font-bold text-slate-700 dark:text-slate-300 uppercase"
                                    >
                                        {{ category }}
                                    </div>
                                    <template
                                        v-for="(types, group) in groups"
                                        :key="group"
                                    >
                                        <div
                                            class="px-3 py-1 text-xs font-semibold text-slate-500"
                                        >
                                            {{ group }}
                                        </div>
                                        <SelectItem
                                            v-for="type in types"
                                            :key="type.value"
                                            :value="type.value"
                                            class="pl-5"
                                        >
                                            {{ type.label }}
                                        </SelectItem>
                                    </template>
                                </template>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="getError('adjustment_type')"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ getError('adjustment_type') }}
                        </p>
                    </div>

                    <!-- Adjustment Code -->
                    <div class="space-y-2">
                        <Label for="adjustment_code">Adjustment Code</Label>
                        <Input
                            id="adjustment_code"
                            v-model="form.adjustment_code"
                            placeholder="e.g., AT-X1Y2"
                        />
                        <p
                            v-if="getError('adjustment_code')"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ getError('adjustment_code') }}
                        </p>
                    </div>

                    <!-- Name -->
                    <div class="space-y-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Adjustment name"
                        />
                        <p
                            v-if="getError('name')"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ getError('name') }}
                        </p>
                    </div>

                    <!-- Frequency -->
                    <div class="space-y-2">
                        <Label>Frequency</Label>
                        <Select v-model="form.frequency">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="one_time">One-Time</SelectItem>
                                <SelectItem value="recurring">Recurring</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Amount -->
                    <div class="space-y-2">
                        <Label for="amount">
                            {{ isRecurring ? 'Amount per Period' : 'Amount' }}
                        </Label>
                        <Input
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                        />
                        <p
                            v-if="getError('amount')"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ getError('amount') }}
                        </p>
                    </div>

                    <!-- Target Period Year (One-Time) -->
                    <div v-if="!isRecurring" class="space-y-2">
                        <Label>Year</Label>
                        <Select v-model="selectedYear">
                            <SelectTrigger>
                                <SelectValue placeholder="Select year" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="year in availableYears"
                                    :key="year"
                                    :value="String(year)"
                                >
                                    {{ year }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Target Period (One-Time) -->
                    <div v-if="!isRecurring" class="space-y-2">
                        <Label>Payroll Period</Label>
                        <Select
                            v-model="form.target_payroll_period_id"
                            :disabled="!selectedYear"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select period (optional)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="period in filteredPayrollPeriods"
                                    :key="period.id"
                                    :value="String(period.id)"
                                >
                                    {{ period.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-slate-500">
                            Leave empty to apply in the next payroll run
                        </p>
                    </div>

                    <!-- Recurring Start Date -->
                    <div v-if="isRecurring" class="space-y-2">
                        <Label for="recurring_start_date">Start Date</Label>
                        <Input
                            id="recurring_start_date"
                            v-model="form.recurring_start_date"
                            type="date"
                        />
                    </div>

                    <!-- Recurring End Date -->
                    <div v-if="isRecurring" class="space-y-2">
                        <Label for="recurring_end_date">End Date (Optional)</Label>
                        <Input
                            id="recurring_end_date"
                            v-model="form.recurring_end_date"
                            type="date"
                        />
                    </div>

                    <!-- Recurring Interval -->
                    <div v-if="isRecurring" class="space-y-2">
                        <Label>Interval</Label>
                        <Select v-model="form.recurring_interval">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="interval in recurringIntervals"
                                    :key="interval.value"
                                    :value="interval.value"
                                >
                                    {{ interval.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Remaining Occurrences -->
                    <div v-if="isRecurring" class="space-y-2">
                        <Label for="remaining_occurrences">
                            Number of Occurrences (Optional)
                        </Label>
                        <Input
                            id="remaining_occurrences"
                            v-model="form.remaining_occurrences"
                            type="number"
                            min="1"
                            max="999"
                            placeholder="Unlimited"
                        />
                        <p class="text-xs text-slate-500">
                            Leave empty for unlimited
                        </p>
                    </div>
                </div>

                <!-- Balance Tracking (for loan types) -->
                <div
                    v-if="showBalanceTracking"
                    class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <Checkbox
                            id="has_balance_tracking"
                            v-model:checked="form.has_balance_tracking"
                        />
                        <Label for="has_balance_tracking" class="cursor-pointer">
                            Enable Balance Tracking
                        </Label>
                    </div>
                    <div v-if="form.has_balance_tracking" class="space-y-2">
                        <Label for="total_amount">Total Amount to Deduct</Label>
                        <Input
                            id="total_amount"
                            v-model="form.total_amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="Total loan/advance amount"
                        />
                        <p class="text-xs text-slate-500">
                            This will track remaining balance as deductions are applied.
                        </p>
                        <p
                            v-if="getError('total_amount')"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ getError('total_amount') }}
                        </p>
                    </div>
                </div>

                <!-- Is Taxable -->
                <div class="flex items-center gap-2">
                    <input
                        id="is_taxable"
                        v-model="form.is_taxable"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                    />
                    <Label for="is_taxable" class="cursor-pointer">
                        Taxable
                    </Label>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="description">Description (Optional)</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Additional details about this adjustment"
                        rows="2"
                    />
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="notes">Notes (Optional)</Label>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Internal notes"
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
                        {{
                            isLoading
                                ? 'Saving...'
                                : isEditing
                                  ? 'Update'
                                  : 'Create'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
