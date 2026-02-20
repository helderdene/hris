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
import { Textarea } from '@/components/ui/textarea';
import type { VisitorVisitData } from '@/types';
import { ref } from 'vue';

const props = defineProps<{
    open: boolean;
    visit: VisitorVisitData | null;
    mode: 'approve' | 'reject';
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    approve: [visitId: number];
    reject: [visitId: number, reason: string];
}>();

const rejectionReason = ref('');
const processing = ref(false);

function handleSubmit() {
    if (!props.visit) return;
    processing.value = true;

    if (props.mode === 'approve') {
        emit('approve', props.visit.id);
    } else {
        emit('reject', props.visit.id, rejectionReason.value);
    }

    processing.value = false;
    rejectionReason.value = '';
}

function close() {
    emit('update:open', false);
    rejectionReason.value = '';
}
</script>

<template>
    <Dialog :open="open" @update:open="close">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ mode === 'approve' ? 'Approve Visit' : 'Reject Visit' }}
                </DialogTitle>
                <DialogDescription v-if="visit">
                    {{ mode === 'approve' ? 'Approve' : 'Reject' }} visit request from
                    <strong>{{ visit.visitor?.full_name }}</strong>
                </DialogDescription>
            </DialogHeader>

            <div v-if="visit" class="space-y-4 py-4">
                <div class="text-sm text-slate-600 dark:text-slate-400">
                    <p><strong>Purpose:</strong> {{ visit.purpose }}</p>
                    <p v-if="visit.expected_at">
                        <strong>Expected:</strong>
                        {{ new Date(visit.expected_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) }}
                    </p>
                    <p v-if="visit.work_location">
                        <strong>Location:</strong> {{ visit.work_location.name }}
                    </p>
                </div>

                <div v-if="mode === 'reject'">
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Reason (optional)
                    </label>
                    <Textarea
                        v-model="rejectionReason"
                        placeholder="Enter reason for rejection..."
                        rows="3"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="close">Cancel</Button>
                <Button
                    :variant="mode === 'approve' ? 'default' : 'destructive'"
                    :disabled="processing"
                    @click="handleSubmit"
                >
                    {{ mode === 'approve' ? 'Approve' : 'Reject' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
