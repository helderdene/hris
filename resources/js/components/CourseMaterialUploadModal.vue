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
import { computed, ref, watch } from 'vue';

interface Props {
    courseId: number;
}

interface FormData {
    file: File | null;
    title: string;
    description: string;
    material_type: string;
    external_url: string;
}

interface FormErrors {
    file?: string;
    title?: string;
    description?: string;
    material_type?: string;
    external_url?: string;
    general?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
const MAX_FILE_SIZE_LABEL = '50MB';

const ALLOWED_DOCUMENT_MIMES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain',
];

const ALLOWED_VIDEO_MIMES = [
    'video/mp4',
    'video/webm',
    'video/quicktime',
    'video/x-msvideo',
];

const ALLOWED_IMAGE_MIMES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
];

const form = ref<FormData>({
    file: null,
    title: '',
    description: '',
    material_type: 'document',
    external_url: '',
});

const errors = ref<FormErrors>({});
const isSubmitting = ref(false);
const recentlySuccessful = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);

const materialTypeOptions = [
    { value: 'document', label: 'Document (PDF, DOC, XLS, PPT)' },
    { value: 'video', label: 'Video (MP4, WebM)' },
    { value: 'image', label: 'Image (JPG, PNG, GIF)' },
    { value: 'link', label: 'External Link' },
];

const isLinkType = computed(() => form.value.material_type === 'link');

const selectedFileInfo = computed(() => {
    if (!form.value.file) return null;
    return {
        name: form.value.file.name,
        size: formatFileSize(form.value.file.size),
    };
});

const canSubmit = computed(() => {
    if (isLinkType.value) {
        return form.value.title && form.value.external_url && !isSubmitting.value;
    }
    return form.value.file && form.value.title && !isSubmitting.value;
});

const acceptedMimeTypes = computed(() => {
    switch (form.value.material_type) {
        case 'document':
            return ALLOWED_DOCUMENT_MIMES.join(',');
        case 'video':
            return ALLOWED_VIDEO_MIMES.join(',');
        case 'image':
            return ALLOWED_IMAGE_MIMES.join(',');
        default:
            return [...ALLOWED_DOCUMENT_MIMES, ...ALLOWED_VIDEO_MIMES, ...ALLOWED_IMAGE_MIMES].join(',');
    }
});

const allowedExtensionsLabel = computed(() => {
    switch (form.value.material_type) {
        case 'document':
            return 'PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT';
        case 'video':
            return 'MP4, WebM, MOV, AVI';
        case 'image':
            return 'JPG, PNG, GIF, WebP';
        default:
            return 'All supported formats';
    }
});

function formatFileSize(bytes: number): string {
    if (bytes >= 1073741824) {
        return (bytes / 1073741824).toFixed(2) + ' GB';
    }
    if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + ' MB';
    }
    if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + ' KB';
    }
    return bytes + ' bytes';
}

function isAllowedMimeType(mimeType: string): boolean {
    const allowedMimes = (() => {
        switch (form.value.material_type) {
            case 'document':
                return ALLOWED_DOCUMENT_MIMES;
            case 'video':
                return ALLOWED_VIDEO_MIMES;
            case 'image':
                return ALLOWED_IMAGE_MIMES;
            default:
                return [...ALLOWED_DOCUMENT_MIMES, ...ALLOWED_VIDEO_MIMES, ...ALLOWED_IMAGE_MIMES];
        }
    })();
    return allowedMimes.includes(mimeType);
}

