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
import { type Document, isPreviewable } from '@/types/document';
import { computed, ref } from 'vue';

interface Props {
    document: Document;
    canManage: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'download', document: Document): void;
    (e: 'preview', document: Document): void;
    (e: 'view-versions', document: Document): void;
    (e: 'delete', document: Document): void;
}>();

/**
 * Delete confirmation dialog state.
 */
const showDeleteConfirm = ref(false);
const isDeleting = ref(false);

/**
 * Format a date string for display.
 */
function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * Check if document can be previewed.
 */
const canPreview = computed(() => isPreviewable(props.document.mime_type));

/**
 * Get file type icon based on MIME type.
 */
const fileTypeIcon = computed(() => {
    const mimeType = props.document.mime_type;

    if (mimeType === 'application/pdf') {
        return 'pdf';
    } else if (mimeType.startsWith('image/')) {
        return 'image';
    } else if (
        mimeType === 'application/msword' ||
        mimeType ===
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ) {
        return 'word';
    } else if (
        mimeType === 'application/vnd.ms-excel' ||
        mimeType ===
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ) {
        return 'excel';
    }

    return 'file';
});

/**
 * Get file type badge color based on type.
 */
const fileTypeBadgeClass = computed(() => {
    const type = props.document.file_type;

    switch (type) {
        case 'PDF':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300';
        case 'DOC':
        case 'DOCX':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 'JPG':
        case 'PNG':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
        case 'XLS':
        case 'XLSX':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
    }
});

/**
 * Handle download button click.
 */
function handleDownload() {
    emit('download', props.document);
}

/**
 * Handle preview button click.
 */
function handlePreview() {
    emit('preview', props.document);
}

/**
 * Handle view versions button click.
 */
function handleViewVersions() {
    emit('view-versions', props.document);
}

/**
 * Handle delete button click.
 */
function handleDeleteClick() {
    showDeleteConfirm.value = true;
}

/**
 * Confirm and execute delete.
 */
async function confirmDelete() {
    isDeleting.value = true;
    emit('delete', props.document);
    // The parent component will handle the actual deletion
    // and close the modal on success
}

/**
 * Cancel delete dialog.
 */
function cancelDelete() {
    showDeleteConfirm.value = false;
    isDeleting.value = false;
}
</script>

<template>
    <tr
        class="border-b border-slate-200 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/50"
        data-test="document-row"
    >
        <!-- Document Name with Icon -->
        <td class="px-4 py-3">
            <div class="flex items-center gap-3">
                <!-- File Type Icon -->
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800"
                >
                    <!-- PDF Icon -->
                    <svg
                        v-if="fileTypeIcon === 'pdf'"
                        class="h-4 w-4 text-red-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                        />
                    </svg>
                    <!-- Image Icon -->
                    <svg
                        v-else-if="fileTypeIcon === 'image'"
                        class="h-4 w-4 text-green-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"
                        />
                    </svg>
                    <!-- Word Icon -->
                    <svg
                        v-else-if="fileTypeIcon === 'word'"
                        class="h-4 w-4 text-blue-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                        />
                    </svg>
                    <!-- Excel Icon -->
                    <svg
                        v-else-if="fileTypeIcon === 'excel'"
                        class="h-4 w-4 text-emerald-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M13.125 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5"
                        />
                    </svg>
                    <!-- Generic File Icon -->
                    <svg
                        v-else
                        class="h-4 w-4 text-slate-500"
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
                        {{ document.name }}
                    </p>
                    <p
                        class="truncate text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{ document.original_filename }}
                    </p>
                </div>
            </div>
        </td>

        <!-- Category -->
        <td class="hidden px-4 py-3 sm:table-cell">
            <span
                class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
            >
                {{ document.category?.name || '-' }}
            </span>
        </td>

        <!-- Version -->
        <td class="hidden px-4 py-3 md:table-cell">
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium"
                    :class="fileTypeBadgeClass"
                >
                    {{ document.file_type }}
                </span>
                <span class="text-sm text-slate-600 dark:text-slate-400">
                    v{{ document.current_version }}
                </span>
            </div>
        </td>

        <!-- Uploaded Date -->
        <td class="hidden px-4 py-3 lg:table-cell">
            <span class="text-sm text-slate-600 dark:text-slate-400">
                {{ formatDate(document.uploaded_at) }}
            </span>
        </td>

        <!-- Uploaded By -->
        <td class="hidden px-4 py-3 lg:table-cell">
            <span class="text-sm text-slate-600 dark:text-slate-400">
                {{ document.uploaded_by_name || '-' }}
            </span>
        </td>

        <!-- Actions -->
        <td class="px-4 py-3">
            <div class="flex items-center justify-end gap-1">
                <!-- Download Button -->
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-8 w-8 p-0"
                    @click="handleDownload"
                    title="Download"
                    data-test="download-button"
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
                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"
                        />
                    </svg>
                </Button>

                <!-- Preview Button (only for previewable types) -->
                <Button
                    v-if="canPreview"
                    variant="ghost"
                    size="sm"
                    class="h-8 w-8 p-0"
                    @click="handlePreview"
                    title="Preview"
                    data-test="preview-button"
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
                            d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                        />
                    </svg>
                </Button>

                <!-- View Versions Button -->
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-8 w-8 p-0"
                    @click="handleViewVersions"
                    title="View Versions"
                    data-test="versions-button"
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
                            d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                        />
                    </svg>
                </Button>

                <!-- Delete Button (HR only) -->
                <Button
                    v-if="canManage"
                    variant="ghost"
                    size="sm"
                    class="h-8 w-8 p-0 text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                    @click="handleDeleteClick"
                    title="Delete"
                    data-test="delete-button"
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
                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                        />
                    </svg>
                </Button>
            </div>
        </td>
    </tr>

    <!-- Delete Confirmation Dialog -->
    <Dialog
        :open="showDeleteConfirm"
        @update:open="(val) => val || cancelDelete()"
    >
        <DialogContent class="sm:max-w-md" data-test="delete-confirm-dialog">
            <DialogHeader>
                <DialogTitle>Delete Document</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete "{{ document.name }}"? This
                    action cannot be undone.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter
                class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3"
            >
                <Button
                    variant="outline"
                    @click="cancelDelete"
                    :disabled="isDeleting"
                    data-test="cancel-delete-button"
                >
                    Cancel
                </Button>
                <Button
                    variant="destructive"
                    @click="confirmDelete"
                    :disabled="isDeleting"
                    data-test="confirm-delete-button"
                >
                    <svg
                        v-if="isDeleting"
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
                    {{ isDeleting ? 'Deleting...' : 'Delete' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
