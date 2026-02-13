<script setup lang="ts">
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ref, useTemplateRef, watch } from 'vue';

interface Props {
    open: boolean;
    title?: string;
    description?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Confirm your password',
    description:
        'For your security, please confirm your password to continue with this action.',
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirmed'): void;
    (e: 'cancelled'): void;
}>();

const passwordInput = useTemplateRef('passwordInput');
const password = ref('');
const error = ref<string | null>(null);
const processing = ref(false);

/**
 * Handles the password confirmation form submission.
 * Posts to the Fortify confirm-password endpoint and emits
 * 'confirmed' event on success.
 */
const handleSubmit = async () => {
    error.value = null;
    processing.value = true;

    try {
        const response = await fetch('/api/password/confirm', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                password: password.value,
            }),
        });

        if (response.status === 201 || response.ok) {
            password.value = '';
            emit('confirmed');
            emit('update:open', false);
        } else if (response.status === 422) {
            const data = await response.json();
            error.value =
                data.errors?.password?.[0] || 'The password is incorrect.';
            passwordInput.value?.$el?.focus();
        } else {
            error.value = 'An unexpected error occurred. Please try again.';
        }
    } catch {
        error.value = 'An unexpected error occurred. Please try again.';
    } finally {
        processing.value = false;
    }
};

/**
 * Gets the CSRF token from cookies for the request header.
 */
const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

/**
 * Handles dialog close/cancel action.
 * Resets form state and emits 'cancelled' event.
 */
const handleCancel = () => {
    password.value = '';
    error.value = null;
    emit('cancelled');
    emit('update:open', false);
};

/**
 * Handles open state change from the dialog.
 */
const handleOpenChange = (open: boolean) => {
    emit('update:open', open);
    if (!open) {
        password.value = '';
        error.value = null;
    }
};

// Reset state when dialog opens
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            password.value = '';
            error.value = null;
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            :show-close-button="false"
            data-test="password-confirmation-modal"
        >
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>
                        {{ description }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="confirm-password" class="sr-only"
                        >Password</Label
                    >
                    <Input
                        id="confirm-password"
                        type="password"
                        v-model="password"
                        ref="passwordInput"
                        placeholder="Password"
                        autocomplete="current-password"
                        autofocus
                        data-test="password-confirmation-input"
                    />
                    <InputError :message="error ?? undefined" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            @click="handleCancel"
                            :disabled="processing"
                            data-test="password-confirmation-cancel"
                        >
                            Cancel
                        </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        :disabled="processing || !password"
                        data-test="password-confirmation-submit"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        Confirm Password
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
