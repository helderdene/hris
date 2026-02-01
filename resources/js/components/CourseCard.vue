<script setup lang="ts">
import CourseLevelBadge from '@/components/CourseLevelBadge.vue';
import CourseStatusBadge from '@/components/CourseStatusBadge.vue';
import DeliveryMethodBadge from '@/components/DeliveryMethodBadge.vue';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';

interface Category {
    id: number;
    name: string;
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

const props = defineProps<{
    course: Course;
    showStatus?: boolean;
    showActions?: boolean;
}>();

const emit = defineEmits<{
    (e: 'view', course: Course): void;
    (e: 'edit', course: Course): void;
}>();

const formattedCost = computed(() => {
    if (!props.course.cost) return null;
    const cost = parseFloat(props.course.cost);
    if (cost === 0) return 'Free';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(cost);
});
</script>

<template>
    <div
        class="group relative overflow-hidden rounded-lg border border-slate-200 bg-white p-5 transition-all hover:border-slate-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600"
    >
        <!-- Status Badge (for admin view) -->
        <div
            v-if="showStatus"
            class="absolute right-4 top-4"
        >
            <CourseStatusBadge :status="course.status" />
        </div>

        <!-- Course Code -->
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
            {{ course.code }}
        </p>

        <!-- Title -->
        <h3
            class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100"
        >
            {{ course.title }}
        </h3>

        <!-- Description -->
        <p
            v-if="course.description"
            class="mt-2 line-clamp-2 text-sm text-slate-600 dark:text-slate-400"
        >
            {{ course.description }}
        </p>

        <!-- Badges -->
        <div class="mt-4 flex flex-wrap gap-2">
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

        <!-- Categories -->
        <div
            v-if="course.categories && course.categories.length > 0"
            class="mt-3"
        >
            <div class="flex flex-wrap gap-1">
                <span
                    v-for="category in course.categories.slice(0, 3)"
                    :key="category.id"
                    class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                >
                    {{ category.name }}
                </span>
                <span
                    v-if="course.categories.length > 3"
                    class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                >
                    +{{ course.categories.length - 3 }} more
                </span>
            </div>
        </div>

        <!-- Meta Info -->
        <div
            class="mt-4 flex items-center gap-4 border-t border-slate-100 pt-4 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400"
        >
            <span v-if="course.formatted_duration" class="flex items-center gap-1">
                <svg
                    class="h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                {{ course.formatted_duration }}
            </span>

            <span v-if="formattedCost" class="flex items-center gap-1">
                <svg
                    class="h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"
                    />
                </svg>
                {{ formattedCost }}
            </span>

            <span
                v-if="course.prerequisites_count && course.prerequisites_count > 0"
                class="flex items-center gap-1"
            >
                <svg
                    class="h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"
                    />
                </svg>
                {{ course.prerequisites_count }} prerequisite{{
                    course.prerequisites_count > 1 ? 's' : ''
                }}
            </span>
        </div>

        <!-- Actions -->
        <div
            v-if="showActions"
            class="mt-4 flex gap-2"
        >
            <Button
                variant="outline"
                size="sm"
                class="flex-1"
                @click="emit('view', course)"
            >
                View Details
            </Button>
            <Button
                variant="ghost"
                size="sm"
                @click="emit('edit', course)"
            >
                Edit
            </Button>
        </div>
        <div v-else class="mt-4">
            <Button
                variant="outline"
                size="sm"
                class="w-full"
                @click="emit('view', course)"
            >
                View Course
            </Button>
        </div>
    </div>
</template>
