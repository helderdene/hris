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
import { computed, onMounted, ref } from 'vue';

interface Allowance {
    id: number;
    adjustment_type: string;
    name: string;
    amount: number;
    is_taxable: boolean;
    frequency: string;
}

const props = defineProps<{
    employeeId: number;
}>();

/**
 * Allowance types matching the allowance cases of the backend AdjustmentType enum.
 */
const allowanceTypes = [
    { value: 'allowance_transportation', label: 'Transportation Allowance' },
    { value: 'allowance_meal', label: 'Meal Allowance' },
    { value: 'allowance_phone', label: 'Phone Allowance' },
    { value: 'allowance_housing', label: 'Housing Allowance' },
    { value: 'allowance_clothing', label: 'Clothing Allowance' },
    { value: 'allowance_other', label: 'Other Allowance' },
];

const allowances = ref<Allowance[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);

const isDialogOpen = ref(false);
const isSaving = ref(false);
const errors = ref<Record<string, string | string[]>>({});
const form = ref(emptyForm());

const groups = computed(() => [
    {
        key: 'taxable',
        title: 'Taxable',
        items: allowances.value.filter((a) => a.is_taxable),
    },
    {
        key: 'non-taxable',
        title: 'Non-Taxable',
        items: allowances.value.filter((a) => !a.is_taxable),
    },
]);

function emptyForm() {
    return {
        adjustment_type: '',
        name: '',
        amount: '',
        is_taxable: true,
        recurring_start_date: new Date().toISOString().split('T')[0],
    };
}

function formatAmount(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
}

function sum(items: Allowance[]): number {
    return items.reduce((total, item) => total + item.amount, 0);
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function getError(field: string): string | undefined {
    const error = errors.value[field];
    return Array.isArray(error) ? error[0] : error;
}

function request(url: string, method: string, body?: unknown) {
    return fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : undefined,
    });
}

async function fetchAllowances() {
    loading.value = true;
    loadError.value = null;

    try {
        const response = await request(
            `/api/employees/${props.employeeId}/adjustments?category=earning&active_only=1`,
            'GET',
        );

        if (!response.ok) {
            throw new Error('Failed to load allowances');
        }

        const data: { data: Allowance[] } = await response.json();
        allowances.value = data.data.filter(
            (a) =>
                a.adjustment_type.startsWith('allowance_') &&
                a.frequency === 'recurring',
        );
    } catch (e) {
        loadError.value = e instanceof Error ? e.message : 'An error occurred';
    } finally {
        loading.value = false;
    }
}

function openDialog() {
    form.value = emptyForm();
    errors.value = {};
    isDialogOpen.value = true;
}

async function handleSubmit() {
    isSaving.value = true;
    errors.value = {};

    const type = allowanceTypes.find(
        (t) => t.value === form.value.adjustment_type,
    );
    const code = `${form.value.adjustment_type
        .split('_')
        .map((part) => part[0])
        .join('')
        .toUpperCase()}-${Math.random().toString(36).substring(2, 6).toUpperCase()}`;

    try {
        const response = await request('/api/adjustments', 'POST', {
            employee_id: props.employeeId,
            adjustment_type: form.value.adjustment_type,
            adjustment_code: code,
            name: form.value.name || type?.label,
            amount: parseFloat(form.value.amount),
            is_taxable: form.value.is_taxable,
            frequency: 'recurring',
            recurring_interval: 'every_period',
            recurring_start_date: form.value.recurring_start_date,
            has_balance_tracking: false,
        });

        if (response.ok) {
            isDialogOpen.value = false;
            await fetchAllowances();
        } else {
            const data = await response.json();
            errors.value = data.errors ?? {
                _form: data.message || 'An error occurred',
            };
        }
    } catch {
        errors.value = { _form: 'An error occurred. Please try again.' };
    } finally {
        isSaving.value = false;
    }
}

