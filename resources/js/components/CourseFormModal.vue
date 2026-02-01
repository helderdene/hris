<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { computed, ref, watch } from 'vue';

interface EnumOption {
    value: string;
    label: string;
}

interface Category {
    id: number;
    name: string;
    code: string;
}

interface Prerequisite {
    id: number;
    title: string;
    code: string;
    is_mandatory?: boolean;
}

interface Course {
    id: number;
    title: string;
    code: string;
    description: string | null;
    delivery_method: string;
    provider_type: string;
    provider_name: string | null;
    duration_hours: number | null;
    duration_days: number | null;
    status: string;
    level: string | null;
    cost: string | null;
    max_participants: number | null;
    learning_objectives: string[] | null;
    syllabus: string | null;
    categories?: { id: number; name: string; code: string }[];
    prerequisites?: Prerequisite[];
}

interface Props {
    open: boolean;
    course: Course | null;
    categories: Category[];
    availablePrerequisites: { id: number; title: string; code: string }[];
    deliveryMethodOptions: EnumOption[];
    providerTypeOptions: EnumOption[];
    levelOptions: EnumOption[];
    statusOptions: EnumOption[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const form = ref({
    title: '',
    code: '',
    description: '',
    delivery_method: 'in_person',
    provider_type: 'internal',
    provider_name: '',
    duration_hours: null as number | null,
    duration_days: null as number | null,
    status: 'draft',
    level: null as string | null,
    cost: null as number | null,
    max_participants: null as number | null,
    learning_objectives: [] as string[],
    syllabus: '',
    category_ids: [] as number[],
    prerequisites: [] as { id: number; is_mandatory: boolean }[],
});

const newObjective = ref('');
const errors = ref<Record<string, string>>({});
const processing = ref(false);
const recentlySuccessful = ref(false);

const isEditing = computed(() => props.course !== null);

const modalTitle = computed(() =>
    isEditing.value ? 'Edit Course' : 'Add Course',
);

const modalDescription = computed(() => {
    if (isEditing.value) {
        return 'Update the course details below.';
    }
    return 'Create a new course for your training catalog.';
});

const filteredPrerequisites = computed(() => {
    if (!isEditing.value) return props.availablePrerequisites;
    return props.availablePrerequisites.filter(p => p.id !== props.course?.id);
});

const handleSubmit = async () => {
    errors.value = {};
    processing.value = true;

    try {
        const url = isEditing.value
            ? `/api/training/courses/${props.course!.id}`
            : '/api/training/courses';

        const method = isEditing.value ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                title: form.value.title,
                code: form.value.code,
                description: form.value.description || null,
                delivery_method: form.value.delivery_method,
                provider_type: form.value.provider_type,
                provider_name: form.value.provider_name || null,
                duration_hours: form.value.duration_hours,
                duration_days: form.value.duration_days,
                status: form.value.status,
                level: form.value.level,
                cost: form.value.cost,
                max_participants: form.value.max_participants,
                learning_objectives: form.value.learning_objectives.length > 0 ? form.value.learning_objectives : null,
                syllabus: form.value.syllabus || null,
                category_ids: form.value.category_ids,
                prerequisites: form.value.prerequisites,
            }),
        });

        if (response.status === 201 || response.ok) {
            recentlySuccessful.value = true;
            emit('success');

            setTimeout(() => {
                recentlySuccessful.value = false;
            }, 2000);
        } else if (response.status === 422) {
            const data = await response.json();
            if (data.errors) {
                errors.value = Object.fromEntries(
                    Object.entries(data.errors as Record<string, string[]>).map(
                        ([key, messages]) => [key, messages[0]],
                    ),
                );
            }
        } else if (response.status === 403) {
            errors.value = {
                title: 'You do not have permission to manage courses.',
            };
        } else {
            errors.value = {
                title: 'An unexpected error occurred. Please try again.',
            };
        }
    } catch {
        errors.value = {
            title: 'An unexpected error occurred. Please try again.',
        };
    } finally {
        processing.value = false;
    }
};

const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const addObjective = () => {
    if (newObjective.value.trim()) {
        form.value.learning_objectives.push(newObjective.value.trim());
        newObjective.value = '';
    }
};

const removeObjective = (index: number) => {
    form.value.learning_objectives.splice(index, 1);
};

const toggleCategory = (categoryId: number) => {
    const index = form.value.category_ids.indexOf(categoryId);
    if (index > -1) {
        form.value.category_ids.splice(index, 1);
    } else {
        form.value.category_ids.push(categoryId);
    }
};

