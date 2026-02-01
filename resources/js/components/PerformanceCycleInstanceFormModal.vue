<script setup lang="ts">
import EnumSelect from '@/components/EnumSelect.vue';
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

interface PerformanceCycle {
    id: number;
    name: string;
    code: string;
    cycle_type: string;
    is_recurring: boolean;
    instances_per_year: number | null;
}

interface PerformanceCycleInstance {
    id: number;
    performance_cycle_id: number;
    name: string;
    year: number;
    instance_number: number;
    start_date: string;
    end_date: string;
    notes: string | null;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    instance: PerformanceCycleInstance | null;
    cycles: PerformanceCycle[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const currentYear = new Date().getFullYear();

const form = ref({
    performance_cycle_id: '',
    name: '',
    year: String(currentYear),
    instance_number: '1',
    start_date: '',
    end_date: '',
    notes: '',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEditing = computed(() => !!props.instance);

const cycleOptions = computed<EnumOption[]>(() => {
    return (props.cycles ?? []).map((cycle) => ({
        value: String(cycle.id),
        label: cycle.name,
    }));
});

const yearOptions = computed<EnumOption[]>(() => {
    const years = [];
    for (let y = currentYear - 2; y <= currentYear + 2; y++) {
        years.push({ value: String(y), label: String(y) });
    }
    return years;
});

watch(
    () => props.instance,
    (newInstance) => {
        if (newInstance) {
            form.value = {
                performance_cycle_id: String(newInstance.performance_cycle_id),
                name: newInstance.name,
                year: String(newInstance.year),
                instance_number: String(newInstance.instance_number),
                start_date: newInstance.start_date,
                end_date: newInstance.end_date,
                notes: newInstance.notes || '',
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
    } else if (!props.instance && props.cycles.length > 0) {
        form.value.performance_cycle_id = String(props.cycles[0].id);
    }
});

function resetForm() {
    form.value = {
        performance_cycle_id:
            props.cycles.length > 0 ? String(props.cycles[0].id) : '',
        name: '',
        year: String(currentYear),
        instance_number: '1',
        start_date: `${currentYear}-01-01`,
        end_date: `${currentYear}-12-31`,
        notes: '',
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
        ? `/api/organization/performance-cycle-instances/${props.instance!.id}`
        : '/api/organization/performance-cycle-instances';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        performance_cycle_id: Number(form.value.performance_cycle_id),
        name: form.value.name,
        year: Number(form.value.year),
        instance_number: Number(form.value.instance_number),
        start_date: form.value.start_date,
        end_date: form.value.end_date,
        notes: form.value.notes || null,
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
            general: 'An error occurred while saving the instance',
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
                    {{
                        isEditing
                            ? 'Edit Cycle Instance'
                            : 'Add Cycle Instance'
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the cycle instance details below.'
                            : 'Fill in the details to create a new cycle instance.'
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

                <!-- Cycle Selection -->
                <div class="space-y-2">
                    <Label for="performance_cycle_id"
                        >Performance Cycle *</Label
                    >
                    <EnumSelect
                        id="performance_cycle_id"
                        v-model="form.performance_cycle_id"
                        :options="cycleOptions"
                        placeholder="Select cycle"
                        :disabled="isEditing"
                    />
                    <p
                        v-if="errors.performance_cycle_id"
                        class="text-sm text-red-500"
                    >
                        {{ errors.performance_cycle_id }}
                    </p>
                </div>

                <!-- Name -->
                <div class="space-y-2">
                    <Label for="name">Instance Name *</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g., Annual Review 2026"
                        :class="{ 'border-red-500': errors.name }"
                    />
                    <p v-if="errors.name" class="text-sm text-red-500">
                        {{ errors.name }}
                    </p>
                </div>

                <!-- Year & Instance Number -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="year">Year *</Label>
                        <EnumSelect
                            id="year"
                            v-model="form.year"
                            :options="yearOptions"
                            placeholder="Select year"
                        />
                        <p v-if="errors.year" class="text-sm text-red-500">
                            {{ errors.year }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="instance_number">Instance Number *</Label>
                        <Input
                            id="instance_number"
                            v-model="form.instance_number"
                            type="number"
                            min="1"
                            max="12"
                            :class="{
                                'border-red-500': errors.instance_number,
                            }"
                        />
                        <p
                            v-if="errors.instance_number"
                            class="text-sm text-red-500"
                        >
                            {{ errors.instance_number }}
                        </p>
                    </div>
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="start_date">Start Date *</Label>
                        <Input
                            id="start_date"
                            v-model="form.start_date"
                            type="date"
                            :class="{ 'border-red-500': errors.start_date }"
                        />
                        <p
                            v-if="errors.start_date"
                            class="text-sm text-red-500"
                        >
                            {{ errors.start_date }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="end_date">End Date *</Label>
                        <Input
                            id="end_date"
                            v-model="form.end_date"
                            type="date"
                            :class="{ 'border-red-500': errors.end_date }"
                        />
                        <p v-if="errors.end_date" class="text-sm text-red-500">
                            {{ errors.end_date }}
                        </p>
                    </div>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="notes">Notes</Label>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Optional notes for this instance"
                        rows="2"
                        :class="{ 'border-red-500': errors.notes }"
                    />
                    <p v-if="errors.notes" class="text-sm text-red-500">
                        {{ errors.notes }}
                    </p>
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
                                ? 'Update Instance'
                                : 'Create Instance'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