async function handleRemove(allowance: Allowance) {
    if (
        !confirm(
            `Stop "${allowance.name}"? It will no longer be added to future payroll.`,
        )
    ) {
        return;
    }

    const response = await request(
        `/api/adjustments/${allowance.id}/status`,
        'PATCH',
        { status: 'cancelled' },
    );

    if (response.ok) {
        await fetchAllowances();
    } else {
        loadError.value = 'Failed to remove allowance';
    }
}

onMounted(fetchAllowances);
</script>

<template>
    <div data-test="allowances-section">
        <div class="mb-3 flex items-center justify-between">
            <h3
                class="text-sm font-semibold text-slate-900 dark:text-slate-100"
            >
                Allowances
            </h3>
            <Button
                variant="outline"
                size="sm"
                @click="openDialog"
                data-test="add-allowance-button"
            >
                Add Allowance
            </Button>
        </div>

        <div
            v-if="loading"
            class="h-24 animate-pulse rounded-lg bg-slate-200 dark:bg-slate-700"
        />

        <p
            v-else-if="loadError"
            class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
        >
            {{ loadError }}
        </p>

        <div v-else class="grid gap-4 sm:grid-cols-2">
            <div
                v-for="group in groups"
                :key="group.key"
                class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
                :data-test="`${group.key}-allowances`"
            >
                <div
                    class="mb-2 flex items-center justify-between text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                >
                    <span>{{ group.title }}</span>
                    <span>{{ formatAmount(sum(group.items)) }}</span>
                </div>
                <p
                    v-if="group.items.length === 0"
                    class="text-sm text-slate-500 dark:text-slate-400"
                >
                    None
                </p>
                <ul v-else class="flex flex-col gap-2">
                    <li
                        v-for="allowance in group.items"
                        :key="allowance.id"
                        class="flex items-center justify-between gap-2 text-sm"
                    >
                        <span class="text-slate-700 dark:text-slate-300">
                            {{ allowance.name }}
                        </span>
                        <span class="flex items-center gap-2">
                            <span
                                class="font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ formatAmount(allowance.amount) }}
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-red-600 hover:text-red-700 dark:text-red-400"
                                :aria-label="`Remove ${allowance.name}`"
                                @click="handleRemove(allowance)"
                            >
                                Remove
                            </Button>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            Amounts are added to every pay period. Non-taxable allowances are
            excluded from withholding tax.
        </p>

        <Dialog v-model:open="isDialogOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Add Allowance</DialogTitle>
                    <DialogDescription>
                        A recurring allowance paid on every payroll period.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="handleSubmit">
                    <div
                        v-if="getError('_form') || getError('adjustment_code')"
                        class="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400"
                    >
                        {{ getError('_form') || getError('adjustment_code') }}
                    </div>

                    <div class="space-y-2">
                        <Label for="allowance_type">Type</Label>
                        <Select v-model="form.adjustment_type">
                            <SelectTrigger id="allowance_type">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="type in allowanceTypes"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="getError('adjustment_type')"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ getError('adjustment_type') }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="allowance_name">Name (Optional)</Label>
                        <Input
                            id="allowance_name"
                            v-model="form.name"
                            placeholder="Defaults to the type"
                        />
                        <p
                            v-if="getError('name')"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ getError('name') }}
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="allowance_amount"
                                >Amount per pay period</Label
                            >
                            <Input
                                id="allowance_amount"
                                v-model="form.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="0.00"
                                required
                            />
                            <p
                                v-if="getError('amount')"
                                class="text-sm text-red-600 dark:text-red-400"
                            >
                                {{ getError('amount') }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="allowance_start">Start date</Label>
                            <Input
                                id="allowance_start"
                                v-model="form.recurring_start_date"
                                type="date"
                                required
                            />
                            <p
                                v-if="getError('recurring_start_date')"
                                class="text-sm text-red-600 dark:text-red-400"
                            >
                                {{ getError('recurring_start_date') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="allowance_is_taxable"
                            v-model="form.is_taxable"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                        />
                        <Label
                            for="allowance_is_taxable"
                            class="cursor-pointer"
                        >
                            Taxable
                        </Label>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="isSaving"
                            @click="isDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="isSaving">
                            {{ isSaving ? 'Saving...' : 'Add Allowance' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
