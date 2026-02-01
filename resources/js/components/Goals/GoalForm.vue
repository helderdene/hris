<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import KeyResultForm from '@/components/Goals/KeyResultForm.vue';
import MilestoneList from '@/components/Goals/MilestoneList.vue';
import { computed, ref, watch } from 'vue';
import axios from 'axios';

interface EnumOption {
    value: string;
    label: string;
    description?: string;
    color?: string;
}

interface ParentGoal {
    id: number;
    title: string;
    goal_type: string;
    owner_name: string;
}

interface KeyResult {
    id?: number;
    title: string;
    description: string;
    metric_type: string;
    metric_unit: string;
    target_value: number;
    starting_value: number;
    weight: number;
}

interface Milestone {
    id?: number;
    title: string;
    description: string;
    due_date: string;
}

interface Goal {
    id?: number;
    goal_type: string;
    title: string;
    description: string;
    category: string;
    visibility: string;
    priority: string;
    parent_goal_id: number | null;
    start_date: string;
    due_date: string;
    key_results?: KeyResult[];
    milestones?: Milestone[];
}

const props = withDefaults(
    defineProps<{
        goal?: Goal;
        availableParentGoals?: ParentGoal[];
        goalTypes: EnumOption[];
        priorities: EnumOption[];
        visibilityOptions: EnumOption[];
        metricTypes: EnumOption[];
        isEditing?: boolean;
    }>(),
    {
        availableParentGoals: () => [],
        isEditing: false,
    },
);

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

const today = new Date().toISOString().split('T')[0];

