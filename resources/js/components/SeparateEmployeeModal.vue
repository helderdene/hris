<script setup lang="ts">
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
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    employeeId: number;
    employeeName: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const separationStatuses = [
    { value: 'resigned', label: 'Resigned' },
    { value: 'terminated', label: 'Terminated' },
    { value: 'retired', label: 'Retired' },
    { value: 'end_of_contract', label: 'End of Contract' },
    { value: 'deceased', label: 'Deceased' },
];

const form = ref({
    employment_status: '',
    termination_date: '',
    remarks: '',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

function resetForm(): void {
    form.value = {
        employment_status: '',
        termination_date: '',
        remarks: '',
    };
    errors.value = {};
}

function handleSubmit(): void {
    isSubmitting.value = true;
    errors.value = {};

    router.post(`/employees/${props.employeeId}/separate`, {
        employment_status: form.value.employment_status,
        termination_date: form.value.termination_date,
        remarks: form.value.remarks || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            resetForm();
        },
        onError: (validationErrors) => {
            errors.value = validationErrors;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}

function handleOpenChange(isOpen: boolean): void {
    open.value = isOpen;
    if (!isOpen && !isSubmitting.value) {
        resetForm();
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        form.value.termination_date = new Date().toISOString().split('T')[0];
    } else {
        resetForm();
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            class="max-h-[90vh] w-[95vw] overflow-y-auto sm:max-w-lg"
            data-test="separate-employee-modal"
        >
            <form @submit.prevent="handleSubmit" class="space-y-4 sm:space-y-6">
                <DialogHeader class="space-y-2 sm:space-y-3">
                    <DialogTitle class="text-lg sm:text-xl">
                        Separate Employee
                    </DialogTitle>
                    <DialogDescription class="text-sm">
                        Separate <strong>{{ employeeName }}</strong> from the
                        organization. This will update their employment status.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3 sm:space-y-4">
                    <!-- Warning -->
                    <div
                        class="rounded-md border border-amber-200 bg-amber-50 p-2.5 text-sm text-amber-800 sm:p-3 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
                    >
                        <div class="flex items-start gap-2">
                            <svg
                                class="mt-0.5 h-4 w-4 shrink-0"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                                />
                            </svg>
                            <span>
                                This action will mark the employee as separated.
                                Please ensure all offboarding steps have been
                                completed.
                            </span>
                        </div>
                    </div>

                    <!-- Employment Status -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="employment-status" class="text-sm">
                            Separation Reason
                            <span class="text-red-500">*</span>
                        </Label>
                        <select
                            id="employment-status"
                            v-model="form.employment_status"
                            class="flex h-9 w-full rounded-md border border-slate-200 bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-slate-950 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-800 dark:focus-visible:ring-slate-300"
                            :class="{
                                'border-red-500': errors.employment_status,
                            }"
                            data-test="employment-status-select"
                        >
                            <option value="" disabled>
                                Select separation reason
                            </option>
                            <option
                                v-for="status in separationStatuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </select>
                        <InputError :message="errors.employment_status" />
                    </div>

                    <!-- Termination Date -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="termination-date" class="text-sm">
                            Separation Date
                            <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="termination-date"
                            type="date"
                            v-model="form.termination_date"
                            class="w-full"
                            :class="{
                                'border-red-500': errors.termination_date,
                            }"
                            data-test="termination-date-input"
                        />
                        <InputError :message="errors.termination_date" />
                    </div>

                    <!-- Remarks -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="remarks" class="text-sm">Remarks</Label>
                        <Textarea
                            id="remarks"
                            v-model="form.remarks"
                            placeholder="Optional: Additional notes about the separation..."
                            rows="3"
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
                        class="w-full bg-red-600 text-white hover:bg-red-700 sm:w-auto dark:bg-red-700 dark:hover:bg-red-800"
                        :disabled="
                            !form.employment_status ||
                            !form.termination_date ||
                            isSubmitting
                        "
                        data-test="confirm-separation-button"
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
                                ? 'Processing...'
                                : 'Confirm Separation'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
