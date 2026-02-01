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
import { Textarea } from '@/components/ui/textarea';
import { computed, ref, watch } from 'vue';

interface PayrollCycle {
    id: number;
    name: string;
    code: string;
    cycle_type: string;
    cycle_type_label: string;
    description: string | null;
    status: string;
    cutoff_rules: Record<string, unknown> | null;
    is_default: boolean;
}

interface EnumOption {
    value: string;
    label: string;
    description?: string;
}

const props = defineProps<{
    cycle: PayrollCycle | null;
    cycleTypes: EnumOption[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    name: '',
    code: '',
    cycle_type: '',
    description: '',
    status: 'active',
    is_default: false,
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEditing = computed(() => !!props.cycle);

const statusOptions: EnumOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

watch(
    () => props.cycle,
    (newCycle) => {
        if (newCycle) {
            form.value = {
                name: newCycle.name,
                code: newCycle.code,
                cycle_type: newCycle.cycle_type,
                description: newCycle.description || '',
                status: newCycle.status,
                is_default: newCycle.is_default,
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
        cycle_type: '',
        description: '',
        status: 'active',
        is_default: false,
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
        ? `/api/organization/payroll-cycles/${props.cycle!.id}`
        : '/api/organization/payroll-cycles';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        name: form.value.name,
        code: form.value.code,
        cycle_type: form.value.cycle_type,
        description: form.value.description || null,
        status: form.value.status,
        is_default: form.value.is_default,
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
            general: 'An error occurred while saving the payroll cycle',
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
                    {{ isEditing ? 'Edit Payroll Cycle' : 'Add Payroll Cycle' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the payroll cycle details below.'
                            : 'Fill in the details to create a new payroll cycle.'
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
                    <Label for="name">Cycle Name *</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g., Semi-Monthly Payroll"
                        :class="{ 'border-red-500': errors.name }"
                        @blur="!isEditing && !form.code && generateCode()"
                    />
                    <p v-if="errors.name" class="text-sm text-red-500">
                        {{ errors.name }}
                    </p>
                </div>

                <!-- Code & Type -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="code">Code *</Label>
                        <Input
                            id="code"
                            v-model="form.code"
                            type="text"
                            placeholder="e.g., SEMI-MONTHLY"
                            :class="{ 'border-red-500': errors.code }"
                        />
                        <p v-if="errors.code" class="text-sm text-red-500">
                            {{ errors.code }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="cycle_type">Cycle Type *</Label>
                        <EnumSelect
                            id="cycle_type"
                            v-model="form.cycle_type"
                            :options="cycleTypes"
                            placeholder="Select type"
                        />
                        <p v-if="errors.cycle_type" class="text-sm text-red-500">
                            {{ errors.cycle_type }}
                        </p>
                    </div>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Optional description of this payroll cycle"
                        rows="2"
                        :class="{ 'border-red-500': errors.description }"
                    />
                    <p v-if="errors.description" class="text-sm text-red-500">
                        {{ errors.description }}
                    </p>
                </div>

                <!-- Status -->
                <div class="space-y-2">
                    <Label for="status">Status</Label>
                    <EnumSelect
                        id="status"
                        v-model="form.status"
                        :options="statusOptions"
                        placeholder="Select status"
                    />
                    <p v-if="errors.status" class="text-sm text-red-500">
                        {{ errors.status }}
                    </p>
                </div>

                <!-- Default Checkbox -->
                <div class="flex items-start gap-3">
                    <input
                        id="is_default"
                        type="checkbox"
                        v-model="form.is_default"
                        class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-800"
                    />
                    <div class="grid gap-1.5 leading-none">
                        <Label
                            for="is_default"
                            class="cursor-pointer text-sm font-medium leading-none"
                        >
                            Set as Default
                        </Label>
                        <p class="text-sm text-muted-foreground">
                            The default cycle is used when generating periods
                            automatically.
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
                        {{
                            isEditing
                                ? 'Update Payroll Cycle'
                                : 'Create Payroll Cycle'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
