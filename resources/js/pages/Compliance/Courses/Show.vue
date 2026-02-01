<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';

interface Course {
    id: number;
    title: string;
    code: string;
    description: string | null;
}

interface Module {
    id: number;
    title: string;
    description: string | null;
    content_type: string;
    content_type_label: string;
    content_type_icon: string;
    sort_order: number;
    is_required: boolean;
    duration_minutes: number | null;
    passing_score: number | null;
    question_count?: number;
    created_at: string;
}

interface Rule {
    id: number;
    name: string;
    rule_type_label: string;
    is_active: boolean;
    priority: number;
    assignments_count?: number;
}

interface ComplianceCourse {
    id: number;
    course_id: number;
    course: Course;
    days_to_complete: number | null;
    validity_months: number | null;
    passing_score: number;
    max_attempts: number | null;
    allow_retakes_after_pass: boolean;
    requires_acknowledgment: boolean;
    acknowledgment_text: string | null;
    reminder_days: number[] | null;
    escalation_days: number[] | null;
    auto_reassign_on_expiry: boolean;
    completion_message: string | null;
    modules: Module[];
    assignment_rules: Rule[];
    total_duration_minutes: number | null;
    required_modules_count: number;
    has_assessments: boolean;
    created_at: string;
    updated_at: string;
}

interface ContentTypeOption {
    value: string;
    label: string;
    icon: string;
}

interface RuleTypeOption {
    value: string;
    label: string;
    description: string;
}

const props = defineProps<{
    complianceCourse: ComplianceCourse;
    contentTypeOptions: ContentTypeOption[];
    ruleTypeOptions: RuleTypeOption[];
}>();

const { tenantName, primaryColor } = useTenant();

const courseData = computed(() => props.complianceCourse);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compliance', href: '/compliance' },
    { title: 'Courses', href: '/compliance/courses' },
    { title: courseData.value?.course?.title ?? 'Course', href: '#' },
];

const activeTab = ref('modules');

const modulesData = computed(() => courseData.value?.modules ?? []);
const rulesData = computed(() => courseData.value?.assignment_rules ?? []);
const contentTypeOptions = computed(() => props.contentTypeOptions ?? []);

function getContentTypeIcon(contentType: string): string {
    const icons: Record<string, string> = {
        video: 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z',
        text: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        pdf: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        scorm: 'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5',
        assessment: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z',
    };
    return icons[contentType] ?? icons.text;
}

function formatDuration(minutes: number | null): string {
    if (!minutes) return '-';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours > 0) {
        return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
    }
    return `${mins}m`;
}

// Add Module Dialog
const showAddModuleDialog = ref(false);
const moduleForm = reactive({
    title: '',
    description: '',
    content_type: '',
    content: '',
    external_url: '',
    duration_minutes: '',
    is_required: true,
    passing_score: '',
    errors: {} as Record<string, string>,
    processing: false,
});

function resetModuleForm() {
    moduleForm.title = '';
    moduleForm.description = '';
    moduleForm.content_type = '';
    moduleForm.content = '';
    moduleForm.external_url = '';
    moduleForm.duration_minutes = '';
    moduleForm.is_required = true;
    moduleForm.passing_score = '';
    moduleForm.errors = {};
    moduleForm.processing = false;
}

function openAddModuleDialog() {
    resetModuleForm();
    showAddModuleDialog.value = true;
}

async function submitModule() {
    moduleForm.processing = true;
    moduleForm.errors = {};

    try {
        const payload = {
            title: moduleForm.title,
            description: moduleForm.description || null,
            content_type: moduleForm.content_type,
            content: moduleForm.content || null,
            external_url: moduleForm.external_url || null,
            duration_minutes: moduleForm.duration_minutes ? parseInt(moduleForm.duration_minutes) : null,
            is_required: moduleForm.is_required,
            passing_score: moduleForm.passing_score ? parseFloat(moduleForm.passing_score) : null,
        };

        await axios.post(`/api/compliance/courses/${courseData.value.id}/modules`, payload);
        showAddModuleDialog.value = false;
        router.reload({ only: ['complianceCourse'] });
    } catch (error: any) {
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors || {};
            for (const [key, messages] of Object.entries(validationErrors)) {
                moduleForm.errors[key] = Array.isArray(messages) ? messages[0] : (messages as string);
            }
        } else {
            moduleForm.errors.title = error.response?.data?.message || 'An error occurred';
        }
    } finally {
        moduleForm.processing = false;
    }
}
</script>

