<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ParentDepartmentSelect from '@/components/ParentDepartmentSelect.vue';
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
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface Department {
    id: number;
    name: string;
    code: string;
    description: string | null;
    status: string;
    parent_id: number | null;
    parent?: { id: number; name: string; code: string } | null;
    children_count?: number;
    department_head_id: number | null;
    created_at: string | null;
    updated_at: string | null;
}

interface Props {
    open: boolean;
    department: Department | null;
    parentId: number | null;
    allDepartments: Department[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const form = useForm({
    name: '',
    code: '',
    parent_id: null as number | null,
    description: '',
    status: 'active',
});

/**
 * Determine if we're editing or creating
 */
const isEditing = computed(() => props.department !== null);

/**
 * Modal title
 */
const modalTitle = computed(() =>
    isEditing.value ? 'Edit Department' : 'Add Department',
);

/**
 * Modal description
 */
const modalDescription = computed(() => {
    if (isEditing.value) {
        return 'Update the department details below.';
    }
    return 'Create a new department in your organization structure.';
});

/**
 * Get descendants of the editing department (invalid as parents)
 */
const invalidParentIds = computed(() => {
    if (!isEditing.value || !props.department) {
        return new Set<number>();
    }

    const departmentId = props.department.id;
    const ids = new Set<number>([departmentId]);

    // Find all descendants recursively
    function findDescendants(parentId: number) {
        props.allDepartments.forEach((dept) => {
            if (dept.parent_id === parentId && !ids.has(dept.id)) {
                ids.add(dept.id);
                findDescendants(dept.id);
            }
        });
    }

    findDescendants(departmentId);
    return ids;
});

/**
 * Filter valid parent options
 */
const validParentDepartments = computed(() => {
    return props.allDepartments.filter(
        (dept) => !invalidParentIds.value.has(dept.id),
    );
});

/**
 * Handles the form submission.
 */
const handleSubmit = () => {
    const url = isEditing.value
        ? `/api/organization/departments/${props.department!.id}`
        : '/api/organization/departments';

    const submitMethod = isEditing.value ? 'put' : 'post';

    form.transform((data) => ({
        ...data,
        description: data.description || null,
    }))[submitMethod](url, {
        onSuccess: () => {
            emit('success');
        },
    });
};

/**
 * Resets the form to initial state.
 */
const resetForm = () => {
    form.reset();
    form.clearErrors();
};

/**
 * Initialize form with department data
 */
const initializeForm = () => {
    if (props.department) {
        form.name = props.department.name;
        form.code = props.department.code;
        form.parent_id = props.department.parent_id;
        form.description = props.department.description ?? '';
        form.status = props.department.status;
    } else {
        resetForm();
        // Set parent_id if adding a child
        form.parent_id = props.parentId;
    }
    form.clearErrors();
};

/**
 * Handles dialog close action.
 */
const handleCancel = () => {
    resetForm();
    emit('update:open', false);
};

/**
 * Handles open state change from the dialog.
 */
const handleOpenChange = (open: boolean) => {
    emit('update:open', open);
    if (!open) {
        resetForm();
    }
};

// Initialize form when dialog opens or department changes
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            initializeForm();
        }
    },
);

watch(
    () => props.department,
    () => {
        if (props.open) {
            initializeForm();
        }
    },
);

watch(
    () => props.parentId,
    () => {
        if (props.open && !props.department) {
            form.parent_id = props.parentId;
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent data-test="department-form-modal">
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                    <DialogDescription>{{
                        modalDescription
                    }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <!-- Name Field -->
                    <div class="grid gap-2">
                        <Label for="department-name"
                            >Name <span class="text-red-500">*</span></Label
                        >
                        <Input
                            id="department-name"
                            type="text"
                            v-model="form.name"
                            placeholder="Engineering"
                            required
                            data-test="department-name-input"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <!-- Code Field -->
                    <div class="grid gap-2">
                        <Label for="department-code"
                            >Code <span class="text-red-500">*</span></Label
                        >
                        <Input
                            id="department-code"
                            type="text"
                            v-model="form.code"
                            placeholder="ENG"
                            required
                            data-test="department-code-input"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            A unique code/abbreviation for this department.
                        </p>
                        <InputError :message="form.errors.code" />
                    </div>

                    <!-- Parent Department Field -->
                    <div class="grid gap-2">
                        <Label for="department-parent">Parent Department</Label>
                        <ParentDepartmentSelect
                            id="department-parent"
                            v-model="form.parent_id"
                            :departments="validParentDepartments"
                            :all-departments="allDepartments"
                            data-test="department-parent-select"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Leave empty for a root-level department.
                        </p>
                        <InputError :message="form.errors.parent_id" />
                    </div>

                    <!-- Description Field -->
                    <div class="grid gap-2">
                        <Label for="department-description">Description</Label>
                        <textarea
                            id="department-description"
                            v-model="form.description"
                            rows="3"
                            placeholder="Brief description of the department's responsibilities..."
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            data-test="department-description-input"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <!-- Status Field -->
                    <div class="grid gap-2">
                        <Label for="department-status"
                            >Status <span class="text-red-500">*</span></Label
                        >
                        <select
                            id="department-status"
                            v-model="form.status"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            data-test="department-status-select"
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            @click="handleCancel"
                            :disabled="form.processing"
                            data-test="department-cancel-button"
                        >
                            Cancel
                        </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        :disabled="form.processing || !form.name || !form.code"
                        data-test="department-submit-button"
                    >
                        <Spinner v-if="form.processing" class="mr-2" />
                        {{
                            form.processing
                                ? 'Saving...'
                                : isEditing
                                  ? 'Update'
                                  : 'Create'
                        }}
                    </Button>
                </DialogFooter>

                <!-- Success Message -->
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-center text-sm text-green-600 dark:text-green-400"
                    >
                        Department
                        {{ isEditing ? 'updated' : 'created' }} successfully!
                    </p>
                </Transition>
            </form>
        </DialogContent>
    </Dialog>
</template>
