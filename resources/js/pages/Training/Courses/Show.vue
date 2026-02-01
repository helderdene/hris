<script setup lang="ts">
import CourseLevelBadge from '@/components/CourseLevelBadge.vue';
import CourseMaterialList from '@/components/CourseMaterialList.vue';
import CourseMaterialUploadModal from '@/components/CourseMaterialUploadModal.vue';
import CourseStatusBadge from '@/components/CourseStatusBadge.vue';
import DeliveryMethodBadge from '@/components/DeliveryMethodBadge.vue';
import DeleteConfirmationModal from '@/components/DeleteConfirmationModal.vue';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface Category {
    id: number;
    name: string;
    code: string;
}

interface Prerequisite {
    id: number;
    title: string;
    code: string;
    is_mandatory: boolean;
}

interface CourseMaterial {
    id: number;
    title: string;
    description: string | null;
    file_name: string | null;
    file_size: number | null;
    formatted_file_size: string | null;
    mime_type: string | null;
    material_type: string;
    material_type_label: string;
    external_url: string | null;
    download_url: string | null;
    sort_order: number;
    uploader?: { id: number; full_name: string } | null;
    created_at: string | null;
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
    provider_name: string | null;
    duration_hours: number | null;
    duration_days: number | null;
    formatted_duration: string | null;
    status: string;
    status_label: string;
    level: string | null;
    level_label: string | null;
    cost: string | null;
    max_participants: number | null;
    learning_objectives: string[] | null;
    syllabus: string | null;
    categories?: Category[];
    prerequisites?: Prerequisite[];
    required_by_count?: number;
    creator?: { id: number; full_name: string } | null;
    created_at: string | null;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    course: Course;
    categories: Category[];
    availablePrerequisites: { id: number; title: string; code: string }[];
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
    { title: props.course.title, href: `/training/courses/${props.course.id}` },
];

const isDeleteModalOpen = ref(false);
const isDeleting = ref(false);
const isPublishing = ref(false);
const isArchiving = ref(false);
const isUploadModalOpen = ref(false);
const isDeleteMaterialModalOpen = ref(false);
const materialToDelete = ref<CourseMaterial | null>(null);
const isDeletingMaterial = ref(false);
const materials = ref<CourseMaterial[]>([]);

const formattedCost = computed(() => {
    if (!props.course.cost) return null;
    const cost = parseFloat(props.course.cost);
    if (cost === 0) return 'Free';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(cost);
});

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handlePublish() {
    isPublishing.value = true;
    try {
        const response = await fetch(`/api/training/courses/${props.course.id}/publish`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            router.reload();
        }
    } finally {
        isPublishing.value = false;
    }
}

async function handleArchive() {
    isArchiving.value = true;
    try {
        const response = await fetch(`/api/training/courses/${props.course.id}/archive`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            router.reload();
        }
    } finally {
        isArchiving.value = false;
    }
}

