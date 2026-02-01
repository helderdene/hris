<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { type DocumentVersion } from '@/types/document';
import { computed } from 'vue';

interface Props {
    versions: DocumentVersion[];
    documentId: number;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const emit = defineEmits<{
    (e: 'download', version: DocumentVersion): void;
}>();

/**
 * Format a date string for display.
 */
function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

/**
 * Format a date string for display (short version for mobile).
 */
function formatDateShort(dateStr: string | null): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * Format a timestamp for display.
 */
function formatTimestamp(dateStr: string | null): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Check if versions array has multiple entries.
 */
const hasMultipleVersions = computed(
    () => props.versions && props.versions.length > 1,
);

/**
 * Check if there are any versions.
 */
const hasVersions = computed(() => props.versions && props.versions.length > 0);

/**
 * Get the latest version number.
 */
const latestVersionNumber = computed(() => {
    if (!props.versions || props.versions.length === 0) return 0;
    return Math.max(...props.versions.map((v) => v.version_number));
});

/**
 * Skeleton items for loading state.
 */
const skeletonItems = [1, 2, 3];

/**
 * Get badge text based on version position.
 */
function getBadgeText(version: DocumentVersion): string {
    if (version.version_number === latestVersionNumber.value) {
        return 'Current Version';
    }
    return `Version ${version.version_number}`;
}

/**
 * Get badge color classes based on whether it's the current version.
 */
function getBadgeClasses(version: DocumentVersion): string {
    if (version.version_number === latestVersionNumber.value) {
        return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
    }
    return 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300';
}

/**
 * Handle download button click.
 */
function handleDownload(version: DocumentVersion) {
    emit('download', version);
}
</script>

<template>
    <div class="relative" data-test="document-version-timeline">
        <!-- Loading State with Skeleton -->
        <div v-if="loading" class="space-y-4 sm:space-y-6">
            <div
                v-for="item in skeletonItems"
                :key="item"
                class="relative flex gap-3 pl-6 sm:gap-4 sm:pl-8"
            >
                <!-- Timeline line and dot skeleton -->
                <div
                    class="absolute top-0 left-0 h-full w-px bg-slate-200 dark:bg-slate-700"
                >
                    <div
                        class="absolute top-1 -left-1 h-3 w-3 animate-pulse rounded-full bg-slate-200 sm:-left-1.5 sm:h-4 sm:w-4 dark:bg-slate-700"
                    />
                </div>

                <!-- Content skeleton -->
                <div class="flex-1 space-y-2 pb-4 sm:space-y-3 sm:pb-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            class="h-5 w-24 animate-pulse rounded bg-slate-200 sm:w-28 dark:bg-slate-700"
                        />
                        <div
                            class="h-4 w-24 animate-pulse rounded bg-slate-200 sm:w-32 dark:bg-slate-700"
                        />
                    </div>
                    <div
                        class="h-16 w-full animate-pulse rounded-lg bg-slate-100 sm:h-20 dark:bg-slate-800"
                    />
                </div>
            </div>
        </div>

        <!-- Empty/Single Version State -->
        <div
            v-else-if="!hasMultipleVersions"
            class="flex flex-col items-center justify-center py-8 text-center sm:py-12"
            data-test="single-version-state"
        >
            <div
                class="rounded-full bg-slate-100 p-2.5 sm:p-3 dark:bg-slate-800"
            >
                <svg
                    class="h-5 w-5 text-slate-400 sm:h-6 sm:w-6 dark:text-slate-500"
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
                class="mt-3 text-sm font-medium text-slate-900 sm:mt-4 dark:text-slate-100"
            >
                {{ hasVersions ? 'Single version' : 'No versions' }}
            </h3>
            <p
                class="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400"
            >
                {{
                    hasVersions
                        ? 'This document has only one version. Upload a new version to see the history timeline.'
                        : 'No version history available.'
                }}
            </p>
        </div>

