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
import type { VisitorVisitData } from '@/types';
import { ref } from 'vue';

const props = defineProps<{
    open: boolean;
    visit: VisitorVisitData | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    checkIn: [visitId: number, badgeNumber: string];
}>();

const badgeNumber = ref('');
const processing = ref(false);

function handleSubmit() {
    if (!props.visit) return;
    processing.value = true;
    emit('checkIn', props.visit.id, badgeNumber.value);
    processing.value = false;
    badgeNumber.value = '';
}

function close() {
    emit('update:open', false);
    badgeNumber.value = '';
}
</script>

<template>
    <Dialog :open="open" @update:open="close">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Check In Visitor</DialogTitle>
                <DialogDescription v-if="visit">
                    Check in <strong>{{ visit.visitor?.full_name }}</strong>
                </DialogDescription>
            </DialogHeader>

            <div v-if="visit" class="space-y-4 py-4">
                <div class="text-sm text-slate-600 dark:text-slate-400">
                    <p><strong>Purpose:</strong> {{ visit.purpose }}</p>
                    <p v-if="visit.work_location">
                        <strong>Location:</strong> {{ visit.work_location.name }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Badge Number (optional)
                    </label>
                    <Input
                        v-model="badgeNumber"
                        placeholder="Enter badge number..."
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="close">Cancel</Button>
                <Button :disabled="processing" @click="handleSubmit">
                    Check In
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
