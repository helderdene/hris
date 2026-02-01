<script setup lang="ts">
import CourseCard from '@/components/CourseCard.vue';
import CourseFilters from '@/components/CourseFilters.vue';
import CourseFormModal from '@/components/CourseFormModal.vue';
import DeleteConfirmationModal from '@/components/DeleteConfirmationModal.vue';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Category {
    id: number;
    name: string;
    code: string;
}

interface Course {
    id: number;
    title: string;
    code: string;
    description: string | null;
    delivery_method: string;
    delivery_method_label: string;
    provider_type: string;
    provider_type_label: string;
    status: string;
    status_label: string;
    level: string | null;
    level_label: string | null;
    formatted_duration: string | null;
    cost: string | null;
    categories?: Category[];
    prerequisites_count?: number;
}

interface EnumOption {
    value: string;
    label: string;
}

interface Filters {
    status?: string | null;
    delivery_method?: string | null;
    category_id?: number | null;
    search?: string | null;
}

const props = defineProps<{
    courses: Course[];
    categories: Category[];
    filters: Filters;
    statusOptions: EnumOption[];
    deliveryMethodOptions: EnumOption[];
    providerTypeOptions: EnumOption[];
    levelOptions: EnumOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Training', href: '/training/courses' },
    { title: 'Courses', href: '/training/courses' },
];

const isFormModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingCourse = ref<Course | null>(null);
const deletingCourse = ref<Course | null>(null);
const isDeleting = ref(false);

const availablePrerequisites = computed(() =>
    props.courses.map((c) => ({ id: c.id, title: c.title, code: c.code })),
);

function handleFilterChange(newFilters: Filters) {
    router.get(
        '/training/courses',
        {
            status: newFilters.status || undefined,
            delivery_method: newFilters.delivery_method || undefined,
            category_id: newFilters.category_id || undefined,
            search: newFilters.search || undefined,
        },
        { preserveState: true },
    );
}

function handleAddCourse() {
    editingCourse.value = null;
    isFormModalOpen.value = true;
}

function handleViewCourse(course: Course) {
    router.visit(`/training/courses/${course.id}`);
}

function handleEditCourse(course: Course) {
    editingCourse.value = course;
    isFormModalOpen.value = true;
}

function handleDeleteCourse(course: Course) {
    deletingCourse.value = course;
    isDeleteModalOpen.value = true;
}

async function confirmDelete() {
    if (!deletingCourse.value) return;

    isDeleting.value = true;

    try {
        const response = await fetch(
            `/api/training/courses/${deletingCourse.value.id}`,
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
            deletingCourse.value = null;
            router.reload({ only: ['courses'] });
        }
    } finally {
        isDeleting.value = false;
    }
}

function handleFormSuccess() {
    isFormModalOpen.value = false;
    editingCourse.value = null;
    router.reload({ only: ['courses'] });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Head :title="`Training Courses - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Training Courses
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage your organization's training course catalog.
                    </p>
                </div>
                <Button
                    @click="handleAddCourse"
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
                    Add Course
                </Button>
            </div>

            <!-- Filters -->
            <CourseFilters
                :filters="filters"
                :categories="categories"
                :status-options="statusOptions"
                :delivery-method-options="deliveryMethodOptions"
                :level-options="levelOptions"
                :show-status="true"
                @update:filters="handleFilterChange"
            />

            <!-- Courses Grid -->
            <div v-if="courses.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <CourseCard
                    v-for="course in courses"
                    :key="course.id"
                    :course="course"
                    :show-status="true"
                    :show-actions="true"
                    @view="handleViewCourse"
                    @edit="handleEditCourse"
                />
            </div>

            <!-- Empty State -->
            <div v-else class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
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
                        d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"
                    />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                    No courses found
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Get started by creating your first training course.
                </p>
                <div class="mt-6">
                    <Button @click="handleAddCourse" :style="{ backgroundColor: primaryColor }">
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
                        Add Course
                    </Button>
                </div>
            </div>
        </div>

        <!-- Course Form Modal -->
        <CourseFormModal
            v-model:open="isFormModalOpen"
            :course="editingCourse"
            :categories="categories"
            :available-prerequisites="availablePrerequisites"
            :delivery-method-options="deliveryMethodOptions"
            :provider-type-options="providerTypeOptions"
            :level-options="levelOptions"
            :status-options="statusOptions"
            @success="handleFormSuccess"
        />

        <!-- Delete Confirmation Modal -->
        <DeleteConfirmationModal
            v-model:open="isDeleteModalOpen"
            title="Delete Course"
            :description="`Are you sure you want to delete the course '${deletingCourse?.title ?? ''}'? This action cannot be undone.`"
            :processing="isDeleting"
            @confirm="confirmDelete"
        />
    </TenantLayout>
</template>
