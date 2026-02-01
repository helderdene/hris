<script setup lang="ts">
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
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { computed, ref, watch } from 'vue';

interface KpiTemplate {
    id: number;
    name: string;
    code: string;
    description: string | null;
    metric_unit: string;
    default_target: number | null;
    default_weight: number;
    category: string | null;
    is_active: boolean;
}

const props = defineProps<{
    template: KpiTemplate | null;
    categories: string[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    name: '',
    code: '',
    description: '',
    metric_unit: 'units',
    default_target: '',
    default_weight: '1.00',
    category: '',
    is_active: true,
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEditing = computed(() => !!props.template);

const metricUnitOptions = [
    { value: 'units', label: 'Units' },
    { value: 'PHP', label: 'PHP (Currency)' },
    { value: '%', label: 'Percentage' },
    { value: 'score', label: 'Score' },
    { value: 'hours', label: 'Hours' },
    { value: 'count', label: 'Count' },
];

watch(
    () => props.template,
    (newTemplate) => {
        if (newTemplate) {
            form.value = {
                name: newTemplate.name,
                code: newTemplate.code,
                description: newTemplate.description || '',
                metric_unit: newTemplate.metric_unit,
                default_target: newTemplate.default_target?.toString() || '',
                default_weight: newTemplate.default_weight?.toString() || '1.00',
                category: newTemplate.category || '',
                is_active: newTemplate.is_active,
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
        metric_unit: 'units',
        default_target: '',
        default_weight: '1.00',
        category: '',
        is_active: true,
    };
    errors.value = {};
}

function generateCode() {
    if (!form.value.name) return;
    const code = form.value.name
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '-')
        .replace(/-+/g, '-')
        .substring(0, 20);
    form.value.code = code;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    const url = isEditing.value
        ? `/api/performance/kpi-templates/${props.template!.id}`
        : '/api/performance/kpi-templates';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        name: form.value.name,
        code: form.value.code,
        description: form.value.description || null,
        metric_unit: form.value.metric_unit,
        default_target: form.value.default_target ? parseFloat(form.value.default_target) : null,
        default_weight: parseFloat(form.value.default_weight) || 1.00,
        category: form.value.category || null,
        is_active: form.value.is_active,
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
    } catch {
        errors.value = {
            general: 'An error occurred while saving the KPI template',
        };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit KPI Template' : 'Add KPI Template' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the KPI template details below.'
                            : 'Create a reusable KPI template that can be assigned to employees.'
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
                    <Label for="name">Template Name *</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g., Monthly Sales Target"
                        :class="{ 'border-red-500': errors.name }"
                        @blur="!isEditing && !form.code && generateCode()"
                    />
                    <p v-if="errors.name" class="text-sm text-red-500">
                        {{ errors.name }}
                    </p>
                </div>

                <!-- Code & Category -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="code">Code *</Label>
                        <Input
                            id="code"
                            v-model="form.code"
                            type="text"
                            placeholder="e.g., SALES-TARGET"
                            :class="{ 'border-red-500': errors.code }"
                        />
                        <p v-if="errors.code" class="text-sm text-red-500">
                            {{ errors.code }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="category">Category</Label>
                        <Input
                            id="category"
                            v-model="form.category"
                            type="text"
                            placeholder="e.g., Sales, Quality"
                            list="category-suggestions"
                        />
                        <datalist id="category-suggestions">
                            <option v-for="cat in categories" :key="cat" :value="cat" />
                        </datalist>
                    </div>
                </div>

                <!-- Metric Unit & Default Target -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="metric_unit">Metric Unit *</Label>
                        <Select v-model="form.metric_unit">
                            <SelectTrigger>
                                <SelectValue placeholder="Select unit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in metricUnitOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.metric_unit" class="text-sm text-red-500">
                            {{ errors.metric_unit }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="default_target">Default Target</Label>
                        <Input
                            id="default_target"
                            v-model="form.default_target"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="e.g., 100000"
                            :class="{ 'border-red-500': errors.default_target }"
                        />
                        <p v-if="errors.default_target" class="text-sm text-red-500">
                            {{ errors.default_target }}
                        </p>
                    </div>
                </div>

                <!-- Default Weight -->
                <div class="space-y-2">
                    <Label for="default_weight">Default Weight</Label>
                    <Input
                        id="default_weight"
                        v-model="form.default_weight"
                        type="number"
                        step="0.01"
                        min="0"
                        max="10"
                        placeholder="e.g., 1.0"
                        :class="{ 'border-red-500': errors.default_weight }"
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Weight used to calculate weighted average achievement (default: 1.0)
                    </p>
                    <p v-if="errors.default_weight" class="text-sm text-red-500">
                        {{ errors.default_weight }}
                    </p>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Optional description of this KPI"
                        rows="2"
                        :class="{ 'border-red-500': errors.description }"
                    />
                    <p v-if="errors.description" class="text-sm text-red-500">
                        {{ errors.description }}
                    </p>
                </div>

                <!-- Active Checkbox -->
                <div class="flex items-start gap-3">
                    <input
                        id="is_active"
                        type="checkbox"
                        v-model="form.is_active"
                        class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-800"
                    />
                    <div class="grid gap-1.5 leading-none">
                        <Label
                            for="is_active"
                            class="cursor-pointer text-sm font-medium leading-none"
                        >
                            Active
                        </Label>
                        <p class="text-sm text-muted-foreground">
                            Only active templates can be assigned to employees.
                        </p>
                    </div>
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
                        {{ isEditing ? 'Update Template' : 'Create Template' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
