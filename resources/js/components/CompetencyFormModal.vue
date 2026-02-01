<script setup lang="ts">
import EnumSelect from '@/Components/EnumSelect.vue';
import { Button } from '@/components/ui/button';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { Competency, CategoryOption } from '@/types/competency';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    competency: Competency | null;
    categories: CategoryOption[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    name: '',
    code: '',
    description: '',
    category: '',
    is_active: true,
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEditing = computed(() => !!props.competency);

const categoryOptions = computed(() => {
    return [
        { value: '', label: 'Select category' },
        ...props.categories.map((cat) => ({
            value: cat.value,
            label: cat.label,
        })),
    ];
});

watch(
    () => props.competency,
    (newCompetency) => {
        if (newCompetency) {
            form.value = {
                name: newCompetency.name,
                code: newCompetency.code,
                description: newCompetency.description || '',
                category: newCompetency.category || '',
                is_active: newCompetency.is_active,
            };
        } else {
            resetForm();
        }
        errors.value = {};
    },
    { immediate: true },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        errors.value = {};
    }
});

function resetForm() {
    form.value = {
        name: '',
        code: '',
        description: '',
        category: '',
        is_active: true,
    };
    errors.value = {};
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    const url = isEditing.value
        ? `/api/performance/competencies/${props.competency!.id}`
        : '/api/performance/competencies';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        ...form.value,
        description: form.value.description || null,
        category: form.value.category || null,
    };

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (response.ok) {
            emit('success');
        } else if (response.status === 422 && data.errors) {
            errors.value = Object.fromEntries(
                Object.entries(data.errors).map(([key, value]) => [
                    key,
                    (value as string[])[0],
                ]),
            );
        } else {
            errors.value = { general: data.message || 'An error occurred' };
        }
    } catch (error) {
        errors.value = {
            general: 'An error occurred while saving the competency',
        };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Competency' : 'Add Competency' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the competency details below.'
                            : 'Fill in the details to create a new competency.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- General Error -->
                <div
                    v-if="errors.general"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
                >
                    {{ errors.general }}
                </div>

                <!-- Name -->
                <div class="space-y-2">
                    <Label for="name">Name *</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g., Communication Skills"
                        :class="{ 'border-red-500': errors.name }"
                    />
                    <p v-if="errors.name" class="text-sm text-red-500">
                        {{ errors.name }}
                    </p>
                </div>

                <!-- Code -->
                <div class="space-y-2">
                    <Label for="code">Code *</Label>
                    <Input
                        id="code"
                        v-model="form.code"
                        type="text"
                        placeholder="e.g., COMM-001"
                        :class="{ 'border-red-500': errors.code }"
                    />
                    <p v-if="errors.code" class="text-sm text-red-500">
                        {{ errors.code }}
                    </p>
                </div>

                <!-- Category -->
                <div class="space-y-2">
                    <Label for="category">Category</Label>
                    <EnumSelect
                        id="category"
                        v-model="form.category"
                        :options="categoryOptions"
                        placeholder="Select category"
                    />
                    <p v-if="errors.category" class="text-sm text-red-500">
                        {{ errors.category }}
                    </p>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Describe what this competency measures..."
                        rows="3"
                        :class="{ 'border-red-500': errors.description }"
                    />
                    <p v-if="errors.description" class="text-sm text-red-500">
                        {{ errors.description }}
                    </p>
                </div>

                <!-- Active Status -->
                <div class="flex items-center justify-between">
                    <div class="space-y-0.5">
                        <Label>Active Status</Label>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Inactive competencies won't be available for new assignments.
                        </p>
                    </div>
                    <Switch
                        :checked="form.is_active"
                        @update:checked="form.is_active = $event"
                    />
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        <svg
                            v-if="isSubmitting"
                            class="mr-2 h-4 w-4 animate-spin"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        {{ isEditing ? 'Update Competency' : 'Create Competency' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
