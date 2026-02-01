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
import {
    type Document,
    type DocumentVersion,
    isPreviewable,
} from '@/types/document';
import { computed, ref, watch } from 'vue';

interface Props {
    document: Document | null;
    version?: DocumentVersion | null;
}

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'download', document: Document, version: DocumentVersion): void;
}>();

/**
 * Loading state for the preview content.
 */
const loading = ref(true);
const error = ref<string | null>(null);

/**
 * Full screen mode state.
 */
const isFullscreen = ref(false);

/**
 * Get the version to preview (either the specified version or the latest).
 */
const previewVersion = computed((): DocumentVersion | null => {
    if (props.version) {
        return props.version;
    }

    if (!props.document?.versions?.length) {
        return null;
    }

    // Get the latest version (highest version_number)
    return props.document.versions.reduce((latest, v) =>
        v.version_number > latest.version_number ? v : latest,
    );
});

/**
 * Check if the document is previewable.
 */
const canPreview = computed(() => {
    if (!props.document) return false;
    return isPreviewable(props.document.mime_type);
});

/**
 * Check if the document is a PDF.
 */
const isPdf = computed(() => {
    return props.document?.mime_type === 'application/pdf';
});

/**
 * Check if the document is an image.
 */
const isImage = computed(() => {
    const mimeType = props.document?.mime_type;
    return mimeType === 'image/jpeg' || mimeType === 'image/png';
});

/**
 * Get the preview URL for the current document version.
 */
const previewUrl = computed(() => {
    if (!props.document || !previewVersion.value) {
        return null;
    }

    return `/api/documents/${props.document.id}/versions/${previewVersion.value.id}/preview`;
});

/**
 * Get file type label for display.
 */
const fileTypeLabel = computed(() => {
    if (!props.document) return '';

    const mimeType = props.document.mime_type;
    switch (mimeType) {
        case 'application/pdf':
            return 'PDF';
        case 'image/jpeg':
            return 'JPEG Image';
        case 'image/png':
            return 'PNG Image';
        case 'application/msword':
            return 'Word Document';
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            return 'Word Document';
        case 'application/vnd.ms-excel':
            return 'Excel Spreadsheet';
        case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            return 'Excel Spreadsheet';
        default:
            return 'Document';
    }
});

/**
 * Handle preview content load.
 */
function handleLoad() {
    loading.value = false;
    error.value = null;
}

/**
 * Handle preview content error.
 */
function handleError() {
    loading.value = false;
    error.value = 'Failed to load preview';
}

/**
 * Handle download button click.
 */
function handleDownload() {
    if (!props.document || !previewVersion.value) return;
    emit('download', props.document, previewVersion.value);
}

/**
 * Handle close button click.
 */
function handleClose() {
    open.value = false;
    emit('close');
}

/**
 * Toggle fullscreen mode.
 */
function toggleFullscreen() {
    isFullscreen.value = !isFullscreen.value;
}

/**
 * Reset state when dialog opens.
 */
watch(open, (newVal) => {
    if (newVal) {
        loading.value = true;
        error.value = null;
        isFullscreen.value = false;
    }
});

/**
 * Dialog content class based on fullscreen state and content type.
 */
