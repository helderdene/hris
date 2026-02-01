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

interface AdjustmentType {
    value: string;
    label: string;
}

interface LeaveBalance {
    id: number;
    employee_id: number;
    leave_type_id: number;
    year: number;
    brought_forward: number;
    earned: number;
    used: number;
    pending: number;
    adjustments: number;
    expired: number;
    total_credits: number;
    available: number;
    employee: {
        id: number;
        employee_number: string;
        full_name: string;
        department: string | null;
        position: string | null;
    };
    leave_type: {
        id: number;
        name: string;
        code: string;
    };
}

const props = defineProps<{
    balance: LeaveBalance | null;
    adjustmentTypes: AdjustmentType[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const adjustmentType = ref('credit');
const days = ref('');
const reason = ref('');
const submitting = ref(false);
const errors = ref<Record<string, string>>({});

const previewBalance = computed(() => {
    if (!props.balance || !days.value) {
        return props.balance?.adjustments ?? 0;
    }

    const daysNum = parseFloat(days.value) || 0;
    const sign = adjustmentType.value === 'credit' ? 1 : -1;

    return (props.balance.adjustments ?? 0) + daysNum * sign;
});

const previewAvailable = computed(() => {
    if (!props.balance || !days.value) {
        return props.balance?.available ?? 0;
    }

    const daysNum = parseFloat(days.value) || 0;
    const sign = adjustmentType.value === 'credit' ? 1 : -1;

    return (props.balance.available ?? 0) + daysNum * sign;
});

watch(open, (newValue) => {
    if (!newValue) {
        resetForm();
    }
});

function resetForm() {
    adjustmentType.value = 'credit';
    days.value = '';
    reason.value = '';
    errors.value = {};
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    if (!props.balance) {
        return;
    }

    errors.value = {};

    // Client-side validation
    if (!days.value || parseFloat(days.value) <= 0) {
        errors.value.days = 'Please enter a valid number of days greater than 0.';
        return;
    }

    if (!reason.value || reason.value.length < 10) {
        errors.value.reason = 'Please provide a reason (at least 10 characters).';
        return;
    }

    submitting.value = true;

    try {
        const response = await fetch(
            `/api/organization/leave-balances/${props.balance.id}/adjust`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    adjustment_type: adjustmentType.value,
                    days: parseFloat(days.value),
                    reason: reason.value,
                }),
            },
        );

        const data = await response.json();

        if (response.ok) {
            emit('success');
            open.value = false;
        } else {
            if (data.errors) {
                errors.value = Object.fromEntries(
                    Object.entries(data.errors).map(([key, value]) => [
                        key,
                        Array.isArray(value) ? value[0] : value,
                    ]),
                );
            } else {
                errors.value.general =
                    data.message || 'Failed to adjust balance.';
            }
        }
    } catch {
        errors.value.general = 'An error occurred. Please try again.';
    } finally {
        submitting.value = false;
    }
}

function formatNumber(value: number): string {
    return value.toFixed(2);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle>Adjust Leave Balance</DialogTitle>
                <DialogDescription v-if="balance">
                    Adjust the leave balance for
                    {{ balance.employee?.full_name }} -
                    {{ balance.leave_type?.name }} ({{ balance.year }})
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Current Balance Info -->
                <div
                    class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800"
                    v-if="balance"
                >
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Current Balance
                    </div>
                    <div class="mt-1 flex items-center justify-between">
                        <span class="text-sm text-slate-600 dark:text-slate-300"
                            >Available:</span
                        >
                        <span
                            class="font-semibold"
                            :class="
                                balance.available >= 0
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-red-600 dark:text-red-400'
                            "
                        >
                            {{ formatNumber(balance.available) }} days
                        </span>
                    </div>
                    <div class="mt-1 flex items-center justify-between">
                        <span class="text-sm text-slate-600 dark:text-slate-300"
                            >Adjustments:</span
                        >
                        <span class="text-sm">
                            {{ formatNumber(balance.adjustments) }} days
                        </span>
                    </div>
                </div>

                <!-- Error alert -->
                <div
                    v-if="errors.general"
                    class="rounded-lg bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400"
                >
                    {{ errors.general }}
                </div>

                <!-- Adjustment Type -->
                <div class="space-y-2">
                    <Label for="adjustment_type">Adjustment Type</Label>
                    <Select v-model="adjustmentType">
                        <SelectTrigger>
                            <SelectValue placeholder="Select type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="type in adjustmentTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Days -->
                <div class="space-y-2">
                    <Label for="days">Days</Label>
                    <Input
                        id="days"
                        v-model="days"
                        type="number"
                        step="0.5"
                        min="0.5"
                        placeholder="Enter number of days"
                        :class="{ 'border-red-500': errors.days }"
                    />
                    <p v-if="errors.days" class="text-sm text-red-500">
                        {{ errors.days }}
                    </p>
                </div>

                <!-- Reason -->
                <div class="space-y-2">
                    <Label for="reason">Reason</Label>
                    <Textarea
                        id="reason"
                        v-model="reason"
                        placeholder="Explain the reason for this adjustment..."
                        rows="3"
                        :class="{ 'border-red-500': errors.reason }"
                    />
                    <p v-if="errors.reason" class="text-sm text-red-500">
                        {{ errors.reason }}
                    </p>
                </div>

                <!-- Preview -->
                <div
                    v-if="days && parseFloat(days) > 0"
                    class="rounded-lg bg-blue-50 p-3 dark:bg-blue-900/20"
                >
                    <div
                        class="text-sm font-medium text-blue-700 dark:text-blue-300"
                    >
                        Preview After Adjustment
                    </div>
                    <div class="mt-1 flex items-center justify-between">
                        <span class="text-sm text-blue-600 dark:text-blue-400"
                            >Adjustments:</span
                        >
                        <span class="font-semibold text-blue-700 dark:text-blue-300">
                            {{ formatNumber(previewBalance) }} days
                        </span>
                    </div>
                    <div class="mt-1 flex items-center justify-between">
                        <span class="text-sm text-blue-600 dark:text-blue-400"
                            >Available:</span
                        >
                        <span
                            class="font-semibold"
                            :class="
                                previewAvailable >= 0
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-red-600 dark:text-red-400'
                            "
                        >
                            {{ formatNumber(previewAvailable) }} days
                        </span>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="submitting"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="submitting">
                        {{ submitting ? 'Adjusting...' : 'Adjust Balance' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
