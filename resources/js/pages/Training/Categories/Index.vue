<script setup lang="ts">
import CourseCategoryFormModal from '@/components/CourseCategoryFormModal.vue';
import DeleteConfirmationModal from '@/components/DeleteConfirmationModal.vue';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Category {
    id: number;
    name: string;
    code: string;
    description: string | null;
    parent_id: number | null;
    parent?: { id: number; name: string; code: string } | null;
    children_count?: number;
    courses_count?: number;
    is_active: boolean;
}

const props = defineProps<{
    categories: Category[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Training', href: '/training/courses' },
    { title: 'Categories', href: '/training/categories' },
];

const isFormModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingCategory = ref<Category | null>(null);
const parentCategoryId = ref<number | null>(null);
const deletingCategory = ref<Category | null>(null);
const isDeleting = ref(false);

function handleAddCategory(parentId: number | null = null) {
    editingCategory.value = null;
    parentCategoryId.value = parentId;
    isFormModalOpen.value = true;
}

function handleEditCategory(category: Category) {
    editingCategory.value = category;
    parentCategoryId.value = category.parent_id;
    isFormModalOpen.value = true;
}

function handleDeleteCategory(category: Category) {
    deletingCategory.value = category;
    isDeleteModalOpen.value = true;
}

async function confirmDelete() {
    if (!deletingCategory.value) return;

    isDeleting.value = true;

    try {
        const response = await fetch(
            `/api/training/categories/${deletingCategory.value.id}`,
            {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        if (response.ok) {
            isDeleteModalOpen.value = false;
            deletingCategory.value = null;
            router.reload({ only: ['categories'] });
        } else if (response.status === 422) {
            const data = await response.json();
            alert(data.message || 'Cannot delete this category.');
        }
    } finally {
        isDeleting.value = false;
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingCategory.value = null;
    parentCategoryId.value = null;
    router.reload({ only: ['categories'] });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Head :title="`Course Categories - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Course Categories
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Organize your training courses into categories.
                    </p>
                </div>
                <Button
                    @click="handleAddCategory(null)"
                    class="shrink-0"
                    :style="{ backgroundColor: primaryColor }"
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
                    Add Category
                </Button>
            </div>

            <!-- Categories Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <table v-if="categories.length > 0" class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Category
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Code
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Parent
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Courses
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Status
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900">
                        <tr
                            v-for="category in categories"
                            :key="category.id"
                            class="hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ category.name }}
                                </div>
                                <div
                                    v-if="category.description"
                                    class="mt-1 max-w-xs truncate text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ category.description }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                {{ category.code }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                {{ category.parent?.name || '-' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                {{ category.courses_count || 0 }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        category.is_active
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400',
                                    ]"
                                >
                                    {{ category.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="handleEditCategory(category)"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-red-600 hover:text-red-700"
                                        @click="handleDeleteCategory(category)"
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty State -->
                <div v-else class="px-6 py-12 text-center">
                    <svg
                        class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"
                        />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No categories
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Get started by creating your first category.
                    </p>
                    <div class="mt-6">
                        <Button @click="handleAddCategory(null)" :style="{ backgroundColor: primaryColor }">
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
                            Add Category
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Form Modal -->
        <CourseCategoryFormModal
            v-model:open="isFormModalOpen"
            :category="editingCategory"
            :parent-id="parentCategoryId"
            :all-categories="categories"
            @success="handleFormSuccess"
        />

        <!-- Delete Confirmation Modal -->
        <DeleteConfirmationModal
            v-model:open="isDeleteModalOpen"
            title="Delete Category"
            :description="`Are you sure you want to delete the category '${deletingCategory?.name ?? ''}'? This action cannot be undone.`"
            :processing="isDeleting"
            @confirm="confirmDelete"
        />
    </TenantLayout>
</template>
