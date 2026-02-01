<script setup lang="ts">
import EnumSelect from '@/components/EnumSelect.vue';
import InputError from '@/components/InputError.vue';
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
import {
    BankAccountType,
    BankAccountTypeLabels,
    PayType,
    PayTypeLabels,
    type CompensationFormData,
    type CompensationFormErrors,
    type EmployeeCompensation,
} from '@/types/compensation';
import { computed, ref, watch } from 'vue';

interface Props {
    employeeId: number;
    currentCompensation: EmployeeCompensation | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref<CompensationFormData>({
    basic_pay: '',
    pay_type: '',
    effective_date: '',
    remarks: '',
    bank_name: '',
    account_name: '',
    account_number: '',
    account_type: '',
});

const errors = ref<CompensationFormErrors>({});
const isSubmitting = ref(false);
const recentlySuccessful = ref(false);

/**
 * Pay type options for the dropdown.
 */
const payTypeOptions = computed(() => {
    return Object.values(PayType).map((type) => ({
        value: type,
        label: PayTypeLabels[type],
    }));
});

/**
 * Bank account type options for the dropdown.
 */
const bankAccountTypeOptions = computed(() => {
    return Object.values(BankAccountType).map((type) => ({
        value: type,
        label: BankAccountTypeLabels[type],
    }));
});

/**
 * Format salary for display using PHP currency.
 */
function formatSalary(salary: string | null): string {
    if (!salary) return '-';
    const num = parseFloat(salary);
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
}

/**
 * Check if form can be submitted.
 */
const canSubmit = computed(() => {
    return (
        form.value.basic_pay &&
        form.value.pay_type &&
        form.value.effective_date &&
        !isSubmitting.value
    );
});

/**
 * Check if this is an edit (vs new compensation).
 */
const isEdit = computed(() => props.currentCompensation !== null);

/**
 * Reset form to initial state.
 */
function resetForm() {
    form.value = {
        basic_pay: '',
        pay_type: '',
        effective_date: '',
        remarks: '',
        bank_name: '',
        account_name: '',
        account_number: '',
        account_type: '',
    };
    errors.value = {};
}

/**
 * Initialize form with current compensation values.
 */
function initializeForm() {
    if (props.currentCompensation) {
        form.value = {
            basic_pay: props.currentCompensation.basic_pay || '',
            pay_type: (props.currentCompensation.pay_type as PayType) || '',
            effective_date: new Date().toISOString().split('T')[0],
            remarks: '',
            bank_name: props.currentCompensation.bank_name || '',
            account_name: props.currentCompensation.account_name || '',
            account_number: props.currentCompensation.account_number || '',
            account_type:
                (props.currentCompensation.account_type as BankAccountType) ||
                '',
        };
    } else {
        form.value = {
            basic_pay: '',
            pay_type: '',
            effective_date: new Date().toISOString().split('T')[0],
            remarks: '',
            bank_name: '',
            account_name: '',
            account_number: '',
            account_type: '',
        };
    }
    errors.value = {};
}

/**
 * Get CSRF token from cookies.
 */
function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Handle form submission.
 */
async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    try {
        const url = `/api/employees/${props.employeeId}/compensation`;

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                basic_pay: parseFloat(form.value.basic_pay.toString()),
                pay_type: form.value.pay_type,
                effective_date: form.value.effective_date,
                remarks: form.value.remarks || null,
                bank_name: form.value.bank_name || null,
                account_name: form.value.account_name || null,
                account_number: form.value.account_number || null,
                account_type: form.value.account_type || null,
            }),
        });

        if (response.status === 201 || response.ok) {
            recentlySuccessful.value = true;
            emit('success');

            setTimeout(() => {
                recentlySuccessful.value = false;
                open.value = false;
                resetForm();
            }, 1500);
        } else if (response.status === 422) {
            const data = await response.json();
            if (data.errors) {
                errors.value = Object.fromEntries(
                    Object.entries(data.errors as Record<string, string[]>).map(
                        ([key, messages]) => [key, messages[0]],
                    ),
                );
            }
        } else if (response.status === 403) {
            errors.value = {
                general:
                    'You do not have permission to manage employee compensation.',
            };
        } else {
            errors.value = {
                general: 'An unexpected error occurred. Please try again.',
            };
        }
    } catch {
        errors.value = {
            general: 'An unexpected error occurred. Please try again.',
        };
    } finally {
        isSubmitting.value = false;
    }
}

/**
 * Handle modal close.
 */
function handleClose() {
    if (!isSubmitting.value) {
        resetForm();
        emit('close');
    }
}

/**
 * Handle open state change.
 */
function handleOpenChange(isOpen: boolean) {
    open.value = isOpen;
    if (!isOpen) {
        handleClose();
    }
}

