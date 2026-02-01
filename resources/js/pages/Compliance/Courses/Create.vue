<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';

interface AvailableCourse {
    id: number;
    title: string;
    code: string;
}

interface ContentTypeOption {
    value: string;
    label: string;
    icon: string;
}

const props = defineProps<{
    availableCourses: AvailableCourse[];
    contentTypeOptions: ContentTypeOption[];
}>();

const { tenantName, primaryColor } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compliance', href: '/compliance' },
    { title: 'Courses', href: '/compliance/courses' },
    { title: 'Create', href: '#' },
];

const availableCoursesData = computed(() => props.availableCourses ?? []);

const form = reactive({
    course_id: '',
    days_to_complete: '30',
    validity_months: '',
    passing_score: '80',
    max_attempts: '3',
    allow_retakes_after_pass: false,
    requires_acknowledgment: false,
    acknowledgment_text: '',
    auto_reassign_on_expiry: true,
    errors: {} as Record<string, string>,
    processing: false,
});

async function handleSubmit() {
    form.processing = true;
    form.errors = {};

    try {
        const payload = {
            course_id: form.course_id ? parseInt(form.course_id) : null,
            days_to_complete: form.days_to_complete ? parseInt(form.days_to_complete) : null,
            validity_months: form.validity_months ? parseInt(form.validity_months) : null,
            passing_score: form.passing_score ? parseFloat(form.passing_score) : 80,
            max_attempts: form.max_attempts ? parseInt(form.max_attempts) : null,
            allow_retakes_after_pass: form.allow_retakes_after_pass,
            requires_acknowledgment: form.requires_acknowledgment,
            acknowledgment_text: form.acknowledgment_text || null,
            auto_reassign_on_expiry: form.auto_reassign_on_expiry,
        };

        await axios.post('/api/compliance/courses', payload);
        router.visit('/compliance/courses');
    } catch (error: any) {
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors || {};
            for (const [key, messages] of Object.entries(validationErrors)) {
                form.errors[key] = Array.isArray(messages) ? messages[0] : messages;
            }
        } else {
            form.errors.course_id = error.response?.data?.message || 'An error occurred';
        }
    } finally {
        form.processing = false;
    }
}
</script>

<template>
    <Head :title="`Create Compliance Course - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl">
            <div class="flex flex-col gap-6">
                <!-- Page Header -->
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Create Compliance Course
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Set up a new mandatory training course for compliance tracking.
                    </p>
                </div>

                <form @submit.prevent="handleSubmit" class="flex flex-col gap-6">
                    <!-- Course Selection -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Course Selection</CardTitle>
                            <CardDescription>
                                Select an existing course to configure for compliance tracking.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-col gap-2">
                                    <Label for="course_id">Base Course *</Label>
                                    <Select v-model="form.course_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a course" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="course in availableCoursesData"
                                                :key="course.id"
                                                :value="String(course.id)"
                                            >
                                                {{ course.title }} ({{ course.code }})
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="form.errors.course_id" class="text-sm text-red-500">
                                        {{ form.errors.course_id }}
                                    </p>
                                </div>

                            </div>
                        </CardContent>
                    </Card>

                    <!-- Completion Settings -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Completion Settings</CardTitle>
                            <CardDescription>
                                Configure completion requirements and scoring.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="flex flex-col gap-2">
                                    <Label for="days_to_complete">Days to Complete</Label>
                                    <Input
                                        id="days_to_complete"
                                        v-model="form.days_to_complete"
                                        type="number"
                                        min="1"
                                        placeholder="e.g., 30"
                                    />
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Leave blank for no deadline
                                    </p>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <Label for="validity_months">Validity Period (Months)</Label>
                                    <Input
                                        id="validity_months"
                                        v-model="form.validity_months"
                                        type="number"
                                        min="1"
                                        placeholder="e.g., 12"
                                    />
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        How long completion remains valid
                                    </p>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <Label for="passing_score">Passing Score (%) *</Label>
                                    <Input
                                        id="passing_score"
                                        v-model="form.passing_score"
                                        type="number"
                                        min="0"
                                        max="100"
                                        required
                                    />
                                </div>

                                <div class="flex flex-col gap-2">
                                    <Label for="max_attempts">Maximum Attempts</Label>
                                    <Input
                                        id="max_attempts"
                                        v-model="form.max_attempts"
                                        type="number"
                                        min="1"
                                        placeholder="Unlimited"
                                    />
                                </div>

                            </div>
                        </CardContent>
                    </Card>

                    <!-- Course Options -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Course Options</CardTitle>
                            <CardDescription>
                                Configure how employees interact with this course.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col gap-4">
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="checkbox"
                                        v-model="form.allow_retakes_after_pass"
                                        class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <span class="text-sm text-slate-700 dark:text-slate-300">
                                        Allow retakes after passing
                                    </span>
                                </label>

                                <label class="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="checkbox"
                                        v-model="form.auto_reassign_on_expiry"
                                        class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <span class="text-sm text-slate-700 dark:text-slate-300">
                                        Automatically reassign when certification expires
                                    </span>
                                </label>

                                <label class="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="checkbox"
                                        v-model="form.requires_acknowledgment"
                                        class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <span class="text-sm text-slate-700 dark:text-slate-300">
                                        Require acknowledgment before starting
                                    </span>
                                </label>

                                <div v-if="form.requires_acknowledgment" class="flex flex-col gap-2 pl-6">
                                    <Label for="acknowledgment_text">Acknowledgment Text</Label>
                                    <Textarea
                                        id="acknowledgment_text"
                                        v-model="form.acknowledgment_text"
                                        placeholder="Enter the acknowledgment text employees must agree to..."
                                        rows="3"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="router.visit('/compliance/courses')"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            {{ form.processing ? 'Creating...' : 'Create Course' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </TenantLayout>
</template>
