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

interface TaxBracket {
    id?: number;
    min_compensation: number;
    max_compensation: number | null;
    base_tax: number;
    excess_rate: number;
}

interface TaxTable {
    id: number;
    pay_period: string;
    effective_from: string;
    description: string | null;
    is_active: boolean;
    brackets: TaxBracket[];
}

const props = defineProps<{
    taxTable: TaxTable | null;
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const isSubmitting = ref(false);
const errors = ref<Record<string, string[]>>({});

const payPeriodOptions = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'semi_monthly', label: 'Semi-Monthly' },
    { value: 'monthly', label: 'Monthly' },
];

const form = ref({
    pay_period: 'monthly',
    effective_from: '',
    description: '',
    is_active: true,
    brackets: [] as TaxBracket[],
});

const isEditing = computed(() => props.taxTable !== null);
const dialogTitle = computed(() =>
    isEditing.value ? 'Edit Withholding Tax Table' : 'Add Withholding Tax Table',
);

watch(
    () => props.taxTable,
    (newValue) => {
        if (newValue) {
            form.value = {
                pay_period: newValue.pay_period,
                effective_from: newValue.effective_from,
                description: newValue.description || '',
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
        if (!props.taxTable) {
            resetForm();
        }
    }
});

function resetForm() {
    form.value = {
        pay_period: 'monthly',
        effective_from: new Date().toISOString().split('T')[0],
        description: '',
        is_active: true,
        brackets: [],
    };
}

function addBracket() {
    const lastBracket = form.value.brackets[form.value.brackets.length - 1];
    const newMinCompensation = lastBracket ? (lastBracket.max_compensation || 0) + 0.01 : 0;

    form.value.brackets.push({
        min_compensation: newMinCompensation,
        max_compensation: null,
        base_tax: 0,
        excess_rate: 0,
    });
}

function removeBracket(index: number) {
    form.value.brackets.splice(index, 1);
}

function loadTrainLawBrackets() {
    const brackets = getTrainLawBrackets(form.value.pay_period);
    form.value.brackets = brackets;
}

function getTrainLawBrackets(payPeriod: string): TaxBracket[] {
    switch (payPeriod) {
        case 'monthly':
            return [
                { min_compensation: 0, max_compensation: 20833, base_tax: 0, excess_rate: 0 },
                { min_compensation: 20833, max_compensation: 33333, base_tax: 0, excess_rate: 0.15 },
                { min_compensation: 33333, max_compensation: 66667, base_tax: 1875, excess_rate: 0.20 },
                { min_compensation: 66667, max_compensation: 166667, base_tax: 8541.67, excess_rate: 0.25 },
                { min_compensation: 166667, max_compensation: 666667, base_tax: 33541.67, excess_rate: 0.30 },
                { min_compensation: 666667, max_compensation: null, base_tax: 183541.67, excess_rate: 0.35 },
            ];
        case 'semi_monthly':
            return [
                { min_compensation: 0, max_compensation: 10417, base_tax: 0, excess_rate: 0 },
                { min_compensation: 10417, max_compensation: 16667, base_tax: 0, excess_rate: 0.15 },
                { min_compensation: 16667, max_compensation: 33333, base_tax: 937.50, excess_rate: 0.20 },
                { min_compensation: 33333, max_compensation: 83333, base_tax: 4270.83, excess_rate: 0.25 },
                { min_compensation: 83333, max_compensation: 333333, base_tax: 16770.83, excess_rate: 0.30 },
                { min_compensation: 333333, max_compensation: null, base_tax: 91770.83, excess_rate: 0.35 },
            ];
        case 'weekly':
            return [
                { min_compensation: 0, max_compensation: 4808, base_tax: 0, excess_rate: 0 },
                { min_compensation: 4808, max_compensation: 7692, base_tax: 0, excess_rate: 0.15 },
                { min_compensation: 7692, max_compensation: 15385, base_tax: 432.69, excess_rate: 0.20 },
                { min_compensation: 15385, max_compensation: 38462, base_tax: 1971.15, excess_rate: 0.25 },
                { min_compensation: 38462, max_compensation: 153846, base_tax: 7740.38, excess_rate: 0.30 },
                { min_compensation: 153846, max_compensation: null, base_tax: 42355.77, excess_rate: 0.35 },
            ];
        case 'daily':
            return [
                { min_compensation: 0, max_compensation: 685, base_tax: 0, excess_rate: 0 },
                { min_compensation: 685, max_compensation: 1096, base_tax: 0, excess_rate: 0.15 },
                { min_compensation: 1096, max_compensation: 2192, base_tax: 61.65, excess_rate: 0.20 },
                { min_compensation: 2192, max_compensation: 5479, base_tax: 280.85, excess_rate: 0.25 },
                { min_compensation: 5479, max_compensation: 21918, base_tax: 1102.60, excess_rate: 0.30 },
                { min_compensation: 21918, max_compensation: null, base_tax: 6034.30, excess_rate: 0.35 },
            ];
        default:
            return [];
    }
}

function formatPercent(rate: number): string {
    return (rate * 100).toFixed(0) + '%';
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

async function handleSubmit() {
    isSubmitting.value = true;
    errors.value = {};

    const url = isEditing.value
        ? `/api/organization/contributions/tax/${props.taxTable!.id}`
        : '/api/organization/contributions/tax';

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
        alert('An error occurred while saving the withholding tax table');
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
                    {{ isEditing ? 'Update the withholding tax table and brackets.' : 'Create a new withholding tax table with brackets based on TRAIN Law.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-6">
                <!-- Basic Info -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="pay_period">Pay Period</Label>
                        <select
                            id="pay_period"
                            v-model="form.pay_period"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900"
                        >
                            <option
                                v-for="option in payPeriodOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <p
                            v-if="errors.pay_period"
                            class="text-sm text-red-500"
                        >
                            {{ errors.pay_period[0] }}
                        </p>
                    </div>

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
                            placeholder="e.g., TRAIN Law 2023 Tax Table"
                        />
                    </div>

                    <div class="flex items-center space-x-2 pt-6">
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
                        <h3 class="text-lg font-medium">Tax Brackets</h3>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="loadTrainLawBrackets"
                            >
                                Load TRAIN Law Brackets
                            </Button>
                            <Button type="button" variant="outline" size="sm" @click="addBracket">
                                Add Bracket
                            </Button>
                        </div>
                    </div>

                    <div v-if="form.brackets.length === 0" class="rounded-lg border border-dashed border-slate-300 p-6 text-center dark:border-slate-700">
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            No brackets added yet. Click "Load TRAIN Law Brackets" to load standard brackets or "Add Bracket" to add custom ones.
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
                                    <span v-if="bracket.excess_rate === 0" class="ml-2 text-xs text-green-600 dark:text-green-400">(Tax Exempt)</span>
                                    <span v-else class="ml-2 text-xs text-slate-500 dark:text-slate-400">({{ formatPercent(bracket.excess_rate) }} rate)</span>
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
                                    <Label :for="`min_compensation_${index}`" class="text-xs">Min Compensation</Label>
                                    <Input
                                        :id="`min_compensation_${index}`"
                                        v-model.number="bracket.min_compensation"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <Label :for="`max_compensation_${index}`" class="text-xs">Max Compensation</Label>
                                    <Input
                                        :id="`max_compensation_${index}`"
                                        v-model.number="bracket.max_compensation"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="No max"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <Label :for="`base_tax_${index}`" class="text-xs">Base Tax</Label>
                                    <Input
                                        :id="`base_tax_${index}`"
                                        v-model.number="bracket.base_tax"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <Label :for="`excess_rate_${index}`" class="text-xs">Excess Rate (0-1)</Label>
                                    <Input
                                        :id="`excess_rate_${index}`"
                                        v-model.number="bracket.excess_rate"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="1"
                                    />
                                </div>
                            </div>

                            <!-- Tax formula preview -->
                            <div class="mt-3 rounded bg-slate-50 p-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                <span class="font-medium">Tax Formula:</span>
                                <span v-if="bracket.excess_rate === 0"> No tax</span>
                                <span v-else>
                                    {{ formatCurrency(bracket.base_tax) }} + {{ formatPercent(bracket.excess_rate) }} of excess over {{ formatCurrency(bracket.min_compensation) }}
                                </span>
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
