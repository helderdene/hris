<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface KeyResult {
    id: number;
    title: string;
    metric_type: string;
    metric_unit?: string;
    target_value: number;
    starting_value: number;
    current_value: number | null;
}

const props = defineProps<{
    open: boolean;
    goalId: number;
    keyResult?: KeyResult | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    success: [];
}>();

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const form = useForm({
    progress_value: 0,
    notes: '',
});

watch(
    () => props.keyResult,
    (kr) => {
        if (kr) {
            form.progress_value = kr.current_value ?? kr.starting_value;
        }
    },
    { immediate: true },
);

const inputLabel = computed(() => {
    if (!props.keyResult) return 'New Value';

    switch (props.keyResult.metric_type) {
        case 'percentage':
            return 'New Percentage (%)';
        case 'currency':
            return `New Amount (${props.keyResult.metric_unit || 'USD'})`;
        case 'boolean':
            return 'Completed (1 = Yes, 0 = No)';
        default:
            return props.keyResult.metric_unit
                ? `New Value (${props.keyResult.metric_unit})`
                : 'New Value';
    }
});

function handleSubmit() {
    if (!props.keyResult) return;

    form.post(`/api/performance/goals/${props.goalId}/key-results/${props.keyResult.id}/progress`, {
        onSuccess: () => {
            isOpen.value = false;
            form.reset();
            emit('success');
        },
    });
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Update Progress</DialogTitle>
                <DialogDescription v-if="keyResult">
                    {{ keyResult.title }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div v-if="keyResult" class="rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800">
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Current</span>
                        <span class="font-medium text-slate-900 dark:text-slate-100">
                            {{ keyResult.current_value ?? keyResult.starting_value }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Target</span>
                        <span class="font-medium text-slate-900 dark:text-slate-100">
                            {{ keyResult.target_value }}
                        </span>
                    </div>
                </div>

                <div>
                    <Label for="progress_value">{{ inputLabel }}</Label>
                    <Input
                        id="progress_value"
                        v-model.number="form.progress_value"
                        type="number"
                        step="any"
                        :class="{ 'border-red-500': form.errors.progress_value }"
                    />
                    <p v-if="form.errors.progress_value" class="mt-1 text-sm text-red-600">
                        {{ form.errors.progress_value }}
                    </p>
                </div>

                <div>
                    <Label for="notes">Notes (optional)</Label>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Add any notes about this progress update"
                        rows="3"
                    />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="isOpen = false">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Update Progress' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
