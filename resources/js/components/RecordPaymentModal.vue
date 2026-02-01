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

interface Loan {
    id: number;
    loan_code: string;
    loan_type_label: string;
    remaining_balance: number;
    monthly_deduction: number;
    employee?: {
        full_name: string;
    };
}

const props = defineProps<{
    loan: Loan | null;
}>();

const emit = defineEmits<{
    success: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref({
    amount: '',
    payment_date: new Date().toISOString().split('T')[0],
    payment_source: 'manual',
    notes: '',
});

const maxAmount = computed(() => props.loan?.remaining_balance || 0);

watch(open, (isOpen) => {
    if (isOpen && props.loan) {
        form.value = {
            amount: String(props.loan.monthly_deduction),
            payment_date: new Date().toISOString().split('T')[0],
            payment_source: 'manual',
            notes: '',
        };
        errors.value = {};
    }
});

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
}

async function handleSubmit() {
    if (!props.loan) return;

    isLoading.value = true;
    errors.value = {};

    const amount = parseFloat(form.value.amount);

    if (amount > maxAmount.value) {
        errors.value = {
            amount: 'Payment amount cannot exceed remaining balance',
        };
        isLoading.value = false;
        return;
    }

    const payload = {
        amount,
        payment_date: form.value.payment_date,
        payment_source: form.value.payment_source,
        notes: form.value.notes || null,
    };

    try {
        const response = await fetch(`/api/loans/${props.loan.id}/payment`, {
            method: 'POST',
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
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Record Payment</DialogTitle>
                <DialogDescription v-if="loan">
                    Record a payment for {{ loan.loan_type_label }} ({{
                        loan.loan_code
                    }})
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="loan"
                class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400"
                        >Employee</span
                    >
                    <span
                        class="font-medium text-slate-900 dark:text-slate-100"
                        >{{ loan.employee?.full_name }}</span
                    >
                </div>
                <div class="mt-2 flex justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400"
                        >Remaining Balance</span
                    >
                    <span
                        class="font-medium text-slate-900 dark:text-slate-100"
                        >{{ formatCurrency(loan.remaining_balance) }}</span
                    >
                </div>
                <div class="mt-2 flex justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400"
                        >Monthly Deduction</span
                    >
                    <span
                        class="font-medium text-slate-900 dark:text-slate-100"
                        >{{ formatCurrency(loan.monthly_deduction) }}</span
                    >
                </div>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div
                    v-if="errors._form"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400"
                >
                    {{ errors._form }}
                </div>

                <!-- Amount -->
                <div class="space-y-2">
                    <Label for="amount">Payment Amount</Label>
                    <Input
                        id="amount"
                        v-model="form.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        :max="maxAmount"
                        placeholder="0.00"
                    />
                    <p
                        v-if="errors.amount"
                        class="text-sm text-red-600 dark:text-red-400"
                    >
                        {{
                            Array.isArray(errors.amount)
                                ? errors.amount[0]
                                : errors.amount
                        }}
                    </p>
                </div>

                <!-- Payment Date -->
                <div class="space-y-2">
                    <Label for="payment_date">Payment Date</Label>
                    <Input
                        id="payment_date"
                        v-model="form.payment_date"
                        type="date"
                    />
                    <p
                        v-if="errors.payment_date"
                        class="text-sm text-red-600 dark:text-red-400"
                    >
                        {{ errors.payment_date[0] }}
                    </p>
                </div>

                <!-- Payment Source -->
                <div class="space-y-2">
                    <Label for="payment_source">Payment Source</Label>
                    <Select v-model="form.payment_source">
                        <SelectTrigger>
                            <SelectValue placeholder="Select source" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="manual"
                                >Manual Payment</SelectItem
                            >
                            <SelectItem value="adjustment"
                                >Balance Adjustment</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="notes">Notes (Optional)</Label>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Payment notes"
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
                        {{ isLoading ? 'Recording...' : 'Record Payment' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