<template>
    <Head :title="`${courseData?.course?.title} - Compliance - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ courseData?.course?.title }}
                        </h1>
                    </div>
                    <p v-if="courseData?.course?.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ courseData.course.description }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline">
                        Edit Course
                    </Button>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Modules</CardDescription>
                        <CardTitle class="text-2xl">
                            {{ modulesData.length }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Duration</CardDescription>
                        <CardTitle class="text-2xl">
                            {{ formatDuration(courseData?.total_duration_minutes) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Passing Score</CardDescription>
                        <CardTitle class="text-2xl">
                            {{ courseData?.passing_score }}%
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Days to Complete</CardDescription>
                        <CardTitle class="text-2xl">
                            {{ courseData?.days_to_complete ?? 'N/A' }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Validity</CardDescription>
                        <CardTitle class="text-2xl">
                            {{ courseData?.validity_months ? `${courseData.validity_months}mo` : 'N/A' }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <!-- Tabs -->
            <Tabs v-model="activeTab" class="w-full">
                <TabsList>
                    <TabsTrigger value="modules">Modules ({{ modulesData.length }})</TabsTrigger>
                    <TabsTrigger value="rules">Rules ({{ rulesData.length }})</TabsTrigger>
                    <TabsTrigger value="settings">Settings</TabsTrigger>
                </TabsList>

                <!-- Modules Tab -->
                <TabsContent value="modules">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Course Modules</CardTitle>
                                <CardDescription>
                                    Content modules for this compliance course
                                </CardDescription>
                            </div>
                            <Button :style="{ backgroundColor: primaryColor }" @click="openAddModuleDialog">
                                Add Module
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div v-if="modulesData.length > 0" class="flex flex-col gap-3">
                                <div
                                    v-for="(module, index) in modulesData"
                                    :key="module.id"
                                    class="flex items-center gap-4 rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                                >
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-sm font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                        {{ index + 1 }}
                                    </div>
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800">
                                        <svg
                                            class="h-5 w-5 text-slate-600 dark:text-slate-400"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="getContentTypeIcon(module.content_type)" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ module.title }}
                                            </span>
                                            <Badge v-if="module.is_required" variant="secondary" class="text-xs">
                                                Required
                                            </Badge>
                                        </div>
                                        <div class="flex gap-3 text-sm text-slate-500 dark:text-slate-400">
                                            <span>{{ module.content_type_label }}</span>
                                            <span v-if="module.duration_minutes">{{ formatDuration(module.duration_minutes) }}</span>
                                            <span v-if="module.question_count">{{ module.question_count }} questions</span>
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="sm">
                                        Edit
                                    </Button>
                                </div>
                            </div>
                            <div v-else class="py-8 text-center text-slate-500 dark:text-slate-400">
                                No modules added yet. Add your first module to get started.
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Rules Tab -->
                <TabsContent value="rules">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Assignment Rules</CardTitle>
                                <CardDescription>
                                    Rules that automatically assign this course to employees
                                </CardDescription>
                            </div>
                            <Link href="/compliance/rules">
                                <Button variant="outline">
                                    Manage Rules
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent>
                            <div v-if="rulesData.length > 0" class="flex flex-col gap-3">
                                <div
                                    v-for="rule in rulesData"
                                    :key="rule.id"
                                    class="flex items-center gap-4 rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                                >
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ rule.name }}
                                            </span>
                                            <Badge :variant="rule.is_active ? 'default' : 'outline'">
                                                {{ rule.is_active ? 'Active' : 'Inactive' }}
                                            </Badge>
                                        </div>
                                        <div class="flex gap-3 text-sm text-slate-500 dark:text-slate-400">
                                            <span>{{ rule.rule_type_label }}</span>
                                            <span>Priority: {{ rule.priority }}</span>
                                            <span v-if="rule.assignments_count !== undefined">
                                                {{ rule.assignments_count }} assignments
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="py-8 text-center text-slate-500 dark:text-slate-400">
                                No assignment rules configured for this course.
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Settings Tab -->
                <TabsContent value="settings">
                    <Card>
                        <CardHeader>
                            <CardTitle>Course Settings</CardTitle>
                            <CardDescription>
                                Configuration options for this compliance course
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Maximum Attempts
                                    </dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">
                                        {{ courseData?.max_attempts ?? 'Unlimited' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Allow Retakes After Passing
                                    </dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">
                                        {{ courseData?.allow_retakes_after_pass ? 'Yes' : 'No' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Auto Reassign on Expiry
                                    </dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">
                                        {{ courseData?.auto_reassign_on_expiry ? 'Yes' : 'No' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Acknowledgment Required
                                    </dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">
                                        {{ courseData?.requires_acknowledgment ? 'Yes' : 'No' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Reminder Days
                                    </dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">
                                        {{ courseData?.reminder_days?.join(', ') || 'Not configured' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        Escalation Days
                                    </dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">
                                        {{ courseData?.escalation_days?.join(', ') || 'Not configured' }}
                                    </dd>
                                </div>
                            </dl>
                            <div v-if="courseData?.acknowledgment_text" class="mt-4">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Acknowledgment Text
                                </dt>
                                <dd class="mt-1 rounded-lg bg-slate-50 p-3 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                    {{ courseData.acknowledgment_text }}
                                </dd>
                            </div>
                            <div v-if="courseData?.completion_message" class="mt-4">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    Completion Message
                                </dt>
                                <dd class="mt-1 rounded-lg bg-slate-50 p-3 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                    {{ courseData.completion_message }}
                                </dd>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>

        <!-- Add Module Dialog -->
        <Dialog v-model:open="showAddModuleDialog">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Add Module</DialogTitle>
                    <DialogDescription>
                        Add a new content module to this compliance course.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitModule" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="module_title">Title *</Label>
                        <Input
                            id="module_title"
                            v-model="moduleForm.title"
                            placeholder="e.g., Introduction to Safety"
                            required
                        />
                        <p v-if="moduleForm.errors.title" class="text-sm text-red-500">
                            {{ moduleForm.errors.title }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="module_content_type">Content Type *</Label>
                        <Select v-model="moduleForm.content_type">
                            <SelectTrigger>
                                <SelectValue placeholder="Select content type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in contentTypeOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="moduleForm.errors.content_type" class="text-sm text-red-500">
                            {{ moduleForm.errors.content_type }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="module_description">Description</Label>
                        <Textarea
                            id="module_description"
                            v-model="moduleForm.description"
                            placeholder="Brief description of this module"
                            rows="2"
                        />
                    </div>

                    <div v-if="moduleForm.content_type === 'video'" class="flex flex-col gap-2">
                        <Label for="module_external_url">Video URL</Label>
                        <Input
                            id="module_external_url"
                            v-model="moduleForm.external_url"
                            type="url"
                            placeholder="https://youtube.com/watch?v=..."
                        />
                        <p v-if="moduleForm.errors.external_url" class="text-sm text-red-500">
                            {{ moduleForm.errors.external_url }}
                        </p>
                    </div>

                    <div v-if="moduleForm.content_type === 'text'" class="flex flex-col gap-2">
                        <Label for="module_content">Content</Label>
                        <Textarea
                            id="module_content"
                            v-model="moduleForm.content"
                            placeholder="Enter the text content..."
                            rows="4"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <Label for="module_duration">Duration (minutes)</Label>
                            <Input
                                id="module_duration"
                                v-model="moduleForm.duration_minutes"
                                type="number"
                                min="1"
                                placeholder="e.g., 30"
                            />
                        </div>

                        <div v-if="moduleForm.content_type === 'assessment'" class="flex flex-col gap-2">
                            <Label for="module_passing_score">Passing Score (%)</Label>
                            <Input
                                id="module_passing_score"
                                v-model="moduleForm.passing_score"
                                type="number"
                                min="0"
                                max="100"
                                placeholder="e.g., 80"
                            />
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="moduleForm.is_required"
                            class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        <span class="text-sm text-slate-700 dark:text-slate-300">
                            This module is required
                        </span>
                    </label>

                    <DialogFooter class="gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showAddModuleDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="moduleForm.processing"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            {{ moduleForm.processing ? 'Adding...' : 'Add Module' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