const togglePrerequisite = (prereqId: number) => {
    const index = form.value.prerequisites.findIndex(p => p.id === prereqId);
    if (index > -1) {
        form.value.prerequisites.splice(index, 1);
    } else {
        form.value.prerequisites.push({ id: prereqId, is_mandatory: true });
    }
};

const isPrerequisiteSelected = (prereqId: number): boolean => {
    return form.value.prerequisites.some(p => p.id === prereqId);
};

const resetForm = () => {
    form.value = {
        title: '',
        code: '',
        description: '',
        delivery_method: 'in_person',
        provider_type: 'internal',
        provider_name: '',
        duration_hours: null,
        duration_days: null,
        status: 'draft',
        level: null,
        cost: null,
        max_participants: null,
        learning_objectives: [],
        syllabus: '',
        category_ids: [],
        prerequisites: [],
    };
    newObjective.value = '';
    errors.value = {};
};

const initializeForm = () => {
    if (props.course) {
        form.value = {
            title: props.course.title,
            code: props.course.code,
            description: props.course.description ?? '',
            delivery_method: props.course.delivery_method,
            provider_type: props.course.provider_type,
            provider_name: props.course.provider_name ?? '',
            duration_hours: props.course.duration_hours,
            duration_days: props.course.duration_days,
            status: props.course.status,
            level: props.course.level,
            cost: props.course.cost ? parseFloat(props.course.cost) : null,
            max_participants: props.course.max_participants,
            learning_objectives: props.course.learning_objectives ?? [],
            syllabus: props.course.syllabus ?? '',
            category_ids: props.course.categories?.map(c => c.id) ?? [],
            prerequisites: props.course.prerequisites?.map(p => ({
                id: p.id,
                is_mandatory: p.is_mandatory ?? true,
            })) ?? [],
        };
    } else {
        resetForm();
    }
    errors.value = {};
};

const handleCancel = () => {
    resetForm();
    emit('update:open', false);
};

const handleOpenChange = (open: boolean) => {
    emit('update:open', open);
    if (!open) {
        resetForm();
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            initializeForm();
        }
    },
);

