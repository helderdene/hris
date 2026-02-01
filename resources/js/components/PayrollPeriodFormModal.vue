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

interface PayrollCycle {
    id: number;
    name: string;
    code: string;
    cycle_type: string;
}

interface PayrollPeriod {
    id: number;
    payroll_cycle_id: number;
    name: string;
    period_type: string;
    year: number;
    period_number: number;
    cutoff_start: string;
    cutoff_end: string;
    pay_date: string;
    notes: string | null;
}

interface EnumOption {
    value: string;
    label: string;
    description?: string;
}

const props = defineProps<{
    period: PayrollPeriod | null;
    cycles: PayrollCycle[];
    periodTypes: EnumOption[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const currentYear = new Date().getFullYear();

const form = ref({
    payroll_cycle_id: '',
    name: '',
    period_type: 'regular',
    year: currentYear,
    period_number: 1,
    cutoff_start: '',
    cutoff_end: '',
    pay_date: '',
    notes: '',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEditing = computed(() => !!props.period);

const cycleOptions = computed<EnumOption[]>(() => {
    return (props.cycles ?? []).map((cycle) => ({
        value: String(cycle.id),
        label: `${cycle.name} (${cycle.code})`,
    }));
});

watch(
    () => props.period,
    (newPeriod) => {
        if (newPeriod) {
            form.value = {
                payroll_cycle_id: String(newPeriod.payroll_cycle_id),
                name: newPeriod.name,
                period_type: newPeriod.period_type,
                year: newPeriod.year,
                period_number: newPeriod.period_number,
                cutoff_start: newPeriod.cutoff_start,
                cutoff_end: newPeriod.cutoff_end,
                pay_date: newPeriod.pay_date,
                notes: newPeriod.notes || '',
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
    const cycles = props.cycles ?? [];
    form.value = {
        payroll_cycle_id: cycles.length > 0 ? String(cycles[0].id) : '',
        name: '',
        period_type: 'regular',
        year: currentYear,
        period_number: 1,
        cutoff_start: '',
        cutoff_end: '',
        pay_date: '',
        notes: '',
    };
    errors.value = {};
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    const url = isEditing.value
        ? `/api/organization/payroll-periods/${props.period!.id}`
        : '/api/organization/payroll-periods';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        payroll_cycle_id: Number(form.value.payroll_cycle_id),
        name: form.value.name,
        period_type: form.value.period_type,
        year: form.value.year,
        period_number: form.value.period_number,
        cutoff_start: form.value.cutoff_start,
        cutoff_end: form.value.cutoff_end,
        pay_date: form.value.pay_date,
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
    } catch {
        errors.value = {
            general: 'An error occurred while saving the payroll period',
        };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        isEditing
                            ? 'Edit Payroll Period'
                            : 'Add Payroll Period'
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the payroll period details below.'
                            : 'Fill in the details to create a new payroll period.'
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

                <!-- Cycle Selection -->
                <div class="space-y-2">
                    <Label for="payroll_cycle_id">Payroll Cycle *</Label>
                    <EnumSelect
                        id="payroll_cycle_id"
                        v-model="form.payroll_cycle_id"
                        :options="cycleOptions"
                        placeholder="Select cycle"
                    />
                    <p
                        v-if="errors.payroll_cycle_id"
                        class="text-sm text-red-500"
                    >
                        {{ errors.payroll_cycle_id }}
                    </p>
                </div>

                <!-- Name -->
                <div class="space-y-2">
                    <Label for="name">Period Name *</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g., January 2026 - 1st Half"
                        :class="{ 'border-red-500': errors.name }"
                    />
                    <p v-if="errors.name" class="text-sm text-red-500">
                        {{ errors.name }}
                    </p>
                </div>

                <!-- Period Type & Year -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="period_type">Period Type *</Label>
                        <EnumSelect
                            id="period_type"
                            v-model="form.period_type"
                            :options="periodTypes"
                            placeholder="Select type"
                        />
                        <p
                            v-if="errors.period_type"
                            class="text-sm text-red-500"
                        >
                            {{ errors.period_type }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="year">Year *</Label>
                        <Input
                            id="year"
                            v-model.number="form.year"
                            type="number"
                            min="2000"
                            max="2100"
                            :class="{ 'border-red-500': errors.year }"
                        />
                        <p v-if="errors.year" class="text-sm text-red-500">
                            {{ errors.year }}
                        </p>
                    </div>
                </div>

                <!-- Period Number -->
                <div class="space-y-2">
                    <Label for="period_number">Period Number *</Label>
                    <Input
                        id="period_number"
                        v-model.number="form.period_number"
                        type="number"
                        min="1"
                        max="52"
                        :class="{ 'border-red-500': errors.period_number }"
                    />
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Sequential number within the year (e.g., 1-24 for
                        semi-monthly, 1-12 for monthly).
                    </p>
                    <p
                        v-if="errors.period_number"
                        class="text-sm text-red-500"
                    >
                        {{ errors.period_number }}
                    </p>
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="cutoff_start">Cutoff Start *</Label>
                        <Input
                            id="cutoff_start"
                            v-model="form.cutoff_start"
                            type="date"
                            :class="{ 'border-red-500': errors.cutoff_start }"
                        />
                        <p
                            v-if="errors.cutoff_start"
                            class="text-sm text-red-500"
                        >
                            {{ errors.cutoff_start }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="cutoff_end">Cutoff End *</Label>
                        <Input
                            id="cutoff_end"
                            v-model="form.cutoff_end"
                            type="date"
                            :class="{ 'border-red-500': errors.cutoff_end }"
                        />
                        <p
                            v-if="errors.cutoff_end"
                            class="text-sm text-red-500"
                        >
                            {{ errors.cutoff_end }}
                        </p>
                    </div>
                </div>

                <!-- Pay Date -->
                <div class="space-y-2">
                    <Label for="pay_date">Pay Date *</Label>
                    <Input
                        id="pay_date"
                        v-model="form.pay_date"
                        type="date"
                        :class="{ 'border-red-500': errors.pay_date }"
                    />
                    <p v-if="errors.pay_date" class="text-sm text-red-500">
                        {{ errors.pay_date }}
                    </p>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="notes">Notes</Label>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Optional notes about this period"
                        rows="2"
                        :class="{ 'border-red-500': errors.notes }"
                    />
                    <p v-if="errors.notes" class="text-sm text-red-500">
                        {{ errors.notes }}
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
                        {{
                            isEditing
                                ? 'Update Payroll Period'
                                : 'Create Payroll Period'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
