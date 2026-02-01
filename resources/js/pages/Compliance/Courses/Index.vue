<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface ComplianceCourse {
    id: number;
    course_id: number;
    course: {
        id: number;
        title: string;
        code: string;
        description: string | null;
    };
    is_mandatory: boolean;
    is_active: boolean;
    days_to_complete: number | null;
    validity_period_months: number | null;
    passing_score: number;
    max_attempts: number | null;
    modules_count?: number;
    assignments_count?: number;
    created_at: string;
}

interface Filters {
    search: string | null;
    is_active: string | null;
}

const props = defineProps<{
    courses: ComplianceCourse[];
    filters: Filters;
}>();

const { tenantName, primaryColor } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compliance', href: '/compliance' },
    { title: 'Courses', href: '/compliance/courses' },
];

const searchQuery = ref(props.filters.search ?? '');
const activeFilter = ref(props.filters.is_active ?? 'all');

const coursesData = computed(() => props.courses ?? []);

function handleSearch() {
    router.get('/compliance/courses', {
        search: searchQuery.value || undefined,
        is_active: activeFilter.value !== 'all' ? activeFilter.value : undefined,
    }, { preserveState: true });
}

function handleFilterChange(value: string) {
    activeFilter.value = value;
    router.get('/compliance/courses', {
        search: searchQuery.value || undefined,
        is_active: value !== 'all' ? value : undefined,
    }, { preserveState: true });
}

watch(searchQuery, () => {
    const timeout = setTimeout(handleSearch, 300);
    return () => clearTimeout(timeout);
});
</script>

<template>
    <Head :title="`Compliance Courses - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Compliance Courses
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage mandatory training courses and their content.
                    </p>
                </div>
                <Link href="/compliance/courses/create">
                    <Button :style="{ backgroundColor: primaryColor }">
                        Create Course
                    </Button>
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1 max-w-sm">
                    <Input
                        v-model="searchQuery"
                        placeholder="Search courses..."
                        class="pl-10"
                    />
                    <svg
                        class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
                        />
                    </svg>
                </div>
                <Select :model-value="activeFilter" @update:model-value="handleFilterChange">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Status</SelectItem>
                        <SelectItem value="1">Active</SelectItem>
                        <SelectItem value="0">Inactive</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Courses Grid -->
            <div v-if="coursesData.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="course in coursesData"
                    :key="course.id"
                    :href="`/compliance/courses/${course.id}`"
                    class="group"
                >
                    <div
                        class="h-full rounded-xl border border-slate-200 bg-white p-6 transition-all hover:border-slate-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-slate-900 dark:text-slate-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 truncate">
                                    {{ course.course?.title ?? 'Untitled' }}
                                </h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ course.course?.code }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <Badge v-if="course.is_mandatory" variant="secondary">
                                    Mandatory
                                </Badge>
                                <Badge :variant="course.is_active ? 'default' : 'outline'">
                                    {{ course.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </div>
                        </div>

                        <p v-if="course.course?.description" class="mt-3 text-sm text-slate-600 dark:text-slate-400 line-clamp-2">
                            {{ course.course.description }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-4 text-sm text-slate-500 dark:text-slate-400">
                            <div v-if="course.days_to_complete">
                                <span class="font-medium">{{ course.days_to_complete }}</span> days to complete
                            </div>
                            <div v-if="course.validity_period_months">
                                Valid for <span class="font-medium">{{ course.validity_period_months }}</span> months
                            </div>
                            <div>
                                Pass: <span class="font-medium">{{ course.passing_score }}%</span>
                            </div>
                        </div>

                        <div class="mt-4 flex gap-4 text-xs text-slate-400 dark:text-slate-500">
                            <span>{{ course.modules_count ?? 0 }} modules</span>
                            <span>{{ course.assignments_count ?? 0 }} assignments</span>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-12 dark:border-slate-700 dark:bg-slate-900"
            >
                <svg
                    class="h-12 w-12 text-slate-400 dark:text-slate-500"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"
                    />
                </svg>
                <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                    No compliance courses
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Get started by creating your first compliance course.
                </p>
                <Link href="/compliance/courses/create" class="mt-4">
                    <Button :style="{ backgroundColor: primaryColor }">
                        Create Course
                    </Button>
                </Link>
            </div>
        </div>
    </TenantLayout>
</template>