// Initialize form when modal opens
watch(open, (isOpen) => {
    if (isOpen) {
        initializeForm();
    } else {
        resetForm();
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            class="max-h-[90vh] w-[95vw] overflow-y-auto sm:max-w-lg"
            data-test="compensation-edit-modal"
        >
            <form @submit.prevent="handleSubmit" class="space-y-4 sm:space-y-6">
                <DialogHeader class="space-y-2 sm:space-y-3">
                    <DialogTitle class="text-lg sm:text-xl">
                        {{ isEdit ? 'Edit Compensation' : 'Add Compensation' }}
                    </DialogTitle>
                    <DialogDescription class="text-sm">
                        {{
                            isEdit
                                ? "Update the employee's compensation details. This will create a new history record."
                                : 'Set up initial compensation details for this employee.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3 sm:space-y-4">
                    <!-- General Error -->
                    <div
                        v-if="errors.general"
                        class="rounded-md bg-red-50 p-2.5 text-sm text-red-700 sm:p-3 dark:bg-red-900/30 dark:text-red-400"
                    >
                        {{ errors.general }}
                    </div>

                    <!-- Current Values Display (when editing) -->
                    <div
                        v-if="isEdit && currentCompensation"
                        class="rounded-md bg-slate-50 p-2.5 sm:p-3 dark:bg-slate-800"
                        data-test="current-values-display"
                    >
                        <p
                            class="text-xs font-medium text-slate-500 uppercase sm:text-xs dark:text-slate-400"
                        >
                            Current Compensation
                        </p>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="text-slate-500 dark:text-slate-400"
                                    >Basic Pay:</span
                                >
                                <span
                                    class="ml-1 font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{
                                        formatSalary(
                                            currentCompensation.basic_pay,
                                        )
                                    }}
                                </span>
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400"
                                    >Pay Type:</span
                                >
                                <span
                                    class="ml-1 font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{
                                        currentCompensation.pay_type_label ||
                                        '-'
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Basic Pay Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="basic-pay" class="text-sm">
                            Basic Pay <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="basic-pay"
                            type="number"
                            v-model="form.basic_pay"
                            placeholder="e.g., 50000.00"
                            step="0.01"
                            min="0"
                            class="w-full"
                            :class="{ 'border-red-500': errors.basic_pay }"
                            data-test="basic-pay-input"
                        />
                        <InputError :message="errors.basic_pay" />
                    </div>

                    <!-- Pay Type Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="pay-type" class="text-sm">
                            Pay Type <span class="text-red-500">*</span>
                        </Label>
                        <EnumSelect
                            id="pay-type"
                            v-model="form.pay_type"
                            :options="payTypeOptions"
                            placeholder="Select pay type"
                            data-test="pay-type-select"
                        />
                        <InputError :message="errors.pay_type" />
                    </div>

                    <!-- Effective Date Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="effective-date" class="text-sm">
                            Effective Date <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="effective-date"
                            type="date"
                            v-model="form.effective_date"
                            class="w-full"
                            :class="{ 'border-red-500': errors.effective_date }"
                            data-test="effective-date-input"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            The date when this compensation takes effect.
                        </p>
                        <InputError :message="errors.effective_date" />
                    </div>

                    <!-- Bank Account Section Header -->
                    <div
                        class="border-t border-slate-200 pt-4 dark:border-slate-700"
                    >
                        <h4
                            class="text-sm font-medium text-slate-900 dark:text-slate-100"
                        >
                            Bank Account Details
                        </h4>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            Optional. Enter the employee's bank account
                            information for payroll.
                        </p>
                    </div>

                    <!-- Bank Name Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="bank-name" class="text-sm">Bank Name</Label>
                        <Input
                            id="bank-name"
                            type="text"
                            v-model="form.bank_name"
                            placeholder="e.g., BDO, BPI, Metrobank"
                            class="w-full"
                            :class="{ 'border-red-500': errors.bank_name }"
                            data-test="bank-name-input"
                        />
                        <InputError :message="errors.bank_name" />
                    </div>

                    <!-- Account Name Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="account-name" class="text-sm"
                            >Account Name</Label
                        >
                        <Input
                            id="account-name"
                            type="text"
                            v-model="form.account_name"
                            placeholder="Account holder's name"
                            class="w-full"
                            :class="{ 'border-red-500': errors.account_name }"
                            data-test="account-name-input"
                        />
                        <InputError :message="errors.account_name" />
                    </div>

                    <!-- Account Number Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="account-number" class="text-sm"
                            >Account Number</Label
                        >
                        <Input
                            id="account-number"
                            type="text"
                            v-model="form.account_number"
                            placeholder="Bank account number"
                            class="w-full"
                            :class="{ 'border-red-500': errors.account_number }"
                            data-test="account-number-input"
                        />
                        <InputError :message="errors.account_number" />
                    </div>

                    <!-- Account Type Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="account-type" class="text-sm"
                            >Account Type</Label
                        >
                        <EnumSelect
                            id="account-type"
                            v-model="form.account_type"
                            :options="bankAccountTypeOptions"
                            placeholder="Select account type"
                            data-test="account-type-select"
                        />
                        <InputError :message="errors.account_type" />
                    </div>

                    <!-- Remarks Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="remarks" class="text-sm">Remarks</Label>
                        <Textarea
                            id="remarks"
                            v-model="form.remarks"
                            placeholder="Optional: Reason for the compensation change..."
                            rows="2"
                            class="w-full resize-none sm:resize-y"
                            :class="{ 'border-red-500': errors.remarks }"
                            data-test="remarks-textarea"
                        />
                        <InputError :message="errors.remarks" />
                    </div>
                </div>

                <DialogFooter
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full sm:w-auto"
                        @click="handleOpenChange(false)"
                        :disabled="isSubmitting"
                        data-test="cancel-button"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        class="w-full sm:w-auto"
                        :disabled="!canSubmit"
                        data-test="submit-button"
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
                            isSubmitting
                                ? 'Saving...'
                                : isEdit
                                  ? 'Save Changes'
                                  : 'Save Compensation'
                        }}
                    </Button>
                </DialogFooter>

                <!-- Success Message -->
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="recentlySuccessful"
                        class="text-center text-sm text-green-600 dark:text-green-400"
                    >
                        Compensation saved successfully!
                    </p>
                </Transition>
            </form>
        </DialogContent>
    </Dialog>
</template>
