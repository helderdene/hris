<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { BookOpen, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface EvaluationGap {
    competency_id: number;
    competency_name: string;
    current_level: number;
    target_level: number;
    gap_size: number;
}

interface FromEvaluation {
    id: number;
    instance_name: string;
}

interface Manager {
    id: number;
    full_name: string;
}

interface EnumOption {
    value: string;
    label: string;
    description?: string;
    color?: string;
    icon?: string;
}

const props = defineProps<{
    priorities: EnumOption[];
    activityTypes: EnumOption[];
    fromEvaluation: FromEvaluation | null;
    evaluationGaps: EvaluationGap[];
    manager: Manager | null;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'Development Plans', href: '/my/development-plans' },
    { title: 'Create', href: '#' },
];

const form = ref({
    title: props.fromEvaluation ? `Development Plan - ${props.fromEvaluation.instance_name}` : '',
    description: '',
    start_date: '',
    target_completion_date: '',
    career_path_notes: '',
    manager_id: props.manager?.id ?? null,
    from_evaluation: props.fromEvaluation?.id ?? null,
    auto_populate_gaps: props.evaluationGaps.length > 0,
});

const isSubmitting = ref(false);
const errors = ref<Record<string, string[]>>({});

async function handleSubmit() {
    isSubmitting.value = true;
    errors.value = {};

    try {
        const response = await axios.post('/api/my/development-plans', form.value);
        router.visit(`/my/development-plans/${response.data.plan.id}`);
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
        } else {
            console.error('Error creating plan:', error);
        }
    } finally {
        isSubmitting.value = false;
    }
}

function handleCancel() {
    router.visit('/my/development-plans');
}
</script>

<template>
    <Head :title="`Create Development Plan - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl">
            <div class="mb-6">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Create Development Plan
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Define your development goals and activities.
                </p>
            </div>

            <!-- From Evaluation Notice -->
            <Card v-if="fromEvaluation" class="mb-6 border-indigo-200 bg-indigo-50 dark:border-indigo-800 dark:bg-indigo-900/20">
                <CardContent class="flex items-start gap-3 p-4">
                    <BookOpen class="h-5 w-5 shrink-0 text-indigo-600 dark:text-indigo-400" />
                    <div>
                        <p class="font-medium text-indigo-900 dark:text-indigo-100">
                            Creating from evaluation: {{ fromEvaluation.instance_name }}
                        </p>
                        <p v-if="evaluationGaps.length > 0" class="mt-1 text-sm text-indigo-700 dark:text-indigo-300">
                            {{ evaluationGaps.length }} competency gap(s) detected that can be added to your plan.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Competency Gaps Preview -->
            <Card v-if="evaluationGaps.length > 0" class="mb-6">
                <CardHeader>
                    <CardTitle class="text-base">Competency Gaps</CardTitle>
                    <CardDescription>
                        These development areas were identified from your evaluation.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div
                            v-for="gap in evaluationGaps"
                            :key="gap.competency_id"
                            class="flex items-center justify-between rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                        >
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ gap.competency_name }}
                                </p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                    Level {{ gap.current_level }} → {{ gap.target_level }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="gap.gap_size >= 2 ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300' : 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300'"
                                >
                                    Gap: {{ gap.gap_size }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-2">
                        <Checkbox
                            id="auto_populate"
                            :checked="form.auto_populate_gaps"
                            @update:checked="form.auto_populate_gaps = $event"
                        />
                        <Label for="auto_populate" class="text-sm">
                            Automatically add these gaps as development items
                        </Label>
                    </div>
                </CardContent>
            </Card>

            <!-- Form -->
            <Card>
                <CardHeader>
                    <CardTitle>Plan Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="title">Title *</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="e.g., Q1 2026 Development Plan"
                                :class="{ 'border-red-500': errors.title }"
                            />
                            <p v-if="errors.title" class="text-sm text-red-500">
                                {{ errors.title[0] }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Describe the overall objectives of this development plan..."
                                rows="3"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="start_date">Start Date</Label>
                                <Input
                                    id="start_date"
                                    v-model="form.start_date"
                                    type="date"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="target_completion_date">Target Completion</Label>
                                <Input
                                    id="target_completion_date"
                                    v-model="form.target_completion_date"
                                    type="date"
                                />
                                <p v-if="errors.target_completion_date" class="text-sm text-red-500">
                                    {{ errors.target_completion_date[0] }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="career_path_notes">Career Path Notes</Label>
                            <Textarea
                                id="career_path_notes"
                                v-model="form.career_path_notes"
                                placeholder="Document your career aspirations and discussions with your manager..."
                                rows="4"
                            />
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Use this space to note your career goals, aspirations, and key points from career discussions.
                            </p>
                        </div>

                        <div v-if="manager" class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                            <p class="text-sm text-slate-600 dark:text-slate-400">
                                <span class="font-medium">Manager for approval:</span> {{ manager.full_name }}
                            </p>
                        </div>

                        <div class="flex justify-end gap-3">
                            <Button type="button" variant="outline" @click="handleCancel">
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                :disabled="isSubmitting"
                                :style="{ backgroundColor: primaryColor }"
                            >
                                <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                                {{ isSubmitting ? 'Creating...' : 'Create Plan' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
