<script setup lang="ts">
import CategoryManagementModal from '@/components/CategoryManagementModal.vue';
import DocumentPreviewModal from '@/components/DocumentPreviewModal.vue';
import DocumentTableRow from '@/components/DocumentTableRow.vue';
import DocumentUploadModal from '@/components/DocumentUploadModal.vue';
import DocumentVersionTimeline from '@/components/DocumentVersionTimeline.vue';
import EnumSelect from '@/components/EnumSelect.vue';
import NewVersionModal from '@/components/NewVersionModal.vue';
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
    type DocumentApiResponse,
    type DocumentCategory,
    type DocumentCategoryApiResponse,
    type DocumentVersion,
    type PaginationMeta,
} from '@/types/document';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface Employee {
    id: number;
}

interface Props {
    employee: Employee;
}

const props = defineProps<Props>();

const page = usePage();

/**
 * Check if current user can manage employees.
 */
const canManageEmployees = computed(
    () => page.props.tenant?.can_manage_employees ?? false,
);

/**
 * Document data state.
 */
const documents = ref<Document[]>([]);
const categories = ref<DocumentCategory[]>([]);
const pagination = ref<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});
const loading = ref(true);
const error = ref<string | null>(null);

/**
 * Filter state.
 */
const selectedCategoryId = ref<string>('');
const currentPage = ref(1);

/**
 * Sort state.
 */
const sortColumn = ref<'name' | 'category' | 'uploaded_at'>('uploaded_at');
const sortDirection = ref<'asc' | 'desc'>('desc');

/**
 * Modal state.
 */
const isUploadModalOpen = ref(false);
const previewDocument = ref<Document | null>(null);
const isPreviewModalOpen = ref(false);
const versionsDocument = ref<Document | null>(null);
const isVersionsModalOpen = ref(false);
const isNewVersionModalOpen = ref(false);
const newVersionDocument = ref<Document | null>(null);
const isCategoryManagementModalOpen = ref(false);

/**
 * Category options for the filter dropdown.
 */
const categoryOptions = computed(() => {
    const options = [
        { value: '', label: 'All Categories' },
        ...categories.value.map((cat) => ({
            value: cat.id.toString(),
            label: cat.name,
        })),
    ];

    // Add "Manage Categories" option for HR users
    if (canManageEmployees.value) {
        options.push({ value: '__manage__', label: '+ Manage Categories...' });
    }

    return options;
});

/**
 * Get CSRF token from cookies.
 */
function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Fetch documents from API.
 */
async function fetchDocuments() {
    loading.value = true;
    error.value = null;

    try {
        let url = `/api/employees/${props.employee.id}/documents?page=${currentPage.value}`;

        if (
            selectedCategoryId.value &&
            selectedCategoryId.value !== '__manage__'
        ) {
            url += `&category_id=${selectedCategoryId.value}`;
        }

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to fetch documents');
        }

        const data: DocumentApiResponse = await response.json();
        documents.value = data.data;
        pagination.value = data.meta;
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'An error occurred';
    } finally {
        loading.value = false;
    }
}

/**
 * Fetch document categories from API.
 */
