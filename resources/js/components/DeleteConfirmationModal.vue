<script setup lang="ts">
/**
 * DeleteConfirmationModal - Generic delete confirmation dialog.
 */
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { computed } from 'vue';

const props = defineProps<{
    open: boolean;
    title: string;
    description: string;
    processing?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
}>();

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

function handleConfirm() {
    emit('confirm');
}

function handleCancel() {
    emit('update:open', false);
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle class="text-red-600 dark:text-red-400">
                    {{ title }}
                </DialogTitle>
                <DialogDescription>
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="outline"
                        @click="handleCancel"
                        :disabled="processing"
                    >
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    type="button"
                    variant="destructive"
                    @click="handleConfirm"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    {{ processing ? 'Deleting...' : 'Delete' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
