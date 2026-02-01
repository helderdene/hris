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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ref, watch, computed } from 'vue';

export type ApprovalAction = 'approve' | 'reject';

export interface ApprovalTarget {
    id: number;
    type: 'leave_approval' | 'requisition_approval';
    title: string;
    employee_name: string;
    description: string;
}

const props = defineProps<{
    open: boolean;
    action: ApprovalAction;
    target: ApprovalTarget | null;
    processing?: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [remarks: string];
}>();

const remarks = ref('');
const error = ref<string | null>(null);

const isApprove = computed(() => props.action === 'approve');
const isReject = computed(() => props.action === 'reject');

const dialogTitle = computed(() => {
    if (isApprove.value) return 'Approve Request';
    return 'Reject Request';
});

const dialogDescription = computed(() => {
    if (!props.target) return '';
    if (isApprove.value) {
        return `You are about to approve the ${props.target.title.toLowerCase()} from ${props.target.employee_name}.`;
    }
    return `You are about to reject the ${props.target.title.toLowerCase()} from ${props.target.employee_name}.`;
});

const remarksLabel = computed(() => {
    if (isApprove.value) return 'Remarks (optional)';
    return 'Reason for rejection';
});

const remarksPlaceholder = computed(() => {
    if (isApprove.value) return 'Add any comments or notes...';
    return 'Please provide a reason for rejecting this request...';
});

watch(() => props.open, (open) => {
    if (!open) {
        remarks.value = '';
        error.value = null;
    }
});

function handleSubmit() {
    error.value = null;

    // Validate - rejection requires remarks
    if (isReject.value && !remarks.value.trim()) {
        error.value = 'Please provide a reason for rejection.';
        return;
    }

    emit('submit', remarks.value.trim());
}

function handleClose() {
    if (!props.processing) {
        emit('update:open', false);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    {{ dialogDescription }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="target" class="py-4">
                <!-- Request details -->
                <div class="rounded-lg bg-slate-50 dark:bg-slate-800/50 p-3 mb-4">
                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                        {{ target.title }}
                    </p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{ target.description }}
                    </p>
                </div>

                <!-- Remarks input -->
                <div class="space-y-2">
                    <Label :for="`remarks-${target.id}`">{{ remarksLabel }}</Label>
                    <Textarea
                        :id="`remarks-${target.id}`"
                        v-model="remarks"
                        :placeholder="remarksPlaceholder"
                        rows="3"
                        :disabled="processing"
                        :class="{ 'border-red-500': error }"
                    />
                    <p v-if="error" class="text-sm text-red-600 dark:text-red-400">
                        {{ error }}
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="processing"
                    @click="handleClose"
                >
                    Cancel
                </Button>
                <Button
                    v-if="isApprove"
                    :disabled="processing"
                    class="bg-green-600 hover:bg-green-700 text-white"
                    @click="handleSubmit"
                >
                    <svg
                        v-if="processing"
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
                    {{ processing ? 'Approving...' : 'Approve' }}
                </Button>
                <Button
                    v-else
                    variant="destructive"
                    :disabled="processing"
                    @click="handleSubmit"
                >
                    <svg
                        v-if="processing"
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
                    {{ processing ? 'Rejecting...' : 'Reject' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