function resetForm() {
    form.value = {
        file: null,
        title: '',
        description: '',
        material_type: 'document',
        external_url: '',
    };
    errors.value = {};
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function handleFileSelect(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    processFile(file);
}

function processFile(file: File | undefined) {
    if (!file) return;

    if (!isAllowedMimeType(file.type)) {
        errors.value.file = `Invalid file type. Allowed types: ${allowedExtensionsLabel.value}`;
        form.value.file = null;
        return;
    }

    if (file.size > MAX_FILE_SIZE) {
        errors.value.file = `File size exceeds maximum limit of ${MAX_FILE_SIZE_LABEL}`;
        form.value.file = null;
        return;
    }

    errors.value.file = undefined;
    form.value.file = file;

    if (!form.value.title) {
        const nameWithoutExt = file.name.replace(/\.[^/.]+$/, '');
        form.value.title = nameWithoutExt;
    }
}

function handleBrowseClick() {
    fileInputRef.value?.click();
}

function clearFile() {
    form.value.file = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function handleDragEnter(event: DragEvent) {
    event.preventDefault();
    isDragging.value = true;
}

function handleDragLeave(event: DragEvent) {
    event.preventDefault();
    isDragging.value = false;
}

function handleDragOver(event: DragEvent) {
    event.preventDefault();
}

function handleDrop(event: DragEvent) {
    event.preventDefault();
    isDragging.value = false;
    const file = event.dataTransfer?.files?.[0];
    processFile(file);
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    if (!isLinkType.value && !form.value.file) {
        errors.value.file = 'Please select a file to upload';
        isSubmitting.value = false;
        return;
    }

    if (isLinkType.value && !form.value.external_url) {
        errors.value.external_url = 'Please enter a URL';
        isSubmitting.value = false;
        return;
    }

    try {
        const formData = new FormData();
        formData.append('title', form.value.title);
        formData.append('material_type', form.value.material_type);

        if (form.value.description) {
            formData.append('description', form.value.description);
        }

        if (isLinkType.value) {
            formData.append('external_url', form.value.external_url);
        } else if (form.value.file) {
            formData.append('file', form.value.file);
        }

        const url = `/api/training/courses/${props.courseId}/materials`;

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
                general: 'You do not have permission to upload materials.',
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

function handleClose() {
    if (!isSubmitting.value) {
        resetForm();
        emit('close');
    }
}

function handleOpenChange(isOpen: boolean) {
    open.value = isOpen;
    if (!isOpen) {
        handleClose();
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        resetForm();
    }
});

watch(() => form.value.material_type, () => {
    form.value.file = null;
    form.value.external_url = '';
    errors.value.file = undefined;
    errors.value.external_url = undefined;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-h-[90vh] w-[95vw] overflow-y-auto sm:max-w-lg">
            <form @submit.prevent="handleSubmit" class="space-y-4 sm:space-y-6">
                <DialogHeader class="space-y-2 sm:space-y-3">
                    <DialogTitle class="text-lg sm:text-xl">
                        Add Course Material
                    </DialogTitle>
                    <DialogDescription class="text-sm">
                        Upload documents, videos, images, or add external links.
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

                    <!-- Material Type -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="material-type" class="text-sm">
                            Material Type <span class="text-red-500">*</span>
                        </Label>
                        <EnumSelect
                            id="material-type"
                            v-model="form.material_type"
                            :options="materialTypeOptions"
                            placeholder="Select type"
                        />
                        <InputError :message="errors.material_type" />
                    </div>

                    <!-- File Upload Area (for non-link types) -->
                    <div v-if="!isLinkType" class="space-y-1.5 sm:space-y-2">
                        <Label class="text-sm">
                            File <span class="text-red-500">*</span>
                        </Label>

                        <input
                            ref="fileInputRef"
                            type="file"
                            class="hidden"
                            :accept="acceptedMimeTypes"
                            @change="handleFileSelect"
                        />

                        <div
                            v-if="!selectedFileInfo"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-8 transition-colors"
                            :class="{
                                'border-blue-400 bg-blue-50 dark:border-blue-600 dark:bg-blue-900/20': isDragging,
                                'border-red-500': errors.file,
                                'border-slate-300 bg-slate-50 hover:border-slate-400 hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-800/50 dark:hover:border-slate-500': !isDragging && !errors.file,
                            }"
                            @click="handleBrowseClick"
                            @dragenter="handleDragEnter"
                            @dragleave="handleDragLeave"
                            @dragover="handleDragOver"
                            @drop="handleDrop"
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
                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"
                                />
                            </svg>
                            <p class="mb-1 text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ isDragging ? 'Drop file here' : 'Drag & drop or click to browse' }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ allowedExtensionsLabel }} (Max: {{ MAX_FILE_SIZE_LABEL }})
                            </p>
                        </div>

                        <div
                            v-else
                            class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50"
                        >
                            <div class="flex items-center gap-3 overflow-hidden">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-200 dark:bg-slate-700">
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
                                            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                                        />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                                        {{ selectedFileInfo.name }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
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
                            >
                                <svg
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </Button>
                        </div>

                        <InputError :message="errors.file" />
                    </div>

                    <!-- External URL (for link type) -->
                    <div v-if="isLinkType" class="space-y-1.5 sm:space-y-2">
                        <Label for="external-url" class="text-sm">
                            URL <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="external-url"
                            type="url"
                            v-model="form.external_url"
                            placeholder="https://example.com/resource"
                            class="w-full"
                            :class="{ 'border-red-500': errors.external_url }"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            External link to a resource, video, or website
                        </p>
                        <InputError :message="errors.external_url" />
                    </div>

                    <!-- Title Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="material-title" class="text-sm">
                            Title <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="material-title"
                            type="text"
                            v-model="form.title"
                            placeholder="e.g., Course Introduction Video"
                            class="w-full"
                            :class="{ 'border-red-500': errors.title }"
                        />
                        <InputError :message="errors.title" />
                    </div>

                    <!-- Description Field -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <Label for="material-description" class="text-sm">
                            Description
                        </Label>
                        <Textarea
                            id="material-description"
                            v-model="form.description"
                            placeholder="Optional: Add a description for this material..."
                            rows="2"
                            class="w-full resize-none sm:resize-y"
                        />
                        <InputError :message="errors.description" />
                    </div>
                </div>

                <DialogFooter class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full sm:w-auto"
                        @click="handleOpenChange(false)"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        class="w-full sm:w-auto"
                        :disabled="!canSubmit"
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
                        {{ isSubmitting ? 'Uploading...' : 'Add Material' }}
                    </Button>
                </DialogFooter>

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
                        Material added successfully!
                    </p>
                </Transition>
            </form>
        </DialogContent>
    </Dialog>
</template>