const dialogContentClass = computed(() => {
    if (isFullscreen.value) {
        return 'max-h-[95vh] w-[95vw] max-w-[95vw] overflow-hidden';
    }

    if (isPdf.value) {
        return 'max-h-[90vh] w-[95vw] max-w-4xl overflow-hidden';
    }

    return 'max-h-[90vh] w-[95vw] max-w-3xl overflow-hidden sm:max-w-xl md:max-w-2xl lg:max-w-3xl';
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            :class="dialogContentClass"
            data-test="document-preview-modal"
        >
            <DialogHeader class="space-y-2 sm:space-y-3">
                <div class="flex items-center justify-between pr-8">
                    <DialogTitle class="text-lg sm:text-xl">
                        {{ document?.name || 'Document Preview' }}
                    </DialogTitle>
                </div>
                <DialogDescription v-if="document" class="text-sm">
                    {{ document.original_filename }}
                    <span v-if="previewVersion" class="text-slate-400">
                        (v{{ previewVersion.version_number }})
                    </span>
                </DialogDescription>
            </DialogHeader>

            <!-- Preview Content -->
            <div
                class="relative flex-1 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800"
                :class="{
                    'h-[60vh]': !isFullscreen && isPdf,
                    'h-[70vh]': isFullscreen && isPdf,
                    'flex items-center justify-center': isImage || !canPreview,
                    'min-h-[200px]': true,
                }"
            >
                <!-- Loading State -->
                <div
                    v-if="loading && canPreview"
                    class="absolute inset-0 flex items-center justify-center bg-slate-100 dark:bg-slate-800"
                    data-test="preview-loading"
                >
                    <div class="flex flex-col items-center gap-3">
                        <svg
                            class="h-8 w-8 animate-spin text-slate-400"
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
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Loading preview...
                        </p>
                    </div>
                </div>

                <!-- Error State -->
                <div
                    v-if="error"
                    class="flex flex-col items-center justify-center gap-3 p-8 text-center"
                    data-test="preview-error"
                >
                    <svg
                        class="h-12 w-12 text-red-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"
                        />
                    </svg>
                    <p
                        class="text-sm font-medium text-red-600 dark:text-red-400"
                    >
                        {{ error }}
                    </p>
                    <Button variant="outline" size="sm" @click="handleDownload">
                        Download Instead
                    </Button>
                </div>

                <!-- PDF Preview using embed -->
                <embed
                    v-if="isPdf && canPreview && previewUrl && !error"
                    :src="previewUrl"
                    type="application/pdf"
                    class="h-full w-full"
                    @load="handleLoad"
                    @error="handleError"
                    data-test="pdf-preview"
                />

                <!-- Image Preview -->
                <img
                    v-else-if="isImage && canPreview && previewUrl && !error"
                    :src="previewUrl"
                    :alt="document?.name || 'Document preview'"
                    class="max-h-full max-w-full object-contain"
                    :class="{
                        'max-h-[50vh]': !isFullscreen,
                        'max-h-[65vh]': isFullscreen,
                    }"
                    @load="handleLoad"
                    @error="handleError"
                    data-test="image-preview"
                />

                <!-- Non-previewable File Message -->
                <div
                    v-else-if="!canPreview && document"
                    class="flex flex-col items-center justify-center gap-4 p-8 text-center"
                    data-test="no-preview-message"
                >
                    <!-- File Type Icon -->
                    <div
                        class="rounded-full bg-slate-200 p-4 dark:bg-slate-700"
                    >
                        <svg
                            class="h-12 w-12 text-slate-500 dark:text-slate-400"
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
                    <div>
                        <h3
                            class="text-lg font-medium text-slate-900 dark:text-slate-100"
                        >
                            Preview not available
                        </h3>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ fileTypeLabel }} files cannot be previewed in the
                            browser.
                        </p>
                    </div>
                    <Button
                        @click="handleDownload"
                        data-test="download-instead-button"
                    >
                        <svg
                            class="mr-2 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"
                            />
                        </svg>
                        Download File
                    </Button>
                </div>
            </div>

            <DialogFooter
                class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between"
            >
                <!-- Left side actions -->
                <div class="flex gap-2">
                    <!-- Full screen toggle (for previewable types) -->
                    <Button
                        v-if="canPreview"
                        variant="outline"
                        size="sm"
                        @click="toggleFullscreen"
                        data-test="fullscreen-toggle"
                    >
                        <svg
                            v-if="!isFullscreen"
                            class="mr-1.5 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"
                            />
                        </svg>
                        <svg
                            v-else
                            class="mr-1.5 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25"
                            />
                        </svg>
                        {{ isFullscreen ? 'Exit Fullscreen' : 'Fullscreen' }}
                    </Button>

                    <!-- Download button -->
                    <Button
                        variant="outline"
                        size="sm"
                        @click="handleDownload"
                        data-test="preview-download-button"
                    >
                        <svg
                            class="mr-1.5 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"
                            />
                        </svg>
                        Download
                    </Button>
                </div>

                <!-- Close button -->
                <Button
                    variant="default"
                    @click="handleClose"
                    data-test="close-preview-button"
                >
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
