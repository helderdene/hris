<script setup lang="ts">
import DeleteConfirmationModal from '@/components/DeleteConfirmationModal.vue';
import DepartmentFormModal from '@/components/DepartmentFormModal.vue';
import DepartmentTreeNode from '@/components/DepartmentTreeNode.vue';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface DepartmentTreeItem {
    id: number;
    name: string;
    code: string;
    status: string;
    parent_id: number | null;
    department_head_id: number | null;
    children?: DepartmentTreeItem[];
}

interface Department {
    id: number;
    name: string;
    code: string;
    description: string | null;
    status: string;
    parent_id: number | null;
    parent?: { id: number; name: string; code: string } | null;
    children_count?: number;
    department_head_id: number | null;
    created_at: string | null;
    updated_at: string | null;
}

const props = defineProps<{
    departments: Department[];
    departmentTree: DepartmentTreeItem[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Organization',
        href: '/organization/departments',
    },
    {
        title: 'Departments',
        href: '/organization/departments',
    },
];

const isFormModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingDepartment = ref<Department | null>(null);
const parentDepartmentId = ref<number | null>(null);
const deletingDepartment = ref<Department | null>(null);
const isDeleting = ref(false);

/**
 * Flatten department tree to get all departments for parent selector
 */
const allDepartments = computed(() => {
    return props.departments;
});

/**
 * Open form modal for creating a new department
 */
function handleAddDepartment(parentId: number | null = null) {
    editingDepartment.value = null;
    parentDepartmentId.value = parentId;
    isFormModalOpen.value = true;
}

/**
 * Open form modal for editing a department
 */
function handleEditDepartment(department: Department) {
    editingDepartment.value = department;
    parentDepartmentId.value = department.parent_id;
    isFormModalOpen.value = true;
}

/**
 * Open delete confirmation modal
 */
function handleDeleteDepartment(department: Department) {
    deletingDepartment.value = department;
    isDeleteModalOpen.value = true;
}

/**
 * Confirm deletion of a department
 */
async function confirmDelete() {
    if (!deletingDepartment.value) return;

    isDeleting.value = true;

    try {
        const response = await fetch(
            `/api/organization/departments/${deletingDepartment.value.id}`,
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
            deletingDepartment.value = null;
            router.reload({ only: ['departments', 'departmentTree'] });
        }
    } finally {
        isDeleting.value = false;
    }
}

/**
 * Handle successful form submission
 */
function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingDepartment.value = null;
    parentDepartmentId.value = null;
    router.reload({ only: ['departments', 'departmentTree'] });
}

/**
 * Gets the CSRF token from cookies for the request header.
 */
function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Head :title="`Departments - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Departments
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage your organization's department hierarchy and
                        structure.
                    </p>
                </div>
                <Button
                    @click="handleAddDepartment(null)"
                    class="shrink-0"
                    :style="{ backgroundColor: primaryColor }"
                    data-test="add-department-button"
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
                    Add Department
                </Button>
            </div>

            <!-- Department Tree Container -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Tree View -->
                <div
                    v-if="departmentTree.length > 0"
                    class="divide-y divide-slate-200 dark:divide-slate-700"
                >
                    <div class="overflow-x-auto">
                        <div class="min-w-full p-4">
                            <DepartmentTreeNode
                                v-for="dept in departmentTree"
                                :key="dept.id"
                                :department="dept"
                                :depth="0"
                                @edit="handleEditDepartment"
                                @add-child="handleAddDepartment"
                                @delete="handleDeleteDepartment"
                            />
                        </div>
                    </div>
                </div>

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
                            d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No departments
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Get started by creating your first department.
                    </p>
                    <div class="mt-6">
                        <Button
                            @click="handleAddDepartment(null)"
                            :style="{ backgroundColor: primaryColor }"
                            data-test="add-first-department-button"
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
                            Add Department
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Form Modal -->
        <DepartmentFormModal
            v-model:open="isFormModalOpen"
            :department="editingDepartment"
            :parent-id="parentDepartmentId"
            :all-departments="allDepartments"
            @success="handleFormSuccess"
        />

        <!-- Delete Confirmation Modal -->
        <DeleteConfirmationModal
            v-model:open="isDeleteModalOpen"
            title="Delete Department"
            :description="`Are you sure you want to delete the department '${deletingDepartment?.name ?? ''}'? This action cannot be undone.`"
            :processing="isDeleting"
            @confirm="confirmDelete"
        />
    </TenantLayout>
</template>
