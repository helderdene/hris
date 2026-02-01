<script setup lang="ts">
/**
 * CategoryManagementModal - Manage document categories with create/delete options.
 *
 * This modal allows HR users to:
 * - View all document categories (predefined and custom)
 * - Create new custom categories
 * - Delete custom categories (predefined categories cannot be deleted)
 */
import CreateCategoryModal from '@/Components/CreateCategoryModal.vue';
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
    type DocumentCategory,
    type DocumentCategoryApiResponse,
} from '@/types/document';
import { ref, watch } from 'vue';

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'categoriesUpdated'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const categories = ref<DocumentCategory[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const isCreateModalOpen = ref(false);

// Delete confirmation state
const categoryToDelete = ref<DocumentCategory | null>(null);
const isDeleting = ref(false);
const deleteError = ref<string | null>(null);

/**
 * Check if category can be deleted (non-predefined only).
 */
function canDeleteCategory(category: DocumentCategory): boolean {
    return !category.is_predefined;
}

/**
 * Get CSRF token from cookies.
 */
function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Fetch categories from API.
 */
async function fetchCategories() {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch('/api/document-categories', {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to fetch categories');
        }

        const data: DocumentCategoryApiResponse = await response.json();
        categories.value = data.data;
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'An error occurred';
    } finally {
        loading.value = false;
    }
}

/**
 * Handle create category button click.
 */
function handleCreateClick() {
    isCreateModalOpen.value = true;
}

/**
 * Handle create modal close.
 */
function handleCreateModalClose() {
    isCreateModalOpen.value = false;
}

/**
 * Handle successful category creation.
 */
function handleCategoryCreated(newCategory: DocumentCategory) {
    // Add the new category to the list
    categories.value = [...categories.value, newCategory];
    // Sort: predefined first, then alphabetically by name
    categories.value.sort((a, b) => {
        if (a.is_predefined !== b.is_predefined) {
            return a.is_predefined ? -1 : 1;
        }
        return a.name.localeCompare(b.name);
    });
    emit('categoriesUpdated');
}

/**
 * Handle delete button click - show confirmation.
 */
function handleDeleteClick(category: DocumentCategory) {
    categoryToDelete.value = category;
    deleteError.value = null;
}

/**
 * Cancel delete - hide confirmation.
 */
function cancelDelete() {
    categoryToDelete.value = null;
    deleteError.value = null;
}

/**
 * Confirm and execute delete.
 */
async function confirmDelete() {
    if (!categoryToDelete.value) return;

    isDeleting.value = true;
    deleteError.value = null;

    try {
        const response = await fetch(
            `/api/document-categories/${categoryToDelete.value.id}`,
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        if (response.status === 204) {
            // Remove from list
            categories.value = categories.value.filter(
                (c) => c.id !== categoryToDelete.value!.id,
            );
            categoryToDelete.value = null;
            emit('categoriesUpdated');
        } else if (response.status === 403) {
            const data = await response.json();
            deleteError.value =
                data.message || 'This category cannot be deleted.';
        } else {
            deleteError.value = 'Failed to delete category. Please try again.';
        }
    } catch {
        deleteError.value = 'Failed to delete category. Please try again.';
    } finally {
        isDeleting.value = false;
    }
}

/**
 * Handle modal close.
 */
function handleClose() {
    categoryToDelete.value = null;
    deleteError.value = null;
    emit('close');
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

// Fetch categories when modal opens
watch(open, (isOpen) => {
    if (isOpen) {
        fetchCategories();
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            class="max-h-[90vh] w-[95vw] overflow-y-auto sm:max-w-lg"
            data-test="category-management-modal"
        >
            <DialogHeader class="space-y-2">
                <DialogTitle class="text-lg sm:text-xl">
                    Manage Categories
                </DialogTitle>
                <DialogDescription class="text-sm">
                    View, create, and delete document categories. Predefined
                    categories cannot be deleted.
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-[200px] py-4">
                <!-- Loading State -->
                <div v-if="loading" class="space-y-2">
                    <div
                        v-for="i in 4"
                        :key="i"
                        class="flex items-center justify-between rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                    >
                        <div class="space-y-1">
                            <div
                                class="h-4 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                            />
                            <div
                                class="h-3 w-20 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                            />
                        </div>
                        <div
                            class="h-8 w-8 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                        />
                    </div>
                </div>

                <!-- Error State -->
                <div
                    v-else-if="error"
                    class="flex flex-col items-center justify-center py-8 text-center"
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
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                        {{ error }}
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        @click="fetchCategories"
                    >
                        Try Again
                    </Button>
                </div>

                <!-- Categories List -->
                <div v-else class="space-y-2">
                    <div
                        v-for="category in categories"
                        :key="category.id"
                        class="flex items-center justify-between rounded-lg border border-slate-200 p-3 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/50"
                        :data-test="`category-item-${category.id}`"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p
                                    class="truncate text-sm font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ category.name }}
                                </p>
                                <span
                                    v-if="category.is_predefined"
                                    class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-400"
                                >
                                    System
                                </span>
                            </div>
                            <p
                                v-if="category.description"
                                class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ category.description }}
                            </p>
                        </div>

                        <!-- Delete Button -->
                        <Button
                            v-if="canDeleteCategory(category)"
                            variant="ghost"
                            size="sm"
                            class="shrink-0 text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400"
                            @click="handleDeleteClick(category)"
                            :disabled="isDeleting"
                            :data-test="`delete-category-${category.id}`"
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

                        <!-- Protected indicator for predefined categories -->
                        <span
                            v-else
                            class="shrink-0 px-2 text-xs text-slate-400 dark:text-slate-500"
                            title="Predefined categories cannot be deleted"
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
                                    d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"
                                />
                            </svg>
                        </span>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="categories.length === 0"
                        class="flex flex-col items-center justify-center py-8 text-center"
                    >
                        <svg
                            class="h-12 w-12 text-slate-400"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"
                            />
                        </svg>
                        <p
                            class="mt-2 text-sm text-slate-600 dark:text-slate-400"
                        >
                            No categories found
                        </p>
                    </div>
                </div>

                <!-- Delete Confirmation -->
                <div
                    v-if="categoryToDelete"
                    class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800/50 dark:bg-red-900/20"
                    data-test="delete-confirmation"
                >
                    <div class="flex items-start gap-3">
                        <svg
                            class="h-5 w-5 shrink-0 text-red-500"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                            />
                        </svg>
                        <div class="flex-1">
                            <p
                                class="text-sm font-medium text-red-800 dark:text-red-300"
                            >
                                Delete "{{ categoryToDelete.name }}"?
                            </p>
                            <p
                                class="mt-1 text-xs text-red-700 dark:text-red-400"
                            >
                                This action cannot be undone. Documents in this
                                category will remain but will need to be
                                recategorized.
                            </p>
                            <div
                                v-if="deleteError"
                                class="mt-2 text-xs font-medium text-red-800 dark:text-red-300"
                            >
                                {{ deleteError }}
                            </div>
                            <div class="mt-3 flex gap-2">
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    @click="confirmDelete"
                                    :disabled="isDeleting"
                                    data-test="confirm-delete-button"
                                >
                                    <svg
                                        v-if="isDeleting"
                                        class="mr-2 h-3 w-3 animate-spin"
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
                                    {{
                                        isDeleting
                                            ? 'Deleting...'
                                            : 'Yes, Delete'
                                    }}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="cancelDelete"
                                    :disabled="isDeleting"
                                    data-test="cancel-delete-button"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter
                class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between"
            >
                <Button
                    variant="outline"
                    @click="handleCreateClick"
                    :disabled="loading"
                    data-test="create-category-button"
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
                            d="M12 4.5v15m7.5-7.5h-15"
                        />
                    </svg>
                    New Category
                </Button>
                <Button
                    variant="default"
                    @click="handleOpenChange(false)"
                    data-test="close-button"
                >
                    Done
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Create Category Modal -->
    <CreateCategoryModal
        v-model:open="isCreateModalOpen"
        @close="handleCreateModalClose"
        @success="handleCategoryCreated"
    />
</template>
