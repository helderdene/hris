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
import { type DocumentCategory } from '@/types/document';
import { computed, ref, watch } from 'vue';

interface FormData {
    name: string;
    description: string;
}

interface FormErrors {
    name?: string;
    description?: string;
    general?: string;
}

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success', category: DocumentCategory): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref<FormData>({
    name: '',
    description: '',
});

const errors = ref<FormErrors>({});
const isSubmitting = ref(false);
const recentlySuccessful = ref(false);

/**
 * Check if form can be submitted.
 */
const canSubmit = computed(() => {
    return form.value.name.trim() && !isSubmitting.value;
});

/**
 * Reset form to initial state.
 */
function resetForm() {
    form.value = {
        name: '',
        description: '',
    };
    errors.value = {};
    recentlySuccessful.value = false;
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

    // Client-side validation
    if (!form.value.name.trim()) {
        errors.value.name = 'Category name is required';
        isSubmitting.value = false;
        return;
    }

    if (form.value.name.length > 100) {
        errors.value.name = 'Category name cannot exceed 100 characters';
        isSubmitting.value = false;
        return;
    }

    if (form.value.description && form.value.description.length > 500) {
        errors.value.description = 'Description cannot exceed 500 characters';
        isSubmitting.value = false;
        return;
    }

    try {
        const response = await fetch('/api/document-categories', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                name: form.value.name.trim(),
                description: form.value.description.trim() || null,
            }),
        });

        if (response.status === 201) {
            const data = await response.json();
            recentlySuccessful.value = true;

            setTimeout(() => {
                emit('success', data.data as DocumentCategory);
                open.value = false;
                resetForm();
            }, 1000);
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
                general: 'You do not have permission to create categories.',
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

// Reset form when modal opens
watch(open, (isOpen) => {
    if (isOpen) {
        resetForm();
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            class="max-h-[90vh] w-[95vw] overflow-y-auto sm:max-w-md"
            data-test="create-category-modal"
        >
            <form @submit.prevent="handleSubmit" class="space-y-4 sm:space-y-5">
                <DialogHeader class="space-y-2">
                    <DialogTitle class="text-lg sm:text-xl">
                        Create New Category
                    </DialogTitle>
                    <DialogDescription class="text-sm">
                        Create a custom document category to organize your
                        documents.
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

                    <!-- Category Name Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="category-name" class="text-sm">
                            Category Name <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="category-name"
                            type="text"
                            v-model="form.name"
                            placeholder="e.g., Training Materials"
                            class="w-full"
                            :class="{ 'border-red-500': errors.name }"
                            :disabled="isSubmitting"
                            maxlength="100"
                            data-test="category-name-input"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Maximum 100 characters
                        </p>
                        <InputError :message="errors.name" />
                    </div>

                    <!-- Description Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="category-description" class="text-sm">
                            Description
                        </Label>
                        <Textarea
                            id="category-description"
                            v-model="form.description"
                            placeholder="Optional: Describe what documents belong in this category..."
                            rows="3"
                            class="w-full resize-none sm:resize-y"
                            :class="{ 'border-red-500': errors.description }"
                            :disabled="isSubmitting"
                            maxlength="500"
                            data-test="category-description-textarea"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Optional. Maximum 500 characters
                        </p>
                        <InputError :message="errors.description" />
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
                        {{ isSubmitting ? 'Creating...' : 'Create Category' }}
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
                        Category created successfully!
                    </p>
                </Transition>
            </form>
        </DialogContent>
    </Dialog>
</template>