const form = ref({
    goal_type: props.goal?.goal_type || 'okr_objective',
    title: props.goal?.title || '',
    description: props.goal?.description || '',
    category: props.goal?.category || '',
    visibility: props.goal?.visibility || 'private',
    priority: props.goal?.priority || 'medium',
    parent_goal_id: props.goal?.parent_goal_id || null,
    start_date: props.goal?.start_date || today,
    due_date: props.goal?.due_date || '',
    key_results: props.goal?.key_results || [] as KeyResult[],
    milestones: props.goal?.milestones || [] as Milestone[],
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const isOkr = computed(() => form.value.goal_type === 'okr_objective');

// Key Results management
function addKeyResult() {
    form.value.key_results.push({
        title: '',
        description: '',
        metric_type: 'number',
        metric_unit: '',
        target_value: 100,
        starting_value: 0,
        weight: 1,
    });
}

function removeKeyResult(index: number) {
    form.value.key_results.splice(index, 1);
}

function updateKeyResult(index: number, keyResult: KeyResult) {
    form.value.key_results[index] = keyResult;
}

// Milestones management
function addMilestone() {
    form.value.milestones.push({
        title: '',
        description: '',
        due_date: '',
    });
}

function removeMilestone(index: number) {
    form.value.milestones.splice(index, 1);
}

function updateMilestone(index: number, milestone: Milestone) {
    form.value.milestones[index] = milestone;
}

// Watch goal type changes to clear irrelevant data
watch(
    () => form.value.goal_type,
    (newType) => {
        if (newType === 'okr_objective') {
            form.value.milestones = [];
        } else {
            form.value.key_results = [];
        }
    },
);

async function handleSubmit() {
    const url = props.isEditing
        ? `/api/my/goals/${props.goal?.id}`
        : '/api/my/goals';

    const method = props.isEditing ? 'put' : 'post';

    processing.value = true;
    errors.value = {};

    console.log('Submitting form data:', JSON.stringify(form.value, null, 2));

    try {
        const response = await axios[method](url, form.value);
        console.log('Success response:', response.data);
        emit('success');
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            console.log('Validation errors:', error.response.data);
            const responseErrors = error.response.data.errors || {};
            errors.value = Object.fromEntries(
                Object.entries(responseErrors).map(([key, messages]) => [
                    key,
                    Array.isArray(messages) ? messages[0] : messages,
                ])
            );
        } else {
            console.error('Error creating goal:', error);
        }
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- General Error Alert -->
        <div
            v-if="Object.keys(errors).length > 0"
            class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
        >
            <h3 class="font-medium text-red-800 dark:text-red-200">
                Please fix the following errors:
            </h3>
            <ul class="mt-2 list-inside list-disc text-sm text-red-700 dark:text-red-300">
                <li v-for="(error, key) in errors" :key="key">
                    {{ error }}
                </li>
            </ul>
        </div>

        <!-- Goal Type Selection -->
        <Card>
            <CardHeader>
                <CardTitle>Goal Type</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 gap-4">
                    <button
                        v-for="type in goalTypes"
                        :key="type.value"
                        type="button"
                        :class="[
                            'rounded-lg border-2 p-4 text-left transition-all',
                            form.goal_type === type.value
                                ? 'border-blue-500 bg-blue-50 dark:border-blue-400 dark:bg-blue-900/20'
                                : 'border-slate-200 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600',
                        ]"
                        @click="form.goal_type = type.value"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                :class="[
                                    'flex h-10 w-10 items-center justify-center rounded-full',
                                    type.value === 'okr_objective'
                                        ? 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400'
                                        : 'bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400',
                                ]"
                            >
                                <svg v-if="type.value === 'okr_objective'" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" />
                                </svg>
                                <svg v-else class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ type.label }}
                                </div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ type.description }}
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
                <p v-if="errors.goal_type" class="mt-2 text-sm text-red-600">
                    {{ errors.goal_type }}
                </p>
            </CardContent>
        </Card>

        <!-- Basic Information -->
        <Card>
            <CardHeader>
                <CardTitle>Goal Details</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div>
                    <Label for="title">Title</Label>
                    <Input
                        id="title"
                        v-model="form.title"
                        type="text"
                        placeholder="Enter your goal title"
                        :class="{ 'border-red-500': errors.title }"
                    />
                    <p v-if="errors.title" class="mt-1 text-sm text-red-600">
                        {{ errors.title }}
                    </p>
                </div>

                <div>
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Describe your goal in detail"
                        rows="3"
                    />
                    <p v-if="errors.description" class="mt-1 text-sm text-red-600">
                        {{ errors.description }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="category">Category</Label>
                        <Input
                            id="category"
                            v-model="form.category"
                            type="text"
                            placeholder="e.g., Professional Development"
                        />
                    </div>

                    <div>
                        <Label for="priority">Priority</Label>
                        <Select v-model="form.priority">
                            <SelectTrigger>
                                <SelectValue placeholder="Select priority" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="priority in priorities"
                                    :key="priority.value"
                                    :value="priority.value"
                                >
                                    {{ priority.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="visibility">Visibility</Label>
                        <Select v-model="form.visibility">
                            <SelectTrigger>
                                <SelectValue placeholder="Select visibility" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in visibilityOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="availableParentGoals.length > 0">
                        <Label for="parent_goal_id">Align to Goal (Optional)</Label>
                        <Select
                            :model-value="form.parent_goal_id?.toString() || 'none'"
                            @update:model-value="(val) => form.parent_goal_id = val === 'none' ? null : parseInt(val)"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="No alignment" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">No alignment</SelectItem>
                                <SelectItem
                                    v-for="parentGoal in availableParentGoals"
                                    :key="parentGoal.id"
                                    :value="parentGoal.id.toString()"
                                >
                                    {{ parentGoal.title }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="start_date">Start Date</Label>
                        <Input
                            id="start_date"
                            v-model="form.start_date"
                            type="date"
                            :class="{ 'border-red-500': errors.start_date }"
                        />
                        <p v-if="errors.start_date" class="mt-1 text-sm text-red-600">
                            {{ errors.start_date }}
                        </p>
                    </div>

                    <div>
                        <Label for="due_date">Due Date</Label>
                        <Input
                            id="due_date"
                            v-model="form.due_date"
                            type="date"
                            :class="{ 'border-red-500': errors.due_date }"
                        />
                        <p v-if="errors.due_date" class="mt-1 text-sm text-red-600">
                            {{ errors.due_date }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Key Results (for OKRs) -->
        <Card v-if="isOkr">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>Key Results</CardTitle>
                    <Button type="button" variant="outline" size="sm" @click="addKeyResult">
                        <svg class="mr-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Key Result
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="form.key_results.length === 0" :class="[
                    'rounded-lg border border-dashed p-6 text-center',
                    errors.key_results
                        ? 'border-red-400 bg-red-50 dark:border-red-600 dark:bg-red-900/20'
                        : 'border-slate-300 dark:border-slate-700'
                ]">
                    <p :class="errors.key_results ? 'text-sm text-red-600 dark:text-red-400' : 'text-sm text-slate-500 dark:text-slate-400'">
                        {{ errors.key_results || 'Add measurable key results to track your objective\'s progress.' }}
                    </p>
                </div>
                <div v-else class="space-y-4">
                    <KeyResultForm
                        v-for="(keyResult, index) in form.key_results"
                        :key="index"
                        :key-result="keyResult"
                        :index="index"
                        :metric-types="metricTypes"
                        :errors="errors"
                        @update="updateKeyResult(index, $event)"
                        @remove="removeKeyResult(index)"
                    />
                </div>
            </CardContent>
        </Card>

        <!-- Milestones (for SMART Goals) -->
        <Card v-else>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>Milestones</CardTitle>
                    <Button type="button" variant="outline" size="sm" @click="addMilestone">
                        <svg class="mr-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Milestone
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="form.milestones.length === 0" :class="[
                    'rounded-lg border border-dashed p-6 text-center',
                    errors.milestones
                        ? 'border-red-400 bg-red-50 dark:border-red-600 dark:bg-red-900/20'
                        : 'border-slate-300 dark:border-slate-700'
                ]">
                    <p :class="errors.milestones ? 'text-sm text-red-600 dark:text-red-400' : 'text-sm text-slate-500 dark:text-slate-400'">
                        {{ errors.milestones || 'Add milestones to break down your goal into achievable steps.' }}
                    </p>
                </div>
                <MilestoneList
                    v-else
                    :milestones="form.milestones"
                    :editable="true"
                    :errors="errors"
                    @update="updateMilestone"
                    @remove="removeMilestone"
                />
            </CardContent>
        </Card>

        <!-- Form Actions -->
        <div class="flex items-center justify-end gap-3">
            <Button type="button" variant="outline" @click="emit('cancel')">
                Cancel
            </Button>
            <Button type="submit" :disabled="processing">
                {{ processing ? 'Saving...' : isEditing ? 'Update Goal' : 'Create Goal' }}
            </Button>
        </div>
    </form>
</template>
