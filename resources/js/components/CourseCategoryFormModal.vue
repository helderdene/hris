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

interface Category {
    id: number;
    name: string;
    code: string;
    description: string | null;
    parent_id: number | null;
    is_active: boolean;
}

interface Props {
    open: boolean;
    category: Category | null;
    parentId: number | null;
    allCategories: Category[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const form = ref({
    name: '',
    code: '',
    parent_id: null as number | null,
    description: '',
    is_active: true,
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);
const recentlySuccessful = ref(false);

const isEditing = computed(() => props.category !== null);

const modalTitle = computed(() =>
    isEditing.value ? 'Edit Category' : 'Add Category',
);

const modalDescription = computed(() => {
    if (isEditing.value) {
        return 'Update the category details below.';
    }
    return 'Create a new category to organize your courses.';
});

const invalidParentIds = computed(() => {
    if (!isEditing.value || !props.category) {
        return new Set<number>();
    }

    const categoryId = props.category.id;
    const ids = new Set<number>([categoryId]);

    function findDescendants(parentId: number) {
        props.allCategories.forEach((cat) => {
            if (cat.parent_id === parentId && !ids.has(cat.id)) {
                ids.add(cat.id);
                findDescendants(cat.id);
            }
        });
    }

    findDescendants(categoryId);
    return ids;
});

const validParentCategories = computed(() => {
    return props.allCategories.filter(
        (cat) => !invalidParentIds.value.has(cat.id),
    );
});

const handleSubmit = async () => {
    errors.value = {};
    processing.value = true;

    try {
        const url = isEditing.value
            ? `/api/training/categories/${props.category!.id}`
            : '/api/training/categories';

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
                name: form.value.name,
                code: form.value.code,
                parent_id: form.value.parent_id,
                description: form.value.description || null,
                is_active: form.value.is_active,
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
                name: 'You do not have permission to manage course categories.',
            };
        } else {
            errors.value = {
                name: 'An unexpected error occurred. Please try again.',
            };
        }
    } catch {
        errors.value = {
            name: 'An unexpected error occurred. Please try again.',
        };
    } finally {
        processing.value = false;
    }
};

const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const resetForm = () => {
    form.value = {
        name: '',
        code: '',
        parent_id: null,
        description: '',
        is_active: true,
    };
    errors.value = {};
};

const initializeForm = () => {
    if (props.category) {
        form.value = {
            name: props.category.name,
            code: props.category.code,
            parent_id: props.category.parent_id,
            description: props.category.description ?? '',
            is_active: props.category.is_active,
        };
    } else {
        resetForm();
        form.value.parent_id = props.parentId;
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
    () => props.category,
    () => {
        if (props.open) {
            initializeForm();
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent>
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                    <DialogDescription>{{ modalDescription }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <!-- Name Field -->
                    <div class="grid gap-2">
                        <Label for="category-name">
                            Name <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="category-name"
                            type="text"
                            v-model="form.name"
                            placeholder="Technical Skills"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <!-- Code Field -->
                    <div class="grid gap-2">
                        <Label for="category-code">
                            Code <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="category-code"
                            type="text"
                            v-model="form.code"
                            placeholder="TECH"
                            required
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            A unique code/abbreviation for this category.
                        </p>
                        <InputError :message="errors.code" />
                    </div>

                    <!-- Parent Category Field -->
                    <div class="grid gap-2">
                        <Label for="category-parent">Parent Category</Label>
                        <select
                            id="category-parent"
                            v-model="form.parent_id"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="null">None (Root Category)</option>
                            <option
                                v-for="cat in validParentCategories"
                                :key="cat.id"
                                :value="cat.id"
                            >
                                {{ cat.name }} ({{ cat.code }})
                            </option>
                        </select>
                        <InputError :message="errors.parent_id" />
                    </div>

                    <!-- Description Field -->
                    <div class="grid gap-2">
                        <Label for="category-description">Description</Label>
                        <textarea
                            id="category-description"
                            v-model="form.description"
                            rows="3"
                            placeholder="Brief description of this category..."
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <!-- Active Status -->
                    <div class="flex items-center gap-2">
                        <input
                            id="category-active"
                            type="checkbox"
                            v-model="form.is_active"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        <Label for="category-active" class="cursor-pointer">
                            Active
                        </Label>
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
                        :disabled="processing || !form.name || !form.code"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        {{
                            processing
                                ? 'Saving...'
                                : isEditing
                                  ? 'Update'
                                  : 'Create'
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
                        Category {{ isEditing ? 'updated' : 'created' }} successfully!
                    </p>
                </Transition>
            </form>
        </DialogContent>
    </Dialog>
</template>