watch(
    () => props.course,
    () => {
        if (props.open) {
            initializeForm();
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                    <DialogDescription>{{ modalDescription }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-6">
                    <!-- Basic Info Section -->
                    <div class="space-y-4">
                        <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Basic Information
                        </h4>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Title -->
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="course-title">
                                    Title <span class="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="course-title"
                                    type="text"
                                    v-model="form.title"
                                    placeholder="Introduction to Project Management"
                                    required
                                />
                                <InputError :message="errors.title" />
                            </div>

                            <!-- Code -->
                            <div class="grid gap-2">
                                <Label for="course-code">
                                    Code <span class="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="course-code"
                                    type="text"
                                    v-model="form.code"
                                    placeholder="PM-101"
                                    required
                                />
                                <InputError :message="errors.code" />
                            </div>

                            <!-- Status -->
                            <div class="grid gap-2">
                                <Label for="course-status">Status</Label>
                                <select
                                    id="course-status"
                                    v-model="form.status"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <option
                                        v-for="option in statusOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="errors.status" />
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="grid gap-2">
                            <Label for="course-description">Description</Label>
                            <textarea
                                id="course-description"
                                v-model="form.description"
                                rows="3"
                                placeholder="Brief description of the course..."
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            />
                            <InputError :message="errors.description" />
                        </div>
                    </div>

                    <!-- Delivery & Provider Section -->
                    <div class="space-y-4 border-t pt-4">
                        <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Delivery & Provider
                        </h4>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Delivery Method -->
                            <div class="grid gap-2">
                                <Label for="course-delivery">
                                    Delivery Method <span class="text-red-500">*</span>
                                </Label>
                                <select
                                    id="course-delivery"
                                    v-model="form.delivery_method"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <option
                                        v-for="option in deliveryMethodOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="errors.delivery_method" />
                            </div>

                            <!-- Provider Type -->
                            <div class="grid gap-2">
                                <Label for="course-provider-type">
                                    Provider Type <span class="text-red-500">*</span>
                                </Label>
                                <select
                                    id="course-provider-type"
                                    v-model="form.provider_type"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <option
                                        v-for="option in providerTypeOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="errors.provider_type" />
                            </div>

                            <!-- Provider Name -->
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="course-provider-name">Provider Name</Label>
                                <Input
                                    id="course-provider-name"
                                    type="text"
                                    v-model="form.provider_name"
                                    placeholder="Training vendor name (if external)"
                                />
                                <InputError :message="errors.provider_name" />
                            </div>
                        </div>
                    </div>

                    <!-- Duration & Cost Section -->
                    <div class="space-y-4 border-t pt-4">
                        <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Duration & Cost
                        </h4>

                        <div class="grid gap-4 sm:grid-cols-4">
                            <!-- Duration Hours -->
                            <div class="grid gap-2">
                                <Label for="course-duration-hours">Hours</Label>
                                <Input
                                    id="course-duration-hours"
                                    type="number"
                                    v-model.number="form.duration_hours"
                                    min="0"
                                    step="0.5"
                                    placeholder="8"
                                />
                            </div>

                            <!-- Duration Days -->
                            <div class="grid gap-2">
                                <Label for="course-duration-days">Days</Label>
                                <Input
                                    id="course-duration-days"
                                    type="number"
                                    v-model.number="form.duration_days"
                                    min="0"
                                    placeholder="1"
                                />
                            </div>

                            <!-- Cost -->
                            <div class="grid gap-2">
                                <Label for="course-cost">Cost (PHP)</Label>
                                <Input
                                    id="course-cost"
                                    type="number"
                                    v-model.number="form.cost"
                                    min="0"
                                    step="0.01"
                                    placeholder="0"
                                />
                            </div>

                            <!-- Max Participants -->
                            <div class="grid gap-2">
                                <Label for="course-max">Max Participants</Label>
                                <Input
                                    id="course-max"
                                    type="number"
                                    v-model.number="form.max_participants"
                                    min="1"
                                    placeholder="20"
                                />
                            </div>
                        </div>

                        <!-- Level -->
                        <div class="grid gap-2">
                            <Label for="course-level">Level</Label>
                            <select
                                id="course-level"
                                v-model="form.level"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option :value="null">Not Specified</option>
                                <option
                                    v-for="option in levelOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Categories Section -->
                    <div v-if="categories.length > 0" class="space-y-4 border-t pt-4">
                        <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Categories
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="category in categories"
                                :key="category.id"
                                type="button"
                                :class="[
                                    'rounded-full px-3 py-1 text-sm transition-colors',
                                    form.category_ids.includes(category.id)
                                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700',
                                ]"
                                @click="toggleCategory(category.id)"
                            >
                                {{ category.name }}
                            </button>
                        </div>
                    </div>

                    <!-- Prerequisites Section -->
                    <div v-if="filteredPrerequisites.length > 0" class="space-y-4 border-t pt-4">
                        <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Prerequisites
                        </h4>
                        <div class="max-h-40 space-y-2 overflow-y-auto">
                            <label
                                v-for="prereq in filteredPrerequisites"
                                :key="prereq.id"
                                class="flex cursor-pointer items-center gap-2 rounded-md p-2 hover:bg-slate-50 dark:hover:bg-slate-800"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isPrerequisiteSelected(prereq.id)"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    @change="togglePrerequisite(prereq.id)"
                                />
                                <span class="text-sm text-slate-700 dark:text-slate-300">
                                    {{ prereq.title }}
                                    <span class="text-slate-500">({{ prereq.code }})</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Learning Objectives Section -->
                    <div class="space-y-4 border-t pt-4">
                        <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Learning Objectives
                        </h4>
                        <div class="flex gap-2">
                            <Input
                                v-model="newObjective"
                                placeholder="Add a learning objective..."
                                @keydown.enter.prevent="addObjective"
                            />
                            <Button type="button" variant="outline" @click="addObjective">
                                Add
                            </Button>
                        </div>
                        <ul v-if="form.learning_objectives.length > 0" class="space-y-2">
                            <li
                                v-for="(objective, index) in form.learning_objectives"
                                :key="index"
                                class="flex items-center gap-2 rounded-md bg-slate-50 p-2 dark:bg-slate-800"
                            >
                                <span class="flex-1 text-sm">{{ objective }}</span>
                                <button
                                    type="button"
                                    class="text-slate-400 hover:text-red-500"
                                    @click="removeObjective(index)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- Syllabus Section -->
                    <div class="space-y-4 border-t pt-4">
                        <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Syllabus
                        </h4>
                        <textarea
                            v-model="form.syllabus"
                            rows="5"
                            placeholder="Course syllabus or outline..."
                            class="flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            @click="handleCancel"
                            :disabled="processing"
                        >
                            Cancel
                        </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        :disabled="processing || !form.title || !form.code"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        {{
                            processing
                                ? 'Saving...'
                                : isEditing
                                  ? 'Update Course'
                                  : 'Create Course'
                        }}
                    </Button>
                </DialogFooter>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="recentlySuccessful"
                        class="text-center text-sm text-green-600 dark:text-green-400"
                    >
                        Course {{ isEditing ? 'updated' : 'created' }} successfully!
                    </p>
                </Transition>
            </form>
        </DialogContent>
    </Dialog>
</template>