        <!-- Timeline Content -->
        <div v-else class="relative">
            <!-- Vertical timeline line -->
            <div
                class="absolute top-2 left-[5px] h-[calc(100%-2rem)] w-0.5 bg-blue-200 sm:left-[7px] dark:bg-blue-800"
            />

            <!-- Timeline entries -->
            <div
                v-for="(version, index) in versions"
                :key="version.id"
                class="relative pb-4 last:pb-0 sm:pb-6"
                data-test="version-entry"
            >
                <!-- Timeline dot - green for current, blue for others -->
                <div
                    class="absolute top-1.5 left-0 flex h-3 w-3 items-center justify-center rounded-full ring-2 ring-white sm:h-4 sm:w-4 sm:ring-4 dark:ring-slate-900"
                    :class="
                        version.version_number === latestVersionNumber
                            ? 'bg-green-500 dark:bg-green-400'
                            : 'bg-blue-500 dark:bg-blue-400'
                    "
                >
                    <div
                        class="h-1 w-1 rounded-full bg-white sm:h-1.5 sm:w-1.5"
                    />
                </div>

                <!-- Entry content -->
                <div class="ml-6 sm:ml-8">
                    <!-- Header row with badge and date -->
                    <div
                        class="flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2"
                    >
                        <span
                            class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-medium sm:px-2.5"
                            :class="getBadgeClasses(version)"
                        >
                            {{ getBadgeText(version) }}
                        </span>
                        <span
                            class="text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                        >
                            <span class="sm:hidden">{{
                                formatDateShort(
                                    version.uploaded_at || version.created_at,
                                )
                            }}</span>
                            <span class="hidden sm:inline"
                                >Uploaded
                                {{
                                    formatDate(
                                        version.uploaded_at ||
                                            version.created_at,
                                    )
                                }}</span
                            >
                        </span>
                    </div>

                    <!-- Version details card -->
                    <div
                        class="mt-2 rounded-lg border border-slate-200 bg-white p-3 sm:p-4 dark:border-slate-700 dark:bg-slate-800"
                    >
                        <!-- Version Info -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <!-- File Size -->
                                <div class="text-sm">
                                    <span
                                        class="font-medium text-slate-700 dark:text-slate-300"
                                        >File Size:</span
                                    >
                                    <span
                                        class="ml-1 text-slate-600 dark:text-slate-400"
                                    >
                                        {{
                                            version.file_size_formatted ||
                                            `${(version.file_size / 1024).toFixed(2)} KB`
                                        }}
                                    </span>
                                </div>

                                <!-- Version Notes if present -->
                                <p
                                    v-if="version.version_notes"
                                    class="mt-2 text-xs text-slate-600 sm:text-sm dark:text-slate-300"
                                    data-test="version-notes"
                                >
                                    <span class="font-medium">Notes:</span>
                                    {{ version.version_notes }}
                                </p>
                            </div>

                            <!-- Download Button -->
                            <Button
                                variant="outline"
                                size="sm"
                                class="shrink-0"
                                @click="handleDownload(version)"
                                :title="`Download version ${version.version_number}`"
                                data-test="download-version-button"
                            >
                                <svg
                                    class="h-4 w-4 sm:mr-1.5"
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
                                <span class="hidden sm:inline">Download</span>
                            </Button>
                        </div>

                        <!-- Metadata row -->
                        <div
                            class="mt-2 flex flex-col gap-1 border-t border-slate-100 pt-2 text-xs text-slate-500 sm:mt-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-4 sm:gap-y-1 sm:pt-3 dark:border-slate-700 dark:text-slate-400"
                        >
                            <span
                                v-if="version.uploaded_by_name"
                                class="flex items-center gap-1"
                            >
                                <svg
                                    class="h-3 w-3 sm:h-3.5 sm:w-3.5"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                                    />
                                </svg>
                                <span class="truncate">{{
                                    version.uploaded_by_name
                                }}</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <svg
                                    class="h-3 w-3 sm:h-3.5 sm:w-3.5"
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
                                {{ formatTimestamp(version.created_at) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
