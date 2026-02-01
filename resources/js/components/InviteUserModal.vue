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
import { ref, watch } from 'vue';

interface Role {
    value: string;
    label: string;
}

interface Props {
    open: boolean;
    roles: Role[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const form = ref({
    email: '',
    name: '',
    role: 'employee',
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);
const recentlySuccessful = ref(false);

/**
 * Handles the invite form submission.
 */
const handleSubmit = async () => {
    errors.value = {};
    processing.value = true;

    try {
        const response = await fetch('/api/users/invite', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(form.value),
        });

        if (response.status === 201 || response.ok) {
            recentlySuccessful.value = true;
            resetForm();
            emit('success');

            setTimeout(() => {
                recentlySuccessful.value = false;
            }, 2000);
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
                email: 'You do not have permission to invite users.',
            };
        } else {
            errors.value = {
                email: 'An unexpected error occurred. Please try again.',
            };
        }
    } catch {
        errors.value = {
            email: 'An unexpected error occurred. Please try again.',
        };
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
 * Resets the form to initial state.
 */
const resetForm = () => {
    form.value = {
        email: '',
        name: '',
        role: 'employee',
    };
    errors.value = {};
};

/**
 * Handles dialog close action.
 */
const handleCancel = () => {
    resetForm();
    emit('update:open', false);
};

/**
 * Handles open state change from the dialog.
 */
const handleOpenChange = (open: boolean) => {
    emit('update:open', open);
    if (!open) {
        resetForm();
    }
};

// Reset state when dialog opens
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent data-test="invite-user-modal">
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Invite Team Member</DialogTitle>
                    <DialogDescription>
                        Send an invitation to a new team member. They will
                        receive an email with instructions to set up their
                        account.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <!-- Name Field -->
                    <div class="grid gap-2">
                        <Label for="invite-name">Name</Label>
                        <Input
                            id="invite-name"
                            type="text"
                            v-model="form.name"
                            placeholder="John Doe"
                            autocomplete="name"
                            required
                            data-test="invite-name-input"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <!-- Email Field -->
                    <div class="grid gap-2">
                        <Label for="invite-email">Email Address</Label>
                        <Input
                            id="invite-email"
                            type="email"
                            v-model="form.email"
                            placeholder="john@example.com"
                            autocomplete="email"
                            required
                            data-test="invite-email-input"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <!-- Role Field -->
                    <div class="grid gap-2">
                        <Label for="invite-role">Role</Label>
                        <select
                            id="invite-role"
                            v-model="form.role"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            data-test="invite-role-select"
                        >
                            <option
                                v-for="role in roles"
                                :key="role.value"
                                :value="role.value"
                            >
                                {{ role.label }}
                            </option>
                        </select>
                        <InputError :message="errors.role" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            @click="handleCancel"
                            :disabled="processing"
                            data-test="invite-cancel-button"
                        >
                            Cancel
                        </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        :disabled="processing || !form.email || !form.name"
                        data-test="invite-submit-button"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        {{ processing ? 'Sending...' : 'Send Invitation' }}
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
                        Invitation sent successfully!
                    </p>
                </Transition>
            </form>
        </DialogContent>
    </Dialog>
</template>
