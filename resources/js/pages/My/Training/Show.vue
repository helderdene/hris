<script setup lang="ts">
import CourseLevelBadge from '@/components/CourseLevelBadge.vue';
import CourseMaterialList from '@/components/CourseMaterialList.vue';
import DeliveryMethodBadge from '@/components/DeliveryMethodBadge.vue';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
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
}

const props = defineProps<{
    course: Course;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'Training', href: '/my/training' },
    { title: props.course.title, href: `/my/training/courses/${props.course.id}` },
];

const formattedCost = computed(() => {
    if (!props.course.cost) return null;
    const cost = parseFloat(props.course.cost);
    if (cost === 0) return 'Free';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(cost);
});

const materials = ref<CourseMaterial[]>([]);

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

onMounted(() => {
    fetchMaterials();
});
</script>

<template>
    <Head :title="`${course.title} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Back Link -->
            <div>
                <Link
                    href="/my/training"
                    class="inline-flex items-center text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300"
                >
                    <svg class="mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Catalog
                </Link>
            </div>

            <!-- Page Header -->
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ course.title }}
                    </h1>
                    <DeliveryMethodBadge
                        :method="course.delivery_method"
                        :label="course.delivery_method_label"
                    />
                    <CourseLevelBadge
                        v-if="course.level"
                        :level="course.level"
                        :label="course.level_label ?? undefined"
                    />
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Code: {{ course.code }}
                </p>
            </div>

            <!-- Main Content -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column - Details -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Description -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            About This Course
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
                            What You'll Learn
                        </h2>
                        <ul class="mt-3 space-y-3">
                            <li
                                v-for="(objective, index) in course.learning_objectives"
                                :key="index"
                                class="flex items-start gap-3 text-slate-600 dark:text-slate-400"
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
                            Course Syllabus
                        </h2>
                        <div class="mt-3 whitespace-pre-wrap text-slate-600 dark:text-slate-400">
                            {{ course.syllabus }}
                        </div>
                    </div>

                    <!-- Prerequisites -->
                    <div
                        v-if="course.prerequisites && course.prerequisites.length > 0"
                        class="rounded-xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-900/50 dark:bg-amber-900/20"
                    >
                        <h2 class="flex items-center gap-2 text-lg font-semibold text-amber-800 dark:text-amber-300">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Prerequisites Required
                        </h2>
                        <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                            You should complete the following courses before taking this one:
                        </p>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="prereq in course.prerequisites"
                                :key="prereq.id"
                                class="flex items-center gap-2 text-amber-800 dark:text-amber-300"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                <Link
                                    :href="`/my/training/courses/${prereq.id}`"
                                    class="underline hover:no-underline"
                                >
                                    {{ prereq.title }}
                                </Link>
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
                    <div
                        v-if="materials.length > 0"
                        class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                            <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            Learning Materials
                        </h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Download documents and resources for this course.
                        </p>
                        <div class="mt-4">
                            <CourseMaterialList
                                :materials="materials"
                                :course-id="course.id"
                                :is-admin="false"
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
                            <!-- Duration -->
                            <div v-if="course.formatted_duration">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Duration
                                </dt>
                                <dd class="mt-1 flex items-center gap-2 text-slate-900 dark:text-slate-100">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ course.formatted_duration }}
                                </dd>
                            </div>

                            <!-- Cost -->
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Cost
                                </dt>
                                <dd class="mt-1 flex items-center gap-2 text-slate-900 dark:text-slate-100">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ formattedCost || 'Free' }}
                                </dd>
                            </div>

                            <!-- Max Participants -->
                            <div v-if="course.max_participants">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Max Participants
                                </dt>
                                <dd class="mt-1 flex items-center gap-2 text-slate-900 dark:text-slate-100">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    {{ course.max_participants }} participants
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
    </TenantLayout>
</template>