async function fetchCategories() {
    try {
        const url = '/api/document-categories';

        const response = await fetch(url, {
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
        console.error('Failed to fetch categories:', e);
    }
}

/**
 * Handle category filter change.
 */
function handleCategoryChange(value: string) {
    // Check if user selected "Manage Categories" option
    if (value === '__manage__') {
        isCategoryManagementModalOpen.value = true;
        return;
    }

    selectedCategoryId.value = value;
    currentPage.value = 1;
    fetchDocuments();
}

/**
 * Handle pagination.
 */
function goToPage(page: number) {
    if (page < 1 || page > pagination.value.last_page) return;
    currentPage.value = page;
    fetchDocuments();
}

/**
 * Handle upload button click.
 */
function handleUploadClick() {
    isUploadModalOpen.value = true;
}

/**
 * Handle upload modal close.
 */
function handleUploadModalClose() {
    isUploadModalOpen.value = false;
}

/**
 * Handle successful document upload.
 */
function handleUploadSuccess() {
    fetchDocuments();
}

/**
 * Handle document download.
 */
function handleDownload(document: Document) {
    // Get the latest version (version with highest version_number)
    const latestVersion =
        document.versions?.length > 0
            ? document.versions.reduce((latest, v) =>
                  v.version_number > latest.version_number ? v : latest,
              )
            : null;

    if (!latestVersion) {
        console.error('No version available for download');
        return;
    }

    // Trigger download
    triggerDownload(document, latestVersion);
}

/**
 * Handle download from preview modal.
 */
function handlePreviewDownload(document: Document, version: DocumentVersion) {
    triggerDownload(document, version);
}

/**
 * Trigger file download via link click.
 */
function triggerDownload(document: Document, version: DocumentVersion) {
    const downloadUrl = `/api/documents/${document.id}/versions/${version.id}/download`;
    const link = window.document.createElement('a');
    link.href = downloadUrl;
    link.download = document.original_filename;
    window.document.body.appendChild(link);
    link.click();
    window.document.body.removeChild(link);
}

/**
 * Handle version download from timeline.
 */
function handleVersionDownload(version: DocumentVersion) {
    if (!versionsDocument.value) return;

    triggerDownload(versionsDocument.value, version);
}

/**
 * Handle document preview.
 */
function handlePreview(document: Document) {
    previewDocument.value = document;
    isPreviewModalOpen.value = true;
}

/**
 * Handle preview modal close.
 */
function handlePreviewModalClose() {
    isPreviewModalOpen.value = false;
    previewDocument.value = null;
}

/**
 * Handle view versions.
 */
function handleViewVersions(document: Document) {
    versionsDocument.value = document;
    isVersionsModalOpen.value = true;
}

/**
 * Handle close versions modal.
 */
function handleVersionsModalClose() {
    isVersionsModalOpen.value = false;
    versionsDocument.value = null;
}

/**
 * Handle upload new version button click.
 */
function handleUploadNewVersion() {
    if (!versionsDocument.value) return;
    newVersionDocument.value = versionsDocument.value;
    isNewVersionModalOpen.value = true;
}

/**
 * Handle close new version modal.
 */
function handleNewVersionModalClose() {
    isNewVersionModalOpen.value = false;
    newVersionDocument.value = null;
}

/**
 * Handle successful new version upload.
 */
function handleNewVersionSuccess(newVersion: DocumentVersion) {
    // Refresh the documents list
    fetchDocuments();

    // Update the versionsDocument if the modal is still showing the same document
    if (
        versionsDocument.value &&
        newVersionDocument.value &&
        versionsDocument.value.id === newVersionDocument.value.id
    ) {
        // Update the versions array with the new version
        versionsDocument.value = {
            ...versionsDocument.value,
            current_version: newVersion.version_number,
            versions: [newVersion, ...(versionsDocument.value.versions || [])],
        };
    }
}

/**
 * Handle document delete.
 */
async function handleDelete(document: Document) {
    try {
        const url = `/api/employees/${props.employee.id}/documents/${document.id}`;

        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to delete document');
        }

        // Refresh document list
        fetchDocuments();
    } catch (e) {
        console.error('Delete failed:', e);
    }
}

/**
 * Handle category management modal close.
 */
function handleCategoryManagementModalClose() {
    isCategoryManagementModalOpen.value = false;
}

/**
 * Handle categories updated - refresh the categories list.
 */
function handleCategoriesUpdated() {
    fetchCategories();
}

/**
 * Handle manage categories button click.
 */
function handleManageCategoriesClick() {
    isCategoryManagementModalOpen.value = true;
}

/**
 * Skeleton items for loading state.
 */
const skeletonItems = [1, 2, 3, 4, 5];

/**
 * Sorted versions for timeline display (latest first).
 */
const sortedVersions = computed(() => {
    if (!versionsDocument.value?.versions) return [];
    return [...versionsDocument.value.versions].sort(
        (a, b) => b.version_number - a.version_number,
    );
});

// Fetch data on mount
onMounted(() => {
    fetchCategories();
    fetchDocuments();
});
</script>

<template>
    <div class="space-y-6" data-test="documents-tab">
        <!-- Header with Upload Button and Filter -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <!-- Category Filter and Manage Categories -->
            <div class="flex items-center gap-2">
                <div class="w-full sm:w-64">
                    <EnumSelect
                        id="category-filter"
                        :model-value="selectedCategoryId"
                        :options="categoryOptions"
                        placeholder="All Categories"
                        @update:model-value="handleCategoryChange"
                        data-test="category-filter"
                    />
                </div>

                <!-- Manage Categories Button (visible on larger screens) -->
                <Button
                    v-if="canManageEmployees"
                    variant="ghost"
                    size="sm"
                    class="hidden shrink-0 sm:flex"
                    @click="handleManageCategoriesClick"
                    title="Manage Categories"
                    data-test="manage-categories-button"
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
                            d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                        />
                    </svg>
                </Button>
            </div>

            <!-- Upload Button -->
            <Button
                v-if="canManageEmployees"
                @click="handleUploadClick"
                data-test="upload-button"
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
                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"
                    />
                </svg>
                Upload Document
            </Button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="space-y-4">
            <!-- Table Skeleton -->
            <div
                class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700"
            >
                <table
                    class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                >
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-left">
                                <div
                                    class="h-4 w-20 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                />
                            </th>
                            <th
                                class="hidden px-4 py-3 text-left sm:table-cell"
                            >
                                <div
                                    class="h-4 w-16 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                />
                            </th>
                            <th
                                class="hidden px-4 py-3 text-left md:table-cell"
                            >
                                <div
                                    class="h-4 w-14 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                />
                            </th>
                            <th
                                class="hidden px-4 py-3 text-left lg:table-cell"
                            >
                                <div
                                    class="h-4 w-20 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                />
                            </th>
                            <th
                                class="hidden px-4 py-3 text-left lg:table-cell"
                            >
                                <div
                                    class="h-4 w-20 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                />
                            </th>
                            <th class="px-4 py-3 text-right">
                                <div
                                    class="ml-auto h-4 w-16 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                />
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900"
                    >
                        <tr v-for="item in skeletonItems" :key="item">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-8 w-8 animate-pulse rounded-lg bg-slate-200 dark:bg-slate-700"
                                    />
                                    <div class="space-y-1">
                                        <div
                                            class="h-4 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                        />
                                        <div
                                            class="h-3 w-24 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                        />
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 sm:table-cell">
                                <div
                                    class="h-5 w-20 animate-pulse rounded-full bg-slate-200 dark:bg-slate-700"
                                />
                            </td>
                            <td class="hidden px-4 py-3 md:table-cell">
                                <div
                                    class="h-4 w-12 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                />
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                <div
                                    class="h-4 w-24 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                />
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                <div
                                    class="h-4 w-20 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <div
                                        class="h-8 w-8 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                    />
                                    <div
                                        class="h-8 w-8 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Error State -->
        <div
            v-else-if="error"
            class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
            data-test="error-state"
        >
            <div class="flex items-center gap-2">
                <svg
                    class="h-5 w-5 text-red-500 dark:text-red-400"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"
                    />
                </svg>
                <p class="text-sm font-medium text-red-700 dark:text-red-400">
                    {{ error }}
                </p>
            </div>
            <Button
                variant="outline"
                size="sm"
                class="mt-3"
                @click="fetchDocuments"
                data-test="retry-button"
            >
                Try Again
            </Button>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="documents.length === 0"
            class="flex flex-col items-center justify-center py-12 text-center"
            data-test="empty-state"
        >
            <div class="rounded-full bg-slate-100 p-3 dark:bg-slate-800">
                <svg
                    class="h-6 w-6 text-slate-400 dark:text-slate-500"
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
            <h3
                class="mt-4 text-sm font-medium text-slate-900 dark:text-slate-100"
            >
                No documents
            </h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{
                    selectedCategoryId
                        ? 'No documents found in this category.'
                        : 'No documents have been uploaded for this employee.'
                }}
            </p>
            <Button
                v-if="canManageEmployees && !selectedCategoryId"
                class="mt-4"
                @click="handleUploadClick"
                data-test="empty-upload-button"
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
                Upload First Document
            </Button>
        </div>

        <!-- Documents Table -->
        <template v-else>
            <div
                class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700"
            >
                <table
                    class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                    data-test="documents-table"
                >
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                Name
                            </th>
                            <th
                                class="hidden px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase sm:table-cell dark:text-slate-400"
                            >
                                Category
                            </th>
                            <th
                                class="hidden px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase md:table-cell dark:text-slate-400"
                            >
                                Version
                            </th>
                            <th
                                class="hidden px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase lg:table-cell dark:text-slate-400"
                            >
                                Uploaded
                            </th>
                            <th
                                class="hidden px-4 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase lg:table-cell dark:text-slate-400"
                            >
                                Uploaded By
                            </th>
                            <th
                                class="px-4 py-3 text-right text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900"
                    >
                        <DocumentTableRow
                            v-for="document in documents"
                            :key="document.id"
                            :document="document"
                            :can-manage="canManageEmployees"
                            @download="handleDownload"
                            @preview="handlePreview"
                            @view-versions="handleViewVersions"
                            @delete="handleDelete"
                        />
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="pagination.last_page > 1"
                class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 dark:border-slate-700 dark:bg-slate-900"
                data-test="pagination"
            >
                <div class="flex flex-1 justify-between sm:hidden">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="pagination.current_page === 1"
                        @click="goToPage(pagination.current_page - 1)"
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="
                            pagination.current_page === pagination.last_page
                        "
                        @click="goToPage(pagination.current_page + 1)"
                    >
                        Next
                    </Button>
                </div>
                <div
                    class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="text-sm text-slate-700 dark:text-slate-300">
                            Showing
                            <span class="font-medium">{{
                                (pagination.current_page - 1) *
                                    pagination.per_page +
                                1
                            }}</span>
                            to
                            <span class="font-medium">{{
                                Math.min(
                                    pagination.current_page *
                                        pagination.per_page,
                                    pagination.total,
                                )
                            }}</span>
                            of
                            <span class="font-medium">{{
                                pagination.total
                            }}</span>
                            documents
                        </p>
                    </div>
                    <div>
                        <nav
                            class="isolate inline-flex -space-x-px rounded-md shadow-sm"
                            aria-label="Pagination"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                class="rounded-r-none"
                                :disabled="pagination.current_page === 1"
                                @click="goToPage(pagination.current_page - 1)"
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
                                        d="M15.75 19.5 8.25 12l7.5-7.5"
                                    />
                                </svg>
                            </Button>
                            <span
                                class="flex items-center border-y border-slate-200 bg-white px-4 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                Page {{ pagination.current_page }} of
                                {{ pagination.last_page }}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                class="rounded-l-none"
                                :disabled="
                                    pagination.current_page ===
                                    pagination.last_page
                                "
                                @click="goToPage(pagination.current_page + 1)"
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
                                        d="m8.25 4.5 7.5 7.5-7.5 7.5"
                                    />
                                </svg>
                            </Button>
                        </nav>
                    </div>
                </div>
            </div>
        </template>

        <!-- Upload Modal -->
        <DocumentUploadModal
            v-model:open="isUploadModalOpen"
            :employee-id="employee.id"
            :categories="categories"
            @close="handleUploadModalClose"
            @success="handleUploadSuccess"
        />

        <!-- Preview Modal -->
        <DocumentPreviewModal
            v-model:open="isPreviewModalOpen"
            :document="previewDocument"
            @close="handlePreviewModalClose"
            @download="handlePreviewDownload"
        />

        <!-- Version History Modal -->
        <Dialog
            :open="isVersionsModalOpen"
            @update:open="(val) => !val && handleVersionsModalClose()"
        >
            <DialogContent
                class="max-h-[90vh] w-[95vw] overflow-y-auto sm:max-w-2xl"
                data-test="versions-modal"
            >
                <DialogHeader class="space-y-2 sm:space-y-3">
                    <DialogTitle class="text-lg sm:text-xl">
                        Version History
                    </DialogTitle>
                    <DialogDescription v-if="versionsDocument" class="text-sm">
                        View all versions of "{{ versionsDocument.name }}" ({{
                            versionsDocument.original_filename
                        }})
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4">
                    <DocumentVersionTimeline
                        v-if="versionsDocument"
                        :versions="sortedVersions"
                        :document-id="versionsDocument.id"
                        @download="handleVersionDownload"
                    />
                </div>

                <DialogFooter
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between"
                >
                    <Button
                        v-if="canManageEmployees"
                        variant="outline"
                        @click="handleUploadNewVersion"
                        data-test="upload-new-version-button"
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
                        Upload New Version
                    </Button>
                    <Button
                        variant="default"
                        @click="handleVersionsModalClose"
                        data-test="close-versions-button"
                    >
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- New Version Modal -->
        <NewVersionModal
            v-if="newVersionDocument"
            v-model:open="isNewVersionModalOpen"
            :document="newVersionDocument"
            @close="handleNewVersionModalClose"
            @success="handleNewVersionSuccess"
        />

        <!-- Category Management Modal -->
        <CategoryManagementModal
            v-model:open="isCategoryManagementModalOpen"
            @close="handleCategoryManagementModalClose"
            @categories-updated="handleCategoriesUpdated"
        />
    </div>
</template>