async function confirmDelete() {
    isDeleting.value = true;
    try {
        const response = await fetch(`/api/training/courses/${props.course.id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            router.visit('/training/courses');
        }
    } finally {
        isDeleting.value = false;
    }
}

function handleEdit() {
    // Navigate back to index with edit mode (or use modal)
    router.visit('/training/courses');
}

async function fetchMaterials() {
    try {
        const response = await fetch(`/api/training/courses/${props.course.id}/materials`, {
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            materials.value = Array.isArray(data) ? data : (data.data ?? []);
        }
    } catch {
        // silently fail
    }
}

function handleMaterialUploadSuccess() {
    fetchMaterials();
}

function handleDeleteMaterial(material: CourseMaterial) {
    materialToDelete.value = material;
    isDeleteMaterialModalOpen.value = true;
}

async function confirmDeleteMaterial() {
    if (!materialToDelete.value) return;

    isDeletingMaterial.value = true;
    try {
        const response = await fetch(
            `/api/training/courses/${props.course.id}/materials/${materialToDelete.value.id}`,
            {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            }
        );

        if (response.ok) {
            isDeleteMaterialModalOpen.value = false;
            materialToDelete.value = null;
            fetchMaterials();
        }
    } finally {
        isDeletingMaterial.value = false;
    }
}

async function handleReorderMaterials(materialIds: number[]) {
    try {
        await fetch(`/api/training/courses/${props.course.id}/materials/reorder`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ material_ids: materialIds }),
        });

        fetchMaterials();
    } catch {
        // silently fail
    }
}

onMounted(() => {
    fetchMaterials();
});
</script>

<template>
    <Head :title="`${course.title} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ course.title }}
                        </h1>
                        <CourseStatusBadge :status="course.status" />
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Code: {{ course.code }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="course.status === 'draft'"
                        @click="handlePublish"
                        :disabled="isPublishing"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        {{ isPublishing ? 'Publishing...' : 'Publish' }}
                    </Button>
                    <Button
                        v-if="course.status === 'published'"
                        variant="outline"
                        @click="handleArchive"
                        :disabled="isArchiving"
                    >
                        {{ isArchiving ? 'Archiving...' : 'Archive' }}
                    </Button>
                    <Button variant="outline" @click="handleEdit">
                        Edit
                    </Button>
                    <Button variant="destructive" @click="isDeleteModalOpen = true">
                        Delete
                    </Button>
                </div>
            </div>

            <!-- Main Content -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column - Details -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Description -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Description
                        </h2>
                        <p
                            v-if="course.description"
                            class="mt-3 text-slate-600 dark:text-slate-400"
                        >
                            {{ course.description }}
                        </p>
                        <p v-else class="mt-3 italic text-slate-400 dark:text-slate-500">
                            No description provided.
                        </p>
                    </div>

                    <!-- Learning Objectives -->
                    <div
                        v-if="course.learning_objectives && course.learning_objectives.length > 0"
                        class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Learning Objectives
                        </h2>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="(objective, index) in course.learning_objectives"
                                :key="index"
                                class="flex items-start gap-2 text-slate-600 dark:text-slate-400"
                            >
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ objective }}
                            </li>
                        </ul>
                    </div>

                    <!-- Syllabus -->
                    <div
                        v-if="course.syllabus"
                        class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Syllabus
                        </h2>
                        <div class="mt-3 whitespace-pre-wrap text-slate-600 dark:text-slate-400">
                            {{ course.syllabus }}
                        </div>
                    </div>

                    <!-- Prerequisites -->
                    <div
                        v-if="course.prerequisites && course.prerequisites.length > 0"
                        class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Prerequisites
                        </h2>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="prereq in course.prerequisites"
                                :key="prereq.id"
                                class="flex items-center gap-2"
                            >
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                </svg>
                                <span class="text-slate-600 dark:text-slate-400">
                                    {{ prereq.title }}
                                    <span class="text-slate-400">({{ prereq.code }})</span>
                                </span>
                                <span
                                    v-if="prereq.is_mandatory"
                                    class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700 dark:bg-red-900/30 dark:text-red-400"
                                >
                                    Required
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- Course Materials -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                Course Materials
                            </h2>
                            <Button
                                variant="outline"
                                size="sm"
                                @click="isUploadModalOpen = true"
                            >
                                <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Material
                            </Button>
                        </div>
                        <div class="mt-4">
                            <CourseMaterialList
                                :materials="materials"
                                :course-id="course.id"
                                :is-admin="true"
                                @delete="handleDeleteMaterial"
                                @reorder="handleReorderMaterials"
                            />
                        </div>
                    </div>
                </div>

                <!-- Right Column - Meta Info -->
                <div class="space-y-6">
                    <!-- Course Details Card -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Course Details
                        </h2>
                        <dl class="mt-4 space-y-4">
                            <!-- Delivery Method -->
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Delivery Method
                                </dt>
                                <dd class="mt-1">
                                    <DeliveryMethodBadge
                                        :method="course.delivery_method"
                                        :label="course.delivery_method_label"
                                    />
                                </dd>
                            </div>

                            <!-- Level -->
                            <div v-if="course.level">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Level
                                </dt>
                                <dd class="mt-1">
                                    <CourseLevelBadge
                                        :level="course.level"
                                        :label="course.level_label ?? undefined"
                                    />
                                </dd>
                            </div>

                            <!-- Duration -->
                            <div v-if="course.formatted_duration">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Duration
                                </dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                                    {{ course.formatted_duration }}
                                </dd>
                            </div>

                            <!-- Cost -->
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Cost
                                </dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                                    {{ formattedCost || 'Free' }}
                                </dd>
                            </div>

                            <!-- Max Participants -->
                            <div v-if="course.max_participants">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Max Participants
                                </dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                                    {{ course.max_participants }}
                                </dd>
                            </div>

                            <!-- Provider -->
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Provider
                                </dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                                    {{ course.provider_type_label }}
                                    <span v-if="course.provider_name" class="text-slate-500">
                                        ({{ course.provider_name }})
                                    </span>
                                </dd>
                            </div>

                            <!-- Created By -->
                            <div v-if="course.creator">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Created By
                                </dt>
                                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                                    {{ course.creator.full_name }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Categories Card -->
                    <div
                        v-if="course.categories && course.categories.length > 0"
                        class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            Categories
                        </h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="category in course.categories"
                                :key="category.id"
                                class="rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
                            >
                                {{ category.name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <DeleteConfirmationModal
            v-model:open="isDeleteModalOpen"
            title="Delete Course"
            :description="`Are you sure you want to delete the course '${course.title}'? This action cannot be undone.`"
            :processing="isDeleting"
            @confirm="confirmDelete"
        />

        <!-- Material Upload Modal -->
        <CourseMaterialUploadModal
            v-model:open="isUploadModalOpen"
            :course-id="course.id"
            @success="handleMaterialUploadSuccess"
            @close="isUploadModalOpen = false"
        />

        <!-- Delete Material Confirmation Modal -->
        <DeleteConfirmationModal
            v-model:open="isDeleteMaterialModalOpen"
            title="Delete Material"
            :description="`Are you sure you want to delete '${materialToDelete?.title}'? This action cannot be undone.`"
            :processing="isDeletingMaterial"
            @confirm="confirmDeleteMaterial"
        />
    </TenantLayout>
</template>
