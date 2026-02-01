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
    ALLOWED_MIME_TYPES,
    type DocumentCategory,
    type DocumentUploadFormData,
    type DocumentUploadFormErrors,
    formatFileSize,
    isAllowedMimeType,
    MAX_FILE_SIZE,
    MAX_FILE_SIZE_LABEL,
} from '@/types/document';
import { computed, ref, watch } from 'vue';

interface Props {
    categories: DocumentCategory[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref<DocumentUploadFormData>({
    file: null,
    name: '',
    document_category_id: '',
    version_notes: '',
});

const errors = ref<DocumentUploadFormErrors>({});
const isSubmitting = ref(false);
const recentlySuccessful = ref(false);

/**
 * File input reference.
 */
const fileInputRef = ref<HTMLInputElement | null>(null);

/**
 * Selected file info for display.
 */
const selectedFileInfo = computed(() => {
    if (!form.value.file) return null;
    return {
        name: form.value.file.name,
        size: formatFileSize(form.value.file.size),
        type: form.value.file.type,
    };
});

/**
 * Category options for the dropdown.
 */
const categoryOptions = computed(() => {
    return props.categories.map((cat) => ({
        value: cat.id.toString(),
        label: cat.name,
    }));
});

/**
 * Check if form can be submitted.
 */
const canSubmit = computed(() => {
    return (
        form.value.file &&
        form.value.name &&
        form.value.document_category_id &&
        !isSubmitting.value
    );
});

/**
 * Allowed file extensions for display.
 */
const allowedExtensions = 'PDF, DOC, DOCX, JPG, PNG, XLS, XLSX';

/**
 * Reset form to initial state.
 */
function resetForm() {
    form.value = {
        file: null,
        name: '',
        document_category_id: '',
        version_notes: '',
    };
    errors.value = {};
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

/**
 * Handle file selection.
 */
function handleFileSelect(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) return;

    // Validate file type
    if (!isAllowedMimeType(file.type)) {
        errors.value.file = `Invalid file type. Allowed types: ${allowedExtensions}`;
        form.value.file = null;
        return;
    }

    // Validate file size
    if (file.size > MAX_FILE_SIZE) {
        errors.value.file = `File size exceeds maximum limit of ${MAX_FILE_SIZE_LABEL}`;
        form.value.file = null;
        return;
    }

    errors.value.file = undefined;
    form.value.file = file;

    // Auto-fill document name from filename (without extension)
    if (!form.value.name) {
        const nameWithoutExt = file.name.replace(/\.[^/.]+$/, '');
        form.value.name = nameWithoutExt;
    }
}

/**
 * Handle browse button click.
 */
function handleBrowseClick() {
    fileInputRef.value?.click();
}

/**
 * Clear selected file.
 */
function clearFile() {
    form.value.file = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
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

    if (!form.value.file) {
        errors.value.file = 'Please select a file to upload';
        isSubmitting.value = false;
        return;
    }

    try {
        const formData = new FormData();
        formData.append('file', form.value.file);
        formData.append('name', form.value.name);
        formData.append(
            'document_category_id',
            form.value.document_category_id.toString(),
        );
        if (form.value.version_notes) {
            formData.append('version_notes', form.value.version_notes);
        }

        // Use company documents API endpoint
        const url = '/api/company-documents';

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: formData,
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
                    'You do not have permission to upload company documents.',
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
            class="max-h-[90vh] w-[95vw] overflow-y-auto sm:max-w-lg"
            data-test="company-document-upload-modal"
        >
            <form @submit.prevent="handleSubmit" class="space-y-4 sm:space-y-6">
                <DialogHeader class="space-y-2 sm:space-y-3">
                    <DialogTitle class="text-lg sm:text-xl">
                        Upload Company Document
                    </DialogTitle>
                    <DialogDescription class="text-sm">
                        Upload a new company-wide document (policy, handbook,
                        announcement). Supported formats:
                        {{ allowedExtensions }}
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

                    <!-- File Upload Area -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label class="text-sm">
                            File <span class="text-red-500">*</span>
                        </Label>

                        <!-- Hidden file input -->
                        <input
                            ref="fileInputRef"
                            type="file"
                            class="hidden"
                            :accept="ALLOWED_MIME_TYPES.join(',')"
                            @change="handleFileSelect"
                            data-test="file-input"
                        />

                        <!-- File Drop Area (visual only, no drag-drop per scope) -->
                        <div
                            v-if="!selectedFileInfo"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 transition-colors hover:border-slate-400 hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-800/50 dark:hover:border-slate-500"
                            :class="{ 'border-red-500': errors.file }"
                            @click="handleBrowseClick"
                            data-test="file-upload-area"
                        >
                            <svg
                                class="mb-3 h-10 w-10 text-slate-400"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"
                                />
                            </svg>
                            <p
                                class="mb-1 text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Click to browse
                            </p>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Max file size: {{ MAX_FILE_SIZE_LABEL }}
                            </p>
                        </div>

                        <!-- Selected File Display -->
                        <div
                            v-else
                            class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50"
                            data-test="selected-file-display"
                        >
                            <div
                                class="flex items-center gap-3 overflow-hidden"
                            >
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-200 dark:bg-slate-700"
                                >
                                    <svg
                                        class="h-5 w-5 text-slate-600 dark:text-slate-400"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                                        />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ selectedFileInfo.name }}
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ selectedFileInfo.size }}
                                    </p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="shrink-0"
                                @click="clearFile"
                                data-test="clear-file-button"
                            >
                                <svg
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M6 18 18 6M6 6l12 12"
                                    />
                                </svg>
                            </Button>
                        </div>

                        <InputError :message="errors.file" />
                    </div>

                    <!-- Document Name Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="document-name" class="text-sm">
                            Document Name <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="document-name"
                            type="text"
                            v-model="form.name"
                            placeholder="e.g., Employee Handbook 2026"
                            class="w-full"
                            :class="{ 'border-red-500': errors.name }"
                            data-test="document-name-input"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            A descriptive name for this company document
                        </p>
                        <InputError :message="errors.name" />
                    </div>

                    <!-- Category Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="document-category" class="text-sm">
                            Category <span class="text-red-500">*</span>
                        </Label>
                        <EnumSelect
                            id="document-category"
                            v-model="form.document_category_id"
                            :options="categoryOptions"
                            placeholder="Select a category"
                            data-test="category-select"
                        />
                        <InputError :message="errors.document_category_id" />
                    </div>

                    <!-- Version Notes Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="version-notes" class="text-sm">
                            Version Notes
                        </Label>
                        <Textarea
                            id="version-notes"
                            v-model="form.version_notes"
                            placeholder="Optional: Add notes about this document version..."
                            rows="2"
                            class="w-full resize-none sm:resize-y"
                            :class="{ 'border-red-500': errors.version_notes }"
                            data-test="version-notes-textarea"
                        />
                        <InputError :message="errors.version_notes" />
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
                        {{ isSubmitting ? 'Uploading...' : 'Upload Document' }}
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
                        Document uploaded successfully!
                    </p>
                </Transition>
            </form>
        </DialogContent>
    </Dialog>
</template>
